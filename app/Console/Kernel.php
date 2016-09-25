<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\UpdateAllocationLists::class,
        \App\Console\Commands\UpdateASNWhoisInfo::class,
        \App\Console\Commands\UpdateBgpData::class,
        \App\Console\Commands\UpdatePrefixWhoisData::class,
        \App\Console\Commands\UpdateDNSTable::class,
        \App\Console\Commands\UpdateRoaTable::class,
        \App\Console\Commands\UpdateIXs::class,
        \App\Console\Commands\UpdateIanaAssignments::class,
        \App\Console\Commands\ReindexES::class,
        \App\Console\Commands\UpdateMaxmindDB::class,
        \App\Console\Commands\ProcessWhoisArin::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('inspire')->hourly();
    }
}
