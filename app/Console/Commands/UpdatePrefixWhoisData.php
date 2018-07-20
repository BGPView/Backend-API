<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Jobs\EnterPrefixes;
use App\Jobs\UpdatePrefixes;
use App\Models\IPv4PrefixWhoisEmail;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhoisEmail;
use App\Models\IPv6BgpPrefix;
use App\Models\IPv6PrefixWhois;
use App\Services\BgpParser;
use App\Services\Whois;
use Carbon\Carbon;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use League\CLImate\CLImate;
use Ubench;

class UpdatePrefixWhoisData extends Command
{
    use DispatchesJobs;

    private $cli;
    private $bench;
    private $bgpParser;
    private $ipUtils;
    private $esClient;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:6-update-prefix-whois-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the whois data for prefixes in the BGP table';

    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli, Ubench $bench, BgpParser $bgpParser, IpUtils $ipUtils)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->bench = $bench;
        $this->bgpParser = $bgpParser;
        $this->ipUtils = $ipUtils;
        $this->esClient = ClientBuilder::create()->setHosts(config('elasticsearch.hosts'))->build();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->updatePrefixes(6);
        $this->updatePrefixes(4);
        $this->updateOldPrefixes(6);
        $this->updateOldPrefixes(4);
    }

    private function getAllPrefixes($ipVersion)
    {

        $rirPrefixes = $this->ipUtils->getAllocatedPrefixes($ipVersion);

        $sourcePrefixes['rir_prefixes'] = $rirPrefixes->shuffle();
        // get all bgp prefixes
        $className = 'App\Models\IPv' . $ipVersion . 'BgpPrefix';
        $sourcePrefixes['bgp_prefixes'] = $className::all()->shuffle();

        return $sourcePrefixes;
    }

    private function updatePrefixes($ipVersion)
    {
        $ipVersion = (string) $ipVersion;
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $threeWeeksAgo = Carbon::now()->subWeeks(3)->timestamp;

        $this->cli->br()->comment('Getting all the IPv' . $ipVersion . 'prefixes from the BGP table');

        $sourcePrefixes = $this->getAllPrefixes($ipVersion);

        // Make sure they are unqie
        $prefixes = [];
        foreach ($sourcePrefixes as $sourcePrefix) {
            foreach ($sourcePrefix as $prefixObj) {
                if (isset($prefixes[$prefixObj->ip . '/' . $prefixObj->cidr]) === false) {
                    $prefixes[$prefixObj->ip . '/' . $prefixObj->cidr] = $prefixObj;
                }
            }
        }

        // Update them
        foreach ($prefixes as $ipPrefix) {

            // Lets skip if its a bogon address
            if ($ipVersion == 4 && $this->ipUtils->isBogonAddress($ipPrefix->ip)) {
                $this->cli->br()->comment('Skipping Bogon Address - '.$ipPrefix->ip.'/'.$ipPrefix->cidr);
                continue;
            }

            $prefixTest = DB::table('ipv' . $ipVersion . '_prefix_whois')->where('ip', $ipPrefix->ip)->where('cidr', $ipPrefix->cidr)->first();
            if (is_null($prefixTest) !== true) {
                continue;
            }
            // Since we dont have the prefix in DB lets create it.

            $ipAllocation = $this->ipUtils->getAllocationEntry($ipPrefix->ip, $ipPrefix->cidr);

            // Skip non allocated
            if (is_null($ipAllocation) === true) {
                continue;
            }

            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Adding new prefix to queue - ' . $ipPrefix->ip . '/' . $ipPrefix->cidr)->br();

            $this->dispatch(new EnterPrefixes($ipVersion, $ipPrefix, $ipAllocation));
        }


        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();

    }

    private function updateOldPrefixes($ipVersion)
    {
        $ipVersion = (string) $ipVersion;
        $this->bench->start();
        $this->cli->br()->comment('===================================================');

        $this->cli->br()->comment('Getting all OLD IPv' . $ipVersion . 'prefixes from the whois table');

        $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhois';
        $oldPrefixes = $className::where('updated_at', '<', Carbon::now()->subWeek(2))->orderBy('updated_at', 'ASC')->limit(8000)->get();

        foreach ($oldPrefixes as $oldPrefix) {
            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Updating prefix queue - ' . $oldPrefix->ip . '/' . $oldPrefix->cidr)->br();

            $this->dispatch(new UpdatePrefixes($ipVersion, $oldPrefix));
        }

        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();
    }
}
