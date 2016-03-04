<?php

namespace App\Models;

use App\Helpers\IpUtils;
use Illuminate\Database\Eloquent\Model;

class IanaAssignment extends Model {

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'iana_assignments';
}
