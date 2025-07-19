<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Jalankan sinkronisasi secret setiap menit
        $schedule->command('ppp:sync-secrets')->everyMinute()->withoutOverlapping();

        // Jalankan pengecekan suspensi setiap 5 menit (atau sesuai kebutuhan)
        $schedule->command('ppp:check-suspension')->everyFiveMinutes()->withoutOverlapping();

        // Jalankan pengecekan restorasi setiap 5 menit (atau sesuai kebutuhan)
        $schedule->command('ppp:check-restoration')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}