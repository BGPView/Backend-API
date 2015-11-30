<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPv6PrefixWhoisEmail extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv6_prefix_whois_emails';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'prefix_whois_id', 'created_at', 'updated_at'];


    public function prefixWhois()
    {
        return $this->belongsTo('App\Models\IPv6PrefixWhois', 'id', 'prefix_whois_id');
    }
}
