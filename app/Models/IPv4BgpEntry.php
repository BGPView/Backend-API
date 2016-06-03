<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPv4BgpEntry extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv4_bgp_table';

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
}
