<?php

namespace App\Services;

use App\Helpers\IpUtils;
use ClickHouseDB\Client as ClickHouseClient;

class Domains
{


    protected $ranges = [];
    protected $clickhouse;

    public function __construct($prefixes)
    {
        $config = [
            'host' => config('clickhouse.host'),
            'port' => config('clickhouse.port'),
            'username' => 'default',
            'password' => ''
        ];
        $this->clickhouse = new ClickHouseClient($config);
        $this->clickhouse->database('default');
        $this->clickhouse->setTimeout(5);
        $this->clickhouse->setConnectTimeOut(10);


        $ipUtils = new IpUtils();

        foreach ($prefixes['ipv4_prefixes'] as $prefix) {
            $this->ranges[] = [
                $startIpDec = $ipUtils->ip2dec($prefix['ip']),
                $startIpDec + $ipUtils->IPv4cidrIpCount()[$prefix['cidr']] - 1,
            ];
        }

        foreach ($prefixes['ipv6_prefixes'] as $prefix) {
            $this->ranges[] = [
                $startIpDec = $ipUtils->ip2dec($prefix['ip']),
                $startIpDec + $ipUtils->IPv6cidrIpCount()[$prefix['cidr']] - 1,
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
        $domains = [];

        foreach ($statement->rows() as $row){
            $domains[$row['ip_address']] = $row['name'];
        }

        return $domains;
    }

}
