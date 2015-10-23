<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RirAsnAllocation extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'rir_asn_allocations';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'rir_id', 'created_at', 'updated_at'];

    protected $fillable = ['rir_id', 'asn', 'counrty_code', 'date_allocated'];
}
