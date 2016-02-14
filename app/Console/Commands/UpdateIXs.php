<?php

namespace App\Console\Commands;

use App\Models\IX;
use App\Models\IXEmail;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
use Ubench;

class UpdateIXs extends Command
{
    private $cli;
    private $bench;
    private $peeringdb_url = 'https://beta.peeringdb.com/api/ix';
    private $ixs = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-ix-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the IX list in the database';

    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli, Ubench $bench)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->bench = $bench;
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

        $this->ixs = json_decode($peeringDB)->data;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->loadPeeringDB();
        $this->updateIxInfo();
    }

    private function updateIxInfo()
    {
        foreach ($this->ixs as $ix) {

            // Normilise the emails
            $emails = [];
            if (!empty($ix->tech_email)) {
                $emails[] = strtolower($ix->tech_email);
            }
            if (!empty($ix->policy_email)) {
                $emails[] = strtolower($ix->policy_email);
            }
            $emails = array_unique($emails);


            $ixEntry = IX::where('peeringdb_id', $ix->id)->first();
            if (is_null($ixEntry) === true) {
                // As this entry does not exists we will create it.
                $this->cli->br()->comment('Adding IX: ' . $ix->name)->br();

                $newIx = new IX;
                $newIx->peeringdb_id    = $ix->id;
                $newIx->name            = $ix->name;
                $newIx->name_full       = empty($ix->name_long) ? $ix->name : $ix->name_long;
                $newIx->website         = empty($ix->website) ? null : $ix->website;
                $newIx->city            = empty($ix->city) ? null : $ix->city;
                $newIx->counrty_code    = $ix->country;
                $newIx->url_stats       = empty($ix->url_stats) ? null : $ix->url_stats;
                $newIx->save();

                foreach ($emails as $email) {
                    $newEmail = new IXEmail;
                    $newEmail->ix_id    = $newIx->id;
                    $newEmail->email_address = $email;
                    $newEmail->save();
                }
                continue;
            }

            $this->cli->br()->comment('Updating IX: ' . $ix->name)->br();

            // Lets update the info that we have about the IX:
            $ixEntry->name            = $ix->name;
            $ixEntry->name_full       = empty($ix->name_long) ? null : $ix->name_long;
            $ixEntry->website         = empty($ix->website) ? null : $ix->website;
            $ixEntry->city            = empty($ix->city) ? null : $ix->city;
            $ixEntry->counrty_code    = $ix->country;
            $ixEntry->url_stats       = empty($ix->url_stats) ? null : $ix->url_stats;
            $ixEntry->save();

            $ixEntry->emails()->delete();
            foreach ($emails as $email) {
                $newEmail = new IXEmail;
                $newEmail->ix_id    = $ixEntry->id;
                $newEmail->email_address = $email;
                $newEmail->save();
            }
        }
    }
}
