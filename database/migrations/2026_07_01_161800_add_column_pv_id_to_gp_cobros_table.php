<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->foreignId('pv_id')->after('user_id')->nullable()->constrained('gp_punto_ventas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cobros', function (Blueprint $table) {
               $table->dropForeign(['pv_id']);
        });
    }
};
