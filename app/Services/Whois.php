<?php

namespace App\Services;

use App\Helpers\IPUtils;

class Whois
{
    private $whoisUrl = "http://185.42.223.50/whois.php";
    private $rir;
    private $input;
    private $rawData;
    private $rawLines;
    private $ipUtils;

    private $ignoreEmailAddresses = [
            ];

    private $ignoreEmailDomains = [
                'apnic.net',
                'afrinic.net',
                'lacnic.net',
                'ripe.net',
                'arin.net',
                'arin.asn',
                'arin.org',

                'example.com',
                'cert.br',
            ];

    public function __construct($input)
    {
        $this->ipUtils = new IPUtils;
        $allocation = $this->ipUtils->getAllocationEntry($input);
        $this->input = $input;

        // Lets make sure we found an allocation first
        if (is_null($allocation) !== true) {
            $this->rir = $allocation->rir;

            // Lets fetch the raw whois data
            $this->rawData = $this->getRawWhois();
            $this->rawLines = explode("\n", $this->rawData);
        } else {
            $this->rawData = null;
            $this->rawLines = [];
        }

    }

    private function getRawWhois()
    {
        $url = $this->whoisUrl . "?input=" . $this->input . "&whois_server=" . $this->rir->whois_server;
        return  file_get_contents($url);
    }

    public function parse()
    {
        $functionName = strtolower($this->rir->name) . "Execute";
        return $this->$functionName();
    }

    public function raw()
    {
        return $this->rawData;
    }

    private function arinExecute()
    {
        $data = new \stdClass();

        // Get All email contacts on object
        $data->emails = $this->extractAllEmails();

        // Extract ARIN Abuse Email
        $abuseEmails = $this->extractValues('OrgAbuseEmail');
        if (is_array($abuseEmails) === true) {
            $data->abuse_emails = $abuseEmails;
        } else {
            $data->abuse_emails[] = $abuseEmails;
        }
        $data->abuse_emails = $this->cleanUpEmails($data->abuse_emails);


        // Get description (Organization)
        $orgs = $this->extractValues('Organization');
        if (is_array($orgs) === true) {
            $orgs = end($orgs);
        }
        // Remove the ARIN OrgID
        $orgParts = explode("(", strrev($orgs), 2);
        $finalOrg = trim(strrev(end($orgParts)));
        $data->description = $finalOrg;


        return $data;
    }

    private function ripeExecute()
    {
        $data = new \stdClass();

        // Get All email contacts on object
        $data->emails = $this->extractAllEmails();

        // Extract Ripe Abuse Email
        $data->abuse_emails = [];
        foreach ($this->rawLines as $line) {
            if (strstr($line, "% Abuse contact for")) {
                $parts = explode(' ', $line);
                $data->abuse_emails[] = strtolower(trim(end($parts), '\''));
                unset($parts);
            }
        }

        // Extract generic abuse emails
        $genericAbuseEmails = $this->extractValues('abuse-mailbox');
        if ($genericAbuseEmails !== null) {
            // Make it an array if not already
            if (is_array($genericAbuseEmails) !== true) {
                $genericAbuseEmails = [$genericAbuseEmails];
            }

            $genericAbuseEmails = array_map('strtolower', $genericAbuseEmails);
            $data->abuse_emails = array_unique(array_merge($genericAbuseEmails));
        }
        $data->abuse_emails = $this->cleanUpEmails($data->abuse_emails);

        // Get description
        $data->description = $this->extractValues('descr');

        return $data;
    }

    private function afrinicExecute()
    {
        $data = new \stdClass();

        // Get All email contacts on object
        $data->emails = $this->extractAllEmails();

        // Extract Afrinic Abuse Email
        $data->abuse_emails = [];
        foreach ($this->rawLines as $line) {
            if (strstr($line, "% Abuse contact for")) {
                $parts = explode(' ', $line);
                $data->abuse_emails[] = strtolower(trim(end($parts), '\''));
                unset($parts);
            }
        }

        // Extract generic abuse emails
        $genericAbuseEmails = $this->extractValues('abuse-mailbox');
        if ($genericAbuseEmails !== null) {
            // Make it an array if not already
            if (is_array($genericAbuseEmails) !== true) {
                $genericAbuseEmails = [$genericAbuseEmails];
            }

            $genericAbuseEmails = array_map('strtolower', $genericAbuseEmails);
            $data->abuse_emails = array_unique(array_merge($genericAbuseEmails));
        }
        $data->abuse_emails = $this->cleanUpEmails($data->abuse_emails);

        // Get description
        $data->description = $this->extractValues('descr');



        return $data;
    }

    private function apnicExecute()
    {
        $data = new \stdClass();

        // Get All email contacts on object
        $data->emails = $this->extractAllEmails();

        // Extract APNIC Abuse Email
        $abuseEmails = $this->extractValues('abuse-mailbox');
        if (is_array($abuseEmails) === true) {
            $data->abuse_emails = $abuseEmails;
        } else {
            $data->abuse_emails[] = $abuseEmails;
        }
        $data->abuse_emails = $this->cleanUpEmails($data->abuse_emails);

        // Get description
        $data->description = $this->extractValues('descr');

        return $data;
    }

    private function lacnicExecute()
    {
        $data = new \stdClass();

        // Get All email contacts on object
        $data->emails = $this->extractAllEmails();

        // Extract Lacnic Abuse Email
        $abuseEmails = $this->extractValues('e-mail');
        if (is_array($abuseEmails) === true) {
            $data->abuse_emails = $abuseEmails;
        } else {
            $data->abuse_emails[] = $abuseEmails;
        }
        $data->abuse_emails = $this->cleanUpEmails($data->abuse_emails);

        // Get description
        $data->description = $this->extractValues('owner');

        return $data;
    }


    private function extractAllEmails()
    {
        $regex = "/[-.\w]+@[-.\w]+/i";
        preg_match_all($regex, $this->rawData, $matches);

        return $this->cleanUpEmails($matches[0]);

    }

    private function cleanUpEmails($emails)
    {
        $emails = array_map('strtolower', $emails);

        // Remove out ignore emails
        foreach ($emails as $key => $email) {

            // Cleanup any invalid email
            if (empty($email) === true) {
                unset($emails[$key]);
                continue;
            }

            //Remove any email addresses we dont want
            $email = trim(strtolower($email), ".");
            if (in_array($email, $this->ignoreEmailAddresses)) {
                unset($emails[$key]);
                continue;
            }

            // remove domains from emails
            $emailDomain = explode("@", $email)[1];
            foreach($this->ignoreEmailDomains as $ignoreDomain) {
                if ($ignoreDomain === $emailDomain) {
                    unset($emails[$key]);
                    continue;
                }
            }

        }

        return array_unique($emails);
    }

    private function extractValues($key, $last = false)
    {
        $values = [];
        $key = strtolower(trim($key));
        foreach ($this->rawLines as $line) {
            $lineParts = explode(":", $line, 2);
            if (strtolower(trim($lineParts[0])) === $key) {
                $values[] = trim($lineParts[1]);
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