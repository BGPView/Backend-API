<?php

namespace App\Models;

use App\Helpers\IpUtils;
use App\Services\Domains;
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
            'type'     => 'text',
            'analyzer' => 'string_lowercase',
            'fielddata' => true,
        ],
        'description' => [
            'type'     => 'text',
            'analyzer' => 'string_lowercase',
            'fielddata' => true,
        ],
        'asn'         => [
            'type'   => 'text',
            'fields' => [
                'sort' => ['type' => 'long'],
            ],
            'fielddata' => true,
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

    public static function getPeers($as_number)
    {
        $ipUtils               = new IpUtils();
        $peerSet['ipv4_peers'] = IPv4Peer::where('asn_1', $as_number)->orWhere('asn_2', $as_number)->get();
        $peerSet['ipv6_peers'] = IPv6Peer::where('asn_1', $as_number)->orWhere('asn_2', $as_number)->get();
        $filteredAsnList['ipv4_peers']  = [];
        $filteredAsnList['ipv6_peers']  = [];
        $output['ipv4_peers']  = [];
        $output['ipv6_peers']  = [];

        // Combine
        foreach ($peerSet as $ipVersion => $peers) {
            foreach ($peers as $peer) {
                if ($peer->asn_1 == $as_number && $peer->asn_2 == $as_number) {
                    continue;
                }

                $peerAsn = $peer->asn_1 == $as_number ? $peer->asn_2 : $peer->asn_1;

                $filteredAsnList[$ipVersion][$peerAsn] = true;
            }
        }

        // Get all ASN Details
        $asnList = array_unique(array_merge(array_keys($filteredAsnList['ipv4_peers']), array_keys($filteredAsnList['ipv6_peers'])));
        $asnListDetails = self::whereIn('asn', $asnList)->get()->keyBy('asn');

        foreach ($filteredAsnList as $ipVersion => $peers) {
            foreach ($peers as $peerAsn => $placeholder) {

                if (isset($asnListDetails[$peerAsn]) === true) {
                    $asn = $asnListDetails[$peerAsn];
                } else {
                    $assignment = $ipUtils->getIanaAssignmentEntry($peerAsn);
                }

                $peerAsnInfo['asn']          = $peerAsn;
                $peerAsnInfo['name']         = empty($asn) ? 'IANA-' . strtoupper($assignment->status) : $asn->name;
                $peerAsnInfo['description']  = empty($asn) ? $assignment->description : $asn->description;
                $peerAsnInfo['country_code'] = empty($asn) ? null : $asn->counrty_code;

                $output[$ipVersion][] = $peerAsnInfo;
            }
        }

        return $output;
    }
    public static function getDomains($as_number, $prefixes = null)
    {
        if ($prefixes === null) {
            $prefixes = self::getPrefixes($as_number);
        }

        $domains = new Domains($prefixes);
        return $domains->get();
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

    public static function getDownstreams($as_number, $asnMeta = true)
    {
        return self::getStreams($as_number, 'downstreams', $asnMeta);
    }

    private static function getStreams($as_number, $direction = 'upstreams', $asnMeta)
    {
        if ($direction == 'upstreams') {
            $searchKey = 'asn';
            $oderKey   = 'upstream_asn';
            $aggBy     = 'upstream_asn';
        } else {
            $searchKey = 'upstream_asn';
            $oderKey   = 'asn';
            $aggBy     = 'asn';
        }

        $client  = ClientBuilder::create()->setHosts(config('elasticquent.config.hosts'))->build();
        $ipUtils = new IpUtils();

        $params = [
            'index'       => 'bgp_data',
            'type'        => 'full_table',
            'body'        => [
                'size'  => 0,
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
                'aggs' => [
                    'unique_asn' => [
                        'terms' => [
                            'field' => $aggBy,
                            'size' => 1000000000,
                            'show_term_doc_count_error' => true,
                        ],
                        'aggs' => [
                            'unique_ip_version' => [
                                'terms' => [
                                    'size' => 1000000000,
                                    'field' => 'ip_version',
                                    'show_term_doc_count_error' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];


        $docs      = $client->search($params);

        // Loop through all ASNs and sort them out into places
        $asnList = [];
        $filteredList['ipv4_' . $direction] = [];
        $filteredList['ipv6_' . $direction] = [];
        $output['ipv4_' . $direction] = [];
        $output['ipv6_' . $direction] = [];

        foreach ($docs['aggregations']['unique_asn']['buckets'] as $agg) {
            $asnList[] = $agg['key'];
            foreach ($agg['unique_ip_version']['buckets'] as $subAgg) {
                $filteredList['ipv'.$subAgg['key'].'_' . $direction][] = $agg['key'];
            }
        }

        // Get the meta data only if needed
        if ($asnMeta === true) {
            $asnListDetails = self::whereIn('asn', $asnList)->get()->keyBy('asn');

            foreach ($filteredList as $ipVersion => $peerAsns) {
                foreach ($peerAsns as $peerAsn) {
                    $peerAsnInfo['asn'] = $peerAsn;

                    if (isset($asnListDetails[$peerAsn]) === true) {
                        $asn = $asnListDetails[$peerAsn];
                        $peerAsnInfo['name'] = $asn->name;
                        $peerAsnInfo['description'] = $asn->description;
                        $peerAsnInfo['country_code'] = $asn->counrty_code;
                    } else {
                        $assignment = $ipUtils->getIanaAssignmentEntry($peerAsn);
                        $peerAsnInfo['name'] = 'IANA-' . strtoupper($assignment->status);
                        $peerAsnInfo['description'] = $assignment->description;
                        $peerAsnInfo['country_code'] = null;
                    }

                    $output[$ipVersion][] = $peerAsnInfo;
                }
            }

            // Get Graph images
            if ($direction === 'upstreams') {
                $imagePathv4 = '/assets/graphs/' . 'AS' . $as_number . '_IPv4.svg';
                $imagePathv6 = '/assets/graphs/' . 'AS' . $as_number . '_IPv6.svg';
                $imageCombinedPath = '/assets/graphs/' . 'AS' . $as_number . '_Combined.svg';

                if (file_exists(public_path() . $imagePathv4) === true) {
                    $output['ipv4_graph'] = config('app.url') . $imagePathv4;
                } else {
                    $output['ipv4_graph'] = null;
                }

                if (file_exists(public_path() . $imagePathv6) === true) {
                    $output['ipv6_graph'] = config('app.url') . $imagePathv6;
                } else {
                    $output['ipv6_graph'] = null;
                }

                if (file_exists(public_path() . $imageCombinedPath) === true) {
                    $output['combined_graph'] = config('app.url') . $imageCombinedPath;
                } else {
                    $output['combined_graph'] = null;
                }
            }
        }

        return $output;
    }

    public static function getUpstreams($as_number, $asnMeta = true)
    {
        return self::getStreams($as_number, 'upstreams', $asnMeta);
    }

    public function getIndexName()
    {
        if (substr_count(config('elasticquent.default_index'), '_') > 1) {
            return config('elasticquent.default_index');
        }

        return config('elasticquent.default_index'). '_asn';
    }

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
        if (empty($value) === true) {
            return [];
        }

        if (is_string($value) !== true) {
            return $value;
        }

        return json_decode($value);
    }

    public function getDescriptionAttribute()
    {
        $descriptionLines = $this->description_full;
        if (empty($descriptionLines) !== true) {
            foreach ($descriptionLines as $descriptionLine) {
                if (preg_match("/[A-Za-z0-9]/i", $descriptionLine)) {
                    return $descriptionLine;
                }
            }
        }

        return $this->name;
    }

    public function getOwnerAddressAttribute($value)
    {
        if (empty($value) === true) {
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
            $email_contacts[] = isset($email->email_address) ? $email->email_address : $email['email_address'];
        }
        return $email_contacts;
    }

    public function getAbuseContactsAttribute()
    {
        $abuse_contacts = [];
        foreach ($this->emails as $email) {
            if ((isset($email->abuse_email) && $email->abuse_email) || (isset($email['abuse_email']) && $email['abuse_email'])) {
                $abuse_contacts[] = isset($email->email_address) ? $email->email_address : $email['email_address'];
            }
        }
        return $abuse_contacts;
    }

}
