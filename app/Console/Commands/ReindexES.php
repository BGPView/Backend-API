<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhois;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
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


        // Setting a brand new index name
        $versionedIndex = config('elasticquent.default_index') . '_' . time();
        Config::set('elasticquent.default_index', $versionedIndex);

        // create new index
        ASN::createIndex();

        $this->reindexClass(IPv4PrefixWhois::class);
        $this->reindexClass(IPv6PrefixWhois::class);
        $this->reindexClass(ASN::class);

        $this->hotSwapIndices($versionedIndex);

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
        $class::putMapping($ignoreConflicts = true);

        $this->warn('=====================================');
        $this->info('Getting total count for '. $class);
        $total = $class::count();
        $this->info('Total: ' . number_format($total));
        $batches = floor($total/$this->batchAmount);
        $this->info('Batch Count: ' . $batches);

        for ($i = 0; $i <= $batches; $i++) {
            $this->info('Indexing Batch number ' . $i . ' on ' . $class);
            $class::with('emails')->with('rir')->offset($i*$this->batchAmount)->limit($this->batchAmount)->get()->addToIndex();
        }
    }

    private function hotSwapIndices($versionedIndex)
    {
        $client = ClientBuilder::create()->build();

        $entityIndexName   = config('elasticquent.default_index');
        $indexExists       = $client->indices()->exists(['index' => $entityIndexName]);
        $previousIndexName = null;
        $indices           = $client->indices()->getAliases();

        foreach ($indices as $indexName => $indexData) {
            if (array_key_exists('aliases', $indexData) && isset($indexData['aliases'][$entityIndexName])) {
                $previousIndexName = $indexName;
                break;
            }
        }

        if ($indexExists === true && $previousIndexName === null) {
            $client->indices()->delete([
                'index' => $entityIndexName,
            ]);

            $client->indices()->putAlias([
                'name' => $entityIndexName,
                'index' => $versionedIndex,
            ]);
        } else {
            if ($previousIndexName !== null) {
                $client->indices()->deleteAlias([
                    'name' => $entityIndexName,
                    'index' => $previousIndexName,
                ]);
            }
            $client->indices()->putAlias([
                'name' => $entityIndexName,
                'index' => $versionedIndex,
            ]);

            if ($previousIndexName !== null) {
                $client->indices()->delete([
                    'index' => $previousIndexName,
                ]);
            }
        }
    }
}
