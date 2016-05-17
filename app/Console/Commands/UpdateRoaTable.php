<?php

namespace App\Console\Commands;

use App\Helpers\IpUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateRoaTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zIPLookup:update-roa-table';
    protected $rpkiServer;
    protected $ipUtils;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the full ROA table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(IpUtils $ipUtils)
    {
        parent::__construct();
        $this->ipUtils = $ipUtils;
        $this->rpkiServer = config('app.rpki_server_url');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Downloading the full ROA cert list');
        $roas = json_decode(file_get_contents($this->rpkiServer));
        $ipv4AmountCidrArray = $this->ipUtils->IPv4cidrIpCount();
        $ipv6AmountCidrArray = $this->ipUtils->IPv6cidrIpCount();
        $mysqlTime = '"' . date('Y-m-d H:i:s') . '"';

        $this->info('Creating the insert query');
        DB::statement('DROP TABLE IF EXISTS roa_table_temp');
        DB::statement('DROP TABLE IF EXISTS backup_roa_table');
        DB::statement('CREATE TABLE roa_table_temp LIKE roa_table');

        $roaInsert = '';
        foreach ($roas->roas as $roa) {
            $roaParts = explode('/', $roa->prefix);
            $roaCidr = $roaParts[1];
            $roaIP = $roaParts[0];
            $roaAsn = str_ireplace('as', '', $roa->asn);

            $startDec = $this->ipUtils->ip2dec($roaIP);
            if ($this->ipUtils->getInputType($roaIP) == 4) {
                $ipAmount = $ipv4AmountCidrArray[$roaCidr];
            } else {
                $ipAmount = $ipv6AmountCidrArray[$roaCidr];
            }
            $endDec = number_format(($startDec + $ipAmount -1), 0, '', '');

            $roaInsert .= '("'.$roaIP.'",'.$roaCidr.','.$startDec.','.$endDec.','.$roaAsn.','.$roa->maxLength.','.$mysqlTime.','.$mysqlTime.'),';
        }

        $this->info('Processing the insert query');
        $roaInsert = rtrim($roaInsert, ',').';';
        DB::statement('INSERT INTO roa_table_temp (ip,cidr,ip_dec_start,ip_dec_end,asn,max_length,updated_at,created_at) VALUES '.$roaInsert);

        $this->info('Hot Swapping the ROA list table');
        DB::statement('RENAME TABLE roa_table TO backup_roa_table, roa_table_temp TO roa_table;');
        DB::statement('DROP TABLE IF EXISTS backup_roa_table');
    }
}
