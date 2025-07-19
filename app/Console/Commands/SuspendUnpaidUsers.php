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
    // Hanya suspend user yang:
    // 1. Status aktif
    // 2. Sudah lewat due_date + grace period
    // 3. Balance tidak cukup untuk memperpanjang
    $usersToSuspend = PppUser::with('package')
        ->where('status', 'active')
        ->where('due_date', '<', now()) // Sudah lewat due_date
        ->whereDoesntHave('package', function($query) {
            $query->whereRaw('ppp_users.balance >= packages.price'); // Balance tidak cukup
        })
        ->get();

    foreach ($usersToSuspend as $user) {
        try {
            // Skip jika masih dalam grace period
            if ($user->due_date->addDays($user->grace_period_days) > now()) {
                continue;
            }

            $response = $mikrotik->suspendUser($user->username);
            
            if ($response['success'] ?? false) {
                $user->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);

                $this->info("Suspended user: {$user->username}");
                Log::channel('ppp')->info("User suspended for non-payment", [
                    'user_id' => $user->id,
                    'username' => $user->username
                ]);
            } else {
                $this->warn("Failed to suspend user {$user->username}: " . ($response['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::channel('ppp')->error("Failed to suspend user", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    $this->info("Selesai. Total user yang di-suspend: " . count($usersToSuspend));
}

}