<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAsnInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asns', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('rir_id')->unsigned()->index();
            $table->integer('asn')->unique();
            $table->string('name');
            $table->string('website')->nullable();
            $table->string('looking_glass')->nullable();
            $table->string('traffic_estimation')->nullable();
            $table->string('traffic_ratio')->nullable();
            $table->string('description')->nullable();
            $table->text('description_full')->nullable();
            $table->string('counrty_code', 2)->index();
            $table->text('owner_address')->nullable();
            $table->text('raw_whois')->nullable();

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
        Schema::dropIfExists('asns');
    }
}
