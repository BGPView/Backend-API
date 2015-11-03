<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIpv4InfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipv4_prefixes', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('rir_id')->unsigned()->index();
            $table->string('ip', 40)->index();
            $table->integer('cidr')->unsigned()->index();
            $table->decimal('ip_dec_start', 39, 0)->unsigned()->index();
            $table->decimal('ip_dec_end', 39, 0)->unsigned()->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->text('description_full')->nullable();
            $table->string('counrty_code', 2)->index();
            $table->text('owner_address')->nullable();
            $table->text('raw_whois')->nullable();
            $table->dateTime('seen_at');
            $table->dateTime('scraped_at');
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
        Schema::dropIfExists('ipv4_prefixes');
    }
}
