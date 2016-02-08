<?php

namespace App\Models;

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
}
