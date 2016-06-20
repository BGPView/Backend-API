<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Models\IanaAssignment;
use Illuminate\Console\Command;
use League\CLImate\CLImate;
use Ubench;

class UpdateIanaAssignments extends Command
{
    private $cli;
    private $bench;
    private $ipUtils;
    private $ipv4AssignmenstUrls = [
        'http://www.iana.org/assignments/ipv4-address-space/ipv4-address-space.csv',
    ];
    private $asnAssignmenstUrls = [
        'http://www.iana.org/assignments/as-numbers/as-numbers-1.csv',
        'http://www.iana.org/assignments/as-numbers/as-numbers-2.csv',
    ];
    private $ipv6AssignmenstUrls = [
        'http://www.iana.org/assignments/ipv6-unicast-address-assignments/ipv6-unicast-address-assignments.csv'
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-iana-assignments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the IANA assignment list to RIR for resources';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        IanaAssignment::truncate();
        $this->loadIPv4Assignments();
        $this->loadIPv6Assignments();
        $this->loadAsnAssignments();
    }

    private function loadIPv4Assignments()
    {
        $this->cli->br()->comment('Adding IPv4 IANA Assignment Entries');
        $ipv4CountInPrefix = $this->ipUtils->IPv4cidrIpCount();

        $assignments = [];
        foreach ($this->ipv4AssignmenstUrls as $url) {
            $assignments = array_merge($assignments, $this->downloadCsv($url));
        }

        foreach ($assignments as $assignment) {
            if (empty($assignment['WHOIS']) === false) {
                $parts = explode('/', $assignment['Prefix']);
                $start = $this->ipUtils->ip2dec(((int)$parts[0]) . '.0.0.0');
                $end = $start + $ipv4CountInPrefix[$parts[1]] - 1; // Adding a /8 worth

                $ianaAssignment = new IanaAssignment();
                $ianaAssignment->type = 4;
                $ianaAssignment->start = $start;
                $ianaAssignment->end = $end;
                $ianaAssignment->whois_server = $assignment['WHOIS'];
                $ianaAssignment->description = $assignment['Designation'];
                $ianaAssignment->status = strtolower($assignment['Status [1]']);
                $ianaAssignment->date_allocated = $assignment['Date'] ? substr($assignment['Date'], 0 , 4) . "-" . substr($assignment['Date'], 4, 2) . "-" . substr($assignment['Date'], 6, 2) : null;
                $ianaAssignment->save();
            }
        }
    }

    private function loadIPv6Assignments()
    {
        $this->cli->br()->comment('Adding IPv6 IANA Assignment Entries');
        $ipv6CountInPrefix = $this->ipUtils->IPv6cidrIpCount();

        $assignments = [];
        foreach ($this->ipv6AssignmenstUrls as $url) {
            $assignments = array_merge($assignments, $this->downloadCsv($url));
        }

        foreach ($assignments as $assignment) {
            if (empty($assignment['WHOIS']) === false) {
                $parts = explode('/', $assignment['Prefix']);
                $start = $this->ipUtils->ip2dec($parts[0]);
                $end = $start + $ipv6CountInPrefix[$parts[1]] - 1;

                $ianaAssignment = new IanaAssignment();
                $ianaAssignment->type = 6;
                $ianaAssignment->start = $start;
                $ianaAssignment->end = $end;
                $ianaAssignment->whois_server = $assignment['WHOIS'];
                $ianaAssignment->description = $assignment['Designation'];
                $ianaAssignment->status = strtolower($assignment['Status']);
                $ianaAssignment->date_allocated = $assignment['Date'] ? substr($assignment['Date'], 0 , 4) . "-" . substr($assignment['Date'], 4, 2) . "-" . substr($assignment['Date'], 6, 2) : null;
                $ianaAssignment->save();
            }
        }
    }

    private function loadAsnAssignments()
    {
        $this->cli->br()->comment('Adding ASN IANA Assignment Entries');

        $assignments = [];
        foreach ($this->asnAssignmenstUrls as $url) {
            $assignments = array_merge($assignments, $this->downloadCsv($url));
        }

        foreach ($assignments as $assignment) {
            if (empty($assignment['WHOIS']) === false) {
                $parts = explode('-', $assignment['Number']);
                $start = trim($parts[0]);
                $end = isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);

                $description = $assignment['Description'];
                if (empty(trim($assignment['Reference'])) !== true) {
                    $description .= ' '.trim($assignment['Reference']);
                }

                if (stripos($assignment['Description'], 'reserved') !== false) {
                    $status = 'reserved';
                } else if (stripos($assignment['Description'], 'assigned') !== false) {
                    $status = 'assigned';
                } else {
                    $status = 'unknown';
                }

                // Who the hell names these keys :/
                $date = $assignment["Registration\n        Date"];

                $ianaAssignment = new IanaAssignment();
                $ianaAssignment->type = 'asn';
                $ianaAssignment->start = $start;
                $ianaAssignment->end = $end;
                $ianaAssignment->whois_server = $assignment['WHOIS'];
                $ianaAssignment->description = $description;
                $ianaAssignment->status = $status;
                $ianaAssignment->date_allocated = $date ? substr($date, 0 , 4) . "-" . substr($date, 4, 2) . "-" . substr($date, 6, 2) : null;
                $ianaAssignment->save();
            }
        }

    }

    private function downloadCsv($url)
    {
        $csv = Array();
        $rowcount = 0;
        if (($handle = fopen($url, "r")) !== FALSE) {
            $max_line_length = 10000;
            $header = fgetcsv($handle, $max_line_length);
            $header_colcount = count($header);
            while (($row = fgetcsv($handle, $max_line_length)) !== FALSE) {
                $row_colcount = count($row);
                if ($row_colcount == $header_colcount) {
                    $entry = array_combine($header, $row);
                    $csv[] = $entry;
                }
                else {
                    return [];
                }
                $rowcount++;
            }
            fclose($handle);
        }
        else {
            return [];
        }

        return $csv;
    }

}
