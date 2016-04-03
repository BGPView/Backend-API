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
use Illuminate\Support\Facades\DB;
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
        $this->updateOldPrefixes(6);
        $this->updateOldPrefixes(4);
    }

    private function updatePrefixes($ipVersion)
    {
        $ipVersion = (string) $ipVersion;
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $threeWeeksAgo = Carbon::now()->subWeeks(3)->timestamp;

        $this->cli->br()->comment('Getting all the IPv' . $ipVersion . 'prefixes from the BGP table');

        $className = 'App\Models\RirIPv' . $ipVersion . 'Allocation';
        $sourcePrefixes['rir_prefixes'] = $className::all()->shuffle();

        $className = 'App\Models\IPv' . $ipVersion . 'BgpPrefix';
        $sourcePrefixes['bgp_prefixes'] = $className::all()->shuffle();

        $prefixes = [];
        foreach ($sourcePrefixes as $sourcePrefix) {
            foreach ($sourcePrefix as $prefixObj) {
                if (isset($prefixes[$prefixObj->ip . '/' . $prefixObj->cidr]) === false) {
                    $prefixes[$prefixObj->ip . '/' . $prefixObj->cidr] = $prefixObj;
                }
            }
        }

        foreach ($prefixes as $ipPrefix) {

            // Lets skip if its a bogon address
            if ($ipVersion == 4 && $this->ipUtils->isBogonAddress($ipPrefix->ip)) {
                $this->cli->br()->comment('Skipping Bogon Address - '.$ipPrefix->ip.'/'.$ipPrefix->cidr);
                continue;
            }

            $prefixTest = DB::table('ipv' . $ipVersion . '_prefix_whois')->where('ip', $ipPrefix->ip)->where('cidr', $ipPrefix->cidr)->first();
            if (is_null($prefixTest) !== true) {
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

            $newPrefixWhois->rir_id = $ipAllocation->rir_id;
            $newPrefixWhois->ip = $ipPrefix->ip;
            $newPrefixWhois->cidr = $ipPrefix->cidr;
            $newPrefixWhois->parent_ip = $ipAllocation->ip;
            $newPrefixWhois->parent_cidr = $ipAllocation->cidr;
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

    private function updateOldPrefixes($ipVersion)
    {
        $ipVersion = (string) $ipVersion;
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $threeWeeksAgo = Carbon::now()->subWeeks(3)->timestamp;

        $this->cli->br()->comment('Getting all OLD IPv' . $ipVersion . 'prefixes from the whois table');

        $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhois';
        $oldPrefixes = $className::where('updated_at', '<', Carbon::now()->subMonth())->orderBy('updated_at', 'ASC')->limit(2000)->get();

        foreach ($oldPrefixes as $oldPrefix) {
            $this->cli->br()->comment('===================================================');
            $this->cli->br()->comment('Updating prefix whois info - ' . $oldPrefix->ip . '/' . $oldPrefix->cidr)->br();

            $ipWhois = new Whois($oldPrefix->ip, $oldPrefix->cidr);
            $parsedWhois = $ipWhois->parse();

            // If null, lets skip
            if (is_null($parsedWhois) === true) {
                $this->cli->br()->error('Seems that whois server returned no results for prefix');
                continue;
            }

            $oldPrefix->name = $parsedWhois->name;
            $oldPrefix->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $oldPrefix->description_full = json_encode($parsedWhois->description);
            $oldPrefix->counrty_code = $parsedWhois->counrty_code;
            $oldPrefix->owner_address = json_encode($parsedWhois->address);
            $oldPrefix->raw_whois = $ipWhois->raw();
            $oldPrefix->save();

            // Save Prefix Emails
            $oldPrefix->emails()->delete();
            foreach ($parsedWhois->emails as $email) {
                $className = 'App\Models\IPv' . $ipVersion . 'PrefixWhoisEmail';
                $prefixEmail = new $className;
                $prefixEmail->prefix_whois_id = $oldPrefix->id;
                $prefixEmail->email_address = $email;

                // Check if its an abuse email
                if (in_array($email, $parsedWhois->abuse_emails)) {
                    $prefixEmail->abuse_email = true;
                }

                $prefixEmail->save();
            }

            dump([
                'name' => $oldPrefix->name,
                'description' => $oldPrefix->description,
                'description_full' => $oldPrefix->description_full,
                'counrty_code' => $oldPrefix->counrty_code,
                'owner_address' => $oldPrefix->owner_address,
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
