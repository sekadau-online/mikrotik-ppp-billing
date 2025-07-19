<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RestorePaidUsers extends Command
{
    protected $signature = 'ppp:restore-paid';
    protected $description = 'Restore PPP users that have fully paid';

    public function handle(MikrotikService $mikrotik)
    {
        $usersToRestore = PppUser::where('status', 'suspended')
            ->where('balance', '<=', 0)
            ->get();

        if ($usersToRestore->isEmpty()) {
            $this->info("No users to restore.");
            return;
        }

        foreach ($usersToRestore as $user) {
            try {
                $response = $mikrotik->restoreUser($user->username);

                if (isset($response['success']) && $response['success']) {
                    $user->update([
                        'status' => 'active',
                        'restored_at' => now(),
                    ]);

                    Log::channel('ppp')->info("User restored after payment", [
                        'user_id' => $user->id,
                        'username' => $user->username
                    ]);
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

        $this->info("Restored {$usersToRestore->count()} users who have paid.");
    }
}
