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
            $table->text('description')->nullable();
            $table->string('counrty_code', 2)->index();
            $table->string('company_name')->nullable();
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
        Schema::dropIfExists('asns');
    }
}
