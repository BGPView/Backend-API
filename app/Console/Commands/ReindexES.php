<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Ubench;

class ReindexES extends Command
{
    use DispatchesJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature   = 'zBGPView:reindex-es';
    protected $bench;

    /**
     * Create a new command instance.
     */
    public function __construct(Ubench $bench)
    {
        parent::__construct();
        $this->bench = $bench;
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex Elastic Search from the MySQL DB';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->bench->start();

        $this->dispatch(new \App\Jobs\ReindexES());

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

    }

}
