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
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->unsignedTinyInteger('import_job')->default(0)->index();
            $table->dateTime('fecha_registro_recordatorio')->nullable()->after('user_ing_recordatorio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->dropColumn(['import_job', 'fecha_registro_recordatorio']);
        });
    }
};
