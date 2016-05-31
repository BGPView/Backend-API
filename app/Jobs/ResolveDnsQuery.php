<?php

namespace App\Jobs;

use App\Helpers\IpUtils;
use App\Jobs\Job;
use App\Models\DNSRecord;
use App\Services\Dns;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResolveDnsQuery extends Job implements ShouldQueue
{
    protected $domain;
    protected $ipUtils;

    use InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($domain)
    {
        $this->domain = trim($domain);
        $this->ipUtils = new IpUtils;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Dns $dns)
    {
        $domainRecords = $dns->getDomainRecords($this->domain);

        foreach ($domainRecords as $type => $records) {
            foreach ($records as $record) {
                /*
		$dnsEntry = new DNSRecord;
                $dnsEntry->input = $this->domain;
                $dnsEntry->type = $type;
                $dnsEntry->entry = $record;
                */
		if ($type === 'A' || $type === 'AAAA') {
                    $this->ipUtils->ip2dec($record);
                }
		
                dump($this->domain, $type, $record,'=======================================');
                //$dnsEntry->save();
            }
        }

        echo 'Done: '.$this->domain.PHP_EOL;

    }
}
