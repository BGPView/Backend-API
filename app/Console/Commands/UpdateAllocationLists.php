<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\RIR;
use App\Models\RirAsnAllocation;
use App\Models\RirIPv4Allocation;
use App\Models\RirIPv6Allocation;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
use Ubench;

class UpdateAllocationLists extends Command
{

    private $cli;
    private $ipUtils;
    private $bench;
    private $progressStarted = false;
    private $progressBar = null;
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

        foreach (RIR::all() as $rir) {
            $this->bench->start();
            $this->progressStarted = false;
            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Downloading ' . $rir->name . ' allocation list');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rir->allocation_list_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'downloadProgress']);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $output = curl_exec($ch);
            curl_close($ch);
            $this->progressBar->finish();
            $this->progressBar = null;
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


    }

    private function downloadProgress($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {

        if ($this->progressStarted === false) {
            $this->progressBar = $this->output->createProgressBar($download_size);
            $this->progressStarted = true;
        }

        if($download_size > 0 && isset($this->progressBar) ===true) {
            $this->progressBar->setProgress($downloaded);
        }
    }

    private function updateDb($rir, $list)
    {
        $lines = explode("\n", $list);
        $this->cli->br()->comment('Updating DB with the ' . $rir->name . ' allocation list ('.count($lines).' entries)');

        $bar = $this->output->createProgressBar(count($lines));

        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount($reverse = true);
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        foreach ($lines as $line) {
            $data = explode('|', $line);

            // Only take allocated resources
            if (isset($data[2]) === true && isset($data[6]) === true && ($data[6] === 'allocated' || $data[6] === 'assigned')) {
                $resourceType = $data[2];

                if (empty($data[5])) {
                    $data[5] = "2000101";
                }

                if ($resourceType === 'asn') {

                    if (is_null(RirAsnAllocation::where('asn', $data[3])->first()) === true) {
                        $asn = RirAsnAllocation::create([
                            'rir_id' => $rir->id,
                            'asn' => $data[3],
                            'counrty_code' => $data[1],
                            'date_allocated' => substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2),
                        ]);
                        $this->cli->br()->comment("Entering new ASN: AS" . $asn->asn . " [" . $rir->name . "]");
                    }

                } else if ($resourceType === 'ipv4') {

                    // If the amount of IP address are unknown, then lets continue...
                    if (isset($ipv4AmountCidrArray[$data[4]]) !== true) {
                        continue;
                    }

                    if (is_null(RirIPv4Allocation::where('ip', $data[3])->where('cidr', $ipv4AmountCidrArray[$data[4]])->first()) === true) {
                        $ipv4 = RirIPv4Allocation::create([
                            'rir_id' => $rir->id,
                            'ip' => $data[3],
                            'cidr' => $ipv4AmountCidrArray[$data[4]],
                            'ip_dec_start' => $this->ipUtils->ip2dec($data[3]),
                            'ip_dec_end' => $this->ipUtils->ip2dec($data[3]) + $data[4],
                            'counrty_code' => $data[1],
                            'date_allocated' => substr($data[5], 0 , 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2),
                        ]);
                        $this->cli->br()->comment("Entering new IPv4 range: " . $ipv4->ip . " [" . $rir->name . "]");
                    }

                } else if ($resourceType === 'ipv6') {

                    // If the amount of IP address are unknown, then lets continue...
                    if (isset($ipv6AmountCidrArray[$data[4]]) !== true) {
                        continue;
                    }
                    
                    if (is_null(RirIPv6Allocation::where('ip', $data[3])->where('cidr', $data[4])->first()) === true) {

                        $ipv6 = RirIPv6Allocation::create([
                            'rir_id' => $rir->id,
                            'ip' => $data[3],
                            'cidr' => $data[4],
                            'ip_dec_start' => $this->ipUtils->ip2dec($data[3]),
                            'ip_dec_end' => ($this->ipUtils->ip2dec($data[3]) + $ipv6AmountCidrArray[$data[4]]),
                            'counrty_code' => $data[1],
                            'date_allocated' => substr($data[5], 0, 4) . "-" . substr($data[5], 4, 2) . "-" . substr($data[5], 6, 2),
                        ]);
                        $this->cli->br()->comment("Entering new IPv4 range: " . $ipv6->ip . " [" . $rir->name . "]");
                    }
                }

                $bar->advance();
                // Continue the rest
            }
        }

        $bar->finish();

    }
}
