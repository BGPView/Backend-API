<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Illuminate\Database\Eloquent\Model;

class ASN extends Model {

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
        return json_decode($value);
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
        $output['ipv4_peers'] = [];
        $output['ipv6_peers'] = [];

        foreach ($peerSet as $ipVersion => $peers) {
            foreach ($peers as $peer) {
                if ($peer->asn_1 == $as_number && $peer->asn_2 == $as_number) {
                    continue;
                }

                $peerAsn = $peer->asn_1 == $as_number ? $peer->asn_2 : $peer->asn_1;
                $asn = self::where('asn', $peerAsn)->first();

                $peerAsnInfo['asn']             = $peerAsn;
                $peerAsnInfo['name']            = is_null($asn) ? null : $asn->name;
                $peerAsnInfo['description']     = is_null($asn) ? null : $asn->description;
                $peerAsnInfo['country_code']    = is_null($asn) ? null : $asn->counrty_code;

                $output[$ipVersion][] = $peerAsnInfo;
            }
        }

        return $output;
    }

    public static function getPrefixes($as_number)
    {
        $prefixes = (new IpUtils())->getBgpPrefixes($as_number);

        $rirNames = [];
        foreach (RIR::all() as $rir) {
            $rirNames[$rir->id] = $rir->name;
        }

        $output['ipv4_prefixes'] = [];
        foreach ($prefixes['ipv4'] as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix']         = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']             = $prefix->ip;
            $prefixOutput['cidr']           = $prefix->cidr;

            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $prefixOutput['parent']['prefix']   = isset($prefixWhois->parent_ip) && isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_ip . '/' . $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['ip']       = isset($prefixWhois->parent_ip) ? $prefixWhois->parent_ip : null;
            $prefixOutput['parent']['cidr']     = isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['rir_name'] = isset($prefixWhois->rir_id) ? $rirNames[$prefixWhois->rir_id] : null;

            $output['ipv4_prefixes'][]  = $prefixOutput;
            $prefixOutput = null;
            $prefixWhois = null;
        }

        $output['ipv6_prefixes'] = [];
        foreach ($prefixes['ipv6'] as $prefix) {
            $prefixWhois = $prefix->whois;

            $prefixOutput['prefix'] = $prefix->ip . '/' . $prefix->cidr;
            $prefixOutput['ip']     = $prefix->ip;
            $prefixOutput['cidr']   = $prefix->cidr;

            $prefixOutput['name']           = isset($prefixWhois->name) ? $prefixWhois->name : null;
            $prefixOutput['description']    = isset($prefixWhois->description) ? $prefixWhois->description : null;
            $prefixOutput['country_code']   = isset($prefixWhois->counrty_code) ? $prefixWhois->counrty_code : null;

            $prefixOutput['parent']['prefix']   = isset($prefixWhois->parent_ip) && isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_ip . '/' . $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['ip']       = isset($prefixWhois->parent_ip) ? $prefixWhois->parent_ip : null;
            $prefixOutput['parent']['cidr']     = isset($prefixWhois->parent_cidr) ? $prefixWhois->parent_cidr : null;
            $prefixOutput['parent']['rir_name'] = isset($prefixWhois->rir_id) ? $rirNames[$prefixWhois->rir_id] : null;

            $output['ipv6_prefixes'][]  = $prefixOutput;
            $prefixOutput = null;
            $prefixWhois = null;
        }

        return $output;
    }

    public static function getUpstreams($as_number)
    {
        $ipv4Upstreams = IPv4BgpEntry::where('asn', $as_number)->get();
        $ipv6Upstreams = IPv6BgpEntry::where('asn', $as_number)->get();

        $output['ipv4_upstreams'] = [];
        foreach ($ipv4Upstreams as $upstream) {

            if (isset($output['ipv4_upstreams'][$upstream->upstream_asn]) === true) {
                if (in_array($upstream->bgp_path, $output['ipv4_upstreams'][$upstream->upstream_asn]['bgp_paths']) === false) {
                    $output['ipv4_upstreams'][$upstream->upstream_asn]['bgp_paths'][] = $upstream->bgp_path;
                }
                continue;
            }

            $upstreamAsn = self::where('asn', $upstream->upstream_asn)->first();

            $upstreamOutput['asn']          = $upstream->upstream_asn;
            $upstreamOutput['name']         = isset($upstreamAsn->name) ? $upstreamAsn->name : null;
            $upstreamOutput['description']  = isset($upstreamAsn->description) ? $upstreamAsn->description : null;
            $upstreamOutput['bgp_paths'][]  = $upstream->bgp_path;

            $output['ipv4_upstreams'][$upstream->upstream_asn]  = $upstreamOutput;
            $upstreamOutput = null;
            $upstreamAsn = null;
        }

        $output['ipv6_upstreams'] = [];
        foreach ($ipv6Upstreams as $upstream) {

            if (isset($output['ipv6_upstreams'][$upstream->upstream_asn]) === true) {
                if (in_array($upstream->bgp_path, $output['ipv6_upstreams'][$upstream->upstream_asn]['bgp_paths']) === false) {
                    $output['ipv6_upstreams'][$upstream->upstream_asn]['bgp_paths'][] = $upstream->bgp_path;
                }
                continue;
            }

            $upstreamAsn = self::where('asn', $upstream->upstream_asn)->first();

            $upstreamOutput['asn']          = $upstream->upstream_asn;
            $upstreamOutput['name']         = isset($upstreamAsn->name) ? $upstreamAsn->name : null;
            $upstreamOutput['description']  = isset($upstreamAsn->description) ? $upstreamAsn->description : null;
            $upstreamOutput['bgp_paths'][]  = $upstream->bgp_path;

            $output['ipv6_upstreams'][$upstream->upstream_asn]  = $upstreamOutput;
            $upstreamOutput = null;
            $upstreamAsn = null;
        }

        $output['ipv4_upstreams'] = array_values($output['ipv4_upstreams']);
        $output['ipv6_upstreams'] = array_values($output['ipv6_upstreams']);

        return $output;
    }
}
