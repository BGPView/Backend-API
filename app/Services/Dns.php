<?php

namespace App\Services;

class DNS
{
    protected $resolvers = [
        '8.8.8.8',
    ];

    protected $recordTypes = [
        'A',
        'AAAA',
        'SOA',
        'NS',
        'PTR',
        'MX',
        'TXT',
        'CNAME',
    ];

    public function getAllRecords($input)
    {

    }

}