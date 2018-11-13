<?php

namespace App\Jobs;

use App\Helpers\IpUtils;
use App\Jobs\Job;
use App\Services\Whois;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\CLImate\CLImate;

class UpdatePrefixes extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $ipPrefix;
    protected $ipVersion;
    protected $cli;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ipVersion, $ipPrefix)
    {
        $this->ipVersion    = $ipVersion;
        $this->ipPrefix     = $ipPrefix;
        $this->cli           = new CLImate();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $oldPrefix    = $this->ipPrefix;
        $ipVersion    = $this->ipVersion;

        $ipWhois = new Whois($oldPrefix->ip, $oldPrefix->cidr);
        $parsedWhois = $ipWhois->parse();

        // If null, lets skip
        if (is_null($parsedWhois) === true) {
            $this->cli->br()->error('Seems that whois server returned no results for prefix: '.$oldPrefix->ip.'/'.$oldPrefix->cidr);
            return;
        }

        $oldPrefix->name = $parsedWhois->name;
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
}
