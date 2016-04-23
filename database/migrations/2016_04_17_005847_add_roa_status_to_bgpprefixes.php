<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRoaStatusToBgpprefixes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ipv4_bgp_prefixes', function($table)
        {
            $table->integer('roa_status')->default(0)->index();
        });

        Schema::table('ipv6_bgp_prefixes', function($table)
        {
            $table->integer('roa_status')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ipv4_bgp_prefixes', function($table)
        {
            $table->dropColumn('roa_status');
        });

        Schema::table('ipv6_bgp_prefixes', function($table)
        {
            $table->dropColumn('roa_status');
        });
    }
}
