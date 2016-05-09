<?php

namespace App\Models;

use Elasticquent\ElasticquentTrait;
use Illuminate\Database\Eloquent\Model;

class IPv6PrefixWhois extends Model {

    use ElasticquentTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ipv6_prefix_whois';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['id', 'rir_id', 'bgp_prefix_id', 'raw_whois', 'created_at', 'updated_at'];


    public function rir()
    {
        return $this->belongsTo('App\Models\Rir');
    }
    
    public function emails()
    {
        return $this->hasMany('App\Models\IPv6PrefixWhoisEmail', 'prefix_whois_id', 'id');
    }

    public function bgpPrefix()
    {
        return $this->belongsTo('App\Models\IPv6BgpPrefix', 'bgp_prefix_id', 'id');
    }

    public function getDescriptionFullAttribute($value)
    {
        return json_decode($value);
    }

    public function getOwnerAddressAttribute($value)
    {
        if (empty($value) === true) {
            return null;
        }

        $data = json_decode($value);

        if (empty($data) === true) {
            return null;
        }

        $addressLines = [];

        foreach($data as $entry) {
            // Remove/Clean all double commas
            $entry = preg_replace('/,+/', ',', $entry);
            $addressArr = explode(',', $entry);
            $addressLines = array_merge($addressLines, $addressArr);
        }

        return array_values(array_filter(array_map('trim', $addressLines)));
    }

    public function getRawWhoisAttribute($value)
    {
        // Remove the "source" entry
        $parts = explode("\n", $value);
        unset($parts[0]);
        return implode($parts, "\n");
    }

    public function getEmailContactsAttribute()
    {
        $email_contacts = [];
        foreach ($this->emails as $email) {
            $email_contacts[] = $email->email_address;
        }
        return $email_contacts;
    }

    public function getAbuseContactsAttribute()
    {
        $abuse_contacts = [];
        foreach ($this->emails as $email) {
            if ($email->abuse_email) {
                $abuse_contacts[] = $email->email_address;
            }
        }
        return $abuse_contacts;
    }
}
