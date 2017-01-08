<?php

namespace App\Models;

use Elasticquent\ElasticquentTrait;
use Illuminate\Database\Eloquent\Model;

class IPv6PrefixWhois extends Model {

    use ElasticquentTrait;

    /**
     * The elasticsearch settings.
     *
     * @var array
     */
    protected $indexSettings = [
        'analysis' => [
            'analyzer' => [
                'string_lowercase' => [
                    'tokenizer' => 'keyword',
                    'filter' => [ 'asciifolding', 'lowercase', 'custom_replace' ],
                ],
            ],
            'filter' => [
                'custom_replace' => [
                    'type' => 'pattern_replace',
                    'pattern' => "[^a-z0-9 ]",
                    'replacement' => "",
                ],
            ],
        ],
    ];
    
    /**
     * The elasticsearch mappings.
     *
     * @var array
     */
    protected $mappingProperties = [
        'name' => [
            'type' => 'string',
            'analyzer' => 'string_lowercase'
        ],
        'description' => [
            'type' => 'string',
            'analyzer' => 'string_lowercase'
        ],
    ];

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
    protected $hidden = ['id', 'rir_id', 'bgp_prefix_id', 'raw_whois', 'created_at', 'updated_at', 'ip_dec_start', 'ip_dec_end'];


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

    public function getDescriptionAttribute()
    {
        $descriptionLines = $this->description_full;
        if (is_null($descriptionLines) !== true) {
            foreach ($descriptionLines as $descriptionLine) {
                if (preg_match("/[A-Za-z0-9]/i", $descriptionLine)) {
                    return $descriptionLine;
                }
            }
        }

        return $this->name;
    }

    public function getDescriptionFullAttribute($value)
    {
        if (is_null($value) === true) {
            return [];
        }

        if (is_string($value) !== true) {
            return $value;
        }

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
