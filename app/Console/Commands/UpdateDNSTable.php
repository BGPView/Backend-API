<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\DNSRecord;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Config;
use Ubench;

class UpdateDNSTable extends Command
{
    use DispatchesJobs;

    protected $batchAmount = 100000;
    protected $bench;
    protected $ipUtils;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-dns-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the DNS list from Alexa top 1m';

    /**
     * Create a new command instance.
     */
    public function __construct(Ubench $bench, IpUtils $ipUtils)
    {
        parent::__construct();
        $this->bench = $bench;
        $this->ipUtils = $ipUtils;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->bench->start();

        // Setting a brand new index name
        $entityIndexName = config('elasticquent.default_index') . '_dns';
        $versionedIndex = $entityIndexName . '_' . time();
        Config::set('elasticquent.default_index', $versionedIndex);

        // create new index
        DNSRecord::createIndex();

        $currentCount = 0;
        $idCounter = 0;
        $filePath = 'final_output.csv';

        $dnsModel = new DNSRecord;
        $name = $dnsModel->getTable();
        $params = ['body' => []];
        $client = $dnsModel->getElasticSearchClient();
        $rrTypes = DNSRecord::$rrTypes;

        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if (empty($line) === true) {
                    continue;
                }

                $parts = explode(',', $line, 3);

                if (count($parts) !== 3) {
                    $this->warn('====================================');
                    $this->error('Error processing the following line:');
                    dump($line);
                    $this->warn('====================================');
                    continue;
                }

                // Make sure the type is something we know/have
                $parts[1] = strtoupper($parts[1]);
                if (isset($rrTypes[$parts[1]]) !== true) {
                    $this->warn('====================================');
                    $this->error('Unkonw rrType: '.$parts[1]);
                    dump($line);
                    $this->warn('====================================');
                    continue;
                }

                $data = [
                    'input' => $parts[0],
                    'type'  => $rrTypes[$parts[1]],
                    'entry' => $parts[2],
                ];

                if ($parts[1] == 'A' || $parts[1] == 'AAAA') {
                    $data['ip_dec'] = $this->ipUtils->ip2dec($data['entry']);
                } else {
                    $data['ip_dec'] = null;
                }

                $params['body'][] = [
                    'index' => [
                        '_index' => $versionedIndex,
                        '_type' => $name,
                        '_id' => $idCounter++,
                    ]
                ];
                $params['body'][] = $data;
                $currentCount++;

                if ($currentCount > $this->batchAmount) {
                    // Get our document body data.
                    $client->bulk($params);
                    $this->info('Inserted ' . number_format($this->batchAmount) . ' DNS records');

                    // Reset the batching
                    $currentCount = 0;
                    $params['body'] = [];
                }
            }
            fclose($fp);
        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $client->bulk($params);
            $this->info('Inserted the remaining ' . count($params['body']) . ' records');
        }

        $this->hotSwapIndices($versionedIndex, $entityIndexName);

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));




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


