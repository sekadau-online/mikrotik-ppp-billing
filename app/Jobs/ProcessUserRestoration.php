<?php

namespace App\Jobs;

use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Untuk transaksi database
use Carbon\Carbon;

class ProcessUserRestoration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;

    /**
     * Create a new job instance.
     *
     * @param int $userId
     * @return void
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @param MikrotikService $mikrotik
     * @return void
     */
    public function handle(MikrotikService $mikrotik)
    {
        $user = PppUser::with('package')->find($this->userId);

        if (!$user) {
            Log::channel('ppp')->warning("ProcessUserRestoration Job: User ID {$this->userId} not found.");
            return;
        }

        // Double check kondisi sebelum restore (penting untuk idempotency)
        // Pastikan user masih suspended dan punya paket
        if ($user->status !== 'suspended' || !$user->package) {
            Log::channel('ppp')->info("ProcessUserRestoration Job: User {$user->username} not eligible for restoration (status: {$user->status}, package: " . ($user->package->name ?? 'N/A') . "). Skipping.");
            return;
        }

        $packagePrice = (float) $user->package->price;
        $balance = (float) $user->balance;

        // Double check saldo di dalam Job (penting jika saldo berubah setelah Job di-dispatch)
        if ($balance < $packagePrice) {
            Log::channel('ppp')->warning("ProcessUserRestoration Job: User {$user->username} balance ({$balance}) is no longer sufficient for package ({$packagePrice}). Skipping restoration.");
            return;
        }

        try {
            DB::transaction(function () use ($user, $mikrotik, $packagePrice, $balance) {
                // 1. Update Mikrotik (ubah profil dari suspend ke profil paket asli)
                // Pastikan MikrotikService->restoreUser menerima username dan target_profile_name
                // target_profile_name harus dari package user
                $targetProfile = $user->package->mikrotik_profile_name; // Asumsi field ini ada di model Package
                $response = $mikrotik->changeProfile($user->username, $targetProfile); // Atau restoreUser jika MikrotikService Anda punya itu

                if (!($response['success'] ?? false)) {
                    throw new \Exception("Failed to restore user {$user->username} on Mikrotik: " . ($response['error'] ?? 'Unknown error'));
                }

                // 2. Update Database User
                $newExpiredAt = now()->addDays($user->package->duration_days);
                $newDueDate = $newExpiredAt->subDays($user->grace_period_days ?? 1); // Gunakan grace_period_days user atau default 1

                $user->update([
                    'status' => 'active',
                    'restored_at' => now(),
                    'expired_at' => $newExpiredAt,
                    'due_date' => $newDueDate,
                    'balance' => $balance - $packagePrice,
                    // Jika Anda melacak 'current_mikrotik_profile_name', update juga di sini
                    'current_mikrotik_profile_name' => $targetProfile,
                ]);

                // 3. Log Transaksi Saldo (Opsional tapi Disarankan)
                // Jika Anda punya tabel balance_transactions, tambahkan record di sini
                // BalanceTransaction::create([
                //     'user_id' => $user->id,
                //     'amount' => -$packagePrice, // Negatif karena pemotongan
                //     'type' => 'package_renewal',
                //     'description' => "Pembayaran paket {$user->package->name}",
                // ]);

                Log::channel('ppp')->info("User {$user->username} restored successfully on Mikrotik and database.", [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'deducted' => $packagePrice,
                    'remaining_balance' => $balance - $packagePrice,
                ]);

                // Opsional: Dispatch notifikasi sukses restore
                // SendNotificationJob::dispatch($user->id, 'service_restored');

            }); // End DB::transaction

        } catch (\Exception $e) {
            Log::channel('ppp')->error("Exception during user restoration for {$user->username}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'username' => $user->username,
                'error' => $e->getMessage()
            ]);
            // Re-throw exception agar Job bisa di-retry oleh queue worker
            throw $e;
        }
    }
}