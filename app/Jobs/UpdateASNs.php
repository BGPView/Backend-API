<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\ASN;
use App\Models\ASNEmail;
use App\Services\Whois;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use League\CLImate\CLImate;

class UpdateASNs extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $oldAsn;
    protected $cli;
    protected $peeringDBData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($oldAsn, $peeringDBData)
    {
        $this->oldAsn = $oldAsn;
        $this->peeringDBData = $peeringDBData;
        $this->cli = new CLImate();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $oldAsn = $this->oldAsn;

        $oldAsn->emails()->delete();

        $this->cli->br()->comment('Updating: AS' . $oldAsn->asn);
        $asnWhois = new Whois($oldAsn->asn);
        $parsedWhois = $asnWhois->parse();

        // Skip null results
        if (is_null($parsedWhois) === true) {
            $oldAsn->touch();
            return;
        }

        $oldAsn->name = $parsedWhois->name;
        $oldAsn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : $parsedWhois->name;
        $oldAsn->description_full = count($parsedWhois->description) > 0 ? json_encode($parsedWhois->description) : json_encode([$oldAsn->description]);

        // If we have the PeerDB info lets update it.
        if ($peerDb = $this->peeringDBData) {
            $oldAsn->website = $peerDb->website;
            $oldAsn->looking_glass = $peerDb->looking_glass;
            $oldAsn->traffic_estimation = $peerDb->info_traffic;
            $oldAsn->traffic_ratio = $peerDb->info_ratio;
        }

        $oldAsn->counrty_code = $parsedWhois->counrty_code;
        $oldAsn->owner_address = json_encode($parsedWhois->address);
        $oldAsn->raw_whois = $asnWhois->raw();
        $oldAsn->save();

        // Save ASN Emails
        foreach ($parsedWhois->emails as $email) {
            $asnEmail = new ASNEmail();
            $asnEmail->asn_id = $oldAsn->id;
            $asnEmail->email_address = $email;

            // Check if its an abuse email
            if (in_array($email, $parsedWhois->abuse_emails)) {
                $asnEmail->abuse_email = true;
            }

            $asnEmail->save();
        }
    }
}
