<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveTheDescSingleField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ipv4_prefix_whois', function($table)
        {
            $table->dropColumn('description');
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->dropColumn('description');
        });

        Schema::table('asns', function($table)
        {
            $table->dropColumn('description');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ipv4_prefix_whois', function($table)
        {
            $table->string('description')->nullable();
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->string('description')->nullable();
        });

        Schema::table('asns', function($table)
        {
            $table->string('description')->nullable();
        });
    }
}
