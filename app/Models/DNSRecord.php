<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model as Moloquent;

class DNSRecord extends Moloquent {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'dns_records';
    protected $collection = 'dns_records';
    protected $connection = 'mongodb';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'created_at', 'updated_at'];


}
