<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Illuminate\Database\Eloquent\Model;

class IPv6BgpPrefix extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv6_bgp_prefixes';

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

    public function setWhoisAttribute($value)
    {
        $this->attributes['whois'] = $value;
    }

    public function whois()
    {
        if (isset($this->whois) !== true) {
            $this->whois = IPv6PrefixWhois::where('ip', $this->ip)->where('cidr', $this->cidr)->first();
        }

        return $this->whois;
    }

    public function getAllocationAttribute()
    {
        return $this->allocation();
    }

    public function setAllocationAttribute($value)
    {
        $this->attributes['allocation'] = $value;
    }

    public function allocation()
    {
        if (isset($this->allocation) !== true) {
            $ipUtils = new IpUtils();
            $this->allocation = $ipUtils->getAllocationEntry($this->ip, $this->cidr);
        }

        return $this->allocation;
    }

    public function asn()
    {
        return $this->belongsTo('App\Models\ASN', 'asn', 'asn');
    }
}
