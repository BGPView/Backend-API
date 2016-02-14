<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IXEmail extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ix_emails';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'ix_id', 'created_at', 'updated_at'];


    public function asn()
    {
        return $this->belongsTo('App\Models\IX');
    }
}
