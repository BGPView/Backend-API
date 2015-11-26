<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IPv4PrefixEmail;
use App\Models\IPv4Prefix;
use App\Models\IPv6PrefixEmail;
use App\Models\IPv6Prefix;
use App\Services\BgpParser;
use App\Services\Whois;
use Carbon\Carbon;
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

        $this->downloadRIBs($filePath, 4);

        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount();
        $time = Carbon::now();

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

                // Skip of already in DB
                $prefixTest = IPv4Prefix::where('ip', $parsedLine->ip)->where('cidr', $parsedLine->cidr)->first();
                if (is_null($prefixTest) === false) {

                    $prefixTest->seen_at = $time;

                    // If the last time the prefix was scraped is older than 7 days, update it
                    if (strtotime($prefixTest->scraped_at) < $time->subWeeks(3)->timestamp) {
                        $this->cli->br()->comment('===================================================');
                        $this->cli->br()->comment('Updating older prefix whois info - ' . $prefixTest->ip . '/' . $prefixTest->cidr . ' [' . $ipAllocation->rir->name . ']')->br();

                        $ipWhois = new Whois($prefixTest->ip, $prefixTest->cidr);
                        $parsedWhois = $ipWhois->parse();

                        $prefixTest->name = $parsedWhois->name;
                        $prefixTest->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
                        $prefixTest->description_full = json_encode($parsedWhois->description);
                        $prefixTest->counrty_code = $parsedWhois->counrty_code;
                        $prefixTest->owner_address = json_encode($parsedWhois->address);
                        $prefixTest->raw_whois = $ipWhois->raw();
                        $prefixTest->seen_at = $time;
                        $prefixTest->scraped_at = $time;

                        // Lets remove old emails for this prefix
                        $prefixTest->emails()->delete();

                        // Save new emails
                        foreach ($parsedWhois->emails as $email) {
                            $prefixEmail = new IPv4PrefixEmail;
                            $prefixEmail->ipv4_prefix_id = $prefixTest->id;
                            $prefixEmail->email_address = $email;

                            // Check if its an abuse email
                            if (in_array($email, $parsedWhois->abuse_emails)) {
                                $prefixEmail->abuse_email = true;
                            }

                            $prefixEmail->save();
                        }

                        dump([
                            'name' => $prefixTest->name,
                            'description' => $prefixTest->description,
                            'description_full' => json_decode($prefixTest->description_full, true),
                            'counrty_code' => $prefixTest->counrty_code,
                            'owner_address' => json_decode($prefixTest->owner_address, true),
                            'abuse_emails' => $prefixTest->emails()->where('abuse_email', true)->get()->lists('email_address'),
                            'emails' => $prefixTest->emails()->lists('email_address'),
                        ]);

                        unset($ipWhois);
                        unset($parsedWhois);
                    }

                    // Update the prefix
                    $prefixTest->save();
                    continue;
                }

                $ipAllocation = $this->ipUtils->getAllocationEntry($parsedLine->ip);

                // Skip non allocated
                if (is_null($ipAllocation) === true) {
                    continue;
                }

                $this->cli->br()->comment('===================================================');
                $this->cli->br()->comment('Adding new prefix whois info - ' . $parsedLine->ip . '/' . $parsedLine->cidr . ' [' . $ipAllocation->rir->name . ']')->br();

                $ipWhois = new Whois($parsedLine->ip, $parsedLine->cidr);
                $parsedWhois = $ipWhois->parse();

                $ipv4Prefix = new IPv4Prefix;
                $ipv4Prefix->rir_id = $ipAllocation->rir_id;
                $ipv4Prefix->ip = $parsedLine->ip;
                $ipv4Prefix->cidr = $parsedLine->cidr;
                $ipv4Prefix->ip_dec_start = $this->ipUtils->ip2dec($parsedLine->ip);
                $ipv4Prefix->ip_dec_end = ($this->ipUtils->ip2dec($parsedLine->ip) + $ipv4AmountCidrArray[$parsedLine->cidr]);
                $ipv4Prefix->name = $parsedWhois->name;
                $ipv4Prefix->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
                $ipv4Prefix->description_full = json_encode($parsedWhois->description);
                $ipv4Prefix->counrty_code = $parsedWhois->counrty_code;
                $ipv4Prefix->owner_address = json_encode($parsedWhois->address);
                $ipv4Prefix->raw_whois = $ipWhois->raw();
                $ipv4Prefix->seen_at = $time;
                $ipv4Prefix->scraped_at = $time;
                $ipv4Prefix->save();

                // Save Prefix Emails
                foreach ($parsedWhois->emails as $email) {
                    $prefixEmail = new IPv4PrefixEmail;
                    $prefixEmail->ipv4_prefix_id = $ipv4Prefix->id;
                    $prefixEmail->email_address = $email;

                    // Check if its an abuse email
                    if (in_array($email, $parsedWhois->abuse_emails)) {
                        $prefixEmail->abuse_email = true;
                    }

                    $prefixEmail->save();
                }

                dump([
                    'name' => $ipv4Prefix->name,
                    'description' => $ipv4Prefix->description,
                    'description_full' => json_decode($ipv4Prefix->description_full, true),
                    'counrty_code' => $ipv4Prefix->counrty_code,
                    'owner_address' => json_decode($ipv4Prefix->owner_address, true),
                    'abuse_emails' => $ipv4Prefix->emails()->where('abuse_email', true)->get()->lists('email_address'),
                    'emails' => $ipv4Prefix->emails()->lists('email_address'),
                ]);
            }
            fclose($fp);

            // Remove all prefixes that are older than 1 day post udpating
            IPv4Prefix::where('seen_at', '<', Carbon::yesterday())->delete();
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
