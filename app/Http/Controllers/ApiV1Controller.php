<?php

namespace App\Http\Controllers;

use App\Models\ASN;
use App\Models\IPv4BgpPrefix;
use App\Models\IPv4Peer;
use App\Models\IPv4PrefixWhois;
use App\Models\IPv6BgpPrefix;
use App\Models\IPv6Peer;
use App\Models\IPv6PrefixWhois;
use App\Models\IX;
use App\Models\IXMember;
use App\Services\Dns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pdp\Parser;
use Pdp\PublicSuffixListManager;

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

        $asnData        = ASN::with('emails')->where('asn', $as_number)->first();
        $allocation     = $this->ipUtils->getAllocationEntry($as_number);
        $ianaAssignment = $this->ipUtils->getIanaAssignmentEntry($as_number);

        if (is_null($asnData) === true && $ianaAssignment === false) {
            $data = $this->makeStatus('Could not find ASN', false);
            return $this->respond($data);
        }

        $output['asn']                = $as_number;
        $output['name']               = isset($asnData->name) ? $asnData->name : null;
        $output['description_short']  = isset($asnData->description_short) ? $asnData->description_short : null;
        $output['description_full']   = isset($asnData->description_full) ? $asnData->description_full : null;
        $output['country_code']       = isset($asnData->counrty_code) ? $asnData->counrty_code : null;
        $output['website']            = isset($asnData->website) ? $asnData->website : null;
        $output['email_contacts']     = isset($asnData->email_contacts) ? $asnData->email_contacts : null;
        $output['abuse_contacts']     = isset($asnData->abuse_contacts) ? $asnData->abuse_contacts : null;
        $output['looking_glass']      = isset($asnData->looking_glass) ? $asnData->looking_glass : null;
        $output['traffic_estimation'] = isset($asnData->traffic_estimation) ? $asnData->traffic_estimation : null;
        $output['traffic_ratio']      = isset($asnData->traffic_ratio) ? $asnData->traffic_ratio : null;
        $output['owner_address']      = isset($asnData->owner_address) ? $asnData->owner_address : null;

        $output['rir_allocation']['rir_name']          = empty($allocation->rir_name) !== true ? $allocation->rir_name : null;
        $output['rir_allocation']['country_code']      = isset($allocation->country_code) ? $allocation->country_code : null;
        $output['rir_allocation']['date_allocated']    = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00' : null;
        $output['rir_allocation']['allocation_status'] = isset($allocation->status) ? $allocation->status : 'unknown';

        $output['iana_assignment']['status']        = $ianaAssignment->status;
        $output['iana_assignment']['description']   = $ianaAssignment->description;
        $output['iana_assignment']['whois_server']  = $ianaAssignment->whois_server;
        $output['iana_assignment']['date_assigned'] = $ianaAssignment->date_assigned;

        // override the ASN data with assignment data if there is no ASNData
        if (is_null($asnData) === true) {
            $output['name']              = 'IANA-' . strtoupper($ianaAssignment->status);
            $output['description_short'] = $ianaAssignment->description;
            $output['description_full']  = $ianaAssignment->description;
            $output['email_contacts']    = ['iana@iana.org'];
            $output['abuse_contacts']    = ['abuse@iana.org'];
        }

        if ($request->has('with_ixs') === true) {
            $output['internet_exchanges'] = IXMember::getMembers($as_number);
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
            $output['raw_whois'] = isset($asnData->raw_whois) ? $asnData->raw_whois : null;
        }

        $output['date_updated'] = isset($asnData->updated_at) ? (string) $asnData->updated_at : null;
        return $this->sendData($output);
    }

    /*
     * URI: /asn/{as_number}/peers
     */
    public function asnPeers($as_number)
    {
        $as_number = $this->ipUtils->normalizeInput($as_number);
        $peers     = ASN::getPeers($as_number);

        return $this->sendData($peers);
    }

    /*
     * URI: /asn/{as_number}/ixs
     */
    public function asnIxs($as_number)
    {
        $as_number = $this->ipUtils->normalizeInput($as_number);
        $ixs       = IXMember::getMembers($as_number);

        return $this->sendData($ixs);
    }

    /*
     * URI: /asn/{as_number}/prefixes
     */
    public function asnPrefixes($as_number)
    {
        $as_number = $this->ipUtils->normalizeInput($as_number);
        $prefixes  = ASN::getPrefixes($as_number);

        return $this->sendData($prefixes);
    }

    /*
     * URI: /asn/{as_number}/upstreams
     */
    public function asnUpstreams($as_number)
    {
        $as_number = $this->ipUtils->normalizeInput($as_number);
        $upstreams = ASN::getUpstreams($as_number);

        return $this->sendData($upstreams);
    }

    /*
     * URI: /asn/{as_number}/downstreams
     */
    public function asnDownstreams($as_number)
    {
        $as_number   = $this->ipUtils->normalizeInput($as_number);
        $downstreams = ASN::getDownstreams($as_number);

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
            $prefixWhoisClass = IPv4PrefixWhois::class;
        } else if ($ipVersion === 6) {
            $prefixWhoisClass = IPv6PrefixWhois::class;
        } else {
            $data = $this->makeStatus('Malformed input', false);
            return $this->respond($data);
        }

        $prefixes = $this->ipUtils->getPrefixesFromBgpTable($ip, $cidr);

        if ($prefixes->count() === 0) {
            if ($ipVersion === 4) {
                $prefix = IPv4PrefixWhois::where('ip', $ip)->where('cidr', $cidr)->first();
            } else {
                $prefix = IPv6PrefixWhois::where('ip', $ip)->where('cidr', $cidr)->first();
            }

            $prefixWhois = $prefix;
        } else {
            $prefix      = $prefixes[0];
            $prefixWhois = $prefixWhoisClass::where('ip', $prefix->ip)->where('cidr', $prefix->cidr)->first();
        }

        if (is_null($prefix) === true) {
            $data = $this->makeStatus('Prefix not found in BGP table or malformed', false);
            return $this->respond($data);
        }

        $allocation      = $this->ipUtils->getAllocationEntry($prefix->ip, $prefix->cidr);
        $geoip           = $this->ipUtils->geoip($prefix->ip);
        $relatedPrefixes = $this->ipUtils->getRealatedPrefixes($prefix->ip, $prefix->cidr);

        $output['prefix'] = $prefix->ip . '/' . $prefix->cidr;
        $output['ip']     = $prefix->ip;
        $output['cidr']   = $prefix->cidr;
        $output['asns']   = [];
        $asnArray         = [];
        foreach ($prefixes as $prefixData) {
            if (isset($asnArray[$prefixData->asn]) === true) {
                // Make sure we dont have said upstream already in our array
                if (in_array($prefixData->upstream_asn, $asnArray[$prefixData->asn]) !== true) {
                    $asnArray[$prefixData->asn][] = $prefixData->upstream_asn;
                }
            } else {
                $asnArray[$prefixData->asn][] = $prefixData->upstream_asn;
            }
        }

        foreach ($asnArray as $baseAsn => $upstreamArray) {
            $asn                         = ASN::where('asn', $baseAsn)->first();
            $asnData['asn']              = $baseAsn;
            $asnData['name']             = $asn->name;
            $asnData['description']      = $asn->description;
            $asnData['country_code']     = empty($asn->counrty_code) !== true ? $asn->counrty_code : null;
            $asnData['prefix_upstreams'] = [];

            foreach ($upstreamArray as $upstreamAsn) {
                $asn                             = ASN::where('asn', $upstreamAsn)->first();
                $upstreamAsnData['asn']          = $upstreamAsn;
                $upstreamAsnData['name']         = isset($asn->name) ? $asn->name : null;
                $upstreamAsnData['description']  = isset($asn->description) ? $asn->description : null;
                $upstreamAsnData['country_code'] = empty($asn->counrty_code) !== true ? $asn->counrty_code : null;

                $asnData['prefix_upstreams'][] = $upstreamAsnData;
            }

            $output['asns'][] = $asnData;
        }

        $output['name']              = $prefixWhois ? $prefixWhois->name : null;
        $output['description_short'] = $prefixWhois ? $prefixWhois->description : null;
        $output['description_full']  = $prefixWhois ? $prefixWhois->description_full : null;
        $output['email_contacts']    = $prefixWhois ? $prefixWhois->email_contacts : null;
        $output['abuse_contacts']    = $prefixWhois ? $prefixWhois->abuse_contacts : null;
        $output['owner_address']     = $prefixWhois ? $prefixWhois->owner_address : null;

        $output['country_codes']['whois_country_code']          = $prefixWhois ? $prefixWhois->counrty_code : null;
        $output['country_codes']['rir_allocation_country_code'] = $allocation ? $allocation->country_code : null;
        $output['country_codes']['maxmind_country_code']        = $geoip ? $geoip->country->isoCode : null;

        $output['rir_allocation']['rir_name']          = empty($allocation->rir_name) !== true ? $allocation->rir_name : null;
        $output['rir_allocation']['country_code']      = isset($allocation->country_code) ? $allocation->country_code : null;
        $output['rir_allocation']['ip']                = isset($allocation->ip) ? $allocation->ip : null;
        $output['rir_allocation']['cidr']              = isset($allocation->cidr) ? $allocation->cidr : null;
        $output['rir_allocation']['prefix']            = isset($allocation->ip) && isset($allocation->cidr) ? $allocation->ip . '/' . $allocation->cidr : null;
        $output['rir_allocation']['date_allocated']    = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00' : null;
        $output['rir_allocation']['allocation_status'] = isset($allocation->status) ? $allocation->status : 'unknown';

        $output['maxmind']['country_code'] = $geoip ? $geoip->country->isoCode : null;
        $output['maxmind']['city']         = $geoip ? $geoip->city->name : null;

        $output['related_prefixes'] = [];
        foreach ($relatedPrefixes as $relatedPrefix) {
            $relatedPrefixWhois = $relatedPrefix->whois();

            $relatedPrefixData['prefix'] = $relatedPrefix->ip . '/' . $relatedPrefix->cidr;
            $relatedPrefixData['ip']     = $relatedPrefix->ip;
            $relatedPrefixData['cidr']   = $relatedPrefix->cidr;

            $relatedPrefixData['name']         = $relatedPrefixWhois ? $relatedPrefixWhois->name : null;
            $relatedPrefixData['description']  = $relatedPrefixWhois ? $relatedPrefixWhois->description : null;
            $relatedPrefixData['country_code'] = $relatedPrefixWhois ? $relatedPrefixWhois->counrty_code : null;

            $output['related_prefixes'][] = $relatedPrefixData;
        }

        if ($request->has('with_raw_whois') === true) {
            $output['raw_whois'] = $prefixWhois ? $prefixWhois->raw_whois : null;
        }

        if ($request->has('with_dns') === true) {
            $output['dns'] = $this->ipUtils->getPrefixDns($output['prefix']);
        }

        $output['date_updated'] = (string) ($prefixWhois ? $prefixWhois->updated_at : $prefix->updated_at);

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

            $rirIp     = $bogonParts[0];
            $rirCidr   = $bogonParts[1];
            $rirPrefix = $bogon;
        } else {
            $prefixes   = $this->ipUtils->getBgpPrefixes($ip);
            $geoip      = $this->ipUtils->geoip($ip);
            $allocation = $this->ipUtils->getAllocationEntry($ip);
            $ptrRecord  = $this->dns->getPtr($ip);

            $rirIp     = isset($allocation->ip) ? $allocation->ip : null;
            $rirCidr   = isset($allocation->cidr) ? $allocation->cidr : null;
            $rirPrefix = isset($allocation->ip) && isset($allocation->cidr) ? $allocation->ip . '/' . $allocation->cidr : null;
        }

        $output['ip']         = $ip;
        $output['ptr_record'] = $ptrRecord;

        $output['prefixes'] = [];
        foreach ($prefixes as $prefix) {
            $prefixWhois = $prefix->whois;
            $asn         = ASN::where('asn', $prefix->asn)->first();

            $prefixOutput['prefix']              = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']                  = $prefix->ip;
            $prefixOutput['cidr']                = $prefix->cidr;
            $prefixOutput['asn']['asn']          = $prefix->asn;
            $prefixOutput['asn']['name']         = $asn->name;
            $prefixOutput['asn']['description']  = $asn->description;
            $prefixOutput['asn']['country_code'] = empty($asn->counrty_code) !== true ? $asn->counrty_code : null;
            $prefixOutput['name']                = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']         = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']        = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $output['prefixes'][] = $prefixOutput;
        }

        // Lets sort out the prefix array from smallest to largest
        usort($output['prefixes'], function ($a, $b) {
            return $b['cidr'] - $a['cidr'];
        });

        $output['rir_allocation']['rir_name']          = isset($allocation->rir_name) && empty($allocation->rir_name) !== true ? $allocation->rir_name : null;
        $output['rir_allocation']['country_code']      = isset($allocation->country_code) ? $allocation->country_code : null;
        $output['rir_allocation']['ip']                = $rirIp;
        $output['rir_allocation']['cidr']              = $rirCidr;
        $output['rir_allocation']['prefix']            = $rirPrefix;
        $output['rir_allocation']['date_allocated']    = isset($allocation->date_allocated) ? $allocation->date_allocated . ' 00:00:00' : null;
        $output['rir_allocation']['allocation_status'] = isset($allocation->status) ? $allocation->status : 'unknown';

        $output['maxmind']['country_code'] = $geoip ? $geoip->country->isoCode : null;
        $output['maxmind']['city']         = $geoip ? $geoip->city->name : null;

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
        $output['country_code'] = empty($ix->counrty_code) !== true ? $ix->counrty_code : null;
        $output['url_stats']    = $ix->url_stats;

        $members = [];
        foreach ($ix->members as $member) {
            $asnInfo = $member->asn_info;

            $memberInfo['asn']          = $member->asn;
            $memberInfo['name']         = $asnInfo ? $asnInfo->name : null;
            $memberInfo['description']  = $asnInfo ? $asnInfo->description : null;
            $memberInfo['country_code'] = $asnInfo ? $asnInfo->counrty_code : null;
            $memberInfo['ipv4_address'] = $member->ipv4_address;
            $memberInfo['ipv6_address'] = $member->ipv6_address;
            $memberInfo['speed']        = $member->speed;

            $members[] = $memberInfo;
        }

        $output['members_count'] = count($members);
        $output['members']       = $members;

        return $this->sendData($output);
    }

    /*
     * URI: /asns/
     */
    public function asns(Request $request)
    {
        $limit = $request->input('limit');
        if (is_numeric($limit) !== true || $limit < 1 || $limit > 100) {
            $limit = 20;
        }

        $asns = ASN::paginate($limit);

        foreach ($asns as $asn) {
            $asnData['asn']               = $asn->asn;
            $asnData['name']              = $asn->name;
            $asnData['description_short'] = $asn->description;
            $asnData['country_code']      = empty($asn->counrty_code) !== true ? $asn->counrty_code : null;

            $output[] = $asnData;
        }

        $data                  = $this->makeStatus();
        $data['results_count'] = $asns->total();
        $data['current_page']  = $asns->currentPage();
        $data['limit']         = $asns->perPage();
        $data['data']          = $output;

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
                'should'               => [
                    ['wildcard' => [
                        'name' => [
                            'value' => '*' . $queryTerm . '*',
                            'boost' => 2,
                        ],
                    ]],
                    ['wildcard' => [
                        'description' => [
                            'value' => '*' . $queryTerm . '*',
                        ],
                    ]],
                    ['wildcard' => [
                        'name_full' => [
                            'value' => '*' . $queryTerm . '*',
                        ],
                    ]],
                    ['multi_match' => [
                        'query'  => $queryTerm,
                        'fields' => ['asn^5'],
                    ]],
                ],
                'minimum_should_match' => 1,
            ],
        ];

        $asnSort = [
            ['asn' => [
                'order' => 'asc',
            ]],
        ];

        $ipSort = [
            ['ip' => [
                'order' => 'asc',
            ]],
        ];

        $asns         = ASN::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 100, $offset = null, $asnSort);
        $ipv4Prefixes = IPv4PrefixWhois::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 200, $offset = null, $ipSort);
        $ipv6Prefixes = IPv6PrefixWhois::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 200, $offset = null, $ipSort);
        $ixs          = IX::searchByQuery($elasticQuery, $aggregations = null, $sourceFields = null, $limit = 200, $offset = null, $ipSort);

        $data['asns'] = [];
        foreach ($asns as $asn) {
            $asnData['asn']            = $asn->asn;
            $asnData['name']           = $asn->name;
            $asnData['description']    = $asn->description;
            $asnData['country_code']   = empty($asn->counrty_code) !== true ? $asn->counrty_code : null;
            $asnData['email_contacts'] = $asn->email_contacts;
            $asnData['abuse_contacts'] = $asn->abuse_contacts;
            $asnData['rir_name']       = $asn->rir->name;

            $data['asns'][] = $asnData;
        }

        $data['ipv4_prefixes'] = [];
        foreach ($ipv4Prefixes as $prefix) {
            $prefixData['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixData['ip']             = $prefix->ip;
            $prefixData['cidr']           = $prefix->cidr;
            $prefixData['name']           = $prefix->name;
            $prefixData['country_code']   = empty($prefix->counrty_code) !== true ? $prefix->counrty_code : null;
            $prefixData['description']    = $prefix->description;
            $prefixData['email_contacts'] = $prefix->email_contacts;
            $prefixData['abuse_contacts'] = $prefix->abuse_contacts;
            $prefixData['rir_name']       = $prefix->rir->name;
            $prefixData['parent_prefix']  = $prefix->parent_ip . '/' . $prefix->parent_cidr;
            $prefixData['parent_ip']      = $prefix->parent_ip;
            $prefixData['parent_cidr']    = $prefix->parent_cidr;

            $data['ipv4_prefixes'][] = $prefixData;
        }

        $data['ipv6_prefixes'] = [];
        foreach ($ipv6Prefixes as $prefix) {
            $prefixData['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixData['ip']             = $prefix->ip;
            $prefixData['cidr']           = $prefix->cidr;
            $prefixData['name']           = $prefix->name;
            $prefixData['country_code']   = empty($prefix->counrty_code) !== true ? $prefix->counrty_code : null;
            $prefixData['description']    = $prefix->description;
            $prefixData['email_contacts'] = $prefix->email_contacts;
            $prefixData['abuse_contacts'] = $prefix->abuse_contacts;
            $prefixData['rir_name']       = $prefix->rir->name;
            $prefixData['parent_prefix']  = $prefix->parent_ip . '/' . $prefix->parent_cidr;
            $prefixData['parent_ip']      = $prefix->parent_ip;
            $prefixData['parent_cidr']    = $prefix->parent_cidr;

            $data['ipv6_prefixes'][] = $prefixData;
        }

        $data['internet_exchanges'] = [];
        foreach ($ixs as $ix) {
            $ixData['ix_id']        = $ix->id;
            $ixData['name']         = $ix->name;
            $ixData['name_full']    = $ix->name_full;
            $ixData['country_code'] = $ix->counrty_code;
            $ixData['city']         = $ix->city;

            $data['internet_exchanges'][] = $ixData;
        }

        return $this->sendData($data);
    }

    /*
     * URI: /dns/live/{hostname}
     *
     */
    public function getLiveDns($hostname)
    {
        $pslManager   = new PublicSuffixListManager();
        $domainParser = new Parser($pslManager->getList());

        $hostname   = strtolower($hostname);
        $baseDomain = $domainParser->getRegisterableDomain($hostname);
        $ipUtils    = $this->ipUtils;

        $records = Cache::remember($hostname, 60 * 24, function () use ($ipUtils, $hostname) {
            $dns     = new Dns(['8.8.8.8', '8.8.4.4', 2]);
            $records = $dns->getDomainRecords($hostname, $testNameserver = false);
            ksort($records);

            if (isset($records['A']) === true) {
                $records['A'] = array_unique($records['A']);
                foreach ($records['A'] as $key => $address) {
                    $geoip = $ipUtils->geoip($address);
                    if ($geoip->country->isoCode) {
                        $country_code = $geoip->country->isoCode;
                        $country_name = $geoip->country->name;
                        $city_name    = $geoip->city->name;
                    } else {
                        $ipDec  = $this->ipUtils->ip2dec($address);
                        $prefix = IPv4BgpPrefix::where('ip_dec_start', '<=', $ipDec)
                            ->where('ip_dec_end', '>=', $ipDec)
                            ->orderBy('cidr', 'asc')
                            ->first();
                        if ($prefix && $prefixWhois = $prefix->whois()) {
                            $country_code = $prefixWhois->counrty_code;
                            $country_name = $prefixWhois->counrty_code ? trans('countries.' . $prefixWhois->counrty_code) : null;
                            $city_name    = null;
                        } else {
                            $country_code = null;
                            $country_name = 'Unknown';
                            $city_name    = null;
                        }
                    }

                    $output['address']      = $address;
                    $output['country_code'] = $country_code;
                    if ($city_name) {
                        $output['location'] = $city_name . ', ' . $country_name;
                    } else {
                        $output['location'] = $country_name;
                    }

                    $records['A'][$key] = $output;
                }
            }

            if (isset($records['AAAA']) === true) {
                $records['AAAA'] = array_unique($records['AAAA']);
                foreach ($records['AAAA'] as $key => $address) {
                    $geoip = $ipUtils->geoip($address);
                    if ($geoip->country->isoCode) {
                        $country_code = $geoip->country->isoCode;
                        $country_name = $geoip->country->name;
                        $city_name    = $geoip->city->name;
                    } else {
                        $ipDec  = $this->ipUtils->ip2dec($address);
                        $prefix = IPv6BgpPrefix::where('ip_dec_start', '<=', $ipDec)
                            ->where('ip_dec_end', '>=', $ipDec)
                            ->orderBy('cidr', 'asc')
                            ->first();
                        if ($prefix && $prefixWhois = $prefix->whois()) {
                            $country_code = $prefixWhois->counrty_code;
                            $country_name = $prefixWhois->counrty_code ? trans('countries.' . $prefixWhois->counrty_code) : null;
                            $city_name    = null;
                        } else {
                            $country_code = null;
                            $country_name = 'Unknown';
                            $city_name    = null;
                        }
                    }

                    $output['address']      = $address;
                    $output['country_code'] = $country_code;
                    if ($city_name) {
                        $output['location'] = $city_name . ', ' . $country_name;
                    } else {
                        $output['location'] = $country_name;
                    }

                    $records['AAAA'][$key] = $output;
                }
            }

            return $records;
        });

        $data['hostname']    = $hostname;
        $data['base_domain'] = $baseDomain;
        $data['dns_records'] = $records;

        return $this->sendData($data);
    }

    /*
     * URI: /sitemap/asn
     *
     */
    public function sitemapUrls()
    {
        $data['urls'] = [];

        $asns = DB::table('asns')->pluck('asn');
        foreach ($asns as $asn) {
            $data['urls'][] = '/asn/' . $asn;
        }

        $ipv4Prefixes = DB::table('ipv4_prefix_whois')->select('ip', 'cidr')->get();
        foreach ($ipv4Prefixes as $prefix) {
            $data['urls'][] = '/prefix/' . $prefix->ip . '/' . $prefix->cidr;
        }

        $ipv6Prefixes = DB::table('ipv6_prefix_whois')->select('ip', 'cidr')->get();
        foreach ($ipv6Prefixes as $prefix) {
            $data['urls'][] = '/prefix/' . $prefix->ip . '/' . $prefix->cidr;
        }

        $ixs = DB::table('ixs')->pluck('id');
        foreach ($ixs as $ix) {
            $data['urls'][] = '/ix/' . $ix;
        }

        return $this->sendData($data);
    }

    /*
     * URI: /reports/countries
     */
    public function countriesReport()
    {
        $ipv4CidrCount  = $this->ipUtils->IPv4cidrIpCount();
        $countriesStats = [];

        $allocatedAsns     = $this->ipUtils->getAllocatedAsns();
        $allocatedPrefixes = $this->ipUtils->getAllocatedPrefixes();
        // Get all routes (BGP)

        // Group ASNs
        foreach ($allocatedAsns as $allocatedAsn) {
            if (isset($countriesStats[$allocatedAsn->country_code]) !== true && is_null($allocatedAsn->country_code) !== true) {
                $countriesStats[$allocatedAsn->country_code]['country_code']                = $allocatedAsn->country_code;
                $countriesStats[$allocatedAsn->country_code]['allocated_asn_count']         = 0;
                $countriesStats[$allocatedAsn->country_code]['allocated_ipv4_prefix_count'] = 0;
                $countriesStats[$allocatedAsn->country_code]['allocated_ipv6_prefix_count'] = 0;
                $countriesStats[$allocatedAsn->country_code]['allocated_ipv4_ip_count']     = 0;
            }

            if (is_null($allocatedAsn->country_code) !== true) {
                $countriesStats[$allocatedAsn->country_code]['allocated_asn_count'] += 1;
            }
        }

        foreach ($allocatedPrefixes as $allocatedPrefix) {
            if (isset($countriesStats[$allocatedPrefix->country_code]) !== true && is_null($allocatedPrefix->country_code) !== true) {
                $countriesStats[$allocatedPrefix->country_code]['country_code']                = $allocatedPrefix->country_code;
                $countriesStats[$allocatedPrefix->country_code]['allocated_asn_count']         = 0;
                $countriesStats[$allocatedPrefix->country_code]['allocated_ipv4_prefix_count'] = 0;
                $countriesStats[$allocatedPrefix->country_code]['allocated_ipv6_prefix_count'] = 0;
                $countriesStats[$allocatedPrefix->country_code]['allocated_ipv4_ip_count']     = 0;
            }

            if (is_null($allocatedPrefix->country_code) !== true) {
                $countriesStats[$allocatedPrefix->country_code]['allocated_ipv' . $allocatedPrefix->ip_version . '_prefix_count'] += 1;
            }

            if ($allocatedPrefix->ip_version == 4 && isset($ipv4CidrCount[$allocatedPrefix->cidr]) === true) {
                $countriesStats[$allocatedPrefix->country_code]['allocated_ipv4_ip_count'] += $ipv4CidrCount[$allocatedPrefix->cidr];
            }
        }

        $countriesStats = collect(array_values($countriesStats));
        return $this->sendData($countriesStats);
    }

    /*
     * URI: /reports/countries/{country_code}
     */
    public function countryReport($country_code)
    {
        $asnData       = [];
        $allocatedAsns = $this->ipUtils->getAllocatedAsns($country_code);

        // Sort out the ASN keys
        foreach ($allocatedAsns as $allocatedAsn) {
            $asnData[$allocatedAsn->asn] = [
                'asn'            => $allocatedAsn->asn,
                'name'           => null,
                'description'    => null,
                'date_allocated' => $allocatedAsn->date_allocated,

                // Setting the default stats
                'ipv4_prefixes'  => 0,
                'ipv6_prefixes'  => 0,
                'ipv4_peers'     => 0,
                'ipv6_peers'     => 0,

            ];
        }

        $asnArray = array_keys($asnData);

        $asnMetas = ASN::whereIn('asn', $asnArray)->get();
        foreach ($asnMetas as $asnMeta) {
            $asnData[$asnMeta->asn]['name']        = $asnMeta->name;
            $asnData[$asnMeta->asn]['description'] = $asnMeta->description;
        }

        $ipv4Prefixes = IPv4BgpPrefix::select(DB::raw('asn, COUNT(asn) as count'))->whereIn('asn', $asnArray)->groupBy('asn')->get();
        foreach ($ipv4Prefixes as $ipv4Prefix) {
            $asnData[$ipv4Prefix->asn]['ipv4_prefixes'] = $ipv4Prefix->count;
        }

        $ipv6Prefixes = IPv6BgpPrefix::select(DB::raw('asn, COUNT(asn) as count'))->whereIn('asn', $asnArray)->groupBy('asn')->get();
        foreach ($ipv6Prefixes as $ipv6Prefix) {
            $asnData[$ipv6Prefix->asn]['ipv6_prefixes'] = $ipv6Prefix->count;
        }

        $seenIpv4Peers = [];
        $ipv4Peers     = IPv4Peer::select(DB::raw('asn_1 as asn, asn_2 as peer, COUNT(asn_1) as count'))->whereIn('asn_1', $asnArray)->groupBy('asn_1')->get();
        foreach ($ipv4Peers as $ipv4Peer) {
            if (isset($seenIpv4Peers[$ipv4Peer->asn][$ipv4Peer->peer]) === true) {
                continue;
            }

            $asnData[$ipv4Peer->asn]['ipv4_peers'] += $ipv4Peer->count;
            $seenIpv4Peers[$ipv4Peer->asn][$ipv4Peer->peer] = true;
        }
        $ipv4Peers = IPv4Peer::select(DB::raw('asn_2 as asn, asn_1 as peer, COUNT(asn_2) as count'))->whereIn('asn_2', $asnArray)->groupBy('asn_2')->get();
        foreach ($ipv4Peers as $ipv4Peer) {
            if (isset($seenIpv4Peers[$ipv4Peer->asn][$ipv4Peer->peer]) === true) {
                continue;
            }

            $asnData[$ipv4Peer->asn]['ipv4_peers'] += $ipv4Peer->count;
            $seenIpv4Peers[$ipv4Peer->asn][$ipv4Peer->peer] = true;
        }

        $seenIpv6Peers = [];
        $ipv6Peers     = IPv6Peer::select(DB::raw('asn_1 as asn, asn_2 as peer, COUNT(asn_1) as count'))->whereIn('asn_1', $asnArray)->groupBy('asn_1')->get();
        foreach ($ipv6Peers as $ipv6Peer) {
            if (isset($seenIpv4Peers[$ipv6Peer->asn][$ipv6Peer->peer]) === true) {
                continue;
            }

            $asnData[$ipv6Peer->asn]['ipv6_peers'] += $ipv6Peer->count;
        }
        $ipv6Peers = IPv6Peer::select(DB::raw('asn_2 as asn, asn_1 as peer, COUNT(asn_2) as count'))->whereIn('asn_2', $asnArray)->groupBy('asn_2')->get();
        foreach ($ipv6Peers as $ipv6Peer) {
            if (isset($seenIpv6Peers[$ipv6Peer->asn][$ipv6Peer->peer]) === true) {
                continue;
            }

            $asnData[$ipv6Peer->asn]['ipv6_peers'] += $ipv6Peer->count;
        }

        return $this->sendData(array_values($asnData));
    }

    /*
     * URI: /as-summery
     *
     */
    public function asnSummery()
    {
        $asns = DB::table('asns')->select(array('asn', 'name', 'description_full', 'counrty_code'))->get();

        $data['results_count'] = count($asns);
        $data['asns']          = [];

        foreach ($asns as $asn) {
            $description = json_decode($asn->description_full);

            // CLean up the description
            if ($asn->description_full == '[null]' || empty($description) === true) {
                $description = [];
            }

            $data['asns'][] = [
                'asn'          => $asn->asn,
                'name'         => $asn->name,
                'description'  => $description,
                'country_code' => $asn->counrty_code,
            ];
        }

        return $this->sendData($data);
    }

    public function getContacts($resource, $cidr = null)
    {
        $data = $this->ipUtils->getContacts($resource);

        return $this->sendData($data);
    }
}
