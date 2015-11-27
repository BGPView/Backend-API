<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv6BgpPrefix;
use App\Services\BgpParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use League\CLImate\CLImate;
use Ubench;

class UpdateBgpData extends Command
{

    private $ipv4RibDownloadUrl = "http://185.42.223.50/rib_ipv4.txt";
    private $ipv6RibDownloadUrl = "http://185.42.223.50/rib_ipv6.txt";
    private $cli;
    private $bench;
    private $bgpParser;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-bgp-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all BGP data';

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
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->updateIPv4Prefixes();
        $this->updateIPv6Prefixes();
    }

    private function updateIPv6Prefixes()
    {
        // TO DO
    }

    private function updateIPv4Prefixes()
    {
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $filePath = sys_get_temp_dir() . '/ipv4_rib.txt';
        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount();

        $this->downloadRIBs($filePath, 4);

        // Cleaning up old temp table
        DB::statement('DROP TABLE IF EXISTS ipv4_bgp_prefixes_temp');

        // Creating a new temp table to store our new BGP data
        DB::statement('CREATE TABLE ipv4_bgp_prefixes_temp LIKE ipv4_bgp_prefixes');

        $oldParsedLine = null;

        // Lets read through the file
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {

                $this->cli->br()->comment($line);

                $parsedLine = $this->bgpParser->parse($line);

                // Before making any DB queries lets check if its a repeated BGP line
                if (is_null($oldParsedLine) === false && $oldParsedLine->ip === $parsedLine->ip && $oldParsedLine->cidr === $parsedLine->cidr) {
                    continue;
                }

                // Lets make sure that v4 is min /24
                if ($parsedLine->cidr > 24) {
                    continue;
                }

                $oldParsedLine = $parsedLine;

                // Skip of already in new temp table
                $prefixTest = IPv4BgpPrefix::where('ip', $parsedLine->ip)->where('cidr', $parsedLine->cidr)->first();
                if (is_null($prefixTest) === fasle) {
                    continue;
                }

                // Get the RIR Allocation info for the prefix
                $ipAllocation = $this->ipUtils->getAllocationEntry($parsedLine->ip);

                // Skip not allocated
                if (is_null($ipAllocation) === true) {
                    continue;
                }

                $this->cli->br()->comment('===================================================');
                $this->cli->br()->comment('Adding new BGP prefix - ' . $parsedLine->ip . '/' . $parsedLine->cidr . ' [' . $ipAllocation->rir->name . ']')->br();

                $ipv4Prefix = new IPv4BgpPrefix;
                $ipv4Prefix->setTable('ipv4_bgp_prefixes_temp');
                $ipv4Prefix->rir_id = $ipAllocation->rir_id;
                $ipv4Prefix->ip = $parsedLine->ip;
                $ipv4Prefix->cidr = $parsedLine->cidr;
                $ipv4Prefix->ip_dec_start = $this->ipUtils->ip2dec($parsedLine->ip);
                $ipv4Prefix->ip_dec_end = ($this->ipUtils->ip2dec($parsedLine->ip) + $ipv4AmountCidrArray[$parsedLine->cidr]);
                $ipv4Prefix->save();

                dump($ipv4Prefix);
            }
            fclose($fp);

        }

        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();

        // Rename temp table to take over
        DB::statement('RENAME TABLE ipv4_bgp_prefixes TO backup_ipv4_bgp_prefixes, ipv4_bgp_prefixes_temp TO ipv4_bgp_prefixes;');

        // Delete old table
        DB::statement('DROP TABLE backup_ipv4_bgp_prefixes');

        // Remove RIB file
        File::delete($filePath);
    }

    private function downloadRIBs($filePath, $ipVersion = 4)
    {
        $this->cli->br()->comment('Downloading IPv' . $ipVersion . ' RIB BGP Dump [' . $filePath . ']');
        $name = 'ipv' . $ipVersion . 'RibDownloadUrl';

        $fp = fopen ($filePath, 'w+');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->$name);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }
}
