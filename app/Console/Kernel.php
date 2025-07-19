<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SuspendUnpaidUsers::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('ppp:suspend-unpaid')->dailyAt('03:00');
        $schedule->command('ppp:restore-paid')->dailyAt('03:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}