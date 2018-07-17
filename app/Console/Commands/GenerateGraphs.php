<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Jobs\GenerateAsnGraphs;
use App\Models\ASN;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Ubench;

class GenerateGraphs extends Command
{
    use DispatchesJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:generate-asn-graphes';
    protected $bench;
    protected $esClient;
    protected $ipUtils;


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
            $this->dispatch(new GenerateAsnGraphs($asn));
        }
    }

    private function getAsns()
    {
        $this->info('Getting all ASNs from ES');
        $bgpAsns = [];
        $params  = [
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
