<?php

namespace App\Services;

use Net_DNS2_Exception;
use Net_DNS2_Resolver;

class Dns
{
    protected $resolvers = [
        '8.8.8.8',
    ];

    protected $recordTypes = [
        'A' => 'address',
        'AAAA' => 'address',
        'SOA' => 'rname',
        'NS' => 'nsdname',
        'MX' => 'exchange',
        'TXT' => 'text',
        'CNAME' => 'cname',
        // 'PTR' => 'ptrdname',  // We take this out as it does not apply for domain
    ];

    private $dns;

    public function __construct()
    {
        $this->dns = new Net_DNS2_Resolver(['nameservers' => $this->resolvers]);
    }

    public function getDomainRecords($input)
    {
        $records = [];
        foreach ($this->recordTypes as $type => $key) {
            try {
                $result = $this->dns->query($input, $type);
                foreach($result->answer as $record)
                {
                    if (is_array($record->$key) === true) {
                        $data = array_values($record->$key)[0];
                    } else {
                        $data = $record->$key;
                    }

                    $records[$type][] = $data;
                }
            } catch(Net_DNS2_Exception $e) {
                continue;
            }
        }

        return $records;
    }

}