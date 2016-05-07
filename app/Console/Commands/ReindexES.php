<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhois;
use Illuminate\Console\Command;
use Ubench;

class ReindexES extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:reindex-es';
    protected $batchAmount = 50000;
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

        $this->reindexClass(IPv4PrefixWhois::class);
        $this->reindexClass(IPv6PrefixWhois::class);
        $this->reindexClass(ASN::class);

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

    }

    private function reindexClass($class)
    {
        $this->warn('=====================================');
        $this->info('Getting total count for '. $class);
        $total = $class::count();
        $this->info('Total: ' . number_format($total));
        $batches = floor($total/$this->batchAmount);
        $this->info('Batch Count: ' . $batches);

        for ($i = 0; $i <= $batches; $i++) {
            $this->info('Indexing Batch number ' . $i . ' on ' . $class);
            $class::with('emails')->offset($i*$this->batchAmount)->limit($this->batchAmount)->get()->addToIndex();
        }
    }
}
