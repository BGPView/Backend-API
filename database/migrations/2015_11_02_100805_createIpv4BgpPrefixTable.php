<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIpv4BgpPrefixTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipv4_bgp_prefixes', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('rir_id')->unsigned()->index();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ipv4_bgp_prefixes');
    }
}
