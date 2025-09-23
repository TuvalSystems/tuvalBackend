<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsReservasToGpCotizacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->enum('st_solResInv', ['pendiente','aprobado', 'rechazada', 'cancelado', 'facturado'])
                ->nullable()
                ->default(null)
                ->index();
            $table->unsignedBigInteger('co_tipSolResInv')->nullable()->unsigned();
             $table->unsignedBigInteger('co_tipTrasResInv')->nullable()->unsigned();
            $table->date('fe_solResInv')->nullable();
            $table->string('tx_obsSolResInv', 400)->nullable();
            $table->date('fe_ingSolResInv')->nullable()->index();
            $table->unsignedBigInteger('co_usrIngSolResInv')->nullable()->unsigned();
            $table->boolean('st_autSolResInv')->default(false);
            $table->date('fe_autSolResInv')->nullable();
            $table->string('tx_obsAutSolResInv', 400)->nullable();
            $table->unsignedBigInteger('co_usrIngAutSolResInv')->nullable()->unsigned();
            $table->date('fe_ingAutSolResInv')->nullable();
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
            $table->dropColumn([
            'st_solResInv',
            'co_TipSolResInv',
            'co_tipTrasResInv',
            'co_FecSolResInv',
            'tx_obsSolResInv',
            'fe_ingSolResInv',
            'co_usrIngSolResInv',
            'st_autSolResInv',
            'fe_autSolResInv',
            'tx_obsAutSolResInv',
            'co_usrIngAutSolResInv',
            'fe_ingAutSolResInv',
        ]);

        });
    }
}
