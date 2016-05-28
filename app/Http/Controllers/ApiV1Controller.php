<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use App\Models\IPv4BgpEntry;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4Peer;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6BgpPrefix;
use App\Models\IPv6Peer;
use App\Models\IPv6PrefixWhois;
use App\Models\IX;
use App\Models\IXMember;
use App\Models\RirAsnAllocation;
use App\Services\Dns;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\ApiBaseController;

class ApiV1Controller extends ApiBaseController
{
    /*
     * URI: /asn/{as_number}
     * Optional Params: with_raw_whois
     * Optional Params: with_peers
     * Optional Params: with_prefixes
     * Optional Params: with_ixs
     * Optional Params: with_downstreams
     * Optional Params: with_upstreams
     */
    public function asn(Request $request, $as_number)
    {
        // lets only use the AS number.
        $as_number = $this->ipUtils->normalizeInput($as_number);

        $asnData = ASN::with('emails')->where('asn', $as_number)->first();
        $allocation = RirAsnAllocation::where('asn', $as_number)->first();

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

        $output['rir_allocation']['rir_name']           = empty($allocation->rir_id) !== true ? $allocation->rir->name : null;
        $output['rir_allocation']['country_code']       = isset($allocation->counrty_code) ? $allocation->counrty_code : null;
        $output['rir_allocation']['date_allocated']     = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00' : null;

        if ($request->has('with_ixs') === true) {
            $output['internet_exchanges'] = IXMember::getMembers($asnData->asn);
        }
        if ($request->has('with_peers') === true) {
            $output['peers'] = ASN::getPeers($as_number);
        }
        if ($request->has('with_prefixes') === true) {
            $output['prefixes'] = ASN::getPrefixes($as_number);
        }
        if ($request->has('with_upstreams') === true) {
            $output['upstreams'] = ASN::getUpstreams($as_number);
        }
        if ($request->has('with_downstreams') === true) {
            $output['downstreams'] = ASN::getDownstreams($as_number);
        }
        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $asnData->raw_whois;
        }

        $output['date_updated']        = (string) $asnData->updated_at;
        return $this->sendData($output);
    }

    /*
     * URI: /asn/{as_number}/peers
     */
    public function asnPeers($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $peers      = ASN::getPeers($as_number);

        return $this->sendData($peers);
    }

    /*
     * URI: /asn/{as_number}/ixs
     */
    public function asnIxs($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $ixs        = IXMember::getMembers($as_number);

        return $this->sendData($ixs);
    }

    /*
     * URI: /asn/{as_number}/prefixes
     */
    public function asnPrefixes($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $prefixes   = ASN::getPrefixes($as_number);

        return $this->sendData($prefixes);
    }

    /*
     * URI: /asn/{as_number}/upstreams
     */
    public function asnUpstreams($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $upstreams  = ASN::getUpstreams($as_number);

        return $this->sendData($upstreams);
    }

    /*
     * URI: /asn/{as_number}/downstreams
     */
    public function asnDownstreams($as_number)
    {
        $as_number  = $this->ipUtils->normalizeInput($as_number);
        $downstreams  = ASN::getDownstreams($as_number);

        return $this->sendData($downstreams);
    }

    /*
     * URI: /prefix/{ip}/{cidr}
     * Optional Params: with_raw_whois
     */
    public function prefix(Request $request, $ip, $cidr)
    {
        $ipVersion = $this->ipUtils->getInputType($ip);

        if ($ipVersion === 4) {
            $prefixes = IPv4BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->get();
        } else if ($ipVersion === 6) {
            $prefixes = IPv6BgpPrefix::where('ip', $ip)->where('cidr', $cidr)->get();
        } else {
            $data = $this->makeStatus('Malformed input', false);
            return $this->respond($data);
        }

        if ($prefixes->count() === 0) {
            if ($ipVersion === 4) {
                $prefix = IPv4PrefixWhois::where('ip', $ip)->where('cidr', $cidr)->first();
            } else {
                $prefix = IPv6PrefixWhois::where('ip', $ip)->where('cidr', $cidr)->first();
            }

            $prefixWhois = $prefix;
        } else {
            $prefix = $prefixes[0];
            $prefixWhois = $prefix->whois();
        }

        if (is_null($prefix) === true) {
            $data = $this->makeStatus('Prefix not found in BGP table or malformed', false);
            return $this->respond($data);
        }

        $allocation = $this->ipUtils->getAllocationEntry($prefix->ip);
        $geoip = $this->ipUtils->geoip($prefix->ip);

        $output['prefix']           = $prefix->ip . '/' . $prefix->cidr;
        $output['ip']               = $prefix->ip;
        $output['cidr']             = $prefix->cidr;
        $output['asns']             = [];
        foreach ($prefixes as $prefixData) {
            $asn = ASN::where('asn', $prefixData->asn)->first();
            $asnData['asn'] = $prefixData->asn;
            $asnData['name'] = $asn->name;
            $asnData['description'] = $asn->description;
            $asnData['country_code'] = $asn->counrty_code;

            $output['asns'][] = $asnData;
        }
        $output['name']             = $prefixWhois ? $prefixWhois->name : null;
        $output['description_short']= $prefixWhois ? $prefixWhois->description : null;
        $output['description_full'] = $prefixWhois ? $prefixWhois->description_full : null;
        $output['email_contacts']   = $prefixWhois ? $prefixWhois->email_contacts : null;
        $output['abuse_contacts']   = $prefixWhois ? $prefixWhois->abuse_contacts : null;
        $output['owner_address']    = $prefixWhois ? $prefixWhois->owner_address : null;

        $output['country_codes']['whois_country_code']          = $prefixWhois ? $prefixWhois->counrty_code : null;
        $output['country_codes']['rir_allocation_country_code'] = $allocation ? $allocation->counrty_code : null;
        $output['country_codes']['maxmind_country_code']        = $geoip ? $geoip->country->isoCode : null;

        $output['rir_allocation']['rir_name']           = empty($allocation->rir_id) !== true ? $allocation->rir->name : null;
        $output['rir_allocation']['country_code']       = isset($allocation->counrty_code) ? $allocation->counrty_code : null;
        $output['rir_allocation']['ip']                 = isset($allocation->ip) ? $allocation->ip : null;
        $output['rir_allocation']['cidr']               = isset($allocation->cidr) ? $allocation->cidr : null;
        $output['rir_allocation']['prefix']             = isset($allocation->ip) && isset($allocation->cidr) ? $allocation->ip . '/' . $allocation->cidr : null;
        $output['rir_allocation']['date_allocated']     = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00' : null;

        $output['maxmind']['country_code']  = $geoip ? $geoip->country->isoCode : null;
        $output['maxmind']['city']          = $geoip ? $geoip->city->name : null;

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $prefixWhois ? $prefixWhois->raw_whois : null;
        }

        if ($request->has('with_dns') === true) {
            $output['dns'] = $this->ipUtils->getPrefixDns($output['prefix']);
        }

        $output['date_updated']   = (string) ($prefixWhois ? $prefixWhois->updated_at : $prefix->updated_at);

        return $this->sendData($output);
    }

    /*
     * URI: /prefix/{ip}/{cidr}/dns
     */
    public function prefixDns($ip, $cidr)
    {
        $prefix = $ip . '/' . $cidr;
        $output = $this->ipUtils->getPrefixDns($prefix);

        return $this->sendData($output);
    }

    /*
     * URI: /ip/{ip}
     */
    public function ip($ip)
    {
        // Check if the IP is in bogon range
        if ($bogon = $this->ipUtils->isBogonAddress($ip)) {
            $bogonParts = explode('/', $bogon);

            $geoip      = null;
            $prefixes   = [];
            $allocation = null;
            $ptrRecord  = null;

            $rirIp      = $bogonParts[0];
            $rirCidr    = $bogonParts[1];
            $rirPrefix  = $bogon;
        } else {
            $prefixes   = $this->ipUtils->getBgpPrefixes($ip);
            $geoip      = $this->ipUtils->geoip($ip);
            $allocation = $this->ipUtils->getAllocationEntry($ip);
            $ptrRecord  = $this->dns->getPtr($ip);

            $rirIp      = isset($allocation->ip) ? $allocation->ip : null;
            $rirCidr    = isset($allocation->cidr) ? $allocation->cidr : null;
            $rirPrefix  = isset($allocation->ip) && isset($allocation->cidr) ? $allocation->ip . '/' . $allocation->cidr : null;
        }

        $output['ip']           = $ip;
        $output['ptr_record']   = $ptrRecord;

        $output['prefixes'] = [];
        foreach ($prefixes as $prefix) {
            $prefixWhois = $prefix->whois;
            $asn = ASN::where('asn',$prefix->asn)->first();

            $prefixOutput['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']             = $prefix->ip;
            $prefixOutput['cidr']           = $prefix->cidr;
            $prefixOutput['asn']['asn']     = $prefix->asn;
            $prefixOutput['asn']['name']    = $asn->name;
            $prefixOutput['asn']['description']     = $asn->description;
            $prefixOutput['asn']['country_code']    = $asn->counrty_code;
            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['prefixes'][]  = $prefixOutput;
        }

        // Lets sort out the prefix array from smallest to largest
        usort($output['prefixes'], function($a, $b) {
            return $b['cidr'] - $a['cidr'];
        });

        $output['rir_allocation']['rir_name']           = isset($allocation->rir_id) && empty($allocation->rir_id) !== true ? $allocation->rir->name : null;
        $output['rir_allocation']['country_code']       = isset($allocation->counrty_code) ? $allocation->counrty_code : null;
        $output['rir_allocation']['ip']                 = $rirIp;
        $output['rir_allocation']['cidr']               = $rirCidr;
        $output['rir_allocation']['prefix']             = $rirPrefix;
        $output['rir_allocation']['date_allocated']     = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00': null;

        $output['maxmind']['country_code']  = $geoip ? $geoip->country->isoCode : null;
        $output['maxmind']['city']          = $geoip ? $geoip->city->name : null;

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
        $output['country_code'] = $ix->counrty_code;
        $output['url_stats']    = $ix->url_stats;

        $members = [];
        foreach ($ix->members as $member) {
            $asnInfo = $member->asn_info;

            $memberInfo['asn']          = $member->asn;
            $memberInfo['name']         = $asnInfo ? $asnInfo->name: null;
            $memberInfo['description']  = $asnInfo ? $asnInfo->description : null;
            $memberInfo['country_code'] = $asnInfo ? $asnInfo->counrty_code : null;
            $memberInfo['ipv4_address'] = $member->ipv4_address;
            $memberInfo['ipv6_address'] = $member->ipv6_address;
            $memberInfo['speed']        = $member->speed;

            $members[] = $memberInfo;
        }

        $output['members_count'] = count($members);
        $output['members'] = $members;

        return $this->sendData($output);
    }

    /*
     * URI: /asns/{country_code?}
     */
    public function asns(Request $request, $country_code = null)
    {
        $limit = $request->input('limit');
        if (is_numeric($limit) !== true || $limit < 1 || $limit > 100) {
            $limit = 20;
        }

        if (is_null($country_code) !== true) {
            $asns = ASN::where('counrty_code', strtoupper($country_code))->paginate($limit);
        } else {
            $asns = ASN::paginate($limit);
        }

        foreach ($asns as $asn) {
            $asnData['asn']                  = $asn->asn;
            $asnData['name']                 = $asn->name;
            $asnData['description_short']    = $asn->description;
            $asnData['country_code']         = $asn->counrty_code;

            $output[] = $asnData;
        }

        $data = $this->makeStatus();
        $data['results_count']  = $asns->total();
        $data['current_page']   = $asns->currentPage();
        $data['limit']          = $asns->perPage();
        $data['data']           = $output;
        
        return $this->respond($data);
    }

    /*
     * URI: /search
     * Mandatory Params: query_term
     *
     */
    public function search(Request $request)
    {
        $queryTerm = $request->get('query_term');
        $queryTerm = trim(str_replace(['/', '?', '!', ':', ',', '\'', '-', '_', '.', '+'], "", strtolower($queryTerm)));

        $elasticQuery['filtered']['query'] = [
            'bool' => [
                'should' => [
                    ['wildcard' => [
                        'name' => [
                            'value' => '*'.$queryTerm.'*',
                        ]
                    ]],
                    ['wildcard' => [
                        'description' => [
                            'value' => '*'.$queryTerm.'*',
                        ]
                    ]],
                    ['multi_match' => [
                        'query' => $queryTerm,
                        'fields' => ['asn^5']
                    ]],
                ],
                'minimum_should_match' => 1,
            ]
        ];

        $asnSort = [
            ['asn' => [
                'order' => 'asc'
            ]]
        ];
        
        $ipSort = [
            ['ip' => [
                'order' => 'asc'
            ]]
        ];

        $asns = ASN::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 100, $offset = null, $asnSort);
        $ipv4Prefixes = IPv4PrefixWhois::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 200, $offset = null, $ipSort);
        $ipv6Prefixes = IPv6PrefixWhois::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 200, $offset = null, $ipSort);

        $data['asns'] = [];
        foreach ($asns as $asn) {
            $asnData['asn']                 = $asn->asn;
            $asnData['name']                = $asn->name;
            $asnData['description']   = $asn->description;
            $asnData['country_code']        = $asn->counrty_code;
            $asnData['email_contacts']      = $asn->email_contacts;
            $asnData['abuse_contacts']      = $asn->abuse_contacts;
            $asnData['rir_name']         = $asn->rir->name;

            $data['asns'][] = $asnData;
        }

        $data['ipv4_prefixes'] = [];
        foreach ($ipv4Prefixes as $prefix) {
            $prefixData['prefix']   = $prefix->ip . '/' . $prefix->cidr;
            $prefixData['ip']       = $prefix->ip;
            $prefixData['cidr']     = $prefix->cidr;
            $prefixData['name']     = $prefix->name;
            $prefixData['country_code']     = $prefix->counrty_code;
            $prefixData['description']    = $prefix->description;
            $prefixData['email_contacts']   = $prefix->email_contacts;
            $prefixData['abuse_contacts']   = $prefix->abuse_contacts;
            $prefixData['rir_name']         = $prefix->rir->name;
            $prefixData['parent_prefix']    = $prefix->parent_ip . '/' . $prefix->parent_cidr;
            $prefixData['parent_ip']        = $prefix->parent_ip;
            $prefixData['parent_cidr']      = $prefix->parent_cidr;

            $data['ipv4_prefixes'][] = $prefixData;
        }

        $data['ipv6_prefixes'] = [];
        foreach ($ipv6Prefixes as $prefix) {
            $prefixData['prefix']   = $prefix->ip . '/' . $prefix->cidr;
            $prefixData['ip']       = $prefix->ip;
            $prefixData['cidr']     = $prefix->cidr;
            $prefixData['name']     = $prefix->name;
            $prefixData['country_code']     = $prefix->counrty_code;
            $prefixData['description']      = $prefix->description;
            $prefixData['email_contacts']   = $prefix->email_contacts;
            $prefixData['abuse_contacts']   = $prefix->abuse_contacts;
            $prefixData['rir_name']         = $prefix->rir->name;
            $prefixData['parent_prefix']    = $prefix->parent_ip . '/' . $prefix->parent_cidr;
            $prefixData['parent_ip']        = $prefix->parent_ip;
            $prefixData['parent_cidr']      = $prefix->parent_cidr;

            $data['ipv6_prefixes'][] = $prefixData;
        }

        return $this->sendData($data);
    }

    /*
     * URI: /dns/live/{hostname}
     *
     */
    public function getLiveDns($hostname)
    {
        $hostname = strtolower($hostname);
        $dns = new Dns(['8.8.8.8', '8.8.4.4', 2]);

        $records = Cache::remember($hostname, 60*24, function() use ($hostname, $dns)
        {
            return $dns->getDomainRecords($hostname);
        });


        $data['hostname']     = $hostname;
        $data['dns_records']  = $records;

        return $this->sendData($data);
    }
}
