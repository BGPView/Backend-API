<?php

namespace App\Jobs;

use App\Models\ASN;
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
        $upstreams = ASN::getUpstreams($this->input_asn, $asnMeta = false);

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
