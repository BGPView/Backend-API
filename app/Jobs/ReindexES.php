<?php

namespace App\Jobs;

use App\Models\ASN;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhois;
use App\Models\IX;
use Elasticsearch\ClientBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use League\CLImate\CLImate;

class ReindexES extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $cli;
    protected $batchAmount = 10000;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cli = new CLImate();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->reindexClass('ix', IX::class, $withRelated = false);
        $this->reindexClass('asn', ASN::class);
        $this->reindexClass('ipv6', IPv6PrefixWhois::class);
        $this->reindexClass('ipv4', IPv4PrefixWhois::class);

    }

    private function reindexClass($name, $class, $withRelated = true)
    {
        // Setting a brand new index name
        $originalIndex = config('elasticquent.default_index');
        $entityIndexName = config('elasticquent.default_index'). '_' . $name;
        $versionedIndex  = $entityIndexName . '_' . time();
        Config::set('elasticquent.default_index', $versionedIndex);

        $class::createIndex();

        $class::putMapping($ignoreConflicts = true);

        $this->cli->comment('=====================================');
        $this->cli->comment('Getting total count for ' . $class);
        $total = $class::count();
        $this->cli->comment('Total: ' . number_format($total));
        $batches = floor($total / $this->batchAmount);
        $this->cli->comment('Batch Count: ' . $batches);

        for ($i = 0; $i <= $batches; $i++) {
            $this->cli->comment('Indexing Batch number ' . $i . ' on ' . $class);

            if ($withRelated === true) {
                $class::with('emails')->with('rir')->offset($i * $this->batchAmount)->limit($this->batchAmount)->get()->addToIndex();
            } else {
                $class::offset($i * $this->batchAmount)->limit($this->batchAmount)->get()->addToIndex();
            }
        }

        $this->hotSwapIndices($versionedIndex, $entityIndexName);

        Config::set('elasticquent.default_index', $originalIndex);

    }

    private function hotSwapIndices($versionedIndex, $entityIndexName)
    {
        $client = ClientBuilder::create()->setHosts(config('elasticquent.config.hosts'))->build();

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
                'name'  => $entityIndexName,
                'index' => $versionedIndex,
            ]);
        } else {
            if ($previousIndexName !== null) {
                $client->indices()->deleteAlias([
                    'name'  => $entityIndexName,
                    'index' => $previousIndexName,
                ]);
            }
            $client->indices()->putAlias([
                'name'  => $entityIndexName,
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
