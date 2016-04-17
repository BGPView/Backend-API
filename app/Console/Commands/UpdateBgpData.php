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
        $this->updatePrefixes();
    }

    private function updatePrefixes()
    {
        $this->bench->start();

        $this->cli->br()->comment('===================================================');

        exec('bash '.base_path() . '/scripts/update_bgp_ribs.sh');
        $filePath = storage_path() . '/bgp_lines.txt';

        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount();
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        $seenV4Prefixes = [];
        $seenV6Prefixes = [];

        $seenV4TableEntries = [];
        $seenV6TableEntries = [];

        $seenV4Peers = [];
        $seenV6Peers = [];

        $newV4Peers = "";
        $newV6Peers = "";

        $newV4Prefixes = "";
        $newV6Prefixes = "";

        $newV4TableEntries = "";
        $newV6TableEntries = "";

        $v4counter = 0;
        $v6counter = 0;

        $v4PrefixCounter = 0;
        $v6PrefixCounter = 0;

        $mysqlTime = "'" . date('Y-m-d H:i:s') . "'";

        // Cleaning up old temp table
        $this->cli->br()->comment('Drop old v4 TEMP table');
        DB::statement('DROP TABLE IF EXISTS ipv4_bgp_prefixes_temp');
        DB::statement('DROP TABLE IF EXISTS ipv4_bgp_table_temp');
        DB::statement('DROP TABLE IF EXISTS ipv4_peers_temp');

        $this->cli->br()->comment('Drop old v6 TEMP table');
        DB::statement('DROP TABLE IF EXISTS ipv6_bgp_prefixes_temp');
        DB::statement('DROP TABLE IF EXISTS ipv6_bgp_table_temp');
        DB::statement('DROP TABLE IF EXISTS ipv6_peers_temp');

        // Creating a new temp table to store our new BGP data
        $this->cli->br()->comment('Cloning ipv4_bgp_prefixes table schema');
        DB::statement('CREATE TABLE ipv4_bgp_prefixes_temp LIKE ipv4_bgp_prefixes');
        DB::statement('CREATE TABLE ipv4_bgp_table_temp LIKE ipv4_bgp_table');
        DB::statement('CREATE TABLE ipv4_peers_temp LIKE ipv4_peers');

        $this->cli->br()->comment('Cloning ipv6_bgp_prefixes table schema');
        DB::statement('CREATE TABLE ipv6_bgp_prefixes_temp LIKE ipv6_bgp_prefixes');
        DB::statement('CREATE TABLE ipv6_bgp_table_temp LIKE ipv6_bgp_table');
        DB::statement('CREATE TABLE ipv6_peers_temp LIKE ipv6_peers');

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Processing input lines');

        // Lets read through the file
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {

                $parsedLine = $this->bgpParser->parse($line);

                // if we dont see an ASN then lets skip
                if (empty($parsedLine->asn) === true){
                    continue;
                }

                // Lets see if we have IPv4 input line
                $v4 = strpos($parsedLine->ip, '.') !== false ? true : false;

                // Now lets set all the variable names
                if ($v4) {
                    $ipVersion = 4;
                    $ipvAmountCidrArray = 'ipv4AmountCidrArray';
                    $seenPrefixes = 'seenV4Prefixes';
                    $seenTableEntries = 'seenV4TableEntries';
                    $seenPeers = 'seenV4Peers';
                    $newPeers = 'newV4Peers';
                    $newPrefixes = 'newV4Prefixes';
                    $newTableEntries = 'newV4TableEntries';
                    $counter = 'v4counter';
                    $prefixCounter = 'v4PrefixCounter';
                } else {
                    $ipVersion = 6;
                    $ipvAmountCidrArray = 'ipv6AmountCidrArray';
                    $seenPrefixes = 'seenV6Prefixes';
                    $seenTableEntries = 'seenV6TableEntries';
                    $seenPeers = 'seenV6Peers';
                    $newPeers = 'newV6Peers';
                    $newPrefixes = 'newV6Prefixes';
                    $newTableEntries = 'newV6TableEntries';
                    $counter = 'v6counter';
                    $prefixCounter = 'v6PrefixCounter';
                }

                // Lets try process the peers
                foreach ($parsedLine->peersSet as $peers) {
                    $peerKeyString = $peers[0] . '|' . $peers[1];
                    if (isset($$seenPeers[$peerKeyString]) === false) {
                        // Bulking the raw bulk insert
                        $$newPeers .= '('.$peers[0].','.$peers[1].','.$mysqlTime.','.$mysqlTime.'),';
                        $$seenPeers[$peerKeyString] = true;
                    }
                }

                $minCidrSize = $v4 ? 24 : 48;
                // Lets make sure that v4 is min /24
                if ($parsedLine->cidr > $minCidrSize || $parsedLine->cidr < 1) {
                    continue;
                }

                // Only entry prefixes which are not set
                if (isset($$seenPrefixes[$parsedLine->prefix.'|'.$parsedLine->asn]) === false) {

                    $$newPrefixes .= "('".$parsedLine->ip."','".$parsedLine->cidr."',".$this->ipUtils->ip2dec($parsedLine->ip).",".number_format(($this->ipUtils->ip2dec($parsedLine->ip) + $$ipvAmountCidrArray[$parsedLine->cidr] -1), 0, '', '').",".$parsedLine->asn.",".$this->bgpParser->checkROA($parsedLine->asn, $parsedLine->prefix).",".$mysqlTime.",".$mysqlTime."),";
                    $$seenPrefixes[$parsedLine->prefix.'|'.$parsedLine->asn] = true;
                    $$prefixCounter++;

                    if ($$prefixCounter > 250000 ) {
                        $this->cli->br()->comment('Inserting another 250,000 prefixes in one bulk query ('.$ipVersion.')');
                        $$newPrefixes = rtrim($$newPrefixes, ',').';';
                        DB::statement('INSERT INTO ipv4_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,roa_status,updated_at,created_at) VALUES '.$$newPrefixes);
                        $$newPrefixes = "";
                        $$prefixCounter = 0;
                    }
                }
                // Lets make sure we have a proper upstream (and not direct peering)
                if (is_null($parsedLine->upstream_asn) === true) {
                    continue;
                }

                // Skip any table entries we have already seen
                // isset() is MUCH faster than using in_array()
                if (isset($$seenTableEntries[$parsedLine->prefix.'|'.$parsedLine->path_string]) === true) {
                    continue;
                }

                // Yeah yeah, what bad practices... I know
                // However we are now able to bulk insert in a easy single transaction with no issues
                // This means we reduced our insert time from ~2 hours to ~10 seconds.
                // Yeah, its worth it!
                $$newTableEntries .= "('".$parsedLine->ip."','".$parsedLine->cidr."',".$parsedLine->asn.",".$parsedLine->upstream_asn.",'".$parsedLine->path_string."',".$mysqlTime.",".$mysqlTime."),";
                $$counter++;

                // Lets do a mass insert if the counter reach 100,00
                if ($$counter > 250000 ) {
                    $this->cli->br()->comment('Inserting another 250,000 bgp entries in one bulk query ('.$ipVersion.')');
                    $$newTableEntries = rtrim($$newTableEntries, ',').';';
                    DB::statement('INSERT INTO ipv' . $ipVersion . '_bgp_table_temp (ip,cidr,asn,upstream_asn,bgp_path,updated_at,created_at) VALUES '.$$newTableEntries);
                    $$newTableEntries = "";
                    $$counter = 0;
                }

                // Lets make note of the table entry we have seen
                // We are setting key here so above we can do a isset() check instead of in_array()
                $$seenTableEntries[$parsedLine->prefix.'|'.$parsedLine->path_string] = true;

            }
            fclose($fp);
        }

        // ================================================================
        $this->cli->br()->comment('Inserting all remaining prefixes in one bulk query (v4)');
        $newV4Prefixes = rtrim($newV4Prefixes, ',').';';
        DB::statement('INSERT INTO ipv4_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,updated_at,created_at) VALUES '.$newV4Prefixes);

        $this->cli->br()->comment('Inserting all remaining prefixes in one bulk query (v6)');
        $newV6Prefixes = rtrim($newV6Prefixes, ',').';';
        DB::statement('INSERT INTO ipv6_bgp_prefixes_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,updated_at,created_at) VALUES '.$newV6Prefixes);
        // ================================================================
        $this->cli->br()->comment('Inserting all remaining bgp table entries in one bulk query (v4)');
        $newV4TableEntries = rtrim($newV4TableEntries, ',').';';
        DB::statement('INSERT INTO ipv4_bgp_table_temp (ip,cidr,asn,upstream_asn,bgp_path,updated_at,created_at) VALUES '.$newV4TableEntries);

        $this->cli->br()->comment('Inserting all remaining bgp table entries in one bulk query (v6)');
        $newV6TableEntries = rtrim($newV6TableEntries, ',').';';
        DB::statement('INSERT INTO ipv6_bgp_table_temp (ip,cidr,asn,upstream_asn,bgp_path,updated_at,created_at) VALUES '.$newV6TableEntries);
        // ================================================================
        $this->cli->br()->comment('Inserting all peers in one bulk query (v4)');
        $newV4Peers = rtrim($newV4Peers, ',').';';
        DB::statement('INSERT INTO ipv4_peers_temp (asn_1,asn_2,updated_at,created_at) VALUES '.$newV4Peers);

        $this->cli->br()->comment('Inserting all peers in one bulk query (v6)');
        $newV6Peers = rtrim($newV6Peers, ',').';';
        DB::statement('INSERT INTO ipv6_peers_temp (asn_1,asn_2,updated_at,created_at) VALUES '.$newV6Peers);
        // ================================================================

        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();

        // Rename temp table to take over
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Swapping v4 TEMP table with production table');
        DB::statement('RENAME TABLE ipv4_bgp_prefixes TO backup_ipv4_bgp_prefixes, ipv4_bgp_prefixes_temp TO ipv4_bgp_prefixes;');
        DB::statement('RENAME TABLE ipv4_bgp_table TO backup_ipv4_bgp_table, ipv4_bgp_table_temp TO ipv4_bgp_table;');
        DB::statement('RENAME TABLE ipv4_peers TO backup_ipv4_peers, ipv4_peers_temp TO ipv4_peers;');
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Swapping v6 TEMP table with production table');
        DB::statement('RENAME TABLE ipv6_bgp_prefixes TO backup_ipv6_bgp_prefixes, ipv6_bgp_prefixes_temp TO ipv6_bgp_prefixes;');
        DB::statement('RENAME TABLE ipv6_bgp_table TO backup_ipv6_bgp_table, ipv6_bgp_table_temp TO ipv6_bgp_table;');
        DB::statement('RENAME TABLE ipv6_peers TO backup_ipv6_peers, ipv6_peers_temp TO ipv6_peers;');

        // Delete old table
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Removing old production 4 prefix table');
        DB::statement('DROP TABLE backup_ipv4_bgp_prefixes');
        DB::statement('DROP TABLE backup_ipv4_bgp_table');
        DB::statement('DROP TABLE backup_ipv4_peers');
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Removing old production 6 prefix table');
        DB::statement('DROP TABLE backup_ipv6_bgp_prefixes');
        DB::statement('DROP TABLE backup_ipv6_bgp_table');
        DB::statement('DROP TABLE backup_ipv6_peers');

        // Remove RIB file
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Deleting RIB file');
        File::delete($filePath);

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('DONE!!!');
        $this->cli->br()->comment('===================================================');
    }
}
