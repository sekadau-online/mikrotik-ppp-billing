<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckUserRestoration extends Command
{
    protected $signature = 'ppp:check-restoration';
    protected $description = 'Checks suspended users and restores them if their balance is sufficient.';

    public function handle(MikrotikService $mikrotik)
    {
        $this->info("Starting user restoration check...");
        Log::channel('ppp')->info("User restoration check started.");

        // Ambil hanya user yang statusnya suspended
        // Eager load package untuk menghindari N+1 query
        $usersToCheck = PppUser::with('package')->where('status', 'suspended')->get();

        $restoredCount = 0;

        foreach ($usersToCheck as $user) {
            try {
                if ($user->shouldBeRestored()) {
                    $this->info("User '{$user->username}' should be restored. Processing...");
                    Log::channel('ppp')->info("User identified for restoration.", ['username' => $user->username, 'current_status' => $user->status]);

                    // Update status di database
                    $user->update([
                        'status' => 'active',
                        'suspended_at' => null,
                        'restored_at' => Carbon::now(),
                    ]);

                    // Update profil di Mikrotik
                    // Dapatkan profil yang diharapkan dari paket user
                    // Fallback jika package_id null atau package tidak ditemukan
                    $expectedProfile = $user->package->mikrotik_profile_name ?? config('mikrotik.default_profile', 'default');

                    $mikrotikSecret = collect($mikrotik->getSecret($user->username))->first();
                    if ($mikrotikSecret) {
                        $updateResponse = $mikrotik->updateUser(
                            $mikrotikSecret['.id'],
                            $user->username,
                            $user->password,
                            $expectedProfile, // Kembalikan ke profil paket aslinya
                            $user->local_address,
                            $user->remote_address
                        );

                        if ($updateResponse['success'] ?? false) {
                            $this->info("Successfully restored user '{$user->username}' on Mikrotik.");
                            Log::channel('ppp')->info("User restored on Mikrotik.", ['username' => $user->username]);
                            $restoredCount++;
                        } else {
                            $this->error("Failed to restore user '{$user->username}' on Mikrotik: " . ($updateResponse['error'] ?? 'Unknown error'));
                            Log::channel('ppp')->error("Failed to restore user on Mikrotik.", ['username' => $user->username, 'error' => $updateResponse['error']]);
                        }
                    } else {
                        $this->error("Mikrotik secret not found for user '{$user->username}'. Cannot update profile for restoration.");
                        Log::channel('ppp')->error("Mikrotik secret not found for restoration.", ['username' => $user->username]);
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error processing restoration for user '{$user->username}': " . $e->getMessage());
                Log::channel('ppp')->error("Error during user restoration check.", ['username' => $user->username, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }
        }

        $this->info("User restoration check finished. Total restored: {$restoredCount}.");
        Log::channel('ppp')->info("User restoration check finished.", ['total_restored' => $restoredCount]);
    }
}