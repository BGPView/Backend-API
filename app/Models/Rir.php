<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rir extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'rirs';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'created_at', 'updated_at'];

    public function asnAllocations()
    {
        return $this->hasMany('App\Models\RirAsnAllocation');
    }

    public function ipv4Allocations()
    {
        return $this->hasMany('App\Models\RirIPv4Allocation');
    }

    public function ipv6Allocations()
    {
        return $this->hasMany('App\Models\RirIPv6Allocation');
    }
}
