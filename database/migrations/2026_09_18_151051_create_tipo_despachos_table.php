<?php

use App\Enums\Ventas\DispatchTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tipo_despachos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion')->nullable();
            $table->string('observacion')
                ->comment('Observación detallada sobre la descripcion')
                ->nullable();
              $table->enum(
                'responsable_pago',
                array_column(DispatchTypeEnum::cases(), 'value')
            );
            $table->foreignId('usuario_ingreso_id')->nullable()
                ->comment('Usuario que realiza el ingreso del registro')
                ->constrained('gp_usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_ingreso')->nullable();
            $table->foreignId('usuario_modifica_id')->nullable()
                ->comment('Usuario que modifica el registro')
                ->constrained('gp_usuarios')
                ->nullOnDelete();
            $table->dateTime('fecha_modificacion')->nullable();
            $table->boolean('estado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_despachos');
    }
};
