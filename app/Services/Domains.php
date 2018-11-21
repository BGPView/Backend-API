<?php

namespace App\Services;

use App\Helpers\IpUtils;

class Domains
{


    protected $ranges = [];
    protected $clickhosue;

    public function __construct($prefixes)
    {
        $config = [
            'host' => config('clickhouse.host'),
            'port' => config('clickhouse.port'),
            'username' => 'default',
            'password' => ''
        ];
        $this->clickhosue = new ClickHouseDB\Client($config);
        $this->clickhosue->database('default');
        $this->clickhosue->setTimeout(5);
        $this->clickhosue->setConnectTimeOut(10);


        $ipUtils = new IpUtils();

        foreach ($prefixes->ipv4_prefixes as $prefix) {
            $this->ranges[] = [
                $startIpDec = $ipUtils->ip2dec($prefix->ip),
                $startIpDec + $ipUtils->IPv4cidrIpCount()[$prefix->cidr] - 1,
            ];
        }

        foreach ($prefixes->ipv6_prefixes as $prefix) {
            $this->ranges[] = [
                $startIpDec = $ipUtils->ip2dec($prefix->ip),
                $startIpDec + $ipUtils->IPv6cidrIpCount()[$prefix->cidr] - 1,
            ];
        }

    }

    public function get()
    {
        $query = '';
        foreach ($this->ranges as $range) {
            $query .= '(ip >= '.$range[0].' AND ip <= '.$range[1].') OR ';
        }

        $query = preg_replace('/\W\w+\s*(\W*)$/', '$1', $query);

        $statement = $this->clickhouse->select('SELECT name, ip_address FROM dns WHERE '.$query);
        return $statement->rows();
    }

}
