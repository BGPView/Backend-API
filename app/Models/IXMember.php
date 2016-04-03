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

    public function asn_info()
    {
        return $this->belongsTo('App\Models\ASN', 'asn', 'asn');
    }

    public static function getMembers($asn)
    {
        $ixs = [];
        foreach (self::where('asn', $asn)->get() as $ixMember) {
            $ixInfo = $ixMember->ix;

            if (is_null($ixInfo) === true) {
                continue;
            }

            $ix_data['ix_id']           = $ixInfo->id;
            $ix_data['name']            = $ixInfo->name;
            $ix_data['name_full']       = $ixInfo->name_full;
            $ix_data['country_code']    = $ixInfo->counrty_code;
            $ix_data['ipv4_address']    = $ixMember->ipv4_address;
            $ix_data['ipv6_address']    = $ixMember->ipv6_address;
            $ix_data['speed']           = $ixMember->speed;

            $ixs[] = $ix_data;
        }

        return $ixs;
    }
}
