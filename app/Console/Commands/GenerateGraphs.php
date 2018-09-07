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
    protected $signature = 'zBGPView:7-generate-asn-graphes';
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
        foreach ($asns as $asnObj) {
            $this->dispatch(new GenerateAsnGraphs($asnObj->asn));
        }
    }

    private function getAsns()
    {
        $this->info('Getting all ASNs from ES');

        $bgpAsns = $this->ipUtils->getBgpAsns();

        $this->info('Found ' . number_format(count($bgpAsns)) . ' unique ASNs in BGP table');
        return $bgpAsns;
    }
}
