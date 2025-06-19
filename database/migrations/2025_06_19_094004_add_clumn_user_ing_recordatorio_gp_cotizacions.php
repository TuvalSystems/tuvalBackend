<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClumnUserIngRecordatorioGpCotizacions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->string('user_ing_recordatorio', 20)->after('observacion')->nullable();

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
            $table->dropColumn('user_ing_recordatorio');
        });
    }
}
