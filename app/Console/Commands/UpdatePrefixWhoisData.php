<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IPv4PrefixWhoisEmail;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4PrefixWhois;
use App\Services\BgpParser;
use App\Services\Whois;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
use Ubench;

class UpdatePrefixWhoisData extends Command
{
    private $cli;
    private $bench;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-prefix-whois-data';

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
        $threeWeeksAgo = Carbon::now()->subWeeks(3)->timestamp;
        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount();

        $this->cli->br()->comment('Getting all the prefixes from the BGP table');
        $ipv4Prefixes = IPv4BgpPrefix::all();

        foreach ($ipv4Prefixes as $ipv4Prefix) {
            $prefixTest = IPv4PrefixWhois::where('bgp_prefix_id', $ipv4Prefix->id)->first();

            // Lets check if we have seen the prefix already
            if (is_null($prefixTest) !== true) {
                // If the last time the prefix was scraped is older than 3 weeks, update it
                if (strtotime($prefixTest->updated_at) < $threeWeeksAgo) {
                    $this->cli->br()->comment('===================================================');
                    $this->cli->br()->comment('Updating older prefix whois info - ' . $prefixTest->ip . '/' . $prefixTest->cidr)->br();

                    $ipWhois = new Whois($prefixTest->ip, $prefixTest->cidr);
                    $parsedWhois = $ipWhois->parse();

                    $prefixTest->name = $parsedWhois->name;
                    $prefixTest->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
                    $prefixTest->description_full = json_encode($parsedWhois->description);
                    $prefixTest->counrty_code = $parsedWhois->counrty_code;
                    $prefixTest->owner_address = json_encode($parsedWhois->address);
                    $prefixTest->raw_whois = $ipWhois->raw();

                    // Lets remove old emails for this prefix
                    $prefixTest->emails()->delete();

                    // Save new emails
                    foreach ($parsedWhois->emails as $email) {
                        $prefixEmail = new IPv4PrefixWhoisEmail;
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

                    $prefixTest->save();
                    continue;
                }
            }

            // Since we dont have the prefix in DB lets create it.

            $ipAllocation = $this->ipUtils->getAllocationEntry($ipv4Prefix->ip);

            // Skip non allocated
            if (is_null($ipAllocation) === true) {
                continue;
            }

            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Adding new prefix whois info - ' . $ipv4Prefix->ip . '/' . $ipv4Prefix->cidr)->br();

            $ipWhois = new Whois($ipv4Prefix->ip, $ipv4Prefix->cidr);
            $parsedWhois = $ipWhois->parse();

            $newPrefixWhois = new IPv4PrefixWhois;
            $newPrefixWhois->bgp_prefix_id = $ipv4Prefix->id;
            $newPrefixWhois->name = $parsedWhois->name;
            $newPrefixWhois->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $newPrefixWhois->description_full = json_encode($parsedWhois->description);
            $newPrefixWhois->counrty_code = $parsedWhois->counrty_code;
            $newPrefixWhois->owner_address = json_encode($parsedWhois->address);
            $newPrefixWhois->raw_whois = $ipWhois->raw();
            $newPrefixWhois->save();

            // Save Prefix Emails
            foreach ($parsedWhois->emails as $email) {
                $prefixEmail = new IPv4PrefixWhoisEmail;
                $prefixEmail->prefix_whois_id = $newPrefixWhois->id;
                $prefixEmail->email_address = $email;

                // Check if its an abuse email
                if (in_array($email, $parsedWhois->abuse_emails)) {
                    $prefixEmail->abuse_email = true;
                }

                $prefixEmail->save();
            }

            dump([
                'name' => $newPrefixWhois->name,
                'description' => $newPrefixWhois->description,
                'description_full' => json_decode($newPrefixWhois->description_full, true),
                'counrty_code' => $newPrefixWhois->counrty_code,
                'owner_address' => json_decode($newPrefixWhois->owner_address, true),
                'abuse_emails' => $newPrefixWhois->emails()->where('abuse_email', true)->get()->lists('email_address'),
                'emails' => $newPrefixWhois->emails()->lists('email_address'),
            ]);

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
