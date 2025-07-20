<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PppUser;
use App\Services\MikrotikService;
use App\Notifications\PppUserDueDateReminder; // Import the notification
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification; // Import Notification facade

class CheckPppUserStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ppp:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks PPP user status, sends due date reminders, and handles suspension/isolation.';

    protected $mikrotikService;

    /**
     * Create a new command instance.
     *
     * @param MikrotikService $mikrotikService
     */
    public function __construct(MikrotikService $mikrotikService)
    {
        parent::__construct();
        $this->mikrotikService = $mikrotikService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('CheckPppUserStatus command started.');

        // --- 1. Send Due Date Reminders (1 day before expired_at) ---
        $usersNearingExpiry = PppUser::where('expired_at', '>', now())
                                     ->where('expired_at', '<=', now()->addDay())
                                     ->whereIn('status', ['active', 'pending']) // Only remind active/pending users
                                     ->get();

        foreach ($usersNearingExpiry as $user) {
            try {
                // Calculate days remaining (should be 1 or less)
                $daysRemaining = now()->diffInDays($user->expired_at, false);
                if ($daysRemaining >= 0 && $user->email) { // Only send if email exists and not already expired
                    $user->notify(new PppUserDueDateReminder($user, $daysRemaining));
                    Log::info("Sent due date reminder to user {$user->username}. Days remaining: {$daysRemaining}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to send due date reminder to user {$user->username}: " . $e->getMessage());
            }
        }

        // --- 2. Handle Isolation/Suspension (on or after expired_at) ---
        $usersToSuspend = PppUser::where('expired_at', '<=', now()) // Expired today or in the past
                                 ->whereIn('status', ['active', 'pending']) // Only suspend active/pending users
                                 ->get();

        foreach ($usersToSuspend as $user) {
            try {
                // Update user status in database
                $user->status = 'suspended';
                $user->suspended_at = now();
                $user->save();

                // Change Mikrotik profile to 'suspend-profile'
                if ($user->mikrotik_id) {
                    $this->mikrotikService->updatePppUser($user->mikrotik_id, ['profile' => 'suspend-profile']);
                    Log::info("User {$user->username} (ID: {$user->id}) suspended and moved to 'suspend-profile' on Mikrotik.");
                } else {
                    Log::warning("User {$user->username} (ID: {$user->id}) suspended in DB, but no Mikrotik ID to update profile.");
                }
            } catch (\Exception $e) {
                Log::error("Failed to suspend user {$user->username} (ID: {$user->id}): " . $e->getMessage());
            }
        }

        Log::info('CheckPppUserStatus command finished.');
        $this->info('PPP user status check completed.');
    }
}
