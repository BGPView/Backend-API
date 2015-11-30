<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPv4PrefixWhois extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv4_prefix_whois';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'bgp_prefix_id', 'raw_whois', 'created_at', 'updated_at'];


    public function emails()
    {
        return $this->hasMany('App\Models\IPv4PrefixWhoisEmail', 'prefix_whois_id', 'id');
    }

    public function bgpPrefix()
    {
        return $this->belongsTo('App\Models\IPv4BgpPrefix', 'bgp_prefix_id', 'id');
    }
}
