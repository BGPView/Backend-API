<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableNullableWhois extends Migration
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
            $table->string('name')->nullable()->change();
            $table->string('counrty_code')->nullable()->change();
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->string('name')->nullable()->change();
            $table->string('counrty_code')->nullable()->change();
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
            $table->string('name')->nullable(false)->change();
            $table->string('counrty_code')->nullable(false)->change();
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->string('name')->nullable(false)->change();
            $table->string('counrty_code')->nullable(false)->change();
        });
    }
}
