<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Ubench;

class ReindexRIRWhois extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $bench;
    protected $ipUtils;
    protected $esClient;
    protected $esBatchAmount = 1000;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex in ES all the RIR raw whois data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->bench = new Ubench;
        $this->esClient = ClientBuilder::create()->setHosts(config('elasticsearch.hosts'))->build();
        $this->versionedIndex = $this->indexName . '_' . time();
        $this->ipUtils = new IpUtils();

    }

    public function hotSwapIndices($versionedIndex, $entityIndexName)
    {

        $indexExists       = $this->esClient->indices()->exists(['index' => $entityIndexName]);
        $previousIndexName = null;
        $indices           = $this->esClient->indices()->getAliases();
        foreach ($indices as $indexName => $indexData) {
            if (array_key_exists('aliases', $indexData) && isset($indexData['aliases'][$entityIndexName])) {
                $previousIndexName = $indexName;
                break;
            }
        }
        if ($indexExists === true && $previousIndexName === null) {
            $this->esClient->indices()->delete([
                'index' => $entityIndexName,
            ]);
            $this->esClient->indices()->putAlias([
                'name' => $entityIndexName,
                'index' => $versionedIndex,
            ]);
        } else {
            if ($previousIndexName !== null) {
                $this->esClient->indices()->deleteAlias([
                    'name' => $entityIndexName,
                    'index' => $previousIndexName,
                ]);
            }
            $this->esClient->indices()->putAlias([
                'name' => $entityIndexName,
                'index' => $versionedIndex,
            ]);
            if ($previousIndexName !== null) {
                $this->esClient->indices()->delete([
                    'index' => $previousIndexName,
                ]);
            }
        }
    }

    public function getContents($url)
    {
        $apiKey = env('WHOIS_DB_ARIN_KEY');
        $url = $url . '?apikey=' . $apiKey;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public function extractValues($rawData, $key, $first = true)
    {
        $values = [];
        $rawLines = explode("\n", $rawData);
        $key = strtolower(trim($key));
        foreach ($rawLines as $line) {
            $lineParts = explode(":", $line, 2);
            if (strtolower(trim($lineParts[0])) === $key) {
                $testVal = trim($lineParts[1]);
                if (empty($testVal) !== true || $testVal === "0") {
                    $values[] = trim($lineParts[1]);
                }
            }
        }
        if (count($values) > 0) {
            return $values[0];
        }
        return null;
    }
}
