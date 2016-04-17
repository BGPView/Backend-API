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

    public function whois()
    {
        if (isset($this->attributes['whois']) !== true) {
            $this->attributes['whois'] = IPv6PrefixWhois::where('ip', $this->ip)->where('cidr', $this->cidr)->first();
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

    public function getRoaStatusAttribute($value)
    {
        if ($value == 1) {
            return 'Valid';
        } elseif ($value == -1) {
            return 'Invalid';
        } else {
            return 'None';
        }
    }

    public function asn()
    {
        return $this->belongsTo('App\Models\ASN', 'asn', 'asn');
    }
}
