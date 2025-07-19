<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckUserSuspension extends Command
{
    protected $signature = 'ppp:check-suspension';
    protected $description = 'Checks users and suspends them if their package has expired and balance is insufficient.';

    public function handle(MikrotikService $mikrotik)
    {
        $this->info("Starting user suspension check...");
        Log::channel('ppp')->info("User suspension check started.");

        // Ambil user yang potensial di-suspend (active/pending, belum suspended/expired)
        // Eager load package untuk menghindari N+1 query
        $usersToCheck = PppUser::with('package')
                                ->whereIn('status', ['active', 'pending'])
                                ->whereNotNull('expired_at')
                                ->get();

        $suspendedCount = 0;

        foreach ($usersToCheck as $user) {
            try {
                if ($user->shouldBeSuspended()) {
                    $this->warn("User '{$user->username}' should be suspended. Processing...");
                    Log::channel('ppp')->info("User identified for suspension.", ['username' => $user->username, 'current_status' => $user->status]);

                    // Update status di database
                    $user->update([
                        'status' => 'suspended',
                        'suspended_at' => Carbon::now(),
                        'restored_at' => null,
                    ]);

                    // Update profil di Mikrotik
                    // Pertama, dapatkan .id secret dari Mikrotik
                    $mikrotikSecret = collect($mikrotik->getSecret($user->username))->first();
                    if ($mikrotikSecret) {
                        $updateResponse = $mikrotik->updateUser(
                            $mikrotikSecret['.id'],
                            $user->username,
                            $user->password,
                            'suspend-profile', // Ganti dengan NAMA PERSIS profil suspend Anda di Mikrotik
                            $user->local_address,
                            $user->remote_address
                        );

                        if ($updateResponse['success'] ?? false) {
                            $this->info("Successfully suspended user '{$user->username}' on Mikrotik.");
                            Log::channel('ppp')->info("User suspended on Mikrotik.", ['username' => $user->username]);
                            $suspendedCount++;
                        } else {
                            $this->error("Failed to suspend user '{$user->username}' on Mikrotik: " . ($updateResponse['error'] ?? 'Unknown error'));
                            Log::channel('ppp')->error("Failed to suspend user on Mikrotik.", ['username' => $user->username, 'error' => $updateResponse['error']]);
                        }
                    } else {
                        $this->error("Mikrotik secret not found for user '{$user->username}'. Cannot update profile for suspension.");
                        Log::channel('ppp')->error("Mikrotik secret not found for suspension.", ['username' => $user->username]);
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error processing suspension for user '{$user->username}': " . $e->getMessage());
                Log::channel('ppp')->error("Error during user suspension check.", ['username' => $user->username, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            }
        }

        $this->info("User suspension check finished. Total suspended: {$suspendedCount}.");
        Log::channel('ppp')->info("User suspension check finished.", ['total_suspended' => $suspendedCount]);
    }
}