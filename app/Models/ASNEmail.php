<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ASNEmail extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'asn_emails';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'asn_id', 'created_at', 'updated_at'];


    public function asn()
    {
        return $this->belongsTo('App\Models\ASN');
    }
}
