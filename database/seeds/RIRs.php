<?php

use App\Models\Rir;
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
        Rir::create([
            'name' => 'AfriNIC',
            'full_name' => 'African Network Information Center',
            'website' => 'afrinic.net',
            'whois_server' => 'whois.afrinic.net',
            'allocation_list_url' => 'ftp://ftp.afrinic.net/stats/afrinic/delegated-afrinic-extended-latest',
        ]);

        Rir::create([
            'name' => 'ARIN',
            'full_name' => 'American Registry for Internet Numbers',
            'website' => 'arin.net',
            'whois_server' => 'whois.arin.net',
            'allocation_list_url' => 'ftp://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
        ]);

        Rir::create([
            'name' => 'APNIC',
            'full_name' => 'Asia-Pacific Network Information Centre',
            'website' => 'apnic.net',
            'whois_server' => 'whois.apnic.net',
            'allocation_list_url' => 'ftp://ftp.apnic.net/pub/stats/apnic/delegated-apnic-extended-latest',
        ]);

        Rir::create([
            'name' => 'Lacnic',
            'full_name' => 'Latin America and Caribbean Network Information Centre',
            'website' => 'lacnic.net',
            'whois_server' => 'whois.lacnic.net',
            'allocation_list_url' => 'ftp://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
        ]);

        Rir::create([
            'name' => 'RIPE',
            'full_name' => 'Reseaux IP Européens Network Coordination Centre',
            'website' => 'ripe.net',
            'whois_server' => 'whois.ripe.net',
            'allocation_list_url' => 'ftp://ftp.ripe.net/ripe/stats/delegated-ripencc-extended-latest',
        ]);
    }
}
