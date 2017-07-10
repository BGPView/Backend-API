<?php

namespace App\Services;

class BgpParser
{
    // These are the teir 1 providers where BGP path should stop at
    private $topTeirAsnSet = [
        7018 => true,   // AT&T
        174 => true,    // CogentCo
        209 => true,    // CenturyLink (Qwest)
        3320 => true,   // Deutsche Telekom
        3257 => true,   // GTT (Tinet)
        3356 => true,   // Level3
        3549 => true,   // Level3
        1 => true,      // Level3
        2914 => true,   // NTT (Verio)
        5511 => true,   // Orange
        6453 => true,   // Tata
        6762 => true,   //Sparkle
        12956 => true,  // Telefonica
        1299 => true,   // TeliaSonera
        701 => true,    // Verizon
        702 => true,    // Verizon
        703 => true,    // Verizon
        2828 => true,   // XO
        6461 => true,   //Zayo (AboveNet)
        6963 => true,   // HE
        3491 => true,   // PCCW
        1273 => true,   // Vodafone (UK)
        1239 => true,   // Sprint
        2497 => true,   // Internet Initiative Japan
    ];


    public function parse($input)
    {
        $input = trim(preg_replace('/\s*{[^)]*}/', '', $input));
        $bgpParts = explode('|', $input);

        // Remove AS-SETS
        $bgpParts[6] = trim(preg_replace('/s*{[^)]*}/', '', $bgpParts[6]));
        $pathArr = explode(' ', $bgpParts[6]);

        $peers = $this->getPeers($pathArr);
        $path = $this->getPath($pathArr);

        $prefixParts = explode('/', $bgpParts[5], 2);

        $bgpPrefixData = new \stdClass();
        $bgpPrefixData->prefix = $bgpParts[5];
        $bgpPrefixData->ip = filter_var($prefixParts[0], FILTER_VALIDATE_IP) ? $prefixParts[0] : null;
        $bgpPrefixData->cidr = $prefixParts[1];
        $bgpPrefixData->source = $bgpParts[8];
        $bgpPrefixData->asn = end($pathArr);
        $bgpPrefixData->upstream_asn = isset($path[1]) ? $path[1] : null; // Second BGP (Ignoring self)
        $bgpPrefixData->path_string = implode(' ', $path);
        $bgpPrefixData->path = $path;
        $bgpPrefixData->peersSet = $peers;

        return $bgpPrefixData;
    }

    private function getPath($pathArr)
    {
        $pathArr = array_unique($pathArr);

        foreach ($pathArr as $key => $asnHop) {
            // Since we are dealing with ONLY publicly seen prefixes
            // This means that we will need to make sure there is at least
            // a single teir one carrier in the prefix
            // else... we will disregard the BGP entry completely
            if (isset($this->topTeirAsnSet[$asnHop]) === true) {
                break;
            }

            unset($pathArr[$key]);
        }

        return array_reverse($pathArr);
    }

    private function getPeers($pathArr)
    {
        $peers = [];
        foreach ($pathArr as $key => $asnHop) {
            // Lets check if next ASN actually exists
            if (isset($pathArr[$key + 1]) === true) {
                $peerSet = [$asnHop, $pathArr[$key + 1]];
                sort($peerSet);
                $peers[] = $peerSet;
            }
        }

        // Remove any dupe peers
        foreach ($peers as $key => $peerSet) {
            if ($peerSet[0] === $peerSet[1]) {
                unset($peers[$key]);
            }
        }

        return array_values($peers);
    }
}