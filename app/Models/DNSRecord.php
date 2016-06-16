<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Elasticquent\ElasticquentTrait;

class DNSRecord {

    use ElasticquentTrait;

    /**
     * The elasticsearch settings.
     *
     * @var array
     */
    protected $indexSettings = [
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
    ];

    public function getTable()
    {
        return 'dns_records';
    }
}
