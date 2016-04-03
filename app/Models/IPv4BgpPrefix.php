<?php

namespace App\Models;

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
        return IPv4PrefixWhois::where('ip', $this->ip)->where('cidr', $this->cidr)->first();
    }

    public function getAllocationAttribute()
    {
        return $this->allocation();
    }

    public function allocation()
    {
        $ipUtils = new IpUtils();
        return $ipUtils->getAllocationEntry($this->ip, $this->cidr);
    }

    public function asn_info()
    {
        return $this->belongsTo('App\Models\ASN', 'asn', 'asn');
    }
}
