<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Illuminate\Database\Eloquent\Model;

class IPv4BgpPrefix extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv4_bgp_prefixes';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'created_at', 'updated_at'];

    public function getWhoisAttribute()
    {
        return $this->whois();
    }

    public function whois()
    {
        if (isset($this->attributes['whois']) !== true) {
            $this->attributes['whois'] = IPv4PrefixWhois::where('ip', $this->ip)->where('cidr', $this->cidr)->first();
        }

        return $this->attributes['whois'];
    }

    public function getAllocationAttribute()
    {
        return $this->allocation();
    }

    public function allocation()
    {
        if (isset($this->attributes['allocation']) !== true) {
            $ipUtils = new IpUtils();
            $this->attributes['allocation'] = $ipUtils->getAllocationEntry($this->ip, $this->cidr);
        }

        return $this->attributes['allocation'];
    }

    public function asn_info()
    {
        return $this->belongsTo('App\Models\ASN', 'asn', 'asn');
    }
}
