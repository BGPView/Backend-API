<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RirIPv4Allocation extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'rir_ipv4_allocations';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'rir_id', 'created_at', 'updated_at'];

    protected $fillable = ['rir_id', 'ip', 'cidr', 'counrty_code', 'date_allocated'];
}
