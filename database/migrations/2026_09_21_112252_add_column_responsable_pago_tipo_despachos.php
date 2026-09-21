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
        Schema::table('tipo_despachos', function (Blueprint $table) {
            $table->enum(
                'responsable_pago',
                array_column(DispatchTypeEnum::cases(), 'value')
            )->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('tipo_despachos', function (Blueprint $table) {
            $table->dropColumn('responsable_pago');
        });
    }
};
