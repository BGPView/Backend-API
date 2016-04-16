<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Services\Dns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\CLImate\CLImate;
use Ubench;

class UpdateDNSTable extends Command
{
    private $cli;
    private $bench;
    private $ipUtils;
    private $dns;
    private $alexaDomainUrl = 'http://s3.amazonaws.com/alexa-static/top-1m.csv.zip';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-dns-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the DNS list from Alexa top 1m';

    /**
     * Create a new command instance.
     */
    public function __construct(CLImate $cli, Ubench $bench, IpUtils $ipUtils, Dns $dns)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->bench = $bench;
        $this->ipUtils = $ipUtils;
        $this->dns = $dns;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Download the zip file
        $file = app_path() . '/top-1m.csv';

        $fp = fopen($file, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                $domain = trim(explode(',', $line)[1]);

                // Delete all old records
                dump($this->dns->getDomainRecords($domain));
            }
        }


    }

}
