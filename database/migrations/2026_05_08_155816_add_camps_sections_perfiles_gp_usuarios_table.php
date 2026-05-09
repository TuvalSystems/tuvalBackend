<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampsSectionsPerfilesGpUsuariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('perfil_app_id')
                ->nullable()
                ->after('perfil_id');

            $table->index('perfil_app_id', 'idx_gp_usuarios_perfil_app_id');
            $table->foreign('perfil_app_id')
                ->references('id')
                ->on('perfiles')
                ->nullOnDelete();
        });

        // Schema::table('perfiles', function (Blueprint $table) {
        //     $table->enum('platform', ['web', 'app', 'both'])
        //         ->default('web')
        //         ->after('name');
        // });

        // Schema::table('sections', function (Blueprint $table) {
        //     $table->enum('platform', ['web', 'app', 'both'])
        //         ->default('web')
        //         ->after('order');
        // });

        // Schema::table('perfil_sections', function (Blueprint $table) {
        //     $table->boolean('state')->default(1)->after('home');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('perfil_sections', function (Blueprint $table) {
            $table->dropColumn('state');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('perfiles', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('gp_usuarios', function (Blueprint $table) {
            // Si agregas foreign key, primero debes eliminarla:
            // $table->dropForeign(['perfil_app_id']);

            $table->dropIndex('idx_gp_usuarios_perfil_app_id');
            $table->dropColumn('perfil_app_id');
        });
    }
}
