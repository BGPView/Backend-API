<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\ASN;
use App\Models\ASNEmail;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6PrefixWhois;
use App\Models\Rir;
use App\Services\Whois;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
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
    protected $ipUtils;
    protected $cli;

    // For docs: https://beta.peeringdb.com/apidocs/
    private $peeringdb_url = 'https://www.peeringdb.com/api/net';
    private $peeringDBData;

    /**
     * Create a new command instance.
     */
    public function __construct(Ubench $bench, IpUtils $ipUtils, CLImate $cli)
    {
        parent::__construct();
        $this->bench = $bench;
        $this->cli = $cli;
        $this->ipUtils = $ipUtils;
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

        $this->loadPeeringDB();

        $this->processAfrinic();
//        $this->processApnicAsn();
//        $this->processRipeAsn();

        $this->output->newLine(1);
        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

    }

    private function processAfrinic()
    {
        $rir = Rir::where('name', 'AfriNIC')->first();
        $url = config('app.whois_afrinic');
        $this->info('Downloading Afrinic ' . $url);
        $bzipContents = file_get_contents($url);
        $content = bzdecompress($bzipContents);
        $whoisEntries = explode("\n", $content);
        $whoisEntries = array_filter($whoisEntries);
        $ipv4CidrAmount = $this->ipUtils->IPv4cidrIpCount($reverse = true);

        $whoisRecords = [];

        // Arrange the dataset
        foreach ($whoisEntries as $whoisEntry) {
            $whoisEntry = str_replace('\t', "\t", $whoisEntry);
            $whoisEntryLines = explode('\n', $whoisEntry);
            $whoisEntryLines = array_filter($whoisEntryLines);

            $whoisTypeParts = explode(':', $whoisEntryLines[0], 2);
            $type = trim(strtolower($whoisTypeParts[0]));

            if(isset($whoisTypeParts[1]) !== true) {
                continue;
            }

            $key = trim($whoisTypeParts[1]);

            if ($type == 'inetnum') {
                $ipParts = explode(' - ', trim($whoisTypeParts[1]));
                $ipDecStart = $this->ipUtils->ip2dec($ipParts[0]);
                $ipDecEnd = $this->ipUtils->ip2dec($ipParts[1]);
                $ipDiff = $ipDecEnd - $ipDecStart + 1;
                if (isset($ipv4CidrAmount[$ipDiff]) !== true) {
                    continue;
                }
                $key = $ipParts[0] . '/' . $ipv4CidrAmount[$ipDiff];
            }

            $key = strtolower($key);
            $whoisRecords[$type][$key] = implode("\n", $whoisEntryLines);
        }

        // Do ASNs
        $this->info('Processing AfriNic ASNs');
        foreach ($whoisRecords['aut-num'] as $rawWhois) {
            $asNumber = str_ireplace('as', '', $this->extractValues($rawWhois, 'aut-num'));
            $org = $this->extractValues($rawWhois, 'org');
            $org = strtolower($org);

            if (empty($org) !== true && isset($whoisRecords['organisation'][$org]) === true) {
                $rawWhois .= "\n\n" . $whoisRecords['organisation'][$org];
            }

            $maintainers = $this->extractValues($rawWhois, 'mnt-by');
            $seenMnt = [];
            if (empty($maintainers) !== true) {
                if (is_array($maintainers) === true) {
                    foreach ($maintainers as $maintainer) {
                        $maintainer = strtolower($maintainer);
                        if ($maintainer != 'afrinic-hm-mnt' && isset($seenMnt[$maintainer]) !== true) {
                            $rawWhois .= "\n\n" . $whoisRecords['mntner'][$maintainer];
                            $seenMnt[$maintainer] = "";
                        }
                    }
                } else if (isset($seenMnt[$maintainers]) !== true && strtolower($maintainers) != 'afrinic-hm-mnt')  {
                    $maintainers = strtolower($maintainers);
                    $rawWhois .= "\n\n" . $whoisRecords['mntner'][$maintainers];
                    $seenMnt[$maintainers] = "";
                }
            }

            $whois = new Whois($rawWhois, $cidr = null, $rir, $useRaw = true);

            $parsedWhois = $whois->parse();
            $asnModel = ASN::where('asn', $asNumber)->where('rir_id', $rir->id)->first();

            if (is_null($asnModel) === true) {
                continue;
            }

            $asnModel->name = $parsedWhois->name;
            $asnModel->description = isset($parsedWhois->description[0]) ? $parsedWhois->description[0] : $parsedWhois->name;
            $asnModel->description_full = count($parsedWhois->description) > 0 ? json_encode($parsedWhois->description) : json_encode([$asnModel->description]);

            // If we have the PeerDB info lets update it.
            if ($peerDb = $this->getPeeringDbInfo($asnModel->asn)) {
                $asnModel->website = $peerDb->website;
                $asnModel->looking_glass = $peerDb->looking_glass;
                $asnModel->traffic_estimation = $peerDb->info_traffic;
                $asnModel->traffic_ratio = $peerDb->info_ratio;
            }

            $asnModel->counrty_code = $parsedWhois->counrty_code;
            $asnModel->owner_address = json_encode($parsedWhois->address);
            $asnModel->raw_whois = $whois->raw();
            $asnModel->save();

            // Save ASN Emails
            $asnModel->emails()->delete();
            foreach ($parsedWhois->emails as $email) {
                $asnEmail = new ASNEmail();
                $asnEmail->asn_id = $asnModel->id;
                $asnEmail->email_address = $email;

                // Check if its an abuse email
                if (in_array($email, $parsedWhois->abuse_emails)) {
                    $asnEmail->abuse_email = true;
                }

                $asnEmail->save();
            }
        }

    }

    private function processRipeAsn()
    {
        $url = 'http://ftp.ripe.net/ripe/dbase/split/ripe.db.organisation.gz';
        $this->info('Downloading RIPE ' . $url);
        $gzipContents = file_get_contents($url);
        $contents = gzdecode($gzipContents);
        $ripeOrgs = explode("\n\n", $contents);
        $orgs = [];

        foreach ($ripeOrgs as $org) {
            $organisation = $this->extractValues($org, 'organisation');
            $name = $this->extractValues($org, 'org-name');
            $orgs[$organisation] = $name;
        }

        $url = 'http://ftp.ripe.net/ripe/dbase/split/ripe.db.aut-num.gz';
        $this->info('Downloading RIPE ' . $url);
        $gzipContents = file_get_contents($url);
        $contents = gzdecode($gzipContents);
        $asns = explode("\n\n", $contents);

        $this->info('Processing RIPE ASNs (' . number_format(count($asns)) . ')');

        foreach($asns as $asn) {
            if (strpos($asn, 'aut-num:') !== false) {

                $status = $this->extractValues($asn, 'status');
                // Only look at RIPE ASNs
                if ($status == 'OTHER') {
                    continue;
                }

                $asNumber = str_ireplace('as', '', $this->extractValues($asn, 'aut-num'));
                $name = $this->extractValues($asn, 'as-name');
                $org = $this->extractValues($asn, 'org');
                $description = $this->extractValues($asn, 'descr');
                $description = empty($description) ? null : $description;
                if (is_array($description) === false && $description !== null) {
                    $description = [$description];
                }

                if (is_null($description) !== true) {
                    $newData['description'] =  isset($description[0]) === true ? $description[0] : $description;
                    $newData['description_full'] = json_encode($description);
                } elseif (is_null($org) !== true && isset($orgs[$org]) === true) {
                    $newData['description'] =  $orgs[$org];
                    $newData['description_full'] = json_encode([$orgs[$org]]);
                }

                if ($name == 'UNSPECIFIED' && isset($newData['description']) === true) {
                    $newData['name'] = strtoupper(str_replace(' ', '-', $newData['description']));
                } else {
                    $newData['name'] = $name;
                }

                // dump('AS' . $asNumber, $newData, '=========');
                $asnClass = new ASN();
                $asnClass->timestamps = false;
                $asnClass->where('asn', $asNumber)->update($newData);
            }

        }
    }

    private function processApnicAsn()
    {
        $url = 'ftp://ftp.apnic.net/public/apnic/whois/apnic.db.aut-num.gz';
        $this->info('Downloading APNIC ' . $url);
        $gzipContents = file_get_contents($url);
        $contents = gzdecode($gzipContents);
        $asns = explode("\n\n", $contents);

        $this->info('Processing APNIC ASNs (' . number_format(count($asns)) . ')');


        foreach($asns as $asn) {
            if (strpos($asn, 'aut-num:') !== false) {
                $asNumber = str_ireplace('as', '', $this->extractValues($asn, 'aut-num'));
                $name = $this->extractValues($asn, 'as-name');
                $description = $this->extractValues($asn, 'descr');
                $description = empty($description) ? null : $description;
                if (is_array($description) === false && $description !== null) {
                    $description = [$description];
                }

                if (is_null($description) !== true) {
                    $newData['description'] =  isset($description[0]) === true ? $description[0] : $description;
                    $newData['description_full'] = json_encode($description);
                }

                if ($name == 'UNSPECIFIED' && isset($newData['description']) === true) {
                    $newData['name'] = strtoupper(str_replace(' ', '-', $newData['description']));
                } else {
                    $newData['name'] = $name;
                }

                // dump('AS' . $asNumber, $newData, '=========');
                $asnClass = new ASN();
                $asnClass->timestamps = false;
                $asnClass->where('asn', $asNumber)->update($newData);
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
            return array_unique(array_filter($values));
        }

        return null;
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

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

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

}
