<?php

namespace App\Services;

class BgpParser
{
    // These are the teir 1 providers where BGP path should stop at
    private $topTeirAsnSet = [
        7018,           // AT&T
        174,            // CogentCo
        209,            // CenturyLink (Qwest)
        3320,           // Deutsche Telekom
        3257,           // GTT (Tinet)
        3356, 3549, 1,  // Level3
        2914,           // NTT (Verio)
        5511,           // Orange
        6453,           // Tata
        6762,           //Sparkle
        12956,          // Telefonica
        1299,           // TeliaSonera
        701, 702, 703,  // Verizon
        2828,           // XO
        6461,           //Zayo (AboveNet)
        6963,           // HE
        3491,           // PCCW
        1273,           // Vodafone (UK)
        1239,           // Sprint
        2497,           // Internet Initiative Japan
    ];


    public function parse($input)
    {
        $bgpParts = explode(' ', $input);

        $peers = $this->getPeers($input);
        $path = $this->getPath($input);

        $prefixParts = explode('/', $bgpParts[0], 2);

        $bgpPrefixData = new \stdClass();
        $bgpPrefixData->prefix = $bgpParts[0];
        $bgpPrefixData->ip = $prefixParts[0];
        $bgpPrefixData->cidr = $prefixParts[1];
        $bgpPrefixData->source = $bgpParts[1];
        $bgpPrefixData->asn = $bgpParts[3];
        $bgpPrefixData->upstream_asn = isset($path[1]) ? $path[1] : null; // Second BGP (Ignoring self)
        $bgpPrefixData->path_string = implode(' ', $path);
        $bgpPrefixData->path = $path;
        $bgpPrefixData->peersSet = $peers;

        return $bgpPrefixData;
    }

    private function getPath($input)
    {
        $path =  explode('as-path', $input, 2)[1];
        $path = trim(explode(':', $path, 2)[1]);
        // Lets reverse the order to start at origin
        $path = explode(' ', $path);

        foreach ($path as $key => $asnHop) {
            // Since we are dealing with ONLY publicly seen prefixes
            // This means that we will need to make sure there is at least
            // a single teir one carrier in the prefix
            // else... we will disregard the BGP entry completely
            if (in_array($asnHop, $this->topTeirAsnSet)) {
                break;
            }

            unset($path[$key]);
        }

        return array_reverse($path);
    }

    private function getPeers($input)
    {
        $path =  explode('as-path', $input, 2)[1];
        $path = trim(explode(':', $path, 2)[1]);
        // Lets reverse the order to start at origin
        $path = explode(' ', $path);

        $peers = [];
        foreach ($path as $key => $asnHop) {
            // Lets check if next ASN actually exists
            if (isset($path[$key + 1]) === true) {
                $peerSet = [$asnHop, $path[$key + 1]];
                sort($peerSet);
                $peers[] = $peerSet;
            }
        }

        return $peers;
    }

}