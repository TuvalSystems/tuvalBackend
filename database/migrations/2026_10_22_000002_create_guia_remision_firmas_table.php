<?php

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_remision_firmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guia_remision_id')->nullable()->unique()->constrained('gp_guia_remisions')->cascadeOnDelete();
            $table->foreignId('guia_reserva_id')->nullable()->unique()->constrained('gp_guia_remision_reservas')->cascadeOnDelete();
            $table->foreignId('media_id')->unique()->constrained('media')->cascadeOnDelete();
            $table->string('nombre_firmante')->nullable();
            $table->string('identificacion_firmante', 50)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('precision_ubicacion', 12, 3)->nullable();
            $table->dateTime('fecha_firma')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('gp_usuarios')->nullOnDelete();
            $table->string('usuario_nombre')->nullable();
            $table->string('usuario_login')->nullable();
            $table->timestamps();
        });

        foreach ([new GpGuiaRemision(), new GpGuiaRemisionReserva()] as $guide) {
            $field = $guide instanceof GpGuiaRemisionReserva ? 'guia_reserva_id' : 'guia_remision_id';
            DB::table('media')->where('collection_name', 'firmas')
                ->where('model_type', $guide->getMorphClass())->orderBy('id')
                ->chunkById(200, function ($files) use ($guide, $field) {
                    foreach ($files as $file) {
                        if (!DB::table($guide->getTable())->where('id', $file->model_id)->exists()) {
                            continue;
                        }
                        $properties = json_decode($file->custom_properties, true) ?: [];
                        $user = DB::table('gp_usuarios')->where('id', $properties['user_id'] ?? null)->first();
                        DB::table('guia_remision_firmas')->updateOrInsert([$field => $file->model_id], [
                            'media_id'                 => $file->id,
                            'nombre_firmante'          => $properties['nombre_firmante'] ?? null,
                            'identificacion_firmante'  => $properties['identificacion_firmante'] ?? null,
                            'latitud'                  => $properties['latitud'] ?? null,
                            'longitud'                 => $properties['longitud'] ?? null,
                            'precision_ubicacion'      => $properties['precision_ubicacion'] ?? null,
                            'fecha_firma'              => empty($properties['fecha_firma']) ? null : Carbon::parse($properties['fecha_firma'])->utc(),
                            'usuario_id'               => $user?->id,
                            'usuario_nombre'           => $properties['user_name'] ?? $user?->USERNAME,
                            'usuario_login'            => $properties['user_login'] ?? $user?->USERID,
                            'created_at'               => empty($properties['uploaded_at']) ? $file->created_at : Carbon::parse($properties['uploaded_at'])->utc(),
                            'updated_at'               => $file->updated_at,
                        ]);
                        foreach (['nombre_firmante', 'identificacion_firmante', 'latitud', 'longitud',
                            'precision_ubicacion', 'fecha_firma', 'user_id', 'user_name', 'user_login', 'uploaded_at'] as $key) {
                            unset($properties[$key]);
                        }
                        DB::table('media')->where('id', $file->id)
                            ->update(['custom_properties' => json_encode((object) $properties)]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Devolver los datos a media permite que el código anterior siga leyéndolos.
        DB::table('guia_remision_firmas')->orderBy('id')->chunkById(200, function ($signatures) {
            foreach ($signatures as $signature) {
                $media = DB::table('media')->where('id', $signature->media_id)->first();
                if (!$media) {
                    continue;
                }
                $properties = json_decode($media->custom_properties, true) ?: [];
                foreach (['nombre_firmante', 'identificacion_firmante', 'latitud', 'longitud', 'precision_ubicacion', 'fecha_firma'] as $field) {
                    $properties[$field] = $signature->$field;
                }
                $properties['user_id']      = $signature->usuario_id;
                $properties['user_name']    = $signature->usuario_nombre;
                $properties['user_login']   = $signature->usuario_login;
                $properties['uploaded_at']  = $signature->created_at;
                DB::table('media')->where('id', $media->id)->update(['custom_properties' => json_encode($properties)]);
            }
        });
        Schema::dropIfExists('guia_remision_firmas');
    }
};
