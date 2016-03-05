<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ApiApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_applications', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('email');
            $table->text('use');
            $table->string('key', 40);
            $table->timestamps();
        });

        Schema::table('api_applications', function ($table) {
            $table->unique('id');
            $table->unique('key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_applications');
    }
}
