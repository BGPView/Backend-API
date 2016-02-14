<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ixs', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('peeringdb_id')->unsigned()->index();
            $table->string('name')->index();
            $table->string('name_full');
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('counrty_code', 2)->index();
            $table->string('url_stats')->nullable();
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
        Schema::dropIfExists('ixs');
    }
}
