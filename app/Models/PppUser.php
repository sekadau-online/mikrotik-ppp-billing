<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PppUser extends Model
{
    // Kolom-kolom yang dapat diisi secara massal
    protected $fillable = [
        'username', 'password', 'service', 'profile',
        'local_address', 'remote_address', 'phone',
        'email', 'address', 'activated_at', 'expired_at',
        'due_date', 'grace_period_days', 'suspended_at',
        'balance', 'payment_history', 'status', 'package_id'
    ];

    // Tipe casting untuk atribut
    protected $casts = [
        'payment_history' => 'array', // Menyimpan riwayat pembayaran sebagai array JSON
        'due_date' => 'date',         // Mengubah due_date menjadi objek Carbon (hanya tanggal)
        'suspended_at' => 'datetime', // Mengubah suspended_at menjadi objek Carbon (tanggal dan waktu)
        'activated_at' => 'datetime', // Mengubah activated_at menjadi objek Carbon (tanggal dan waktu)
        'expired_at' => 'datetime',   // Mengubah expired_at menjadi objek Carbon (tanggal dan waktu)
        'balance' => 'decimal:2',     // Mengubah balance menjadi desimal dengan 2 angka di belakang koma
    ];

    /**
     * Mendefinisikan relasi "belongs to" dengan model Package.
     * Seorang pengguna PPP memiliki satu paket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Menentukan apakah pengguna harus ditangguhkan (suspended).
     *
     * @return bool
     */
    public function shouldBeSuspended(): bool
    {
        // Jika status pengguna sudah 'suspended' atau 'expired', tidak perlu ditangguhkan ulang.
        if (in_array($this->status, ['suspended', 'expired'])) {
            return false;
        }

        // Periksa apakah tanggal saat ini masih dalam masa tenggang.
        // `due_date` di-cast sebagai tanggal saja, jadi kita perlu memastikan perbandingan waktu sudah benar.
        // Kita memperlakukan `due_date` sebagai awal hari jatuh tempo.
        // `addDays($this->grace_period_days)->endOfDay()` memastikan seluruh hari terakhir masa tenggang tercakup.
        if ($this->due_date && Carbon::now()->startOfDay()->lte($this->due_date->addDays($this->grace_period_days)->endOfDay())) {
            return false; // Masih dalam masa tenggang
        }

        // Pengguna harus ditangguhkan jika saldo terutang (`balance`) lebih besar dari 0
        // setelah masa tenggang berakhir.
        return $this->balance > 0;
    }

    /**
     * Menambahkan pembayaran dan memperpanjang masa aktif layanan pengguna.
     * Metode ini juga memperbarui saldo terutang, status, dan tanggal aktif/kedaluwarsa.
     *
     * @param float $amount Jumlah yang dibayarkan.
     * @param string $method Metode pembayaran yang digunakan.
     * @param string $reference Referensi pembayaran/ID transaksi.
     * @param Package $package Objek paket yang terkait dengan pembayaran ini, digunakan untuk durasi.
     * @return void
     */
    public function addPayment(float $amount, string $method, string $reference, Package $package): void
    {
        // Catat detail pembayaran dalam riwayat pembayaran pengguna.
        $payment = [
            'date' => now()->toDateString(), // Tanggal pembayaran
            'amount' => $amount,             // Jumlah pembayaran
            'method' => $method,             // Metode pembayaran
            'reference' => $reference,       // Referensi transaksi
        ];

        // Ambil riwayat pembayaran yang sudah ada atau inisialisasi array kosong jika belum ada.
        $history = $this->payment_history ?? [];
        // Tambahkan pembayaran baru ke riwayat.
        $history[] = $payment;

        // Hitung `due_date` dan `expired_at` yang baru.
        // Tanggal dasar untuk perpanjangan adalah `due_date` saat ini (jika di masa depan)
        // atau waktu saat ini (jika `due_date` di masa lalu atau null).
        // Kita menggunakan `endOfDay()` untuk `due_date` untuk memastikan mencakup sepanjang hari.
        $currentDueDate = $this->due_date ? Carbon::parse($this->due_date)->endOfDay() : null;

        // Secara default, tanggal dasar perpanjangan adalah waktu saat ini.
        $baseDateForExtension = Carbon::now();

        // Jika `currentDueDate` ada dan di masa depan, gunakan itu sebagai tanggal dasar.
        if ($currentDueDate && $currentDueDate->isFuture()) {
            $baseDateForExtension = $currentDueDate;
        } 
        // Jika `currentDueDate` ada dan adalah hari ini, gunakan akhir hari ini sebagai tanggal dasar.
        elseif ($currentDueDate && $currentDueDate->isToday()) {
            $baseDateForExtension = $currentDueDate;
        }
        // Jika `currentDueDate` di masa lalu atau null, `baseDateForExtension` tetap `Carbon::now()`.

        // Tambahkan durasi paket (dalam hari) ke tanggal dasar untuk mendapatkan `due_date` yang baru.
        $newDueDate = $baseDateForExtension->copy()->addDays($package->duration_days);
        // Untuk kesederhanaan, `expired_at` dicocokkan dengan `due_date` yang baru.
        $newExpiredAt = $newDueDate->copy();

        // Perbarui detail pengguna di database.
        $this->update([
            'balance' => max(0, $this->balance - $amount), // Kurangi saldo terutang (minimal 0)
            'payment_history' => $history,                 // Perbarui riwayat pembayaran
            'status' => 'active',                          // Atur status menjadi aktif setelah pembayaran
            'suspended_at' => null,                        // Hapus `suspended_at` jika sudah diatur
            'package_id' => $package->id,                  // Perbarui ke ID paket yang baru dibeli
            'activated_at' => $this->activated_at ?? now(),// Atur `activated_at` jika null (pembayaran pertama)
            'expired_at' => $newExpiredAt,                 // Perbarui tanggal kedaluwarsa
            'due_date' => $newDueDate,                     // Perbarui tanggal jatuh tempo
        ]);
    }

    // Metode `calculateNextDueDate()` tidak lagi diperlukan karena logikanya sudah diintegrasikan ke dalam `addPayment()`.
    // protected function calculateNextDueDate(): ?Carbon
    // {
    //     if (!$this->package) {
    //         return null;
    //     }
    //     return now()->addDays($this->package->duration_days);
    // }
}
