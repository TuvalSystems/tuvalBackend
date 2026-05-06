<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppVersionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();

            //platform: androuid por ahora luego podran ser otros
            $table->string('platform', 50)->default('android');
            //version visible de la app
            $table->string('version_name', 50);

            //build interno de android. debe subir siempre
            $table->integer('build_number');


            // Build mínimo permitido para usar la app.
            // Si la app instalada tiene build menor a este, se fuerza actualización.
            $table->integer('minimum_required_build')->default(1);

            // Información del archivo APK.
            $table->string('apk_file_name')->nullable();
            $table->string('apk_path')->nullable();
            $table->string('apk_url', 1000)->nullable();
            $table->unsignedBigInteger('apk_size_bytes')->nullable();

            // Hash opcional para validar integridad del archivo.
            $table->string('checksum_sha256', 100)->nullable();

            // Mensaje visible en la app.
            $table->text('message')->nullable();

            // Notas internas o detalle de cambios.
            $table->longText('release_notes')->nullable();

            // Control de estado.
            $table->boolean('is_active')->default(true);

            // Auditoría básica.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->unique(['platform', 'build_number']);
            $table->index(['platform', 'is_active', 'build_number']);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_versions');
    }
}
