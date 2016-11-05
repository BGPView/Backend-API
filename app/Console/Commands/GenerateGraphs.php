<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\ASN;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Ubench;

class GenerateGraphs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:generate-asn-graphes';
    protected $bench;
    protected $esClient;
    protected $ipUtils;
    protected $maxLineThickness = 5;

    /**
     * Create a new command instance.
     */
    public function __construct(Ubench $bench, IpUtils $ipUtils)
    {
        parent::__construct();
        $this->bench    = $bench;
        $this->ipUtils  = $ipUtils;
        $this->esClient = ClientBuilder::create()->setHosts(config('elasticsearch.hosts'))->build();

    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all ASN relation graphes';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->bench->start();

        $asns = $this->getAsns();
        $this->generateGraphs($asns);

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

    }

    private function generateGraphs($asns)
    {
        $this->info('Generating graph images per ASN');
        foreach ($asns as $asn) {
            $upstreams = ASN::getUpstreams($asn, $asnMeta = false);

            if (count($upstreams['ipv4_upstreams']) > 0) {
                $this->createGraphviz($asn, 4, $upstreams['ipv4_upstreams']);
            }
            if (count($upstreams['ipv6_upstreams']) > 0) {
                $this->createGraphviz($asn, 6, $upstreams['ipv6_upstreams']);
            }
        }
    }

    private function createGraphviz($inputAsn, $ipVersion, $upstreams)
    {
        $relations = [];
        foreach ($upstreams as $upstream) {
            foreach ($upstream['bgp_paths'] as $path) {
                $asns = explode(' ', $path);

                $previousAsn = false;
                foreach ($asns as $key => $asn) {
                    // If its the last ASN, then stop
                    if (isset($asns[$key + 1]) !== true) {
                        continue;
                    }

                    if (isset($relations[$asn . '-' . $asns[$key + 1]]) === true) {
                        $relations[$asn . '-' . $asns[$key + 1]]['weight']++;
                        continue;
                    }

                    $relations[$asn . '-' . $asns[$key + 1]] = [
                        'weight' => 1,
                        'asn1'   => $asn,
                        'asn2'   => $asns[$key + 1],
                    ];
                }
            }
        }

        $keyAsns      = [];
        $realRelation = [];
        foreach ($relations as $relation) {
            $keyAsns[$relation['asn1']][] = $relation;
        }

        foreach ($keyAsns as $groupedAsns) {
            $highestNumber = $groupedAsns[0]['weight'];
            foreach ($groupedAsns as $asn) {
                if ($asn['weight'] > $highestNumber) {
                    $highestNumber = $asn['weight'];
                }
            }

            // Set the lowest to be 1;
            if ($highestNumber > $this->maxLineThickness) {
                $devider = $highestNumber / $this->maxLineThickness;
            } else {
                $devider = 1;
            }
            foreach ($groupedAsns as $asn) {
                $asn['weight']  = $asn['weight'] / $devider;
                $realRelation[] = $asn;
            }
        }

        $outputGraphvizText = "digraph {" . PHP_EOL;
        $outputGraphvizText .= "rankdir=LR;" . PHP_EOL;
        foreach ($realRelation as $relation) {
            $outputGraphvizText .= 'AS' . $relation['asn1'] . ' -> AS' . $relation['asn2'] . ' [ penwidth = ' . $relation['weight'] . ' ];' . PHP_EOL;
        }
        $outputGraphvizText .= "}" . PHP_EOL;

        exec('echo "' . $outputGraphvizText . '" | dot -Tpng -o ' . public_path() . '/assets/graphs/AS' . $inputAsn . '_v' . $ipVersion . '.png');
    }

    private function getAsns()
    {
        $this->info('Getting all ASNs from ES');
        $bgpAsns = [];
        $params  = [
            'search_type' => 'scan',
            'scroll'      => '30s',
            'size'        => 10000,
            'index'       => 'bgp_data',
            'type'        => 'full_table',
        ];

        $docs      = $this->esClient->search($params);
        $scroll_id = $docs['_scroll_id'];

        while (true) {
            $response = $this->esClient->scroll(
                array(
                    "scroll_id" => $scroll_id,
                    "scroll"    => "30s",
                )
            );

            if (count($response['hits']['hits']) > 0) {
                $results = $this->ipUtils->cleanEsResults($response);
                foreach ($results as $result) {
                    if (isset($bgpAsns[$result->asn]) !== true) {
                        $bgpAsns[$result->asn] = true;
                    }
                }
                // Get new scroll_id
                $scroll_id = $response['_scroll_id'];
            } else {
                // All done scrolling over data
                break;
            }
        }

        $this->info('Found ' . number_format(count($bgpAsns)) . ' unique ASNs in BGP table');
        return array_keys($bgpAsns);
    }
}
