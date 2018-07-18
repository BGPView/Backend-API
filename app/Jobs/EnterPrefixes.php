<?php

namespace App\Jobs;

use App\Helpers\IpUtils;
use App\Jobs\Job;
use App\Services\Whois;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnterPrefixes extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $ipPrefix;
    protected $ipVersion;
    protected $ipAllocation;
    protected $ipUtils;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ipVersion, $ipPrefix, $ipAllocation)
    {
        $this->ipVersion    = $ipVersion;
        $this->ipPrefix     = $ipPrefix;
        $this->ipAllocation = $ipAllocation;
        $this->ipUtils      = new IpUtils();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ipPrefix     = $this->ipPrefix;
        $ipVersion    = $this->ipVersion;
        $ipAllocation = $this->ipAllocation;

        $ipWhois     = new Whois($ipPrefix->ip, $ipPrefix->cidr);
        $parsedWhois = $ipWhois->parse();

        $className      = 'App\Models\IPv' . $ipVersion . 'PrefixWhois';
        $newPrefixWhois = new $className;

        // Skip null results
        if (is_null($parsedWhois) === true) {

            // Add them as null record
            $newPrefixWhois->ip        = $ipPrefix->ip;
            $newPrefixWhois->cidr      = $ipPrefix->cidr;
            $newPrefixWhois->raw_whois = $ipWhois->raw();
            $newPrefixWhois->save();

            return;
        }

        $newPrefixWhois->rir_id = $ipAllocation->rir_id;
        $newPrefixWhois->ip     = $ipPrefix->ip;
        $newPrefixWhois->cidr   = $ipPrefix->cidr;

        $newPrefixWhois->ip_dec_start = $this->ipUtils->ip2dec($newPrefixWhois->ip);
        if ($this->ipUtils->getInputType($newPrefixWhois->ip) === 4) {
            $ipv4Cidrs = $this->ipUtils->IPv4cidrIpCount();
            $ipCount   = $ipv4Cidrs[$newPrefixWhois->cidr];
        } else {
            $ipv6Cidrs = $this->ipUtils->IPv6cidrIpCount();
            $ipCount   = $ipv6Cidrs[$newPrefixWhois->cidr];
        }
        $newPrefixWhois->ip_dec_end = bcsub(bcadd($ipCount, $newPrefixWhois->ip_dec_start), 1);

        $newPrefixWhois->parent_ip        = $ipAllocation->ip;
        $newPrefixWhois->parent_cidr      = $ipAllocation->cidr;
        $newPrefixWhois->name             = $parsedWhois->name;
        $newPrefixWhois->description_full = json_encode($parsedWhois->description);
        $newPrefixWhois->counrty_code     = $parsedWhois->counrty_code;
        $newPrefixWhois->owner_address    = json_encode($parsedWhois->address);
        $newPrefixWhois->raw_whois        = $ipWhois->raw();

        $newPrefixWhois->save();

        // Save Prefix Emails
        foreach ($parsedWhois->emails as $email) {
            $className                    = 'App\Models\IPv' . $ipVersion . 'PrefixWhoisEmail';
            $prefixEmail                  = new $className;
            $prefixEmail->prefix_whois_id = $newPrefixWhois->id;
            $prefixEmail->email_address   = $email;

            // Check if its an abuse email
            if (in_array($email, $parsedWhois->abuse_emails)) {
                $prefixEmail->abuse_email = true;
            }

            $prefixEmail->save();
        }

        dump([
            'prefix'           => $newPrefixWhois->ip . '/' . $newPrefixWhois->cidr,
            'name'             => $newPrefixWhois->name,
            'description'      => $newPrefixWhois->description,
            'description_full' => $newPrefixWhois->description_full,
            'counrty_code'     => $newPrefixWhois->counrty_code,
            'owner_address'    => $newPrefixWhois->owner_address,
        ]);
    }
}
