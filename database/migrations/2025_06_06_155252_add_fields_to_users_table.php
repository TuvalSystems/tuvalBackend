<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_usuarios', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->boolean('status')->default(1);
            $table->boolean('must_change_password')->default(true);
            $table->boolean('process_job')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_usuarios', function (Blueprint $table) {
            $table->string('password')->nullable();
            $table->dropColumn('status');
            $table->dropColumn('must_change_password');
            $table->dropColumn('process_job');
        });
    }
}
