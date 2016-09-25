<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ubench;

class ProcessWhoisArin extends Command
{

    private $bench;
    private $ipUtils;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zWhois:ARIN';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process ARIN whois static files';

    /**
     * Create a new command instance.
     *
     * @return void
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
        // Clean up all old tables
        DB::statement('DROP TABLE IF EXISTS whois_db_arin_asns_temp');
        DB::statement('DROP TABLE IF EXISTS whois_db_arin_orgs_temp');
        DB::statement('DROP TABLE IF EXISTS whois_db_arin_pocs_temp');
        DB::statement('DROP TABLE IF EXISTS whois_db_arin_prefixes_temp');

        // Prep new temp tables for hot swap ability
        DB::statement('CREATE TABLE whois_db_arin_asns_temp LIKE whois_db_arin_asns');
        DB::statement('CREATE TABLE whois_db_arin_orgs_temp LIKE whois_db_arin_orgs');
        DB::statement('CREATE TABLE whois_db_arin_pocs_temp LIKE whois_db_arin_pocs');
        DB::statement('CREATE TABLE whois_db_arin_prefixes_temp LIKE whois_db_arin_prefixes');

        $this->warn("########################################################################");
        $this->warn("###  MAKE SURE 'max_allowed_packet' IS SET < 1G IN YOUR my.cnf file  ###");
        $this->warn("########################################################################");
        $this->processAsns();
        $this->processPrefixes();
        $this->processPocs();
        $this->processOrgs();

        // Apply the hot swaping
        DB::statement('RENAME TABLE whois_db_arin_asns TO backup_whois_db_arin_asns, whois_db_arin_asns_temp TO whois_db_arin_asns;');
        DB::statement('RENAME TABLE whois_db_arin_orgs TO backup_whois_db_arin_orgs, whois_db_arin_orgs_temp TO whois_db_arin_orgs;');
        DB::statement('RENAME TABLE whois_db_arin_pocs TO backup_whois_db_arin_pocs, whois_db_arin_pocs_temp TO whois_db_arin_pocs;');
        DB::statement('RENAME TABLE whois_db_arin_prefixes TO backup_whois_db_arin_prefixes, whois_db_arin_prefixes_temp TO whois_db_arin_prefixes;');


        // Clean up old backup tables
        DB::statement('DROP TABLE IF EXISTS backup_whois_db_arin_asns');
        DB::statement('DROP TABLE IF EXISTS backup_whois_db_arin_orgs');
        DB::statement('DROP TABLE IF EXISTS backup_whois_db_arin_pocs');
        DB::statement('DROP TABLE IF EXISTS whois_db_arin_prefixes');

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function processAsns()
    {
        $this->bench->start();

        $file = 'arin_db_asns.txt';
        $rawContent = file_get_contents($file);
        $this->info('Reading ARIN ASN whois file');

        // Split all block
        $whoisBlocks = explode("\n\n\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' ASN Blocks Found');


        $multiSqlInsertString = '';
        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);
            $orgId = $this->extractValues($whoisBlock, 'OrgID');
            $asns = $this->extractValues($whoisBlock, 'ASNumber');

            if (empty($asns) === true && $asns !== "0") {
                $this->warn('-------------------');
                $this->warn('Unknown ASN on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            // Detect if the ASN is a block rather than a single ASN
            if (is_numeric($asns) === true) {
                $asnStart = $asns;
                $asnEnd = $asns;
            } else {
                $asnParts = explode(" - ", $asns);
                $asnStart = trim($asns[0]);
                $asnEnd = trim($asns[1]);
            }

            // Bulk insert mysql
            $multiSqlInsertString .= '('.$asnStart.','.$asnEnd.',"'.$orgId.'", "'.addslashes($whoisBlock).'"),';
        }

        $this->info('Doing a bulk insert of all ASN Blocks');
        $multiSqlInsertString = rtrim($multiSqlInsertString, ',').';';
        DB::statement('INSERT INTO whois_db_arin_asns_temp (asn_start, asn_end, org_id, raw) VALUES ' . $multiSqlInsertString);

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));
        $this->warn('===================');

    }

    public function processPocs()
    {
        $this->bench->start();

        $file = 'arin_db_pocs.txt';
        $rawContent = file_get_contents($file);
        $this->info('Reading ARIN POC whois file');

        // Split all block
        $whoisBlocks = explode("\n\n\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' POC Blocks Found');


        $multiSqlInsertString = '';
        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);
            $pocId = $this->extractValues($whoisBlock, 'POCHandle');

            if (empty($pocId) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown POC on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            // Bulk insert mysql
            $multiSqlInsertString .= '("'.$pocId.'", "'.addslashes($whoisBlock).'"),';
        }

        $this->info('Doing a bulk insert of all POC Blocks');
        $multiSqlInsertString = rtrim($multiSqlInsertString, ',').';';
        DB::statement('INSERT INTO whois_db_arin_pocs_temp (poc_id, raw) VALUES ' . $multiSqlInsertString);

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));
        $this->warn('===================');

    }

    public function processOrgs()
    {
        $this->bench->start();

        $file = 'arin_db_orgs.txt';
        $rawContent = file_get_contents($file);
        $this->info('Reading ARIN ORG whois file');

        // Split all block
        $whoisBlocks = explode("\n\n\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' ORG Blocks Found');


        $multiSqlInsertString = '';
        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);
            $orgId = $this->extractValues($whoisBlock, 'OrgID');

            if (empty($orgId) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown ORG on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            // Bulk insert mysql
            $multiSqlInsertString .= '("'.$orgId.'", "'.addslashes($whoisBlock).'"),';
        }

        $this->info('Doing a bulk insert of all ORG Blocks');
        $multiSqlInsertString = rtrim($multiSqlInsertString, ',').';';
        DB::statement('INSERT INTO whois_db_arin_orgs_temp (org_id, raw) VALUES ' . $multiSqlInsertString);

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));
        $this->warn('===================');

    }

    public function processPrefixes()
    {
        $this->bench->start();

        $file = 'arin_db_prefixes.txt';
        $rawContent = file_get_contents($file);
        $this->info('Reading ARIN Prefix whois file');

        // Split all block
        $whoisBlocks = explode("\n\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' Prefix Blocks Found');


        $multiSqlInsertString = '';
        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);
            $netRange = $this->extractValues($whoisBlock, 'NetRange');

            if (empty($netRange) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown NetRange on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $netRangeParts = explode(' - ', $netRange);
            $ipDecStart = $this->ipUtils->ip2dec($netRangeParts[0]);
            $ipDecEnd = $this->ipUtils->ip2dec($netRangeParts[1]);

            // Bulk insert mysql
            $multiSqlInsertString .= '('.$ipDecStart.','.$ipDecEnd.',"'.addslashes($whoisBlock).'"),';
        }

        $this->info('Doing a bulk insert of all Prefix Blocks');
        $multiSqlInsertString = rtrim($multiSqlInsertString, ',').';';
        DB::statement('INSERT INTO whois_db_arin_prefixes_temp (ip_dec_start, ip_dec_end, raw) VALUES ' . $multiSqlInsertString);

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('===================');
    }

    private function extractValues($rawData, $key, $first = true)
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
