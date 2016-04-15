<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\CLImate\CLImate;
use Ubench;

class UpdateDNSTable extends Command
{
    private $cli;
    private $bench;
    private $ipUtils;

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
    public function __construct(CLImate $cli, Ubench $bench, IpUtils $ipUtils)
    {
        parent::__construct();
        $this->cli = $cli;
        $this->bench = $bench;
        $this->ipUtils = $ipUtils;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

    }

}
