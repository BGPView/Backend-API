<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\ASNEmail;
use App\Models\IPv4BgpEntry;
use App\Models\IPv6BgpEntry;
use App\Models\IXMember;
use App\Models\RirAsnAllocation;
use App\Services\Whois;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\CLImate\CLImate;

class UpdateASNWhoisInfo extends Command
{

    private $cli;

    // For docs: https://beta.peeringdb.com/apidocs/
    private $peeringdb_url = 'https://www.peeringdb.com/api/net';
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

        $sourceAsns['allocated_asns'] = RirAsnAllocation::all();
        $sourceAsns['ix_ssns'] = IXMember::all();
        $sourceAsns['ipv4_bgp_asns'] = IPv4BgpEntry::select('asn')->distinct()->get();
        $sourceAsns['ipv6_bgp_asns'] = IPv6BgpEntry::select('asn')->distinct()->get();

        $asns = [];
        foreach ($sourceAsns as $sourceAsn) {
            foreach ($sourceAsn as $asnObj) {
                if (isset($asns[$asnObj->asn]) === false) {
                    $asns[$asnObj->asn] = isset($asnObj->rir_id) ? $asnObj->rir_id : null;
                }
            }
        }

        shuffle($asns);

        foreach ($asns as $as_number => $rir_id) {
            // Lets check if the ASN has already been looked at in the past
            if (ASN::where('asn', $as_number)->first() === null) {
                $this->cli->br()->comment('Looking up and adding: AS' . $as_number);

                $asnWhois = new Whois($as_number);
                $parsedWhois = $asnWhois->parse();

                $asn = new ASN;

                // Skip null results
                if (is_null($parsedWhois) === true) {

                    // Save the null entry
                    $asn->rir_id = $rir_id;
                    $asn->asn = $as_number;
                    $asn->raw_whois = $asnWhois->raw();
                    $asn->save();

                    continue;
                }

                $asn->rir_id = $rir_id;
                $asn->asn = $as_number;
                $asn->name = empty($parsedWhois->name) !== true ? $parsedWhois->name : null;
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

                $this->cli->br()->comment($asn->asn . ' - ' . $asn->description . ' ['.$asn->name.']')->br();
            }
        }


        // Ok, now that we are done with new allocations, lets update the old records
        $oldAsns = ASN::where('updated_at', '<', Carbon::now()->subMonth())->orderBy('updated_at', 'ASC')->limit(2000)->get();
        $oldAsns->shuffle();

        foreach ($oldAsns as $oldAsn) {
            $oldAsn->emails()->delete();

            $this->cli->br()->comment('Updating: AS' . $oldAsn->asn);
            $asnWhois = new Whois($oldAsn->asn);
            $parsedWhois = $asnWhois->parse();

            // Skip null results
            if (is_null($parsedWhois) === true) {
                continue;
            }

            $oldAsn->name = $parsedWhois->name;
            $oldAsn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $oldAsn->description_full = json_encode($parsedWhois->description);

            // If we have the PeerDB info lets update it.
            if ($peerDb = $this->getPeeringDbInfo($oldAsn->asn)) {
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
                $asnEmail = new ASNEmail;
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
}
