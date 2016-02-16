<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIpv6BgpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipv6_bgp_table', function($table)
        {
            $table->increments('id')->unique();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->integer('asn')->unsigned()->index();
            $table->integer('upstream_asn')->unsigned()->index();
            $table->string('bgp_path');
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
        Schema::dropIfExists('ipv6_bgp_table');
    }
}
