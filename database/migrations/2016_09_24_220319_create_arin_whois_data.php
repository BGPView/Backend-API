<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateArinWhoisData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('whois_db_arin_asns', function($table)
        {
            $table->integer('asn_start')->unsigned()->index();
            $table->integer('asn_end')->unsigned()->index();
            $table->string('org_id')->index();
            $table->longText('raw');
        });

        Schema::create('whois_db_arin_orgs', function($table)
        {
            $table->string('org_id')->index();
            $table->longText('raw');
        });

        Schema::create('whois_db_arin_pocs', function($table)
        {
            $table->string('poc_id')->index();
            $table->longText('raw');
        });

        Schema::create('whois_db_arin_prefixes', function($table)
        {
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
            $table->longText('raw');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('whois_db_arin_asns');
        Schema::dropIfExists('whois_db_arin_orgs');
        Schema::dropIfExists('whois_db_arin_pocs');
        Schema::dropIfExists('whois_db_arin_prefixes');
    }
}
