<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IX;
use App\Models\IXMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\CLImate\CLImate;
use Ubench;

class UpdateIXs extends Command
{
    private $cli;
    private $bench;
    private $ipUtils;
    private $peeringdb_ix_url = 'https://www.peeringdb.com/api/ix';
    private $peeringdb_ix_members_url = 'https://www.peeringdb.com/api/netixlan';
    private $ixs = [];
    private $ix_memebrs = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-ix-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the IX list and their membrs in the database';

    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli, Ubench $bench, IpUtils $ipUtils)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->bench = $bench;
        $this->ipUtils = $ipUtils;
    }

    public function cleanData($ixs)
    {
        foreach ($ixs as $ixKey => $ix) {
            foreach ($ix as $key => $value) {
                $value = trim($value);
                if (empty($value) === true) {
                    $ix->$key = null;
                } else {
                    $ix->$key = $value;
                }
            }
            $ix->$ixKey = $ix;
        }

        return $ixs;
    }

    private function loadIxList()
    {
        $this->cli->br()->comment('Downloading the Peeringhub IX list...')->br();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->peeringdb_ix_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        $peeringDB = curl_exec($ch);
        curl_close($ch);

        $ixData = json_decode($peeringDB)->data;
        $this->ixs = $this->cleanData($ixData);
    }

    private function loadIxMembersList()
    {
        $this->cli->br()->comment('Downloading the Peeringhub IX Members list...')->br();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->peeringdb_ix_members_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING , "gzip");
        $peeringDB = curl_exec($ch);
        curl_close($ch);

        $ixData = json_decode($peeringDB)->data;
        $this->ix_memebrs = $this->cleanData($ixData);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->loadIxList();
        $this->loadIxMembersList();
        $this->updateIxInfo();
        $this->updateIxMembersInfo();
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
                $newIx->name_full       = !empty($ix->name_long) ? $ix->name_long : $ix->name;
                $newIx->website         = $ix->website;
                $newIx->tech_email      = $ix->tech_email;
                $newIx->tech_phone      = $ix->tech_phone;
                $newIx->policy_email    = $ix->policy_email;
                $newIx->policy_phone    = $ix->policy_phone;
                $newIx->city            = $ix->city;
                $newIx->counrty_code    = $ix->country;
                $newIx->url_stats       = $ix->url_stats;
                $newIx->save();
                continue;
            }

            $this->cli->br()->comment('Updating IX: ' . $ix->name)->br();

            // Lets update the info that we have about the IX:
            $ixEntry->name          = $ix->name;
            $ixEntry->name_full     = !empty($ix->name_long) ? $ix->name_long : $ix->name;
            $ixEntry->website       = $ix->website;
            $ixEntry->tech_email    = $ix->tech_email;
            $ixEntry->tech_phone    = $ix->tech_phone;
            $ixEntry->policy_email  = $ix->policy_email;
            $ixEntry->policy_phone  = $ix->policy_phone;
            $ixEntry->city          = $ix->city;
            $ixEntry->counrty_code  = $ix->country;
            $ixEntry->url_stats     = $ix->url_stats;
            $ixEntry->save();
        }
    }

    private function updateIxMembersInfo()
    {
        $this->bench->start();
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Starting to re-enter the IX member data ');

        $memberList = '';
        $mysqlTime = date('Y-m-d H:i:s');

        // Cleaning up old temp table
        $this->cli->br()->comment('Drop old TEMP IX members table');
        DB::statement('DROP TABLE IF EXISTS ix_members_temp');

        // Creating a new temp table to store our new BGP data
        $this->cli->br()->comment('Cloning ix_members table schema');
        DB::statement('CREATE TABLE ix_members_temp LIKE ix_members');

        $this->cli->br()->comment('Entering ' . count($this->ix_memebrs) . ' IX members into DB');
        foreach ($this->ix_memebrs as $member) {

            if (empty($member->asn) === true) {
                continue;
            }

            $member->ipaddr4 = $member->ipaddr4 ?: 'NULL';
            $member->ipaddr6 = $member->ipaddr6 ?: 'NULL';
            $member->ipaddr4_dec = $member->ipaddr4 ? $this->ipUtils->ip2dec($member->ipaddr4) : 'NULL';
            $member->ipaddr6_dec = $member->ipaddr6 ? $this->ipUtils->ip2dec($member->ipaddr6) : 'NULL';

            $memberList .= '('.$member->ixlan_id.',
                            '.(int) $member->speed.',
                            '.$member->asn.',
                            "'.$member->ipaddr4.'",
                            '.$member->ipaddr4_dec.',
                            "'.$member->ipaddr6.'",
                            '.$member->ipaddr6_dec.',
                            "'.$mysqlTime.'",
                            "'.$mysqlTime.'"
                            ),';
        }

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Inserting all members in one bulk query');
        $memberList = rtrim($memberList, ',').';';
        DB::statement('INSERT INTO ix_members_temp (ix_peeringdb_id,speed,asn,ipv4_address,ipv4_dec,ipv6_address,ipv6_dec,updated_at,created_at) VALUES '.$memberList);

        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Swapping TEMP table with production table');
        DB::statement('RENAME TABLE ix_members TO backup_ix_members, ix_members_temp TO ix_members;');

        // Delete old table
        $this->cli->br()->comment('===================================================');
        $this->cli->br()->comment('Removing old production prefix table');
        DB::statement('DROP TABLE backup_ix_members');

        $this->output->newLine(1);
        $this->bench->end();
        $this->cli->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ))->br();
    }

}
