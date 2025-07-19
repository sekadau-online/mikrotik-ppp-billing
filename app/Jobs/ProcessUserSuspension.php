<?php

namespace App\Jobs;

use App\Models\PppUser;
use App\Services\MikrotikService; // Pastikan ini di-import
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUserSuspension implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;

    /**
     * Create a new job instance.
     *
     * @param int $userId ID dari PppUser yang akan diproses
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
            Log::channel('ppp')->warning("ProcessUserSuspension Job: User ID {$this->userId} not found.");
            return;
        }

        // Double check kondisi sebelum suspend (penting untuk idempotency)
        // Pastikan user masih aktif dan sudah melewati expired_at
        if ($user->status !== 'active' || $user->expired_at > now()) {
            Log::channel('ppp')->info("ProcessUserSuspension Job: User {$user->username} not eligible for suspension (status: {$user->status}, expired_at: {$user->expired_at}). Skipping.");
            return;
        }

        try {
            // Panggil MikrotikService untuk mengganti profil ke suspend
            // Asumsi MikrotikService memiliki method changeProfile atau suspendUser
            // yang menerima username dan nama profil suspend
            $response = $mikrotik->changeProfile($user->username, 'suspend-profile'); // Ganti 'suspend-profile' dengan nama profil suspend Anda di Mikrotik
            
            if ($response['success'] ?? false) {
                $user->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    // Jika Anda melacak 'current_mikrotik_profile_name', update juga di sini
                    'current_mikrotik_profile_name' => 'suspend-profile',
                ]);

                Log::channel('ppp')->info("User {$user->username} suspended successfully on Mikrotik and database.", [
                    'user_id' => $user->id,
                    'username' => $user->username
                ]);
            } else {
                Log::channel('ppp')->error("Failed to suspend user {$user->username} on Mikrotik: " . ($response['error'] ?? 'Unknown error'), [
                    'user_id' => $user->id,
                    'username' => $user->username
                ]);
                // Jika gagal di Mikrotik, mungkin Anda ingin me-retry Job ini
                // $this->release(60); // Coba lagi dalam 60 detik
            }
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Exception during user suspension for {$user->username}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'username' => $user->username,
                'error' => $e->getMessage()
            ]);
            // Jika terjadi exception, Job akan otomatis di-retry sesuai konfigurasi queue worker
            // atau gagal jika sudah melewati batas tries
            throw $e; // Re-throw exception agar Laravel tahu Job gagal
        }
    }
}