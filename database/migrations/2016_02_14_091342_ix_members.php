<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IxMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ix_members', function($table)
        {
            $table->increments('id')->unique();
            $table->integer('ix_peeringdb_id')->unsigned()->index();
            $table->integer('asn')->unsigned()->index();
            $table->integer('speed')->unsigned()->index()->default(0);
            $table->string('ipv4_address')->nullable();
            $table->decimal('ipv4_dec', 39, 0)->unsigned()->nullable()->index();
            $table->string('ipv6_address')->nullable();
            $table->decimal('ipv6_dec', 39, 0)->unsigned()->nullable()->index();
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
        Schema::dropIfExists('ix_members');
    }
}
