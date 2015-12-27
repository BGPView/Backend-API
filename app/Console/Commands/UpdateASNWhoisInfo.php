<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\ASNEmail;
use App\Models\RirAsnAllocation;
use App\Services\Whois;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\CLImate\CLImate;

class UpdateASNWhoisInfo extends Command
{

    private $cli;

    // For docs: https://beta.peeringdb.com/apidocs/
    private $peeringdb_url = 'https://beta.peeringdb.com/api/asn';
    private $peeringDBData;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-asn-whois';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all old Whois info about ASN resources';

    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli)
    {
        parent::__construct();
        $this->cli = $cli;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->loadPeeringDB();
        $this->updateASN();
    }

    private function loadPeeringDB()
    {
        $this->cli->br()->comment('Downloading the Peeringhub DB...')->br();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->peeringdb_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        $peeringDB = curl_exec($ch);
        curl_close($ch);

        $this->peeringDBData = json_decode($peeringDB)->data;
    }

    private function getPeeringDbInfo($asn)
    {
        foreach ($this->peeringDBData as $data) {
            if ($data->asn === $asn) {
                foreach ($data as $key => $value) {
                    if (empty($value) === true) {
                        $data->$key = null;
                    }
                }
                return $data;
            }
        }
        return null;
    }

    private function updateASN()
    {
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Adding newly allocated ASNs')->br();
        $allocatedAsns = RirAsnAllocation::all();


        foreach ($allocatedAsns as $allocatedAsn) {
            // Lets check if the ASN has already been looked at in the past
            if (ASN::where('asn', $allocatedAsn->asn)->first() === null) {
                $this->cli->br()->comment('Looking up and adding: AS' . $allocatedAsn->asn);

                $asnWhois = new Whois($allocatedAsn->asn);
                $parsedWhois = $asnWhois->parse();

                // Skip null results
                if (is_null($parsedWhois) === true) {
                    continue;
                }

                // Dont save things without names
                if (empty($parsedWhois->name) === true) {
                    continue;
                }

                $asn = new ASN;
                $asn->rir_id = $allocatedAsn->rir_id;
                $asn->asn = $allocatedAsn->asn;
                $asn->name = $parsedWhois->name;
                $asn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
                $asn->description_full = json_encode($parsedWhois->description);

                // Insert PeerDB Info if we get any
                if ($peerDb = $this->getPeeringDbInfo($asn->asn)) {
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
                    $asnEmail = new ASNEmail;
                    $asnEmail->asn_id = $asn->id;
                    $asnEmail->email_address = $email;

                    // Check if its an abuse email
                    if (in_array($email, $parsedWhois->abuse_emails)) {
                        $asnEmail->abuse_email = true;
                    }

                    $asnEmail->save();
                }

                $finalASN = ASN::where('id', $asn->id)->first();
                dump([
                    'name' => $finalASN->name,
                    'website' => $asn->website,
                    'looking_glass' => $asn->looking_glass,
                    'traffic_estimation' => $asn->traffic_estimation,
                    'traffic_ratio' => $asn->traffic_ratio,
                    'description' => $finalASN->description,
                    'description_full' => json_decode($finalASN->description_full, true),
                    'counrty_code' => $finalASN->counrty_code,
                    'owner_address' => json_decode($finalASN->owner_address, true),
                    'abuse_emails' => $finalASN->emails()->where('abuse_email', true)->get()->lists('email_address'),
                    'emails' => $finalASN->emails()->lists('email_address'),
                ]);
            }
        }


        // Ok, now that we are done with new allocations, lets update the old records
        $oldAsns = ASN::where('updated_at', '<', Carbon::now()->subMonth())->orderBy('updated_at', 'ASC')->limit(2000)->get();
        foreach ($oldAsns as $oldAsn) {
            $oldAsn->emails()->delete();

            $this->cli->br()->comment('Updating: AS' . $oldAsn->asn);
            $asnWhois = new Whois($oldAsn->asn);
            $parsedWhois = $asnWhois->parse();

            // Skip null results
            if (is_null($parsedWhois) === true) {
                continue;
            }

            $asn->name = $parsedWhois->name;
            $asn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $asn->description_full = json_encode($parsedWhois->description);

            // If we have the PeerDB info lets update it.
            if ($peerDb = $this->getPeeringDbInfo($asn->asn)) {
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
                $asnEmail = new ASNEmail;
                $asnEmail->asn_id = $asn->id;
                $asnEmail->email_address = $email;

                // Check if its an abuse email
                if (in_array($email, $parsedWhois->abuse_emails)) {
                    $asnEmail->abuse_email = true;
                }

                $asnEmail->save();
            }
        }


    }
}
