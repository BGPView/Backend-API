<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusToRirAllocation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rir_ipv4_allocations', function($table)
        {
            $table->string('status')->index();
            $table->string('counrty_code', 2)->nullable()->change();
            $table->date('date_allocated')->nullable()->change();
        });

        Schema::table('rir_ipv6_allocations', function($table)
        {
            $table->string('status')->index();
            $table->string('counrty_code', 2)->nullable()->change();
            $table->date('date_allocated')->nullable()->change();
        });

        Schema::table('rir_asn_allocations', function($table)
        {
            $table->string('status')->index();
            $table->string('counrty_code', 2)->nullable()->change();
            $table->date('date_allocated')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rir_ipv4_allocations', function($table)
        {
            $table->dropColumn('status');
            $table->string('counrty_code', 2)->nullable(false)->change();
            $table->date('date_allocated')->nullable(false)->change();
        });

        Schema::table('rir_ipv6_allocations', function($table)
        {
            $table->dropColumn('status');
            $table->string('counrty_code', 2)->nullable(false)->change();
            $table->date('date_allocated')->nullable(false)->change();
        });

        Schema::table('rir_asn_allocations', function($table)
        {
            $table->dropColumn('status');
            $table->string('counrty_code', 2)->nullable(false)->change();
            $table->date('date_allocated')->nullable(false)->change();
        });
    }
}
