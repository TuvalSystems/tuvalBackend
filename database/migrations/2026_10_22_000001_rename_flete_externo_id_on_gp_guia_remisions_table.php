<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->renameColumn('flete_externo_id', 'fle_externo_id');
        });
    }

    public function down(): void
    {
        Schema::table('gp_guia_remisions', function (Blueprint $table) {
            $table->renameColumn('fle_externo_id', 'flete_externo_id');
        });
    }
};
