<?php

namespace App\Services\Ventas\Compartido;

use App\Services\Ventas\Compartido\Contracts\IdempotentSaveServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IdempotentSaveService implements IdempotentSaveServiceInterface
{
    public function requestData(FormRequest $request): array
    {
        $data = $request->validated();
        $key = $request->header('Idempotency-Key');
        if ($key === null) {
            return $data; // Compatibilidad con otros consumidores de la API.
        }
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Identificador de guardado inválido.']);
        }
        $payload = [$data, $request->input('imagenes_existentes', []), $request->input('pdfs_existentes', []), $request->allFiles()];
        $data['_guardado'] = [
            'clave' => $key,
            'huella' => hash('sha256', json_encode($this->normalize($payload), JSON_THROW_ON_ERROR)),
        ];
        return $data;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['nombre' => $value->getClientOriginalName(), 'sha256' => hash_file('sha256', $value->getRealPath())];
        }
        if (is_array($value)) {
            if (!array_is_list($value)) ksort($value);
            return array_map(fn ($item) => $this->normalize($item), $value);
        }
        return $value;
    }

    public function execute(string $tipo, int $usuarioId, array $data, string $modelo, callable $guardar): Model
    {
        $operation = $data['_guardado'] ?? null;
        if (!$operation) return $guardar();

        // El documento y su comprobante se confirman en la MISMA transacción.
        // La restricción única serializa solicitudes concurrentes, sin depender de caché.
        return DB::transaction(function () use ($tipo, $usuarioId, $operation, $modelo, $guardar) {
            $identity = ['usuario_id' => $usuarioId, 'tipo' => $tipo, 'clave' => $operation['clave']];
            DB::table('operaciones_guardado')->insertOrIgnore($identity + [
                'huella' => $operation['huella'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $query = DB::table('operaciones_guardado')->where($identity);
            $record = (clone $query)->lockForUpdate()->first();
            if (!$record || !hash_equals($record->huella, $operation['huella'])) {
                throw new ConflictHttpException('Este identificador ya pertenece a un guardado con datos diferentes.');
            }
            if ($record->documento_id !== null) {
                return $modelo::findOrFail($record->documento_id);
            }
            $document = $guardar();
            $query->update(['documento_id' => $document->getKey(), 'updated_at' => now()]);
            return $document;
        });
    }
}
