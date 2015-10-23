<?php

namespace App\Console\Commands;

use App\helpers\IpUtils;
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
        RirIPv4Allocation::truncate();
        RirIPv6Allocation::truncate();

        $this->cli->br()->comment('Updating the IPv4, IPv6 and ASN RIR allocated resources');

        $rirs = RIR::all();

        foreach ($rirs as $rir) {
            $this->bench->start();
            $this->progressStarted = false;
            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Downloading ' . $rir->rir_name . ' allocation list');

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
        $this->cli->br()->comment('Updating DB with the ' . $rir->rir_name . ' allocation list ('.count($lines).' entries)');

        $bar = $this->output->createProgressBar(count($lines));

        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount($rverse = true);

        foreach ($lines as $line) {
            $data = explode('|', $line);

            // Only take allocated resources
            if (isset($data[2]) === true && isset($data[6]) === true && $data[6] === 'allocated') {
                $resourceType = $data[2];

                if ($resourceType === 'asn') {

                    RirAsnAllocation::create([
                        'rir_id' => $rir->id,
                        'asn' => $data[3],
                        'counrty_code' => $data[1],
                        'date_allocated' => $data[5],
                    ]);

                } else if ($resourceType === 'ipv4') {

                    // If the amount of IP address are unknown, then lets continue...
                    if (isset($ipv4AmountCidrArray[$data[4]]) !== true) {
                        continue;
                    }

                    RirIPv4Allocation::create([
                        'rir_id' => $rir->id,
                        'ip' => $data[3],
                        'cidr' => $ipv4AmountCidrArray[$data[4]],
                        'counrty_code' => $data[1],
                        'date_allocated' => $data[5],
                    ]);
                } else if ($resourceType === 'ipv6') {
                    RirIPv6Allocation::create([
                        'rir_id' => $rir->id,
                        'ip' => $data[3],
                        'cidr' => $data[4],
                        'counrty_code' => $data[1],
                        'date_allocated' => $data[5],
                    ]);
                }

                $bar->advance();
                // Continue the rest
            }
        }

        $bar->finish();

    }
}
