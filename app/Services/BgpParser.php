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

        $prefix = $bgpParts[0];
        $source = $bgpParts[1];
        $asn = $bgpParts[3];

        return [
            'prefix' => $prefix,
            'asn' => $asn,
            'source' => $source,
            'as_path' => $path,
        ];
    }

}