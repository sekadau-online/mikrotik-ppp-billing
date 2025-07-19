<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMikrotikSecretsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
{
    $this->info("Dispatching Mikrotik PPP secret synchronization job...");
    \App\Jobs\SyncMikrotikSecretsJob::dispatch();
    $this->info("Mikrotik sync job dispatched.");
}
}