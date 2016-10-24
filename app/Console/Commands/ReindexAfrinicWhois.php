<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReindexAfrinicWhois extends ReindexRIRWhois
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:reindex-afrinic-whois';
    protected $indexName = 'afrinic_whois_db';
    protected $versionedIndex;
    protected $whoisDump;

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
            'body'  => $this->getIndexMapping(),
        ];
        $this->esClient->indices()->create($params);

        $this->populateWHoisDump();

        $this->processAsnBlocks();
        $this->processAsns();
        $this->processPrefixes(4);
        $this->processPrefixes(6);
        $this->processMaintainers();
        $this->processOrgs();

        $this->hotSwapIndices($this->versionedIndex, $this->indexName);
    }

    private function populateWHoisDump()
    {
        $this->info('Downloading and populating Afrinic WHOIS dump');
        $bz2Content = $this->getContents(env('WHOIS_DB_AFRINIC_BASE_URL'));
        $rawData    = bzdecompress($bz2Content);
        $dataParts  = array_filter(explode("\n", $rawData));

        // Categorise the whois data dump
        foreach ($dataParts as $dataPart) {
            $elementType                     = explode(':', $dataPart, 2)[0];
            $this->whoisDump[$elementType][] = str_replace('\t', "\t", str_replace('\n', "\n", $dataPart));
        }
    }

    private function processAsnBlocks()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading Afrinic ASN Blocks whois file');
        $this->info(number_format(count($this->whoisDump['as-block'])) . ' ASN Blocks Found');

        foreach ($this->whoisDump['as-block'] as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            $asBlock = $this->extractValues($whoisBlock, 'as-block');
            if (empty($asBlock) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown ASN Block on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $asBlock  = str_ireplace('as', '', $asBlock);
            $asnParts = explode(" - ", $asBlock);
            $asnStart = trim($asnParts[0]);
            $asnEnd   = trim($asnParts[1]);

            $data = [
                'asn_start'   => $asnStart,
                'asn_end'     => $asnEnd,
                'asn_count'   => $asnEnd - $asnStart + 1,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type'  => 'asns',
                ],
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount   = 0;
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

        $this->info('Reading Afrinic ASNs whois file');
        $this->info(number_format(count($this->whoisDump['aut-num'])) . ' ASNs Found');

        foreach ($this->whoisDump['aut-num'] as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            $asNum = $this->extractValues($whoisBlock, 'aut-num');
            if (empty($asNum) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown ASN on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $asNum    = str_ireplace('as', '', $asNum);
            $asnStart = trim($asNum);
            $asnEnd   = trim($asNum);

            $data = [
                'asn_start'   => $asnStart,
                'asn_end'     => $asnEnd,
                'asn_count'   => $asnEnd - $asnStart + 1,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type'  => 'asns',
                ],
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount   = 0;
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
        $currentCount        = 0;
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();
        $vUrl                = $ipVersion == 4 ? '' : 6;

        $this->info('Reading Afrinic IPv' . $ipVersion . ' prefixes whois file');
        $this->info(number_format(count($this->whoisDump['inet' . $vUrl . 'num'])) . ' IPv' . $ipVersion . ' Prefixes Found');

        foreach ($this->whoisDump['inet' . $vUrl . 'num'] as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            $prefix = $this->extractValues($whoisBlock, 'inet' . $vUrl . 'num');
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
                $ipDecEnd   = $this->ipUtils->ip2dec($netRangeParts[1]);
            } else {
                $netRangeParts = explode('/', $prefix);
                $ipDecStart    = $this->ipUtils->ip2dec($netRangeParts[0]);
                $ipDecEnd      = bcsub(bcadd($this->ipUtils->ip2dec($netRangeParts[0]), $ipv6AmountCidrArray[$netRangeParts[1]]), 1);
            }

            $data = [
                'ip_dec_start' => $ipDecStart,
                'ip_dec_end'   => $ipDecEnd,
                'ip_count'     => bcadd(1, bcsub($ipDecEnd, $ipDecStart)),
                'ip_version'   => $ipVersion,
                'whois_block'  => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type'  => 'nets',
                ],
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount   = 0;
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

    private function processOrgs()
    {
        $this->bench->start();
        $currentCount = 0;

        $this->info('Reading Afrinic Orgs whois file');
        $this->info(number_format(count($this->whoisDump['organisation'])) . ' Orgs Found');

        foreach ($this->whoisDump['organisation'] as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            $orgId = $this->extractValues($whoisBlock, 'organisation');
            if (empty($orgId) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown Org on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $data = [
                'org_id'      => $orgId,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type'  => 'orgs',
                ],
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount   = 0;
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

        $this->info('Reading Afrinic Maintainers whois file');
        $this->info(number_format(count($this->whoisDump['mntner'])) . ' Maintainers Found');

        foreach ($this->whoisDump['mntner'] as $whoisBlock) {
            // Do quick cleanup
            $whoisBlock = trim($whoisBlock);

            $nicHdl = $this->extractValues($whoisBlock, 'mntner');
            if (empty($nicHdl) === true) {
                $this->warn('-------------------');
                $this->warn('Unknown Maintainer on:');
                $this->info($whoisBlock);
                $this->warn('-------------------');
                continue;
            }

            $data = [
                'mntner_id'   => $nicHdl,
                'whois_block' => $whoisBlock,
            ];

            $params['body'][] = [
                'index' => [
                    '_index' => $this->versionedIndex,
                    '_type'  => 'mntners',
                ],
            ];
            $params['body'][] = $data;
            $currentCount++;

            if ($currentCount > $this->esBatchAmount) {
                // Get our document body data.
                $this->esClient->bulk($params);
                // Reset the batching
                $currentCount   = 0;
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
                'asns'    => [
                    'properties' => [
                        'asn_start'   => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'asn_end'     => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'asn_count'   => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'whois_block' => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'nets'    => [
                    'properties' => [
                        'ip_dec_start' => ['type' => 'double', 'index' => 'not_analyzed'],
                        'ip_dec_end'   => ['type' => 'double', 'index' => 'not_analyzed'],
                        'ip_count'     => ['type' => 'double', 'index' => 'not_analyzed'],
                        'ip_version'   => ['type' => 'integer', 'index' => 'not_analyzed'],
                        'whois_block'  => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'orgs'    => [
                    'properties' => [
                        'org_id'      => ['type' => 'string', 'index' => 'not_analyzed'],
                        'whois_block' => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
                'mntners' => [
                    'properties' => [
                        'mntner_id'   => ['type' => 'string', 'index' => 'not_analyzed'],
                        'whois_block' => ['type' => 'string', 'index' => 'not_analyzed'],
                    ],
                ],
            ],
        ];
    }

}
