<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Elasticquent\ElasticquentTrait;
use Elasticsearch\ClientBuilder;
use Illuminate\Database\Eloquent\Model;

class ASN extends Model
{

    use ElasticquentTrait;

    /**
     * The elasticsearch settings.
     *
     * @var array
     */
    protected $indexSettings = [
        'analysis' => [
            'analyzer' => [
                'string_lowercase' => [
                    'tokenizer' => 'keyword',
                    'filter'    => ['asciifolding', 'lowercase', 'custom_replace'],
                ],
            ],
            'filter'   => [
                'custom_replace' => [
                    'type'        => 'pattern_replace',
                    'pattern'     => "[^a-z0-9 ]",
                    'replacement' => "",
                ],
            ],
        ],
    ];

    /**
     * The elasticsearch mappings.
     *
     * @var array
     */
    protected $mappingProperties = [
        'name'        => [
            'type'     => 'string',
            'analyzer' => 'string_lowercase',
        ],
        'description' => [
            'type'     => 'string',
            'analyzer' => 'string_lowercase',
        ],
        'asn'         => [
            'type'   => 'string',
            'fields' => [
                'sort' => ['type' => 'long'],
            ],
        ],
    ];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'asns';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'rir_id', 'raw_whois', 'created_at', 'updated_at'];

    public function emails()
    {
        return $this->hasMany('App\Models\ASNEmail', 'asn_id', 'id');
    }

    public function rir()
    {
        return $this->belongsTo('App\Models\Rir');
    }

    public function ipv4_prefixes()
    {
        return $this->hasMany('App\Models\IPv4BgpPrefix', 'asn', 'asn');
    }

    public function ipv6_prefixes()
    {
        return $this->hasMany('App\Models\IPv6BgpPrefix', 'asn', 'asn');
    }

    public function getDescriptionFullAttribute($value)
    {
        return json_decode($value);
    }

    public function getOwnerAddressAttribute($value)
    {
        if (is_null($value) === true) {
            return null;
        }

        $data         = json_decode($value);
        $addressLines = [];

        if (is_object($data) !== true && is_array($data) !== true) {
            return $addressLines;
        }

        foreach ($data as $entry) {
            // Remove/Clean all double commas
            $entry        = preg_replace('/,+/', ',', $entry);
            $addressArr   = explode(',', $entry);
            $addressLines = array_merge($addressLines, $addressArr);
        }

        return array_map('trim', $addressLines);
    }

    public function getRawWhoisAttribute($value)
    {
        // Remove the "source" entry
        $parts = explode("\n", $value);
        unset($parts[0]);
        return implode($parts, "\n");
    }

    public function getEmailContactsAttribute()
    {
        $email_contacts = [];
        foreach ($this->emails as $email) {
            $email_contacts[] = $email->email_address;
        }
        return $email_contacts;
    }

    public function getAbuseContactsAttribute()
    {
        $abuse_contacts = [];
        foreach ($this->emails as $email) {
            if ($email->abuse_email) {
                $abuse_contacts[] = $email->email_address;
            }
        }
        return $abuse_contacts;
    }

    public static function getPeers($as_number)
    {
        $peerSet['ipv4_peers'] = IPv4Peer::where('asn_1', $as_number)->orWhere('asn_2', $as_number)->get();
        $peerSet['ipv6_peers'] = IPv6Peer::where('asn_1', $as_number)->orWhere('asn_2', $as_number)->get();
        $output['ipv4_peers']  = [];
        $output['ipv6_peers']  = [];

        foreach ($peerSet as $ipVersion => $peers) {
            foreach ($peers as $peer) {
                if ($peer->asn_1 == $as_number && $peer->asn_2 == $as_number) {
                    continue;
                }

                $peerAsn = $peer->asn_1 == $as_number ? $peer->asn_2 : $peer->asn_1;
                $asn     = self::where('asn', $peerAsn)->first();

                $peerAsnInfo['asn']          = $peerAsn;
                $peerAsnInfo['name']         = is_null($asn) ? null : $asn->name;
                $peerAsnInfo['description']  = is_null($asn) ? null : $asn->description;
                $peerAsnInfo['country_code'] = is_null($asn) ? null : $asn->counrty_code;

                $output[$ipVersion][] = $peerAsnInfo;
            }
        }

        return $output;
    }

    public static function getPrefixes($as_number)
    {
        $prefixes = (new IpUtils())->getBgpPrefixes($as_number);

        $rirNames = [];
        foreach (Rir::all() as $rir) {
            $rirNames[$rir->id] = $rir->name;
        }

        $output['ipv4_prefixes'] = [];
        foreach ($prefixes['ipv4'] as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']     = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']         = $prefix->ip;
            $prefixOutput['cidr']       = $prefix->cidr;
            $prefixOutput['roa_status'] = $prefix->roa_status;

            $prefixOutput['name']         = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']  = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code'] = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $prefixOutput['parent']['prefix']            = empty($prefixWhois->parent_ip) !== true && isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_ip . '/' . $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['ip']                = empty($prefixWhois->parent_ip) !== true ? $prefixWhois->parent_ip : null;
            $prefixOutput['parent']['cidr']              = empty($prefixWhois->parent_cidr) !== true ? $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['rir_name']          = empty($prefixWhois->rir_id) !== true ? $rirNames[$prefixWhois->rir_id] : null;
            $prefixOutput['parent']['allocation_status'] = empty($prefixWhois->status) !== true ? $prefixWhois->status : 'unknown';

            $output['ipv4_prefixes'][] = $prefixOutput;
            $prefixOutput              = null;
            $prefixWhois               = null;
        }

        $output['ipv6_prefixes'] = [];
        foreach ($prefixes['ipv6'] as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']     = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']         = $prefix->ip;
            $prefixOutput['cidr']       = $prefix->cidr;
            $prefixOutput['roa_status'] = $prefix->roa_status;

            $prefixOutput['name']         = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']  = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code'] = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $prefixOutput['parent']['prefix']            = empty($prefixWhois->parent_ip) !== true && isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_ip . '/' . $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['ip']                = empty($prefixWhois->parent_ip) !== true ? $prefixWhois->parent_ip : null;
            $prefixOutput['parent']['cidr']              = empty($prefixWhois->parent_cidr) !== true ? $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['rir_name']          = empty($prefixWhois->rir_id) !== true ? $rirNames[$prefixWhois->rir_id] : null;
            $prefixOutput['parent']['allocation_status'] = empty($prefixWhois->status) !== true ? $prefixWhois->status : 'unknown';

            $output['ipv6_prefixes'][] = $prefixOutput;
            $prefixOutput              = null;
            $prefixWhois               = null;
        }

        return $output;
    }

    private static function getStreams($as_number, $direction = 'upstreams', $asnMeta)
    {
        if ($direction == 'upstreams') {
            $searchKey = 'asn';
            $oderKey   = 'upstream_asn';
        } else {
            $searchKey = 'upstream_asn';
            $oderKey   = 'asn';
        }

        $client  = ClientBuilder::create()->setHosts(config('elasticquent.config.hosts'))->build();
        $ipUtils = new IpUtils();

        $params = [
            'search_type' => 'scan',
            'scroll'      => '30s',
            'size'        => 10000,
            'index'       => 'bgp_data',
            'type'        => 'full_table',
            'body'        => [
                'sort'  => [
                    $oderKey => [
                        'order' => 'asc',
                    ],
                ],
                'query' => [
                    'match' => [
                        $searchKey => $as_number,
                    ],
                ],
            ],
        ];

        $docs      = $client->search($params);
        $scroll_id = $docs['_scroll_id'];

        $steams = [];
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

        $output['ipv4_' . $direction] = [];
        $output['ipv6_' . $direction] = [];
        foreach ($steams as $steam) {

            if (isset($output['ipv' . $steam->ip_version . '_' . $direction][$steam->$oderKey]) === true) {
                if (in_array($steam->bgp_path, $output['ipv' . $steam->ip_version . '_' . $direction][$steam->$oderKey]['bgp_paths']) === false) {
                    $output['ipv' . $steam->ip_version . '_' . $direction][$steam->$oderKey]['bgp_paths'][] = $steam->bgp_path;
                }
                continue;
            }

            $asnOutput['asn']          = $steam->$oderKey;

            if ($asnMeta === true) {
                $asnData = self::where('asn', $steam->$oderKey)->first();
                $asnOutput['name']         = isset($asnData->name) ? $asnData->name : null;
                $asnOutput['description']  = isset($asnData->description) ? $asnData->description : null;
                $asnOutput['country_code'] = isset($asnData->counrty_code) ? $asnData->counrty_code : null;
            }

            $asnOutput['bgp_paths'][]  = $steam->bgp_path;

            $output['ipv' . $steam->ip_version . '_' . $direction][$steam->$oderKey] = $asnOutput;
            $asnOutput                                                               = null;
            $upstreamAsn                                                             = null;
        }

        $output['ipv4_' . $direction] = array_values($output['ipv4_' . $direction]);
        $output['ipv6_' . $direction] = array_values($output['ipv6_' . $direction]);

        return $output;
    }

    public static function getDownstreams($as_number, $asnMeta = true)
    {
        return self::getStreams($as_number, 'downstreams', $asnMeta);
    }

    public static function getUpstreams($as_number, $asnMeta = true)
    {
        return self::getStreams($as_number, 'upstreams', $asnMeta);
    }

}
