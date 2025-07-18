<?php

namespace App\Console\Commands;

use App\Models\PppUser;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SuspendUnpaidUsers extends Command
{
    protected $signature = 'ppp:suspend-unpaid';
    protected $description = 'Suspend PPP users with overdue payments';

    public function handle(MikrotikService $mikrotik)
    {
        $usersToSuspend = PppUser::where('status', 'active')
            ->where(function($query) {
                $query->where('due_date', '<', now()->subDays(7))
                      ->orWhere('balance', '>', 0);
            })
            ->get();

        foreach ($usersToSuspend as $user) {
            try {
                $response = $mikrotik->suspendUser($user->username);
                
                if ($response['success']) {
                    $user->update([
                        'status' => 'suspended',
                        'suspended_at' => now(),
                    ]);
                    
                    Log::channel('ppp')->info("User suspended for non-payment", [
                        'user_id' => $user->id,
                        'username' => $user->username
                    ]);
                }
            } catch (\Exception $e) {
                Log::channel('ppp')->error("Failed to suspend user", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Suspended {$usersToSuspend->count()} users with overdue payments");
    }
}