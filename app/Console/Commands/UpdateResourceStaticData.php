<?php

namespace App\Console\Commands;

use App\Models\ASN;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhois;
use Illuminate\Console\Command;
use Ubench;

class UpdateResourceStaticData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-static-resource-data';
    protected $bench;
    protected $whois;

    /**
     * Create a new command instance.
     */
    public function __construct(Ubench $bench)
    {
        parent::__construct();
        $this->bench = $bench;
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Meta Data for resrouces from RIR static list';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->bench->start();


        $this->processRipeAsn();
//

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

    }

    private function processRipeAsn() {
        $url = 'http://ftp.ripe.net/ripe/dbase/split/ripe.db.organisation.gz';
        $this->info('Downloading RIPE ' . $url);
        $gzipContents = file_get_contents($url);
        $contents = gzdecode($gzipContents);
        $ripeOrgs = explode("\n\n", $contents);
        $orgs = [];

        foreach ($ripeOrgs as $org) {
            $organisation = $this->extractValues($org, 'organisation');
            $name = $this->extractValues($org, 'name');
            $orgs[$organisation] = $name;
        }

        $url = 'http://ftp.ripe.net/ripe/dbase/split/ripe.db.aut-num.gz';
        $this->info('Downloading RIPE ' . $url);
        $gzipContents = file_get_contents($url);
        $contents = gzdecode($gzipContents);
        $asns = explode("\n\n", $contents);

        $this->info('Processing RIPE ASNs (' . count($asns) . ')');

        foreach($asns as $asn) {
            if (strpos($asn, 'aut-num:') !== false) {
                $asNumber = str_ireplace('as', '', $this->extractValues($asn, 'aut-num'));
                $name = $this->extractValues($asn, 'as-name');
                $description = $this->extractValues($asn, 'descr');
                $description = is_array($description) === true ? $description : empty($description) ? null : [$description];
                $org = $this->extractValues($asn, 'org');

                $newData = [
                    'name' => $name,
                ];

                if (is_null($description) !== true) {
                    $newData['description'] =  isset($description[0]) === true ? $description[0] : $description;
                    $newData['description_full'] = json_encode($description);
                } elseif (is_null($org) !== true && isset($orgs[$org]) === true) {
                    $newData['description'] =  $orgs[$org];
                    $newData['description_full'] = json_encode([$orgs[$org]]);
                }

                // dump('AS' . $asNumber, $newData);
                ASN::where('asn', $asNumber)->update($newData);
                $this->info('========================');
            }

        }


    }

    private function extractValues($string, $key)
    {
        $values = [];
        $key = strtolower(trim($key));
        $lines = explode("\n", $string);

        foreach ($lines as $line) {
            $lineParts = explode(":", $line, 2);
            if (strtolower(trim($lineParts[0])) === $key) {
                $testVal = trim($lineParts[1]);
                if (empty($testVal) !== true) {
                    $values[] = trim($lineParts[1]);
                }
            }
        }

        if (count($values) === 1) {
            return $values[0];
        }

        if (count($values) > 1) {
            return array_unique($values);
        }

        return null;
    }

}
