<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateMaxmindDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-maxmind-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the maxmind database';
    protected $maxmindDbUrl = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $maxmindDbPath = database_path() . '/GeoLite2-City.mmdb';

        $this->info('Downloading a new copy of the maxmind DB');
        $gzipDb = file_get_contents($this->maxmindDbUrl);
        $data = gzdecode($gzipDb);

        if (file_exists($maxmindDbPath) === true) {
            unlink($maxmindDbPath);
        }

        file_put_contents($maxmindDbPath, $data);
    }
}
