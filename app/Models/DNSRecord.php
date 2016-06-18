<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Elasticsearch\ClientBuilder;
use Elasticquent\ElasticquentTrait;

class DNSRecord {

    private $ipUtils;

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

    public function getDomains($prefix)
    {
        $this->ipUtils = new IpUtils();
        $client = ClientBuilder::create()->build();
        $prefixParts = explode('/', $prefix);



        if ($this->ipUtils->getInputType($prefixParts[0]) === 4) {
            $startIpDec = $this->ipUtils->ip2dec($prefixParts[0]);
            $endIpDec = $startIpDec + $this->ipUtils->IPv4cidrIpCount()[$prefixParts[1]];
        } else {
            $startIpDec = number_format($this->ipUtils->ip2dec($prefixParts[0]), 0 , '', '');
            $endIpDec = $startIpDec + $this->ipUtils->IPv6cidrIpCount()[$prefixParts[1]];
            $endIpDec = number_format($endIpDec, 0 , '', '');
        }

        $searchParams = [
            'index' => 'main_index_dns',
            'type' => 'dns_records',
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'range' => [
                                    'ip_dec' => [
                                        'gte' => $startIpDec
                                    ],
                                ]
                            ],
                            [
                                'range' => [
                                    'ip_dec' => [
                                        'lte' => $endIpDec
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $searchResults = $client->search($searchParams)['hits']['hits'];

        $data = collect([]);
        foreach ($searchResults as $searchResult) {
            $data->push($searchResult['_source']);
        }

        return $data;
    }
}
