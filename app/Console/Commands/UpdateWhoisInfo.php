<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\ASNEmail;
use App\Models\RirAsnAllocation;
use App\Services\Whois;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
use Ubench;

class UpdateWhoisInfo extends Command
{

    private $cli;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-whois-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all old Whois info about ASN, IPv4 and IPv6 resources';

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
        $this->updateASN();
    }

    private function updateASN()
    {
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Adding newly allocated ASNs')->br();
        $allocatedAsns = RirAsnAllocation::all();

        // Lets check if the ASN has already been looked at in the past
        foreach ($allocatedAsns as $allocatedAsn)
        if (ASN::where('asn', $allocatedAsn->asn)->first() === null) {
            $this->cli->br()->comment('Looking up and adding: AS' . $allocatedAsn->asn . ' ['.$allocatedAsn->rir->name.']');
            sleep(1.5);
            $asnWhois = new Whois($allocatedAsn->asn);
            $parsedWhois = $asnWhois->parse();

            // Dont save things without names
            if (empty($parsedWhois->name) === true) {
                continue;
            }

            $asn = new ASN;
            $asn->rir_id = $allocatedAsn->rir->id;
            $asn->asn = $allocatedAsn->asn;
            $asn->name = $parsedWhois->name;
            $asn->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : null;
            $asn->description_full = json_encode($parsedWhois->description);

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
                'description' => $finalASN->description,
                'description_full' => json_decode($finalASN->description_full, true),
                'counrty_code' => $finalASN->counrty_code,
                'owner_address' => json_decode($finalASN->owner_address, true),
                'abuse_emails' => $finalASN->emails()->where('abuse_email', true)->get()->lists('email_address'),
                'emails' => $finalASN->emails()->lists('email_address'),
            ]);

        }

    }
}
