<?php

namespace App\Services;

use Net_DNS2_Exception;
use Net_DNS2_Resolver;

class Dns
{
    protected $resolvers = [
        '8.8.8.8',
    ];

    protected $timeout = 1; // Seconds

    protected $recordTypes = [
        'NS' => 'nsdname',
        'SOA' => 'rname',
        'A' => 'address',
        'AAAA' => 'address',
        'MX' => 'exchange',
        'TXT' => 'text',
        'CNAME' => 'cname',
        // 'PTR' => 'ptrdname',  // We take this out as it does not apply for domain
    ];

    private $dns;

    public function __construct($resolvers = null, $timeOut = null)
    {
        $this->dns = new Net_DNS2_Resolver([
            'nameservers' => $resolvers ?: $this->resolvers,
            'timeout' => $timeOut ?: $this->timeout,
        ]);
    }

    public function getDomainRecords($input, $testNameserver = true)
    {
        $records = [];
        foreach ($this->recordTypes as $type => $key) {
            try {
                $result = $this->dns->query($input, $type);
                foreach($result->answer as $record)
                {

                    if (isset($record->$key) !== true) {
                        // If there is no SOA lets return nothing
                        if ($type === 'NS' && $testNameserver == true) {
                            return [];
                        }
                        continue;
                    }

                    if (is_array($record->$key) === true) {
                        $data = array_values($record->$key)[0];
                    } else {
                        $data = $record->$key;
                    }

                    $records[$type][] = $data;
                }
            } catch(Net_DNS2_Exception $e) {

                // If there is no SOA lets return nothing
                if ($type === 'NS' && $testNameserver == true) {
                    return [];
                }

                continue;
            }
        }

        return $records;
    }

    public function getPtr($ip)
    {
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === $ip) {
            $parts = explode( '.', $ip );
            $arpa = sprintf( '%d.%d.%d.%d.in-addr.arpa.', $parts[3], $parts[2], $parts[1], $parts[0] );
        } else if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === $ip) {
            $addr = inet_pton($ip);
            $unpack = unpack('H*hex', $addr);
            $hex = $unpack['hex'];
            $arpa = implode('.', array_reverse(str_split($hex))) . '.ip6.arpa.';
        } else {
            return null;
        }

        try {
            $result = $this->dns->query($arpa, 'PTR');
            foreach($result->answer as $record)
            {
               return $record->ptrdname;
            }
        } catch(\Exception $e) {

        }

        return null;
    }

}
