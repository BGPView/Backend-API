<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RirIpv4Delegation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rir_ipv4_allocations', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('rir_id')->unsigned()->index();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
            $table->string('counrty_code', 2)->index();
            $table->date('date_allocated')->index();
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
        Schema::dropIfExists('rir_ipv4_allocations');
    }
}
