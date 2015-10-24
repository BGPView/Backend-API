<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class Rirs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rirs', function($table)
        {
            $table->increments('id')->unique();
            $table->string('name');
            $table->string('full_name');
            $table->string('website');
            $table->string('whois_server');
            $table->text('allocation_list_url');
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
        Schema::dropIfExists('rirs');
    }
}
