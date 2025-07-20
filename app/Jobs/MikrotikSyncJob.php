<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MikrotikService; // Import the MikrotikService
use Illuminate\Support\Facades\Log;

class MikrotikSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes, adjust as needed for Mikrotik operations

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3; // Retry up to 3 times on failure

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // No parameters needed for syncProfiles as it fetches data internally
    }

    /**
     * Execute the job.
     *
     * @param MikrotikService $mikrotikService Automatically injected by Laravel's service container
     */
    public function handle(MikrotikService $mikrotikService): void
    {
        Log::info('MikrotikSyncJob started.');

        try {
            $results = $mikrotikService->syncProfiles();
            Log::info('MikrotikSyncJob completed successfully.', ['results' => $results]);
        } catch (\Exception $e) {
            Log::error('MikrotikSyncJob failed: ' . $e->getMessage(), ['exception' => $e]);
            // You can re-throw the exception if you want the job to be retried
            // or fail after max tries.
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::critical('MikrotikSyncJob failed permanently after retries: ' . $exception->getMessage(), ['exception' => $exception]);
        // You might send a notification (e.g., email, Slack) here
    }
}
