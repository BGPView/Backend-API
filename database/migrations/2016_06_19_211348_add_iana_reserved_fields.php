<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIanaReservedFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('iana_assignments', function($table)
        {
            $table->string('description')->nullable();
            $table->date('date_allocated')->nullable();
            $table->string('status')->index();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('iana_assignments', function($table)
        {
            $table->dropColumn('description');
            $table->dropColumn('date_allocated');
            $table->dropColumn('status');
        });
    }
}
