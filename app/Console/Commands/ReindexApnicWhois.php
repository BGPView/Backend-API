<?php

namespace App\Console\Commands;

use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;

class ReindexApnicWhois extends ReindexRIRWhois
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:reindex-apnic-whois';
    protected $indexName = 'apnic_whois_db';
    protected $versionedIndex;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex in ES all the RIR raw whois data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->versionedIndex = $this->indexName . '_' . time();

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $params = [
            'index' => $this->versionedIndex,
            'body' => $this->getIndexMapping(),
        ];
        $this->esClient->indices()->create($params);

        $this->processAsnBlocks();
        $this->processAsns();
        $this->processPrefixes(6);
        $this->processPrefixes(4);
        $this->processPersons();
        $this->processRoles();
        $this->processMaintainers();

        $this->hotSwapIndices($this->versionedIndex, $this->indexName);
    }

    private function processAsnBlocks()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading APNIC ASN Blocks whois file');
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.as-block.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' ASN Blocks Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $asBlock = $this->extractValues($whoisBlock, 'as-block');
            if (empty($asBlock) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown ASN Block on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $asBlock = str_ireplace('as', '', $asBlock);
            $asnParts = explode(" - ", $asBlock);
            $asnStart = trim($asnParts[0]);
            $asnEnd = trim($asnParts[1]);


            $data = [
                'asn_start' => $asnStart,
                'asn_end' => $asnEnd,
                'asn_count' => $asnEnd - $asnStart + 1,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'asns',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }

        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    private function processAsns()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading APNIC ASNs whois file');
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.aut-num.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' ASNs Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $asNum = $this->extractValues($whoisBlock, 'aut-num');
            if (empty($asNum) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown ASN on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $asNum = str_ireplace('as', '', $asNum);
            $asnStart = trim($asNum);
            $asnEnd = trim($asNum);


            $data = [
                'asn_start' => $asnStart,
                'asn_end' => $asnEnd,
                'asn_count' => $asnEnd - $asnStart + 1,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'asns',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }

        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    private function processPrefixes($ipVersion = 4)
    {
        $this->bench->start();
        $currentCount = 0;
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        $this->info('Reading APNIC IPv' . $ipVersion . ' Prefixes whois file');
        $vUrl = $ipVersion == 4? '' : 6;
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.inet' . $vUrl . 'num.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' IPv' . $ipVersion . ' prefixes Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $prefix = $this->extractValues($whoisBlock, 'inet' . $vUrl .'num');
            if (empty($prefix) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown IPv' . $ipVersion . ' prefixes on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            if ($ipVersion === 4) {
                $prefix = str_replace("\t", " ", $prefix);
                $prefix = str_replace('  ', ' ', $prefix);

                $netRangeParts = explode(' - ', $prefix);

                if (count($netRangeParts) < 2) {
                    continue;
                }

                $ipDecStart = $this->ipUtils->ip2dec($netRangeParts[0]);
                $ipDecEnd = $this->ipUtils->ip2dec($netRangeParts[1]);
            } else {
                $netRangeParts = explode('/', $prefix);
                $ipDecStart = $this->ipUtils->ip2dec($netRangeParts[0]);
                $ipDecEnd = bcsub(bcadd($this->ipUtils->ip2dec($netRangeParts[0]), $ipv6AmountCidrArray[$netRangeParts[1]]),  1);
            }


            $data = [
                'ip_dec_start' => $ipDecStart,
                'ip_dec_end' => $ipDecEnd,
                'ip_count' => bcadd(1, bcsub($ipDecEnd, $ipDecStart)),
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'nets',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }

        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    private function processRoles()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading APNIC Role whois file');
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.role.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' Roles Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $nicHdl = $this->extractValues($whoisBlock, 'nic-hdl');
            if (empty($nicHdl) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown Role on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }


            $data = [
                'role_id' => $nicHdl,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'roles',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }
        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    private function processPersons()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading APNIC Person whois file');
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.person.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' Persons Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $nicHdl = $this->extractValues($whoisBlock, 'nic-hdl');
            if (empty($nicHdl) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown Person on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }


            $data = [
                'person_id' => $nicHdl,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'persons',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }
        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    private function processMaintainers()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading APNIC Maintainers whois file');
        $url = env('WHOIS_DB_APNIC_BASE_URL') . 'apnic.db.mntner.gz';
        $rawContent = gzdecode($this->getContents($url));

        // Split all block
        $whoisBlocks = explode("\n\n", $rawContent);
        $this->info(number_format(count($whoisBlocks)) . ' Maintainers Found');

        foreach ($whoisBlocks as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            if (empty($whoisBlock) === true || strrpos($whoisBlock, "#", -strlen($whoisBlock)) !== false) {
                continue;
            }

            $nicHdl = $this->extractValues($whoisBlock, 'mntner');
            if (empty($nicHdl) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown Maintainer on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }


            $data = [
                'mntner_id' => $nicHdl,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type' => 'mntners',
                ]
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount = 0;
                $params['body'] = [];
            }
        }

        // Insert the remaining entries
        if (count($params['body']) > 0) {
            $this->esClient->bulk($params);
        }

        $this->bench->end();
        $this->info(sprintf(
            'Time: %s, Memory: %s',
            $this->bench->getTime(),
            $this->bench->getMemoryPeak()
        ));

        $this->warn('=========================');
    }

    /**
     * Return index mapping.
     */
    public function getIndexMapping()
    {
        return [
            'mappings' => [
                'asns'  => [
                    'properties' => [
                        'asn_start'    => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'asn_end'    => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'asn_count'    => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'whois_block'    => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'nets'  => [
                    'properties' => [
                        'ip_dec_start'    => ['type' => 'double', 'index' => 'not_analyzed'],
                        'ip_dec_end'    => ['type' => 'double', 'index' => 'not_analyzed'],
                        'ip_count'    => ['type' => 'double', 'index' => 'not_analyzed'],
                        'whois_block'    => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'roles'  => [
                    'properties' => [
                        'role_id'    => ['type' => 'string', 'index' => 'not_analyzed'],
                        'whois_block'    => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'persons'  => [
                    'properties' => [
                        'person_id'    => ['type' => 'string', 'index' => 'not_analyzed'],
                        'whois_block'    => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'mntners'  => [
                    'properties' => [
                        'mntner_id'    => ['type' => 'string', 'index' => 'not_analyzed'],
                        'whois_block'    => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
            ],
        ];
    }

}
