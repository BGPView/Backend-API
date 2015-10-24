<?php

use App\Models\RIR;
use Illuminate\Database\Seeder;

class RirSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        RIR::create([
            'name' => 'AfriNIC',
            'full_name' => 'African Network Information Center',
            'website' => 'afrinic.net',
            'whois_server' => 'whois.afrinic.net',
            'allocation_list_url' => 'ftp://ftp.afrinic.net/stats/afrinic/delegated-afrinic-extended-latest',
        ]);

        RIR::create([
            'name' => 'ARIN',
            'full_name' => 'American Registry for Internet Numbers',
            'website' => 'arin.net',
            'whois_server' => 'whois.arin.net',
            'allocation_list_url' => 'ftp://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
        ]);

        RIR::create([
            'name' => 'APNIC',
            'full_name' => 'Asia-Pacific Network Information Centre',
            'website' => 'apnic.net',
            'whois_server' => 'whois.apnic.net',
            'allocation_list_url' => 'ftp://ftp.apnic.net/pub/stats/apnic/delegated-apnic-extended-latest',
        ]);

        RIR::create([
            'name' => 'Lacnic',
            'full_name' => 'Latin America and Caribbean Network Information Centre',
            'website' => 'lacnic.net',
            'whois_server' => 'whois.lacnic.net',
            'allocation_list_url' => 'ftp://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
        ]);

        RIR::create([
            'name' => 'RIPE NCC',
            'full_name' => 'Réseaux IP Européens Network Coordination Centre',
            'website' => 'ripe.net',
            'whois_server' => 'whois.ripe.net',
            'allocation_list_url' => 'ftp://ftp.ripe.net/ripe/stats/delegated-ripencc-extended-latest',
        ]);
    }
}
