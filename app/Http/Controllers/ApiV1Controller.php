<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv6BgpPrefix;
use App\Models\IX;
use App\Models\IXMember;
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
        // lets only use the AS number.
        $as_number = str_ireplace('as', '', $as_number);

        $asnData = ASN::with('emails')->where('asn', $as_number)->first();

        if (is_null($asnData)) {
            $data = $this->makeStatus('Could not find ASN', false);
            return $this->respond($data);
        }

        $output['asn']  = $asnData->asn;
        $output['name'] = $asnData->name;
        $output['description_short'] = $asnData->description;
        $output['description_full']  = $asnData->description_full;
        $output['country_code']         = $asnData->counrty_code;
        $output['website']              = $asnData->website;
        $output['email_contacts']       = $asnData->email_contacts;
        $output['abuse_contacts']       = $asnData->abuse_contacts;
        $output['looking_glass']        = $asnData->looking_glass;
        $output['traffic_estimation']   = $asnData->traffic_estimation;
        $output['traffic_ratio']        = $asnData->traffic_ratio;
        $output['owner_address']        = $asnData->owner_address;

        $ixs = [];
        foreach (IXMember::where('asn', $asnData->asn)->get() as $ixMember) {
            $ixInfo = $ixMember->ix;

            $ix_data['ix_id']           = $ixInfo->id;
            $ix_data['name']            = $ixInfo->name;
            $ix_data['name_full']       = $ixInfo->name_full;
            $ix_data['counrty_code']    = $ixInfo->counrty_code;
            $ix_data['ipv4_address']    = $ixMember->ipv4_address;
            $ix_data['ipv6_address']    = $ixMember->ipv6_address;
            $ix_data['speed']           = $ixMember->speed;

            $ixs[] = $ix_data;
        }
        $output['internet_exchanges']        = $ixs;

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

    /*
     * URI: /prefix/{ip}/{cidr}
     * Optional Params: with_raw_whois
     */
    public function prefix(Request $request, $ip, $cidr)
    {
        $ipVersion = $this->ipUtils->getInputType($ip);

        if ($ipVersion === 4) {
            $prefix = IPv4BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->first();
        } else if ($ipVersion === 6) {
            $prefix = IPv6BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->first();
        } else {
            $data = $this->makeStatus('Malformed input', false);
            return $this->respond($data);
        }

        if (is_null($prefix) === true) {
            $data = $this->makeStatus('Count not find prefix', false);
            return $this->respond($data);
        }

        $prefixWhois = $prefix->whois();
        $allocation = $this->ipUtils->getAllocationEntry($prefix->ip);
        $geoip = $this->ipUtils->geoip($prefix->ip);

        $output['prefix']           = $prefix->ip . '/' . $prefix->cidr;
        $output['ip']               = $prefix->ip;
        $output['cidr']             = $prefix->cidr;
        $output['asn']              = $prefix->asn;
        $output['name']             = $prefixWhois ? $prefixWhois->name : null;
        $output['description_short']= $prefixWhois ? $prefixWhois->description : null;
        $output['description_full'] = $prefixWhois ? $prefixWhois->description_full : null;
        $output['emails']           = $prefixWhois ? $prefixWhois->email_contacts : null;
        $output['abuse_emails']     = $prefixWhois ? $prefixWhois->abuse_contacts : null;
        $output['owner_address']    = $prefixWhois ? $prefixWhois->owner_address : null;

        $output['country_codes']['whois_country_code']          = $prefixWhois ? $prefixWhois->counrty_code : null;
        $output['country_codes']['rir_allocation_country_code'] = $allocation ? $allocation->counrty_code : null;
        $output['country_codes']['maxmind_country_code']        = $geoip->country->isoCode ?: null;

        $output['rir_allocation']['rir_name']           = $allocation->rir->name;
        $output['rir_allocation']['country_code']       = $allocation->counrty_code;
        $output['rir_allocation']['ip']                 = $allocation->ip;
        $output['rir_allocation']['cidr']               = $allocation->cidr;
        $output['rir_allocation']['prefix']             = $allocation->ip . '/' . $allocation->cidr;
        $output['rir_allocation']['date_allocated']     = $allocation->date_allocated . ' 00:00:00';

        $output['maxmind']['country_code']  = $geoip->country->isoCode ?: null;
        $output['maxmind']['city']          = $geoip->city->name ?: null;

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $prefixWhois ? $prefixWhois->raw_whois : null;
        }

        $output['date_updated']   = (string) ($prefixWhois ? $prefixWhois->updated_at : $prefix->updated_at);

        return $this->sendData($output);
    }

    /*
     * URI: /ip/{ip}
     */
    public function ip($ip)
    {
        $prefixes = $this->ipUtils->getBgpPrefixes($ip);
        $geoip = $this->ipUtils->geoip($ip);
        $allocation = $this->ipUtils->getAllocationEntry($ip);

        $output['prefixes'] = [];
        foreach ($prefixes as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']             = $prefix->ip;
            $prefixOutput['cidr']           = $prefix->cidr;
            $prefixOutput['asn']            = $prefix->asn;
            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['prefixes'][]  = $prefixOutput;
        }

        $output['rir_allocation']['rir_name']           = $allocation->rir->name;
        $output['rir_allocation']['country_code']       = $allocation->counrty_code;
        $output['rir_allocation']['ip']                 = $allocation->ip;
        $output['rir_allocation']['cidr']               = $allocation->cidr;
        $output['rir_allocation']['prefix']             = $allocation->ip . '/' . $allocation->cidr;
        $output['rir_allocation']['date_allocated']     = $allocation->date_allocated . ' 00:00:00';

        $output['maxmind']['country_code']  = $geoip->country->isoCode ?: null;
        $output['maxmind']['city']          = $geoip->city->name ?: null;

        return $this->sendData($output);
    }

    /*
     * URI: /ix/{ix_id}
     */
    public function ix($ix_id)
    {
        $ix = IX::find($ix_id);

        if (is_null($ix) === true) {
            $data = $this->makeStatus('Could not find IX', false);
            return $this->respond($data);
        }

        $output['name']         = $ix->name;
        $output['name_full']    = $ix->name_full;
        $output['website']      = $ix->website;
        $output['tech_email']   = $ix->tech_email;
        $output['tech_phone']   = $ix->tech_phone;
        $output['policy_email'] = $ix->policy_email;
        $output['policy_phone'] = $ix->policy_phone;
        $output['city']         = $ix->city;
        $output['counrty_code'] = $ix->counrty_code;
        $output['url_stats']    = $ix->url_stats;

        $members = [];
        foreach ($ix->members as $member) {
            $asnInfo = $member->asn_info;

            $memberInfo['asn']          = $asnInfo ? $asnInfo->asn : null;
            $memberInfo['name']         = $asnInfo ? $asnInfo->name: null;
            $memberInfo['description']  = $asnInfo ? $asnInfo->description : null;
            $memberInfo['counrty_code'] = $asnInfo ? $asnInfo->counrty_code : null;
            $memberInfo['ipv4_address'] = $member->ipv4_address;
            $memberInfo['ipv6_address'] = $member->ipv6_address;
            $memberInfo['speed']        = $member->speed;

            $members[] = $memberInfo;
        }

        $output['members_count'] = count($members);
        $output['members'] = $members;

        return $this->sendData($output);
    }
}
