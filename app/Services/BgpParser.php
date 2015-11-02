<?php

namespace App\Services;

class BgpParser
{
    public function parse($input)
    {
        $bgpParts = explode(' ', $input);

        $path =  explode('as-path', $input, 2)[1];
        $path = trim(explode(':', $path, 2)[1]);
        $path = explode(' ', $path);
        $prefixParts = explode('/', $bgpParts[0], 2);

        $bgpPrefixData = new \stdClass();
        $bgpPrefixData->prefix = $bgpParts[0];
        $bgpPrefixData->ip = $prefixParts[0];
        $bgpPrefixData->cidr = $prefixParts[1];
        $bgpPrefixData->source = $bgpParts[1];
        $bgpPrefixData->asn = $bgpParts[3];
        $bgpPrefixData->path = $path;

        return $bgpPrefixData;
    }

}