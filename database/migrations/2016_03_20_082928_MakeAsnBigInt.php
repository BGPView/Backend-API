<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeAsnBigInt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('asns', function($table)
        {
            $table->bigInteger('asn')->unsigned()->change();
        });

        Schema::table('ipv4_bgp_prefixes', function($table)
        {
            $table->bigInteger('asn')->unsigned()->change();
        });

        Schema::table('ipv6_bgp_prefixes', function($table)
        {
            $table->bigInteger('asn')->unsigned()->change();
        });

        Schema::table('ix_members', function($table)
        {
            $table->bigInteger('asn')->unsigned()->change();
        });

        Schema::table('ipv4_peers', function($table)
        {
            $table->bigInteger('asn_1')->unsigned()->change();
            $table->bigInteger('asn_2')->unsigned()->change();
        });

        Schema::table('ipv6_peers', function($table)
        {
            $table->bigInteger('asn_1')->unsigned()->change();
            $table->bigInteger('asn_2')->unsigned()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('asns', function($table)
        {
            $table->integer('asn')->change();
        });

        Schema::table('ipv4_bgp_prefixes', function($table)
        {
            $table->integer('asn')->change();
        });

        Schema::table('ix_members', function($table)
        {
            $table->integer('asn')->change();
        });

        Schema::table('ipv6_bgp_prefixes', function($table)
        {
            $table->integer('asn')->change();
        });

        Schema::table('ipv4_peers', function($table)
        {
            $table->integer('asn_1')->change();
            $table->integer('asn_2')->change();
        });

        Schema::table('ipv6_peers', function($table)
        {
            $table->integer('asn_1')->change();
            $table->integer('asn_2')->change();
        });
        
    }
}
