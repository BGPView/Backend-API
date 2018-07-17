<?php

namespace App\Console\Commands;

use App\Models\Rir;
use Illuminate\Console\Command;

class UpdateAllocationLists extends ReindexRIRWhois
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zBGPView:reindex-rir-allocations';
    protected $indexName = 'rir_allocations';
    protected $indexVersion;
    protected $seenIpv4Allocation = [];
    protected $seenIpv6Allocation = [];
    protected $seenAsnAllocation = [];


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex in ES RIR allocation lists';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->indexVersion = time();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach ($this->getIndexMappings() as $indexType => $mapping) {
            $params = [
                'index' => $this->indexName . '_' . $indexType . '_' . $this->indexVersion,
                'body'  => $mapping,
            ];
            $this->esClient->indices()->create($params);
        }


        $this->info('Updating the IPv4, IPv6 and ASN RIR allocated resources');

        foreach (Rir::all() as $rir) {
            $this->warn('===================================================');
            $this->info('Downloading ' . $rir->name . ' allocation list');

            $rirAllocationData = $this->getContents($rir->allocation_list_url);

            $this->getAllocations($rir, $rirAllocationData);

        }

        $this->insertAllocations('asns', $this->seenAsnAllocation);
        $this->insertAllocations('prefixes', $this->seenIpv4Allocation);
        $this->insertAllocations('prefixes', $this->seenIpv6Allocation);

        foreach ($this->getIndexMappings() as $indexType => $mapping) {
            $this->hotSwapIndices($this->indexName . '_' . $indexType . '_' . $this->indexVersion, $this->indexName . '_' . $indexType);
        }

    }

    private function insertAllocations($type, $allocations)
    {
        $this->bench->start();
        $currentCount = 0;

        $this->warn('===================================================');
        $this->info('Inserting ' . number_format(count($allocations)) . ' ' . $type);


        foreach ($allocations as $allocation) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->indexName . '_' . $type . '_' . $this->indexVersion,
                    '_type'  => $type,
                ],
            ];
            $params['body'][] = $allocation;
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

    }

    private function getAllocations($rir, $rirAllocationData)
    {
        $rirAllocationData = explode("\n", $rirAllocationData);
        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount($reverse = true);
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();

        $this->info('Arranging ' . number_format(count($rirAllocationData)) . ' ' . $rir->name . ' resources');

        foreach ($rirAllocationData as $allocatedResource) {

            $allocationData = explode('|', $allocatedResource);

            // if it doesnt have 8 parts, skip
            if (count($allocationData) !== 8 && isset($allocationData[6]) === true && $allocationData[6] != 'available' && $allocationData[6] != 'reserved') {
                continue;
            }

            // Only take allocated resources
            if (empty($allocationData[2]) !== true && empty($allocationData[6]) !== true && empty($allocationData[3]) !== true) {

                $resourceType = $allocationData[2];

                // Replace 'ZZ' with null country code
                $allocationData[1] = $allocationData[1] == 'ZZ' ? null : $allocationData[1];

                if ($resourceType === 'asn') {

                    if (isset($this->seenAsnAllocation[$allocationData[3]]) === true) {
                        continue;
                    }
                    $this->seenAsnAllocation[$allocationData[3]] = [
                        'rir_id'         => $rir->id,
                        'rir_name'       => $rir->name,
                        'asn'            => $allocationData[3],
                        'country_code'   => $allocationData[1] ?: null,
                        'date_allocated' => $allocationData[5] ? substr($allocationData[5], 0, 4) . "-" . substr($allocationData[5], 4, 2) . "-" . substr($allocationData[5], 6, 2) : null,
                        'status'         => $allocationData[6],
                    ];

                } else {
                    if ($resourceType === 'ipv4') {

                        // Since some RIRs allocate random non CIDR addresses
                        // We shall split them up into the best CIDR that we can
                        // Really unhappy with this :/
                        if (isset($ipv4AmountCidrArray[$allocationData[4]]) !== true) {

                            $roundedCidr = 32 - intval(log($allocationData[4]) / log(2));
                            $roundedAmount = pow(2, (32 - $roundedCidr));
                            $this->seenIpv4Allocation[$allocationData[3] . '/' . $roundedCidr] = [
                                'rir_id'         => $rir->id,
                                'rir_name'       => $rir->name,
                                'ip_version'     => 4,
                                'ip'             => $allocationData[3],
                                'cidr'           => $roundedCidr,
                                'ip_dec_start'   => $this->ipUtils->ip2dec($allocationData[3]),
                                'ip_dec_end'     => $this->ipUtils->ip2dec($allocationData[3]) + $roundedAmount - 1,
                                'country_code'   => $allocationData[1] ?: null,
                                'date_allocated' => $allocationData[5] ? substr($allocationData[5], 0, 4) . "-" . substr($allocationData[5], 4, 2) . "-" . substr($allocationData[5], 6, 2) : null,
                                'status'         => $allocationData[6],
                            ];

                            // Deal with the remainder
                            $remainingIps = $allocationData[4] - $roundedAmount;
                            $remainCidr = 32 - intval(log($remainingIps) / log(2));
                            $startIpDec = bcadd($this->ipUtils->ip2dec($allocationData[3]), $roundedAmount);
                            $startIp = $this->ipUtils->dec2ip($startIpDec);
                            $this->seenIpv4Allocation[$allocationData[3] . '/' . $remainCidr] = [
                                'rir_id'         => $rir->id,
                                'rir_name'       => $rir->name,
                                'ip'             => $startIp,
                                'ip_version'     => 4,
                                'cidr'           => $remainCidr,
                                'ip_dec_start'   => $startIpDec,
                                'ip_dec_end'     => bcsub(bcadd($startIpDec, $remainingIps), 1),
                                'country_code'   => $allocationData[1] ?: null,
                                'date_allocated' => $allocationData[5] ? substr($allocationData[5], 0, 4) . "-" . substr($allocationData[5], 4, 2) . "-" . substr($allocationData[5], 6, 2) : null,
                                'status'         => $allocationData[6],
                            ];

                            continue;
                        }

                        $cidr = $ipv4AmountCidrArray[$allocationData[4]];

                        if (isset($this->seenIpv4Allocation[$allocationData[3] . '/' . $cidr]) === true) {
                            continue;
                        }

                        $this->seenIpv4Allocation[$allocationData[3] . '/' . $cidr] = [
                            'rir_id'         => $rir->id,
                            'rir_name'       => $rir->name,
                            'ip'             => $allocationData[3],
                            'ip_version'     => 4,
                            'cidr'           => $cidr,
                            'ip_dec_start'   => $this->ipUtils->ip2dec($allocationData[3]),
                            'ip_dec_end'     => $this->ipUtils->ip2dec($allocationData[3]) + $allocationData[4] - 1,
                            'country_code'   => $allocationData[1] ?: null,
                            'date_allocated' => $allocationData[5] ? substr($allocationData[5], 0, 4) . "-" . substr($allocationData[5], 4, 2) . "-" . substr($allocationData[5], 6, 2) : null,
                            'status'         => $allocationData[6],
                        ];

                    } else {
                        if ($resourceType === 'ipv6') {

                            // If the amount of IP address are unknown, then lets continue...
                            if (isset($ipv6AmountCidrArray[$allocationData[4]]) !== true) {
                                continue;
                            }

                            if (isset($this->seenIpv6Allocation[$allocationData[3] . '/' . $allocationData[4]]) === true) {
                                continue;
                            }

                            $this->seenIpv6Allocation[$allocationData[3] . '/' . $allocationData[4]] = [
                                'rir_id'         => $rir->id,
                                'rir_name'       => $rir->name,
                                'ip'             => $allocationData[3],
                                'ip_version'     => 6,
                                'cidr'           => $allocationData[4],
                                'ip_dec_start'   => $this->ipUtils->ip2dec($allocationData[3]),
                                'ip_dec_end'     => bcsub(bcadd($this->ipUtils->ip2dec($allocationData[3]), $ipv6AmountCidrArray[$allocationData[4]]), 1),
                                'country_code'   => $allocationData[1] ?: null,
                                'date_allocated' => $allocationData[5] ? substr($allocationData[5], 0, 4) . "-" . substr($allocationData[5], 4, 2) . "-" . substr($allocationData[5], 6, 2) : null,
                                'status'         => $allocationData[6],
                            ];
                        }
                    }
                }
            }
        }
    }

    /**
     * Return index mapping.
     */
    public function getIndexMappings()
    {
        return [
            'prefixes' => [
                'mappings' => [
                    'prefixes' => [
                        'properties' => [
                            'rir_id'         => ['type' => 'integer', 'index' => false],
                            'rir_name'       => ['type' => 'keyword', 'index' => false],
                            'ip_version'     => ['type' => 'integer', 'index' => false],
                            'ip'             => ['type' => 'keyword', 'index' => false],
                            'cidr'           => ['type' => 'integer', 'index' => false],
                            'ip_dec_start'   => ['type' => 'double', 'index' => false],
                            'ip_dec_end'     => ['type' => 'double', 'index' => false],
                            'country_code'   => ['type' => 'keyword', 'index' => false],
                            'date_allocated' => ['type' => 'date', 'index' => false],
                            'status'         => ['type' => 'keyword', 'index' => false],
                        ],
                    ],
                ],

            ],
            'asns'     => [
                'mappings' => [
                    'asns' => [
                        'properties' => [
                            'rir_id'         => ['type' => 'integer', 'index' => false],
                            'rir_name'       => ['type' => 'keyword', 'index' => false],
                            'asn'            => ['type' => 'integer', 'index' => false],
                            'country_code'   => ['type' => 'keyword', 'index' => false],
                            'date_allocated' => ['type' => 'date', 'index' => false],
                            'status'         => ['type' => 'keyword', 'index' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

}
