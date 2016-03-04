<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIanaAssignmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iana_assignments', function($table)
        {
            $table->increments('id')->unique();
            $table->string('type')->index();
            $table->decimal('start', 39, 0)->unsigned()->index();
            $table->decimal('end', 39, 0)->unsigned()->index();
            $table->string('whois_server');
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
        Schema::dropIfExists('iana_assignments');
    }
}
