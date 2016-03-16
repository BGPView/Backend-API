<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IPv4PrefixWhoisEmail;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhoisEmail;
use App\Models\IPv6BgpPrefix;
use App\Models\IPv6PrefixWhois;
use App\Models\RirIPv4Allocation;
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
    private $bgpParser;
    private $ipUtils;
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
        $this->updatePrefixes(6);
        $this->updatePrefixes(4);
    }

    private function updatePrefixes($ipVersion)
    {
        $ipVersion = (string) $ipVersion;
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $threeWeeksAgo = Carbon::now()->subWeeks(3)->timestamp;

        $this->cli->br()->comment('Getting all the IPv' . $ipVersion . 'prefixes from the BGP table');

        $className = 'App\Models\RirIPv' . $ipVersion . 'Allocation';
        $sourcePrefixes['rir_prefixes'] = $className::all();

        $className = 'App\Models\IPv' . $ipVersion . 'BgpPrefix';
        $sourcePrefixes['bgp_prefixes'] = $className::all();

        $prefixes = [];
        foreach ($sourcePrefixes as $sourcePrefix) {
            foreach ($sourcePrefix as $prefixObj) {
                if (isset($prefixes[$prefixObj->ip . '/' . $prefixObj->cidr]) === false) {
                    $prefixes[$prefixObj->ip . '/' . $prefixObj->cidr] = $prefixObj;
                }
            }
        }

        shuffle($prefixes);

        foreach ($prefixes as $ipPrefix) {

            // Lets skip if its a bogon address
            if ($ipVersion == 4 && $this->ipUtils->isBogonAddress($ipPrefix->ip)) {
                $this->cli->br()->comment('Skipping Bogon Address - '.$ipPrefix->ip.'/'.$ipPrefix->cidr);
                continue;
            }

            $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhois';
            $prefixTest = $className::where('ip', $ipPrefix->ip)->where('cidr', $ipPrefix->cidr)->first();

            // Lets check if we have seen the prefix already
            if (is_null($prefixTest) !== true) {
                // If the last time the prefix was scraped is older than 3 weeks, update it
                if (strtotime($prefixTest->updated_at) < $threeWeeksAgo) {
                    $this->cli->br()->comment('===================================================');
                    $this->cli->br()->comment('Updating older prefix whois info - ' . $ipPrefix->ip . '/' . $ipPrefix->cidr)->br();

                    $ipWhois = new Whois($ipPrefix->ip, $ipPrefix->cidr);
                    $parsedWhois = $ipWhois->parse();

                    // Skip null results
                    if (is_null($parsedWhois) === true) {
                        continue;
                    }

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
                        $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhoisEmail';
                        $prefixEmail = new $className;
                        $prefixEmail->prefix_whois_id = $prefixTest->id;
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
                        'description_full' => $prefixTest->description_full,
                        'counrty_code' => $prefixTest->counrty_code,
                        'owner_address' => $prefixTest->owner_address,
                        'abuse_emails' => $prefixTest->emails()->where('abuse_email', true)->get()->lists('email_address'),
                        'emails' => $prefixTest->emails()->lists('email_address'),
                    ]);

                    $prefixTest->save();
                }

                continue;
            }

            // Since we dont have the prefix in DB lets create it.

            $ipAllocation = $this->ipUtils->getAllocationEntry($ipPrefix->ip);

            // Skip non allocated
            if (is_null($ipAllocation) === true) {
                continue;
            }

            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Adding new prefix whois info - ' . $ipPrefix->ip . '/' . $ipPrefix->cidr)->br();

            $ipWhois = new Whois($ipPrefix->ip, $ipPrefix->cidr);
            $parsedWhois = $ipWhois->parse();

            $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhois';
            $newPrefixWhois = new $className;

            // Skip null results
            if (is_null($parsedWhois) === true) {

                // Add them as null record
                $newPrefixWhois->ip = $ipPrefix->ip;
                $newPrefixWhois->cidr = $ipPrefix->cidr;
                $newPrefixWhois->raw_whois = $ipWhois->raw();
                $newPrefixWhois->save();

                continue;
            }

            $newPrefixWhois->ip = $ipPrefix->ip;
            $newPrefixWhois->cidr = $ipPrefix->cidr;
            $newPrefixWhois->name = $parsedWhois->name;
            $newPrefixWhois->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $newPrefixWhois->description_full = json_encode($parsedWhois->description);
            $newPrefixWhois->counrty_code = $parsedWhois->counrty_code;
            $newPrefixWhois->owner_address = json_encode($parsedWhois->address);
            $newPrefixWhois->raw_whois = $ipWhois->raw();
            $newPrefixWhois->save();

            // Save Prefix Emails
            foreach ($parsedWhois->emails as $email) {
                $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhoisEmail';
                $prefixEmail = new $className;
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
                'description_full' => $newPrefixWhois->description_full,
                'counrty_code' => $newPrefixWhois->counrty_code,
                'owner_address' => $newPrefixWhois->owner_address,
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
