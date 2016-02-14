<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IXMember extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ix_members';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'ix_peeringdb_id', 'ipv4_dec', 'ipv6_dec', 'created_at', 'updated_at'];

    public function ix()
    {
        return $this->belongsTo('App\Models\IX', 'ix_peeringdb_id', 'peeringdb_id');
    }
}
