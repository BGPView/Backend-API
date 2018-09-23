<?php

namespace App\Jobs;

use App\Helpers\IpUtils;
use App\Models\ASN;
use Elasticsearch\ClientBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\CLImate\CLImate;

class GenerateAsnGraphs extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $input_asn;
    protected $cli;
    protected $maxLineThickness = 4.5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($inputAsn)
    {
        $this->input_asn = $inputAsn;
        $this->cli       = new CLImate();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $upstreams = $this->getUpstreams($this->input_asn);

        if (count($upstreams['ipv4_upstreams']) > 0) {
            $this->processAsnGraph($this->input_asn, 'IPv4', $upstreams['ipv4_upstreams']);
        }
        if (count($upstreams['ipv6_upstreams']) > 0) {
            $this->processAsnGraph($this->input_asn, 'IPv6', $upstreams['ipv6_upstreams']);
        }

        $combined = array_merge($upstreams['ipv4_upstreams'], $upstreams['ipv6_upstreams']);
        if (count($combined) > 0) {
            $this->processAsnGraph($this->input_asn, 'Combined', $combined);
        }
    }

    private function getUpstreams($as_number)
    {
        $client  = ClientBuilder::create()->setHosts(config('elasticquent.config.hosts'))->build();
        $ipUtils = new IpUtils();

        $params = [
            'scroll'      => '30s',
            'size'        => 10000,
            'index'       => 'bgp_data',
            'type'        => 'full_table',
            'body'        => [
                'sort'  => [
                    'upstream_asn' => [
                        'order' => 'asc',
                    ],
                ],
                'query' => [
                    'match' => [
                        'asn' => $as_number,
                    ],
                ],
            ],
        ];
        $docs      = $client->search($params);
        $steams    = [];
        $scroll_id = $docs['_scroll_id'];


        //Get Initial set of results
        if (count($docs['hits']['hits']) > 0) {
            $steams = $ipUtils->cleanEsResults($docs);
        }

        while (true) {
            $response = $client->scroll(
                array(
                    "scroll_id" => $scroll_id,
                    "scroll"    => "30s",
                )
            );
            if (count($response['hits']['hits']) > 0) {
                $results = $ipUtils->cleanEsResults($response);
                $steams  = array_merge($steams, $results);
                // Get new scroll_id
                $scroll_id = $response['_scroll_id'];
            } else {
                // All done scrolling over data
                break;
            }
        }

        $output['ipv4_upstreams'] = [];
        $output['ipv6_upstreams'] = [];
        foreach ($steams as $steam) {
            if (isset($output['ipv' . $steam->ip_version . '_upstreams'][$steam->upstream_asn]) === true) {
                if (in_array($steam->bgp_path, $output['ipv' . $steam->ip_version . '_upstreams'][$steam->upstream_asn]['bgp_paths']) === false) {
                    $output['ipv' . $steam->ip_version . '_upstreams'][$steam->upstream_asn]['bgp_paths'][] = $steam->bgp_path;
                }
                continue;
            }
            $asnOutput['asn'] = $steam->upstream_asn;

            $asnOutput['bgp_paths'][] = $steam->bgp_path;
            $output['ipv' . $steam->ip_version . '_upstreams'][$steam->upstream_asn] = $asnOutput;
            $asnOutput                                                               = null;
            $upstreamAsn                                                             = null;
        }
        $output['ipv4_upstreams'] = array_values($output['ipv4_upstreams']);
        $output['ipv6_upstreams'] = array_values($output['ipv6_upstreams']);

        return $output;
    }

    private function processAsnGraph($inputAsn, $ipVersion, $upstreams)
    {
        $relations = [];
        foreach ($upstreams as $upstream) {
            foreach ($upstream['bgp_paths'] as $path) {
                $asns = explode(' ', $path);

                foreach ($asns as $key => $asn) {
                    $asnList[] = $asn;

                    // If its the last ASN, then stop
                    if (isset($asns[$key + 1]) !== true) {
                        continue;
                    }

                    if (isset($relations[$asn . '-' . $asns[$key + 1]]) === true) {
                        $relations[$asn . '-' . $asns[$key + 1]]['weight']++;
                        continue;
                    }

                    $relations[$asn . '-' . $asns[$key + 1]] = [
                        'weight' => 1,
                        'asn1'   => $asn,
                        'asn2'   => $asns[$key + 1],
                    ];
                }
            }
        }

        $keyAsns      = [];
        $realRelation = [];
        foreach ($relations as $relation) {
            $keyAsns[$relation['asn1']][] = $relation;
        }

        $asnsData = ASN::whereIn('asn', array_unique($asnList))->get();

        foreach ($keyAsns as $groupedAsns) {
            $highestNumber = $groupedAsns[0]['weight'];
            foreach ($groupedAsns as $asn) {
                if ($asn['weight'] > $highestNumber) {
                    $highestNumber = $asn['weight'];
                }
            }

            // Set the lowest to be 1;
            if ($highestNumber > $this->maxLineThickness) {
                $devider = $highestNumber / $this->maxLineThickness;
            } else {
                $devider = 1;
            }
            foreach ($groupedAsns as $asn) {
                $asn['weight']  = $asn['weight'] / $devider;
                $realRelation[] = $asn;
            }
        }

        $outputGraphvizText = 'digraph "AS' . $inputAsn . ' ' . $ipVersion . ' Upstream Graph" {' . PHP_EOL;
        $outputGraphvizText .= 'rankdir=LR;' . PHP_EOL;
        $outputGraphvizText .= 'node [style=filled,fillcolor="#ffffff",fontcolor="#2C94B3"];' . PHP_EOL;

        $processedAsn = [];
        foreach ($realRelation as $relation) {

            // Add labels and hyperlinks
            if (isset($processedAsn[$relation['asn1']]) !== true) {
                $asnMeta = $asnsData->where('asn', (int) $relation['asn1'])->first();
                if (is_null($asnMeta) !== true) {
                    $countryCode = empty($asnMeta->counrty_code) !== true ? ' [' . $asnMeta->counrty_code . ']' : '';
                    $description = strlen($asnMeta->description) > 35 ? $asnMeta->name : $asnMeta->description;
                    $description = str_replace("'", "", $description);
                    $outputGraphvizText .= 'AS' . $relation['asn1'] . ' ';
                    $outputGraphvizText .= '[';
                    $outputGraphvizText .= 'tooltip="AS' . $asnMeta->asn . ' ~ ' . addslashes($description) . $countryCode . '" ';
                    $outputGraphvizText .= 'URL="https://bgpview.io/asn/' . $asnMeta->asn . '" ';
                    $outputGraphvizText .= 'fontcolor="#2C94B3" ';
                    $outputGraphvizText .= ']' . PHP_EOL;
                    $processedAsn[$relation['asn1']] = true;
                }
            }
            if (isset($processedAsn[$relation['asn2']]) !== true) {
                $asnMeta = $asnsData->where('asn', (int) $relation['asn2'])->first();
                if (is_null($asnMeta) !== true) {
                    $countryCode = empty($asnMeta->counrty_code) !== true ? ' [' . $asnMeta->counrty_code . ']' : '';
                    $description = strlen($asnMeta->description) > 35 ? $asnMeta->name : $asnMeta->description;
                    $description = str_replace("'", "", $description);
                    $outputGraphvizText .= 'AS' . $relation['asn2'] . ' ';
                    $outputGraphvizText .= '[';
                    $outputGraphvizText .= 'tooltip="AS' . $asnMeta->asn . ' ~ ' . addslashes($description) . $countryCode . '" ';
                    $outputGraphvizText .= 'URL="https://bgpview.io/asn/' . $asnMeta->asn . '" ';
                    $outputGraphvizText .= ']' . PHP_EOL;
                    $processedAsn[$relation['asn2']] = true;
                }
            }

            $outputGraphvizText .= 'AS' . $relation['asn1'] . ' -> AS' . $relation['asn2'] . ' [ penwidth = ' . $relation['weight'] . ' ];' . PHP_EOL;
        }
        $outputGraphvizText .= 'AS' . $inputAsn . '[fontcolor="#880000"]' . PHP_EOL;
        $outputGraphvizText .= '}' . PHP_EOL;

        exec('echo \'' . $outputGraphvizText . '\' | dot -Tsvg -o ' . public_path() . '/assets/graphs/AS' . $inputAsn . '_' . $ipVersion . '.svg');

        $this->cli->comment('Generated graph for AS' . $inputAsn . ' [' . $ipVersion . ']');
    }
}
