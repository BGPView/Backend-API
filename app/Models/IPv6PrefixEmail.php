<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IPv6PrefixEmail extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv6_prefix_emails';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'ipv6_prefix_id', 'created_at', 'updated_at'];


    public function prefix()
    {
        return $this->belongsTo('App\Models\IPv6Prefix');
    }
}
