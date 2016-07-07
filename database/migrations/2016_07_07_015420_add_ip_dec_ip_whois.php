<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIpDecIpWhois extends Migration
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
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
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
            $table->dropColumn('ip_dec_start');
            $table->dropColumn('ip_dec_end');
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->dropColumn('ip_dec_start');
            $table->dropColumn('ip_dec_end');
        });
    }
}
