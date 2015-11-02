<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPv6Prefix extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv6_prefixes';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'rir_id', 'raw_whois', 'created_at', 'updated_at'];


    public function emails()
    {
        return $this->hasMany('App\Models\IPv6PrefixEmail', 'ipv6_prefix_id', 'id');
    }

    public function rir()
    {
        return $this->belongsTo('App\Models\Rir');
    }
}
