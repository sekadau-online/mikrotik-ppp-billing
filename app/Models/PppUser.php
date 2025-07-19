<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log; // Tambahkan ini untuk logging

class PppUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ppp_users';

    protected $fillable = [
        'username', 'password', 'service',
        // 'profile', // Hapus ini jika Anda mengikuti rekomendasi migrasi
        'local_address', 'remote_address', 'phone',
        'email', 'address', 'activated_at', 'expired_at',
        'due_date', 'grace_period_days', 'suspended_at',
        'balance',
        // 'payment_history', // Hapus ini jika Anda mengikuti rekomendasi migrasi
        'status', 'package_id', 'restored_at'
    ];

    protected $casts = [
        'due_date' => 'date',
        'suspended_at' => 'datetime',
        'restored_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'balance' => 'decimal:2',
    ];

    // Relasi ke model Package
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    // Relasi ke model Payment
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Proses pembayaran dan update akun user.
     * Mengatur expired_at dan due_date berdasarkan paket.
     *
     * @param float $amount Jumlah pembayaran
     * @param string $method Metode pembayaran
     * @param string $reference Referensi transaksi
     * @return array Hasil proses pembayaran
     */
    public function processPayment(float $amount, string $method, string $reference): array
    {
        DB::beginTransaction();

        try {
            // Validasi paket
            if (!$this->package) {
                throw new \Exception('User tidak memiliki paket yang terhubung untuk memproses pembayaran.');
            }

            $durationDays = $this->package->duration_days;
            $packagePrice = $this->package->price;

            // Jika saldo yang dibayarkan kurang dari harga paket, kembalikan error
            // Asumsi: user membayar sesuai harga paket
            if ($amount < $packagePrice) {
                throw new \Exception("Jumlah pembayaran (Rp" . number_format($amount) . ") kurang dari harga paket (Rp" . number_format($packagePrice) . ").");
            }

            // Tentukan expired_at baru
            // Jika expired_at saat ini sudah lewat atau belum ada, mulai dari sekarang
            // Jika expired_at saat ini masih di masa depan, tambahkan dari expired_at saat ini
            $currentExpiredAt = $this->expired_at ?? now();
            $newExpiredAt = (Carbon::now()->isAfter($currentExpiredAt) || !$this->expired_at)
                            ? Carbon::now()->addDays($durationDays)
                            : $currentExpiredAt->addDays($durationDays);

            // Buat record pembayaran
            $payment = $this->payments()->create([
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'date' => now(),
            ]);

            // Update user account
            $this->update([
                'balance' => $this->balance + $amount, // Menambahkan saldo (model deposit)
                'expired_at' => $newExpiredAt, // Update expired_at
                'due_date' => $newExpiredAt->copy()->toDateString(), // due_date sama dengan expired_at
                'status' => 'active', // Pastikan status menjadi aktif
                'suspended_at' => null, // Hapus status suspend jika ada
                'restored_at' => now(), // Catat waktu restore
            ]);

            DB::commit();

            return [
                'success' => true,
                'payment' => $payment,
                'user' => $this->fresh()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error processing payment for user {$this->username}: " . $e->getMessage(), ['user_id' => $this->id, 'trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tentukan apakah user harus ditangguhkan.
     * Logika: Suspended jika tanggal sekarang melewati expired_at + grace_period_days (1 hari)
     * DAN user belum suspended/expired DAN saldo tidak cukup untuk harga paket.
     *
     * @return bool
     */
    public function shouldBeSuspended(): bool
    {
        // 1. Jika user sudah dalam status 'suspended' atau 'expired', tidak perlu proses lagi.
        if (in_array($this->status, ['suspended', 'expired'])) {
            Log::debug("User {$this->username} already in suspended/expired status. Skipping suspension check.");
            return false;
        }

        // 2. Jika user tidak memiliki paket atau paket tidak punya harga,
        // asumsikan tidak bisa di-suspend berdasarkan harga paket.
        if (!$this->package || !isset($this->package->price)) {
            Log::warning("User {$this->username} has no package or package price defined. Skipping suspension check.");
            return false;
        }

        $packagePrice = (float) $this->package->price;

        // 3. Jika expired_at belum disetel (misal: user 'pending' atau baru), tidak bisa di-suspend berdasarkan expired_at.
        if (!$this->expired_at) {
            Log::info("User {$this->username} has no expired_at set. Cannot determine suspension by expiry.");
            return false;
        }

        // Tentukan batas akhir grace period: expired_at + grace_period_days
        $gracePeriodEnd = $this->expired_at->copy()->addDays($this->grace_period_days)->endOfDay();
        Log::debug("User {$this->username}: expired_at = {$this->expired_at->format('Y-m-d H:i:s')}, grace_period_days = {$this->grace_period_days}, gracePeriodEnd = {$gracePeriodEnd->format('Y-m-d H:i:s')}, now = " . Carbon::now()->format('Y-m-d H:i:s'));

        // 4. Jika tanggal saat ini masih dalam batas grace period, JANGAN di-suspend.
        if (Carbon::now()->lte($gracePeriodEnd)) {
            Log::debug("User {$this->username} is still within grace period. Not suspending.");
            return false;
        }

        // 5. Jika saldo user masih cukup untuk membayar paket, JANGAN di-suspend.
        if ((float) $this->balance >= $packagePrice) {
            Log::debug("User {$this->username} has sufficient balance (Rp" . number_format($this->balance) . " vs Rp" . number_format($packagePrice) . "). Not suspending.");
            return false;
        }

        // Jika semua kondisi di atas tidak terpenuhi (yaitu, sudah lewat grace period DAN saldo tidak cukup),
        // maka user HARUS ditangguhkan.
        Log::info("User {$this->username} qualifies for suspension (expired and insufficient balance).");
        return true;
    }

    /**
     * Tentukan apakah user harus dikembalikan dari penangguhan.
     * Logika: User berstatus 'suspended' DAN memiliki saldo yang cukup (atau sudah membayar).
     *
     * @return bool
     */
    public function shouldBeRestored(): bool
    {
        // Hanya user yang berstatus 'suspended' yang bisa di-restore
        if ($this->status !== 'suspended') {
            Log::debug("User {$this->username} is not suspended. Skipping restoration check.");
            return false;
        }

        // Pastikan user memiliki paket dan harga paket tersedia
        if (!$this->package || !isset($this->package->price)) {
            Log::warning("User {$this->username} has no package or package price defined. Cannot determine restoration by balance.");
            return false;
        }

        $packagePrice = (float) $this->package->price;

        // User harus di-restore jika statusnya 'suspended' DAN saldonya cukup untuk membayar paketnya
        if ((float) $this->balance >= $packagePrice) {
            Log::info("User {$this->username} qualifies for restoration (suspended with sufficient balance).");
            return true;
        }

        Log::debug("User {$this->username} is suspended but balance (Rp" . number_format($this->balance) . ") is insufficient for restoration (Rp" . number_format($packagePrice) . ").");
        return false;
    }
}