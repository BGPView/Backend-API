<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Elasticsearch\ClientBuilder;
use Elasticquent\ElasticquentTrait;

class DNSRecord {

    use ElasticquentTrait;

    /**
     * The elasticsearch settings.
     *
     * @var array
     */
    protected $indexSettings = [
        'number_of_shards' => 8,
        'analysis' => [
            'analyzer' => [
                'string_lowercase' => [
                    'tokenizer' => 'keyword',
                    'filter' => [ 'asciifolding', 'lowercase', 'custom_replace' ],
                ],
            ],
            'filter' => [
                'custom_replace' => [
                    'type' => 'pattern_replace',
                    'pattern' => "[^a-z0-9 ]",
                    'replacement' => "",
                ],
            ],
        ],
    ];

    /**
     * The elasticsearch mappings.
     *
     * @var array
     */
    protected $mappingProperties = [
        'input' => [
            'type' => 'string',
            'analyzer' => 'string_lowercase'
        ],
        'ip_dec' => [
            'type' => 'double',
        ],
    ];

    // To save on space we will use the ints instead of a char byte on ES storage
    public static $rrTypes = [
        'A' => 1,
        'AAAA' => 2,
        'CNAME' => 3,
        'NS' => 4,
        'MX' => 5,
        'SOA' => 6,
        'TXT' => 7,
    ];

    public function getTable()
    {
        return 'dns_records';
    }

    public function getKey()
    {
        return $this->getTable();
    }
}
