<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateSectionsTableStructure extends Migration
{
    public function up()
    {
        /*
         * Limpieza previa para evitar errores con parent_id.
         */
        DB::statement("
            UPDATE sections
            SET parent_id = NULL
            WHERE parent_id = 0
        ");

        DB::statement("
            UPDATE sections s
            LEFT JOIN sections p ON p.id = s.parent_id
            SET s.parent_id = NULL
            WHERE s.parent_id IS NOT NULL
              AND p.id IS NULL
        ");

        /*
         * Ajustar parent_id al mismo tipo que sections.id.
         */
        DB::statement("
            ALTER TABLE sections
            MODIFY parent_id BIGINT UNSIGNED NULL
        ");

        /*
         * Renombrar columna to → route_name si todavía existe.
         */
        if (Schema::hasColumn('sections', 'to') && !Schema::hasColumn('sections', 'route_name')) {
            DB::statement("
                ALTER TABLE sections
                CHANGE `to` route_name VARCHAR(100) NULL
            ");
        }

        /*
         * Renombrar columna order → sort_order si todavía existe.
         */
        if (Schema::hasColumn('sections', 'order') && !Schema::hasColumn('sections', 'sort_order')) {
            DB::statement("
                ALTER TABLE sections
                CHANGE `order` sort_order INT NOT NULL DEFAULT 0
            ");
        }

        /*
         * Si la columna sort_order ya existe, solo normalizamos nulos.
         */
        if (Schema::hasColumn('sections', 'sort_order')) {
            DB::statement("
                UPDATE sections
                SET sort_order = 0
                WHERE sort_order IS NULL
            ");

            DB::statement("
                ALTER TABLE sections
                MODIFY sort_order INT NOT NULL DEFAULT 0
            ");
        }

        /*
         * Ajuste de tamaños.
         */
        if (Schema::hasColumn('sections', 'route_name')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY route_name VARCHAR(100) NULL
            ");
        }

        if (Schema::hasColumn('sections', 'url')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY url VARCHAR(255) NULL
            ");
        }

        if (Schema::hasColumn('sections', 'icon')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY icon VARCHAR(100) NULL
            ");
        }

        /*
         * Convertimos platform de ENUM a VARCHAR para evitar problemas con Doctrine.
         */
        if (Schema::hasColumn('sections', 'platform')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY platform VARCHAR(10) NOT NULL DEFAULT 'web'
            ");
        } else {
            Schema::table('sections', function (Blueprint $table) {
                $table->string('platform', 10)
                    ->default('web')
                    ->after('sort_order');
            });
        }

        /*
         * Estado global de la sección.
         */
        if (!Schema::hasColumn('sections', 'status')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->boolean('status')
                    ->default(true)
                    ->after('platform');
            });
        }

        /*
         * Agregar foreign key solo si no existe.
         */
        if (!$this->foreignKeyExists('sections', 'sections_parent_id_foreign')) {
            DB::statement("
                ALTER TABLE sections
                ADD CONSTRAINT sections_parent_id_foreign
                FOREIGN KEY (parent_id)
                REFERENCES sections(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
            ");
        }
    }

    public function down()
    {
        if ($this->foreignKeyExists('sections', 'sections_parent_id_foreign')) {
            DB::statement("
                ALTER TABLE sections
                DROP FOREIGN KEY sections_parent_id_foreign
            ");
        }

        if (Schema::hasColumn('sections', 'status')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('sections', 'platform')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY platform ENUM('web', 'app', 'both') NOT NULL DEFAULT 'web'
            ");
        }

        if (Schema::hasColumn('sections', 'route_name') && !Schema::hasColumn('sections', 'to')) {
            DB::statement("
                ALTER TABLE sections
                CHANGE route_name `to` VARCHAR(40) NULL
            ");
        }

        if (Schema::hasColumn('sections', 'sort_order') && !Schema::hasColumn('sections', 'order')) {
            DB::statement("
                ALTER TABLE sections
                CHANGE sort_order `order` INT NOT NULL DEFAULT 0
            ");
        }

        DB::statement("
            ALTER TABLE sections
            MODIFY parent_id INT NULL
        ");

        if (Schema::hasColumn('sections', 'url')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY url VARCHAR(60) NULL
            ");
        }

        if (Schema::hasColumn('sections', 'icon')) {
            DB::statement("
                ALTER TABLE sections
                MODIFY icon VARCHAR(30) NULL
            ");
        }
    }

    private function foreignKeyExists(string $tableName, string $foreignKeyName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$databaseName, $tableName, $foreignKeyName]);

        return $result !== null;
    }
}
