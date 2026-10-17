<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('platform:heartbeat')->everyMinute()->withoutOverlapping();
        $schedule->command('communications:work')->everyMinute()->withoutOverlapping(10);
        $schedule->command('platform:check-integrity')->dailyAt('03:10')->timezone('Europe/Istanbul')->withoutOverlapping(180);
        $schedule->command('platform:recover')->everyMinute()->withoutOverlapping(180);
        $schedule->command('platform:purge-history')->hourlyAt(20)->between('03:00','06:59')->withoutOverlapping();
        $schedule->command('platform:backup')->dailyAt('23:55')->timezone('Europe/Istanbul')->withoutOverlapping(180);
        $schedule->command('platform:backup --requested')->everyMinute()->withoutOverlapping(180);
        $schedule->command('subscriptions:purge-demos --execute')->dailyAt('04:40')->timezone('Europe/Istanbul')->withoutOverlapping();
        $schedule->command('inventory:purge-purchases')->daily()->withoutOverlapping();
        $schedule->command('photos:purge')->dailyAt('03:30')->timezone('Europe/Istanbul')->withoutOverlapping(180);
        $schedule->command('security:purge-owner-logins')->hourly()->withoutOverlapping();
        $schedule->command('appointments:expire')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
