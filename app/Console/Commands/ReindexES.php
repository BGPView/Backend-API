<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReindexES extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:reindex-es';
    protected $batchAmount = 50000;

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

    }

    private function reindexClass($class)
    {
        $total = $class::count();
        $batches = floor($total/$this->batchAmount);

        for ($i = 0; $i <= $batches; $i++) {
            $this->info('Indexing Batch number ' . $i . ' on ' . $class);
            $class::with('emails')->offset($i*$this->batchAmount)->limit($this->batchAmount)->get()->addToIndex();
        }
    }
}
