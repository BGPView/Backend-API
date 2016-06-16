<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\Rir;
use App\Models\RirAsnAllocation;
use App\Models\RirIPv4Allocation;
use App\Models\RirIPv6Allocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\CLImate\CLImate;
use Ubench;

class UpdateAllocationLists extends Command
{

    private $cli;
    private $ipUtils;
    private $bench;

    private $seenIpv4Allocation = [];
    private $seenIpv6Allocation = [];
    private $seenAsnAllocation = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-allocation-lists';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the RIR allocation lists (IPv4, IPv6 and ASN)';


    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli, IpUtils $ipUtils, Ubench $bench)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->ipUtils = $ipUtils;
        $this->bench = $bench;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cli->br()->comment('Updating the IPv4, IPv6 and ASN RIR allocated resources');

        foreach (Rir::all() as $rir) {
            $this->bench->start();
            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Downloading ' . $rir->name . ' allocation list');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rir->allocation_list_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }

            $output = curl_exec($ch);
            curl_close($ch);
            $this->output->newLine(1);
            $this->updateDb($rir, $output);
            $this->output->newLine(2);
            $output = null;

            $this->bench->end();
            $this->cli->info(sprintf(
                'Time: %s, Memory: %s',
                $this->bench->getTime(),
                $this->bench->getMemoryPeak()
            ))->br();
        }

        $this->bench->start();
        $mysqlTime = date('Y-m-d H:i:s');
        $this->cli->br()->comment('Inserting the allocations into DB');

        DB::statement('DROP TABLE IF EXISTS rir_ipv4_allocations_temp');
        DB::statement('DROP TABLE IF EXISTS rir_ipv6_allocations_temp');
        DB::statement('DROP TABLE IF EXISTS rir_asn_allocations_temp');

        DB::statement('CREATE TABLE rir_ipv4_allocations_temp LIKE rir_ipv4_allocations');
        DB::statement('CREATE TABLE rir_ipv6_allocations_temp LIKE rir_ipv6_allocations');
        DB::statement('CREATE TABLE rir_asn_allocations_temp LIKE rir_asn_allocations');

        $asnInsertLine = '';
        foreach ($this->seenAsnAllocation as $asn) {
            $date_allocated = $asn['date_allocated'] ? '"'.$asn['date_allocated'].'"' : 'null';
            $counrty_code = $asn['counrty_code'] ? '"'.$asn['counrty_code'].'"' : 'null';
            $asnInsertLine .= '('.$asn['rir_id'].','.$asn['asn'].','.$counrty_code.','.$date_allocated.',"'.$asn['status'].'","'.$mysqlTime.'", "'.$mysqlTime.'"),';
        }
        $asnInsertLine= rtrim($asnInsertLine, ',').';';
        DB::statement('INSERT INTO rir_asn_allocations_temp (rir_id,asn,counrty_code,date_allocated,status,updated_at,created_at) VALUES '.$asnInsertLine);

        $ipv4InsertLine = '';
        foreach ($this->seenIpv4Allocation as $ipv4) {
            $date_allocated = $ipv4['date_allocated'] ? '"'.$ipv4['date_allocated'].'"' : 'null';
            $counrty_code = $ipv4['counrty_code'] ? '"'.$ipv4['counrty_code'].'"' : 'null';
            $ipv4InsertLine .= '('.$ipv4['rir_id'].',"'.$ipv4['ip'].'",'.$ipv4['cidr'].','.$ipv4['ip_dec_start'].','.$ipv4['ip_dec_end'].','.$counrty_code.','.$date_allocated.',"'.$ipv4['status'].'","'.$mysqlTime.'", "'.$mysqlTime.'"),';
        }
        $ipv4InsertLine= rtrim($ipv4InsertLine, ',').';';
        DB::statement('INSERT INTO rir_ipv4_allocations_temp (rir_id,ip,cidr,ip_dec_start,ip_dec_end,counrty_code,date_allocated,status,updated_at,created_at) VALUES '.$ipv4InsertLine);

        $ipv6InsertLine = '';
        foreach ($this->seenIpv6Allocation as $ipv6) {
            $date_allocated = $ipv6['date_allocated'] ? '"'.$ipv6['date_allocated'].'"' : 'null';
            $counrty_code = $ipv6['counrty_code'] ? '"'.$ipv6['counrty_code'].'"' : 'null';
            $ipv6InsertLine .= '('.$ipv6['rir_id'].',"'.$ipv6['ip'].'",'.$ipv6['cidr'].','.$ipv6['ip_dec_start'].','.$ipv6['ip_dec_end'].','.$counrty_code.','.$date_allocated.',"'.$ipv6['status'].'", "'.$mysqlTime.'", "'.$mysqlTime.'"),';
        }
        $ipv6InsertLine= rtrim($ipv6InsertLine, ',').';';
        DB::statement('INSERT INTO rir_ipv6_allocations_temp (rir_id,ip,cidr,ip_dec_start,ip_dec_end,counrty_code,date_allocated,status,updated_at,created_at) VALUES '.$ipv6InsertLine);

        DB::statement('RENAME TABLE rir_ipv4_allocations TO backup_rir_ipv4_allocations, rir_ipv4_allocations_temp TO rir_ipv4_allocations;');
        DB::statement('RENAME TABLE rir_ipv6_allocations TO backup_rir_ipv6_allocations, rir_ipv6_allocations_temp TO rir_ipv6_allocations;');
        DB::statement('RENAME TABLE rir_asn_allocations TO backup_rir_asn_allocations, rir_asn_allocations_temp TO rir_asn_allocations;');

        DB::statement('DROP TABLE backup_rir_ipv4_allocations');
        DB::statement('DROP TABLE backup_rir_ipv6_allocations');
        DB::statement('DROP TABLE backup_rir_asn_allocations');

        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();
    }

    private function updateDb($rir, $list)
    {
        $lines = explode("\n", $list);
        $this->cli->br()->comment('Collection and parsing all resources from ' . $rir->name . ' allocation list ('.count($lines).' entries)');

        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount($reverse = true);
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        foreach ($lines as $line) {
            $data = explode('|', $line);

            // if it doesnt have 8 parts, skip
            if (count($data) !== 8 && isset($data[6]) === true && $data[6] != 'available' && $data[6] != 'reserved') {
                continue;
            }

            // Only take allocated resources
            if (empty($data[2]) !== true && empty($data[6]) !== true && empty($data[3]) !== true) {

                $resourceType = $data[2];

                // Replace 'ZZ' with null country code
                $data[1] = $data[1] == 'ZZ' ? null : $data[1];

                if ($resourceType === 'asn') {

                    if (isset($this->seenAsnAllocation[$data[3]]) === true) {
                        continue;
                    }

                    $this->seenAsnAllocation[$data[3]] = [
                        'rir_id' => $rir->id,
                        'asn' => $data[3],
                        'counrty_code' => $data[1] ?: null,
                        'date_allocated' => $data[5] ? substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2) : null,
                        'status' => $data[6],
                    ];

                } else if ($resourceType === 'ipv4') {

                    // Since some RIRs allocate random non CIDR addresses
                    // We shall split them up into the best CIDR that we can
                    // Really unhappy with this :/
                    if (isset($ipv4AmountCidrArray[$data[4]]) !== true) {
                        $roundedCidr = 32 - intval(log($data[4])/log(2));
                        $roundedAmount = pow(2, (32 - $roundedCidr));
                        $this->seenIpv4Allocation[$data[3].'/'.$roundedCidr] = [
                            'rir_id' => $rir->id,
                            'ip' => $data[3],
                            'cidr' => $roundedCidr,
                            'ip_dec_start' => $this->ipUtils->ip2dec($data[3]),
                            'ip_dec_end' => $this->ipUtils->ip2dec($data[3]) + $roundedAmount - 1,
                            'counrty_code' => $data[1] ?: null,
                            'date_allocated' => $data[5] ? substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2) : null,
                            'status' => $data[6],
                        ];

                        // Deal with the remainder
                        $remainingIps = $data[4] - $roundedAmount;
                        $remainCidr = 32 - intval(log($remainingIps)/log(2));
                        $startIpDec = $this->ipUtils->ip2dec($data[3]) + $roundedAmount;
                        $startIp = $this->ipUtils->dec2ip($startIpDec);
                        $this->seenIpv4Allocation[$data[3].'/'.$remainCidr] = [
                            'rir_id' => $rir->id,
                            'ip' => $startIp,
                            'cidr' => $remainCidr,
                            'ip_dec_start' => $startIpDec,
                            'ip_dec_end' => $startIpDec + $remainingIps - 1,
                            'counrty_code' => $data[1] ?: null,
                            'date_allocated' => $data[5] ? substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2) : null,
                            'status' => $data[6],
                        ];

                        continue;
                    }

                    $cidr = $ipv4AmountCidrArray[$data[4]];
                    if (isset($this->seenIpv4Allocation[$data[3].'/'.$cidr]) === true) {
                        continue;
                    }

                    $this->seenIpv4Allocation[$data[3].'/'.$cidr] = [
                        'rir_id' => $rir->id,
                        'ip' => $data[3],
                        'cidr' => $cidr,
                        'ip_dec_start' => $this->ipUtils->ip2dec($data[3]),
                        'ip_dec_end' => $this->ipUtils->ip2dec($data[3]) + $data[4] - 1,
                        'counrty_code' => $data[1] ?: null,
                        'date_allocated' => $data[5] ? substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2) : null,
                        'status' => $data[6],
                    ];

                } else if ($resourceType === 'ipv6') {

                    // If the amount of IP address are unknown, then lets continue...
                    if (isset($ipv6AmountCidrArray[$data[4]]) !== true) {
                        continue;
                    }

                    if (isset($this->seenIpv6Allocation[$data[3].'/'.$data[4]]) === true) {
                        continue;
                    }

                    $this->seenIpv6Allocation[$data[3].'/'.$data[4]] = [
                        'rir_id' => $rir->id,
                        'ip' => $data[3],
                        'cidr' => $data[4],
                        'ip_dec_start' => $this->ipUtils->ip2dec($data[3]),
                        'ip_dec_end' => ($this->ipUtils->ip2dec($data[3]) + $ipv6AmountCidrArray[$data[4]] - 1),
                        'counrty_code' => $data[1] ?: null,
                        'date_allocated' => $data[5] ? substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2) : null,
                        'status' => $data[6],
                    ];

                }
            }
        }
    }
}
