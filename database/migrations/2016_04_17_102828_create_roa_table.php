<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roa_table', function($table)
        {
            $table->increments('id')->unique();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
            $table->integer('asn')->unsigned()->index();
            $table->integer('max_length')->unsigned()->index();
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
        Schema::dropIfExists('roa_table');
    }
}
