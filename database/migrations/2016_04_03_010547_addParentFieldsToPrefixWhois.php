<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddParentFieldsToPrefixWhois extends Migration
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
            $table->integer('rir_id')->nullable()->unsigned()->index();
            $table->string('parent_ip', 40)->nullable()->index();
            $table->integer('parent_cidr')->nullable()->unsigned()->index();
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->integer('rir_id')->nullable()->unsigned()->index();
            $table->string('parent_ip', 40)->nullable()->index();
            $table->integer('parent_cidr')->nullable()->unsigned()->index();
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
            $table->dropColumn('rir_id');
            $table->dropColumn('parent_ip');
            $table->dropColumn('parent_cidr');
        });

        Schema::table('ipv6_prefix_whois', function($table)
        {
            $table->dropColumn('rir_id');
            $table->dropColumn('parent_ip');
            $table->dropColumn('parent_cidr');
        });
    }
}
