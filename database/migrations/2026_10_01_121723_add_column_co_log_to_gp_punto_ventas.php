<?php

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
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_local')
                ->nullable()
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gp_punto_ventas', function (Blueprint $table) {
            $table->dropColumn(['codigo_local']);
        });
    }
};
