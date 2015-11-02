<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IPv6PrefixEmail;
use App\Models\IPv6Prefix;
use App\Services\BgpParser;
use App\Services\Whois;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use League\CLImate\CLImate;
use Ubench;

class UpdateBgpData extends Command
{

    private $ipv4RibDownloadUrl = "http://185.42.223.50/rib_ipv4.txt";
    private $ipv6RibDownloadUrl = "http://185.42.223.50/rib_ipv6.txt";
    private $cli;
    private $bench;
    private $progressStarted = false;
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
        $this->updateIPv6Prefixes();
        $this->updateIPv4Prefixes();
    }

    private function updateIPv6Prefixes()
    {
        $this->bench->start();
        $this->progressStarted = false;
        $this->cli->br()->comment('===================================================');
        $filePath = sys_get_temp_dir() . '/ipv6_rib.txt';

        $this->downloadRIBs($filePath, 6);

        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        // Lets read through the file
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                $parsedLine = $this->bgpParser->parse($line);
                $ipAllocation = $this->ipUtils->getAllocationEntry($parsedLine->ip);

                // Skip non allocated
                if (is_null($ipAllocation) === true) {
                    continue;
                }

                // Skip of already in DB
                $prefixTest = IPv6Prefix::where('ip', $parsedLine->ip)->where('cidr', $parsedLine->cidr)->first();
                if (is_null($prefixTest) === false) {
                    // ### TO DO: Now that we know the prefix is in DB we will need to update it or at least mark it
                    // Also need to figure out a way to remove all stale prefixes
                    continue;
                }

                $ipWhois = new Whois($parsedLine->ip);
                $parsedWhois = $ipWhois->parse();

                $ipv6Prefix = new IPv6Prefix;
                $ipv6Prefix->rir_id = $ipAllocation->rir->id;
                $ipv6Prefix->ip = $parsedLine->ip;
                $ipv6Prefix->cidr = $parsedLine->cidr;
                $ipv6Prefix->ip_dec_start = $this->ipUtils->ip2dec($parsedLine->ip);
                $ipv6Prefix->ip_dec_end = ($this->ipUtils->ip2dec($parsedLine->ip) + $ipv6AmountCidrArray[$parsedLine->cidr]);
                $ipv6Prefix->name = $parsedWhois->name;
                $ipv6Prefix->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
                $ipv6Prefix->description_full = json_encode($parsedWhois->description);
                $ipv6Prefix->counrty_code = $parsedWhois->counrty_code;
                $ipv6Prefix->owner_address = json_encode($parsedWhois->address);
                $ipv6Prefix->raw_whois = $ipWhois->raw();
                $ipv6Prefix->save();

                // Save ASN Emails
                foreach ($parsedWhois->emails as $email) {
                    $prefixEmail = new IPv6PrefixEmail;
                    $prefixEmail->ipv6_prefix_id = $ipv6Prefix->id;
                    $prefixEmail->email_address = $email;

                    // Check if its an abuse email
                    if (in_array($email, $parsedWhois->abuse_emails)) {
                        $prefixEmail->abuse_email = true;
                    }

                    $prefixEmail->save();
                }

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

        File::delete($filePath);
    }

    private function updateIPv4Prefixes()
    {
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $filePath = sys_get_temp_dir() . '/ipv4_rib.txt';

        $this->downloadRIBs($filePath, 4);

        // Lets read through the file
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                // ### TO DO: Here we will need to do the actual BGP entry processing
                dump();
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
