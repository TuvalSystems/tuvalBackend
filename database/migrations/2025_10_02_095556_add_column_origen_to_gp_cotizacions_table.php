<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnOrigenToGpCotizacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gp_cotizacions', function (Blueprint $table) {
            $table->enum('origen', ['cotizacion', 'reserva'])
                ->nullable()
                ->default('cotizacion')
                ->index();
            $table->foreignId('pedido_reserva_id')->nullable()->constrained('gp_pedidos')->nullOnDelete();
            $table->dateTime('pedido_generado_at')->nullable();
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
            $table->dropColumn(['origen', 'pedido_reserva_id', 'pedido_generado_at']);
        });
    }
}
