<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIPv4PrefixWhoisEmails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ipv4_prefix_whois_emails', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('prefix_whois_id')->unsigned()->index();
            $table->string('email_address')->index();
            $table->boolean('abuse_email')->default(false);
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
        Schema::dropIfExists('ipv4_prefix_whois_emails');
    }
}
