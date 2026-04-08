<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnIsAdminCobrosToGpUsuario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_usuarios', function (Blueprint $table) {
            $table->boolean('is_admin_cobros')->default(false)->after('perfil_id');
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
            $table->dropColumn('is_admin_cobros');
        });
    }
}
