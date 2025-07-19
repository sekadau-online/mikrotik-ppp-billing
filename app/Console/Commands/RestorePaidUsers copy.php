<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RestorePaidUsers extends Command
{
    protected $signature = 'ppp:restore-paid';
    protected $description = 'Restore PPP users whose balance is enough to pay for the package';

    public function handle(MikrotikService $mikrotik)
    {
        // Ambil semua user suspended beserta paketnya
        $usersToRestore = PppUser::with('package')
            ->where('status', 'suspended')
            ->get();

        if ($usersToRestore->isEmpty()) {
            $this->info("No users to restore.");
            return;
        }

        $restoredCount = 0;

        foreach ($usersToRestore as $user) {
            $packagePrice = $user->package->price ?? 0;

            if ($user->balance >= $packagePrice) {
                try {
                    $response = $mikrotik->restoreUser($user->username, $user->profile);

                    if (isset($response['success']) && $response['success']) {
                        $user->update([
                            'status' => 'active',
                            'restored_at' => now(),
                            'expired_at' => now()->addDays($user->package->duration_days),
                            'due_date' => now()->addDays($user->package->duration_days),
                            'balance' => $user->balance - $packagePrice,
                        ]);

                        Log::channel('ppp')->info("User restored after sufficient balance", [
                            'user_id' => $user->id,
                            'username' => $user->username,
                            'deducted' => $packagePrice,
                            'remaining_balance' => $user->balance,
                        ]);

                        $restoredCount++;
                    } else {
                        Log::channel('ppp')->warning("Mikrotik restoreUser response invalid", [
                            'user_id' => $user->id,
                            'response' => $response
                        ]);
                    }

                    usleep(100000); // optional delay 100ms
                } catch (\Exception $e) {
                    Log::channel('ppp')->error("Failed to restore user", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->info("Restored {$restoredCount} users who had sufficient balance.");
    }
}
