<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     private string $table = 'gp_cobros_retenciones';

    public function up(): void
    {
        if (!Schema::hasColumn($this->table, 'enviado_iva_gp')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->boolean('enviado_iva_gp')
                    ->default(false)
                    ->after('total');
            });
        }

        if (!Schema::hasColumn($this->table, 'enviado_renta_gp')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->boolean('enviado_renta_gp')
                    ->default(false)
                    ->after('enviado_iva_gp');
            });
        }

        if (!Schema::hasColumn($this->table, 'enviado_iva_srv_gp')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->boolean('enviado_iva_srv_gp')
                    ->default(false)
                    ->after('enviado_renta_gp');
            });
        }

        if (!Schema::hasColumn($this->table, 'enviado_renta_srv_gp')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->boolean('enviado_renta_srv_gp')
                    ->default(false)
                    ->after('enviado_iva_srv_gp');
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'enviado_iva_gp',
            'enviado_renta_gp',
            'enviado_iva_srv_gp',
            'enviado_renta_srv_gp',
        ];

        $existingColumns = array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($this->table, $column)
        );

        if (empty($existingColumns)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) use ($existingColumns) {
            $table->dropColumn(array_values($existingColumns));
        });
    }
};
