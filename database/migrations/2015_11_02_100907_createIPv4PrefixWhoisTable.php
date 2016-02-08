<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIPv4PrefixWhoisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipv4_prefix_whois', function($table)
        {
            $table->increments('id')->unique();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->string('name');
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
        Schema::dropIfExists('ipv4_prefix_whois');
    }
}
