<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $this->dropForeignIfExists('gp_cobros', 'fk_gp_usuarios_user_aprobador');
        $this->dropForeignIfExists('gp_cobros', 'fk_gp_usuarios_user_rechazo');

        $this->normalizeDecisionFields('gp_cobros');

        Schema::table('gp_cobros', function (Blueprint $table) {
            $table->foreign('user_aprobador_rechazo_id', 'fk_gp_cobros_user_aprobador_rechazo')
                ->references('id')
                ->on('gp_usuarios')
                ->nullOnDelete();
        });


        $this->dropForeignIfExists('gp_cobros_depositos', 'fk_gp_cobros_depositos_user_aprobador_rechazo');
        $this->dropForeignIfExists('gp_cobros_depositos', 'fk_gp_cobros_depositos_user_rechazo');

        $this->normalizeDecisionFields('gp_cobros_depositos');

        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            $table->foreign('user_aprobador_rechazo_id', 'fk_gp_cobros_depositos_user_aprobador_rechazo')
                ->references('id')
                ->on('gp_usuarios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropForeignIfExists('gp_cobros', 'fk_gp_cobros_user_aprobador_rechazo');
        $this->dropForeignIfExists('gp_cobros_depositos', 'fk_gp_cobros_depositos_user_aprobador_rechazo');

        Schema::table('gp_cobros', function (Blueprint $table) {
            if (! Schema::hasColumn('gp_cobros', 'fecha_rechazo')) {
                $table->dateTime('fecha_rechazo')->nullable();
            }

            if (! Schema::hasColumn('gp_cobros', 'user_rechazo_id')) {
                $table->unsignedBigInteger('user_rechazo_id')->nullable();
            }
        });

        Schema::table('gp_cobros_depositos', function (Blueprint $table) {
            if (! Schema::hasColumn('gp_cobros_depositos', 'fecha_rechazo')) {
                $table->dateTime('fecha_rechazo')->nullable();
            }

            if (! Schema::hasColumn('gp_cobros_depositos', 'user_rechazo_id')) {
                $table->unsignedBigInteger('user_rechazo_id')->nullable();
            }
        });
    }

     private function normalizeDecisionFields(string $tableName): void
    {
        /*
        | Si antes renombraste fecha_aprobacion_rechazo a fecha_aprobacion,
        | aquí la regresamos al nombre correcto.
        */
        if (
            Schema::hasColumn($tableName, 'fecha_aprobacion') &&
            ! Schema::hasColumn($tableName, 'fecha_aprobacion_rechazo')
        ) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('fecha_aprobacion', 'fecha_aprobacion_rechazo');
            });
        }

        /*
        | Si no existe la fecha unificada, se crea.
        */
        if (! Schema::hasColumn($tableName, 'fecha_aprobacion_rechazo')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dateTime('fecha_aprobacion_rechazo')->nullable();
            });
        }

        /*
        | Si existía fecha_rechazo con datos, copiamos esa fecha al campo unificado.
        */
        if (Schema::hasColumn($tableName, 'fecha_rechazo')) {
            DB::table($tableName)
                ->whereNotNull('fecha_rechazo')
                ->update([
                    'fecha_aprobacion_rechazo' => DB::raw('fecha_rechazo'),
                ]);
        }

        /*
        | Si antes existía user_aprobador_id, lo renombramos a user_aprobador_rechazo_id.
        */
        if (
            Schema::hasColumn($tableName, 'user_aprobador_id') &&
            ! Schema::hasColumn($tableName, 'user_aprobador_rechazo_id')
        ) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('user_aprobador_id', 'user_aprobador_rechazo_id');
            });
        }

        /*
        | Si no existe el usuario unificado, se crea.
        */
        if (! Schema::hasColumn($tableName, 'user_aprobador_rechazo_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('user_aprobador_rechazo_id')->nullable();
            });
        }

        /*
        | Si existía user_rechazo_id con datos, copiamos ese usuario al campo unificado.
        */
        if (Schema::hasColumn($tableName, 'user_rechazo_id')) {
            DB::table($tableName)
                ->whereNotNull('user_rechazo_id')
                ->update([
                    'user_aprobador_rechazo_id' => DB::raw('user_rechazo_id'),
                ]);
        }

        /*
        | Eliminamos los campos separados que ya no se usarán.
        */
        $columnsToDrop = [];

        if (Schema::hasColumn($tableName, 'fecha_rechazo')) {
            $columnsToDrop[] = 'fecha_rechazo';
        }

        if (Schema::hasColumn($tableName, 'user_rechazo_id')) {
            $columnsToDrop[] = 'user_rechazo_id';
        }

        if (! empty($columnsToDrop)) {
            Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function dropForeignIfExists(string $tableName, string $foreignName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($foreignName) {
                $table->dropForeign($foreignName);
            });
        } catch (\Throwable $e) {
            // No se hace nada si la FK no existe.
        }
    }
};
