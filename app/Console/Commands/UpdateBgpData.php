<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
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
        $this->cli->br();
        $this->cli->red("########################################################################")->br();
        $this->cli->red("###  MAKE SURE 'max_allowed_packet' IS SET < 1G IN YOUR my.cnf file  ###")->br();
        $this->cli->red("########################################################################")->br();
        $this->updatePrefixes($ipVersion = 6);
        $this->updatePrefixes($ipVersion = 4);
    }

    private function updatePrefixes($ipVersion)
    {
        $this->cli->br()->comment('===================================================');

        $filePath = sys_get_temp_dir() . '/ipv' . $ipVersion . '_rib.txt';
        $funcName = 'IPv' . $ipVersion . 'cidrIpCount';
        $ipvAmountCidrArray = $this->ipUtils->$funcName();
        $seenPrefixes = [];
        $seenTableEntries = [];
        $seenPeers = [];
        $newPeers = "";
        $newPrefixes = "";
        $newTableEntries = "";
        $counter = 0;
        $mysqlTime = "'" . date('Y-m-d H:i:s') . "'";

        $this->downloadRIBs($filePath, $ipVersion);

        $this->bench->start();

        // Cleaning up old temp table
        $this->cli->br()->comment('Drop old TEMP table');
        DB::statement('DROP TABLE IF EXISTS ipv' . $ipVersion . '_bgp_prefixes_temp');
        DB::statement('DROP TABLE IF EXISTS ipv' . $ipVersion . '_bgp_table_temp');
        DB::statement('DROP TABLE IF EXISTS ipv' . $ipVersion . '_peers_temp');

        // Creating a new temp table to store our new BGP data
        $this->cli->br()->comment('Cloning ipv' . $ipVersion . '_bgp_prefixes table schema');
        DB::statement('CREATE TABLE ipv' . $ipVersion . '_bgp_prefixes_temp LIKE ipv' . $ipVersion . '_bgp_prefixes');
        DB::statement('CREATE TABLE ipv' . $ipVersion . '_bgp_table_temp LIKE ipv' . $ipVersion . '_bgp_table');
        DB::statement('CREATE TABLE ipv' . $ipVersion . '_peers_temp LIKE ipv' . $ipVersion . '_peers');

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Processing BGP IPv' . $ipVersion . ' entries');

        // Lets read through the file
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {

                $parsedLine = $this->bgpParser->parse($line);

                // Lets try process the peers
                foreach ($parsedLine->peersSet as $peers) {
                    $peerKeyString = $peers[0] . '|' . $peers[1];
                    if (isset($seenPeers[$peerKeyString]) === false) {
                        // Bulking the raw bulk insert
                        $newPeers .= '('.$peers[0].','.$peers[1].','.$mysqlTime.','.$mysqlTime.'),';
                        $seenPeers[$peerKeyString] = true;
                    }
                }

                $minCidrSize = $ipVersion == 4 ? 24 : 48;
                // Lets make sure that v4 is min /24
                if ($parsedLine->cidr > $minCidrSize || $parsedLine->cidr < 1) {
                    continue;
                }

                // Only entry entries which are not set
                if (isset($seenPrefixes[$parsedLine->prefix.'|'.$parsedLine->asn]) === false) {
                    $newPrefixes .= "('".$parsedLine->ip."','".$parsedLine->cidr."',".$this->ipUtils->ip2dec($parsedLine->ip).",".number_format(($this->ipUtils->ip2dec($parsedLine->ip) + $ipvAmountCidrArray[$parsedLine->cidr] -1), 0, '', '').",".$parsedLine->asn.",".$mysqlTime.",".$mysqlTime."),";
                    $seenPrefixes[$parsedLine->prefix.'|'.$parsedLine->asn] = true;
                }

                // Lets make sure we have a proper upstream (and not direct peering)
                if (is_null($parsedLine->upstream_asn) === true) {
                    continue;
                }

                // Skip any table entries we have already seen
                // isset() is MUCH faster than using in_array()
                if (isset($seenTableEntries[$parsedLine->prefix.'|'.$parsedLine->path_string]) === true) {
                    continue;
                }

                // Yeah yeah, what bad practices... I know
                // However we are now able to bulk insert in a easy single transaction with no issues
                // This means we reduced our insert time from ~2 hours to ~10 seconds.
                // Yeah, its worth it!
                $newTableEntries .= "('".$parsedLine->ip."','".$parsedLine->cidr."',".$parsedLine->asn.",".$parsedLine->upstream_asn.",'".$parsedLine->path_string."',".$mysqlTime.",".$mysqlTime."),";
                $counter++;

                // Lets do a mass insert if the counter reach 100,00
                if ($counter > 250000) {
                    $this->cli->br()->comment('Inserting another 250,000 bgp entries in one bulk query');
                    $newTableEntries = rtrim($newTableEntries, ',').';';
                    DB::statement('INSERT INTO ipv' . $ipVersion . '_bgp_table_temp (ip,cidr,asn,upstream_asn,bgp_path,updated_at,created_at) VALUES '.$newTableEntries);
                    $newTableEntries = "";
                    $counter = 0;
                }

                // Lets make note of the table entry we have seen
                // We are setting key here so above we can do a isset() check instead of in_array()
                $seenTableEntries[$parsedLine->prefix.'|'.$parsedLine->path_string] = true;

            }
            fclose($fp);
        }

        $this->cli->br()->comment('Inserting all remaining prefixes in one bulk query');
        $newPrefixes = rtrim($newPrefixes, ',').';';
        DB::statement('INSERT INTO ipv' . $ipVersion . '_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,updated_at,created_at) VALUES '.$newPrefixes);

        $this->cli->br()->comment('Inserting all remaining bgp table entries in one bulk query');
        $newTableEntries = rtrim($newTableEntries, ',').';';
        DB::statement('INSERT INTO ipv' . $ipVersion . '_bgp_table_temp (ip,cidr,asn,upstream_asn,bgp_path,updated_at,created_at) VALUES '.$newTableEntries);

        $this->cli->br()->comment('Inserting all peers in one bulk query');
        $newPeers = rtrim($newPeers, ',').';';
        DB::statement('INSERT INTO ipv' . $ipVersion . '_peers_temp (asn_1,asn_2,updated_at,created_at) VALUES '.$newPeers);

        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();

        // Rename temp table to take over
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Swapping TEMP table with production table');
        DB::statement('RENAME TABLE ipv' . $ipVersion . '_bgp_prefixes TO backup_ipv' . $ipVersion . '_bgp_prefixes, ipv' . $ipVersion . '_bgp_prefixes_temp TO ipv' . $ipVersion . '_bgp_prefixes;');
        DB::statement('RENAME TABLE ipv' . $ipVersion . '_bgp_table TO backup_ipv' . $ipVersion . '_bgp_table, ipv' . $ipVersion . '_bgp_table_temp TO ipv' . $ipVersion . '_bgp_table;');
        DB::statement('RENAME TABLE ipv' . $ipVersion . '_peers TO backup_ipv' . $ipVersion . '_peers, ipv' . $ipVersion . '_peers_temp TO ipv' . $ipVersion . '_peers;');

        // Delete old table
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Removing old production prefix table');
        DB::statement('DROP TABLE backup_ipv' . $ipVersion . '_bgp_prefixes');
        DB::statement('DROP TABLE backup_ipv' . $ipVersion . '_bgp_table');
        DB::statement('DROP TABLE backup_ipv' . $ipVersion . '_peers');

        // Remove RIB file
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Deleting RIB file');
        File::delete($filePath);

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('DONE!!!');
        $this->cli->br()->comment('===================================================');
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
