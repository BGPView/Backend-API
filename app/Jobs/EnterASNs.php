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

class EnterASNs extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $as_number;
    protected $rir_id;
    protected $cli;
    protected $peeringDBData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($as_number, $rir_id, $peeringDBData)
    {
        $this->as_number = $as_number;
        $this->rir_id = $rir_id;
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
        $rir_id = $this->rir_id;
        $as_number = $this->as_number;

        $this->cli->br()->comment('Looking up and adding: AS' . $as_number);

        $asnWhois = new Whois($as_number);
        $parsedWhois = $asnWhois->parse();

        $asn = new ASN();

        // Skip null results
        if (is_null($parsedWhois) === true) {

            // Save the null entry
            $asn->rir_id = $rir_id;
            $asn->asn = $as_number;
            $asn->raw_whois = $asnWhois->raw();
            $asn->save();

            return;
        }

        $asn->rir_id = $rir_id;
        $asn->asn = $as_number;
        $asn->name = empty($parsedWhois->name) !== true ? $parsedWhois->name : null;
        $asn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : $asn->name;
        $asn->description_full = count($parsedWhois->description) > 0 ? json_encode($parsedWhois->description) : json_encode([$asn->description]);

        // Insert PeerDB Info if we get any
        if ($peerDb = $this->peeringDBData) {
            $asn->website = $peerDb->website;
            $asn->looking_glass = $peerDb->looking_glass;
            $asn->traffic_estimation = $peerDb->info_traffic;
            $asn->traffic_ratio = $peerDb->info_ratio;
        }

        $asn->counrty_code = $parsedWhois->counrty_code;
        $asn->owner_address = json_encode($parsedWhois->address);
        $asn->raw_whois = $asnWhois->raw();
        $asn->save();

        // Save ASN Emails
        foreach ($parsedWhois->emails as $email) {
            $asnEmail = new ASNEmail();
            $asnEmail->asn_id = $asn->id;
            $asnEmail->email_address = $email;

            // Check if its an abuse email
            if (in_array($email, $parsedWhois->abuse_emails)) {
                $asnEmail->abuse_email = true;
            }

            $asnEmail->save();
        }

        $this->cli->br()->comment($asn->asn . ' - ' . $asn->description . ' ['.$asn->name.']');
    }
}
