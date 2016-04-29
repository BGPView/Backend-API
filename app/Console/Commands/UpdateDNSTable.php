<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use App\Jobs\ResolveDnsQuery;
use App\Models\DNSRecord;
use App\Services\Dns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use League\CLImate\CLImate;
use Ubench;

class UpdateDNSTable extends Command
{
    use DispatchesJobs;

    private $domainUrl;

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
    public function __construct()
    {
        parent::__construct();
        $this->domainUrl = 'https://wwws.io/api/full/539/'.config('wwws.email').'/'.config('wwws.password').'/';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $filePath = '';
        $fp = fopen($filePath, 'r');
        if ($fp) {
            while (($line = fgets($fp)) !== false) {
                $this->dispatch(new ResolveDnsQuery($line));
            }
            fclose($fp);
        }
    }

}
