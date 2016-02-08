<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv6BgpPrefix;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\ApiBaseController;

class ApiV1Controller extends ApiBaseController
{
    /*
     * URI: /asn/{as_number}
     * Optional Params: with_raw_whois
     */
    public function asn(Request $request, $as_number)
    {
        // lets only use the AS number
        $as_number = str_ireplace('as', '', $as_number);

        $asnData = ASN::where('asn', $as_number)->first();

        if (is_null($asnData)) {
            $data = $this->makeStatus('Could not find ASN', false);
            return $this->respond($data);
        }

        $output['asn']  = $asnData->asn;
        $output['name'] = $asnData->name;
        $output['description']['short'] = $asnData->description;
        $output['description']['full']  = $asnData->description_full;
        $output['country_code']         = $asnData->counrty_code;
        $output['website']              = $asnData->website;
        $output['looking_glass']        = $asnData->looking_glass;
        $output['traffic_estimation']   = $asnData->traffic_estimation;
        $output['traffic_ratio']        = $asnData->traffic_ratio;
        $output['owner_address']        = $asnData->owner_address;

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $asnData->raw_whois;
        }

        $output['date_updated']        = (string) $asnData->updated_at;

        return $this->sendData($output);
    }

    /*
     * URI: /asn/{as_number}/prefixes
     */
    public function asnPrefixes($as_number)
    {
        // lets only use the AS number
        $as_number = str_ireplace('as', '', $as_number);

        $ipv4Prefixes = IPv4BgpPrefix::where('asn', $as_number)->get();
        $ipv6Prefixes = IPv6BgpPrefix::where('asn', $as_number)->get();

        $output['asn'] = (int) $as_number;

        $output['ipv4_prefixes'] = [];
        foreach ($ipv4Prefixes as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']             = $prefix->ip;
            $prefixOutput['cidr']           = $prefix->cidr;

            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['ipv4_prefixes'][]  = $prefixOutput;
            $prefixOutput = null;
            $prefixWhois = null;
        }

        $output['ipv6_prefixes'] = [];
        foreach ($ipv6Prefixes as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix'] = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']     = $prefix->ip;
            $prefixOutput['cidr']   = $prefix->cidr;

            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['ipv6_prefixes'][]  = $prefixOutput;
            $prefixOutput = null;
            $prefixWhois = null;
        }

        return $this->sendData($output);
    }
}
