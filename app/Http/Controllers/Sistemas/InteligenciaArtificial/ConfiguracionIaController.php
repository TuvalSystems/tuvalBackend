<?php

namespace App\Http\Controllers\Sistemas\InteligenciaArtificial;

use App\Http\Controllers\Controller;
use App\Services\Sistemas\InteligenciaArtificial\ConsumoIaService;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfiguracionIaController extends Controller
{
    use JsonResponseTrait;

    private const CAMPOS = ['habilitado' => 'habilitado', 'limiteDiarioGlobal' => 'limite_diario_global', 'limiteMensualGlobal' => 'limite_mensual_global',
        'maxEntrada' => 'max_entrada', 'maxSalida' => 'max_salida'];

    public function obtener(ConsumoIaService $consumo): JsonResponse
    {
        $config = $consumo->configuracion();
        $data = [];
        foreach (self::CAMPOS as $json => $columna) $data[$json] = $json === 'habilitado' ? (bool) $config->$columna : (int) $config->$columna;
        $presupuestos = DB::table('ia_presupuestos_perfil')->pluck('puntos_porcentaje', 'perfil_id');
        $usos = DB::table('ia_consumos')->where('created_at', '>=', now()->startOfMonth())
            ->select('perfil_id')->selectRaw('SUM(tokens_contabilizados) as mensual')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN tokens_contabilizados ELSE 0 END) as diario', [now()->startOfDay()])
            ->groupBy('perfil_id')->get()->keyBy('perfil_id');
        $data['perfiles'] = DB::table('perfiles')->where('platform', 'web')->orderBy('name')->get()->map(function ($perfil) use ($config, $presupuestos, $usos) {
            $puntos = (int) ($presupuestos[$perfil->id] ?? 0);
            return ['perfilId' => $perfil->id, 'nombre' => $perfil->name, 'estado' => $perfil->state,
                'porcentaje' => $puntos / 100,
                'limiteDiario' => intdiv((int) $config->limite_diario_global * $puntos, 10000),
                'limiteMensual' => intdiv((int) $config->limite_mensual_global * $puntos, 10000),
                'consumoDiario' => (int) ($usos[$perfil->id]->diario ?? 0),
                'consumoMensual' => (int) ($usos[$perfil->id]->mensual ?? 0)];
        });
        $data['actualizadoPor'] = $config->actualizado_por;
        $data['actualizadoEn'] = $config->updated_at;
        return $this->successResponse('Configuración de IA.', $data);
    }

    public function actualizar(Request $request, ConsumoIaService $consumo): JsonResponse
    {
        $data = $request->validate([
            'habilitado' => 'required|boolean', 'limiteDiarioGlobal' => 'required|integer|min:0|max:1000000000',
            'limiteMensualGlobal' => 'required|integer|gte:limiteDiarioGlobal|max:2000000000',
            'maxEntrada' => 'required|integer|min:1000|max:100000', 'maxSalida' => 'required|integer|min:256|max:6000',
            'perfiles' => 'required|array|max:1000',
            'perfiles.*.perfilId' => ['required', 'integer', 'distinct', Rule::exists('perfiles', 'id')->where('platform', 'web')],
            'perfiles.*.porcentaje' => 'required|numeric|decimal:0,2|min:0|max:100',
        ]);
        $total = array_sum(array_map(fn ($perfil) => (int) round($perfil['porcentaje'] * 100), $data['perfiles']));
        if ($total > 10000) {
            throw ValidationException::withMessages(['perfiles' => 'La suma de porcentajes no puede superar el 100%.']);
        }
        $values = ['actualizado_por' => $request->user()->id, 'updated_at' => now()];
        foreach (self::CAMPOS as $json => $columna) $values[$columna] = $data[$json];
        DB::transaction(function () use ($values, $data) {
            DB::table('ia_configuracion')->where('id', 1)->lockForUpdate()->first();
            DB::table('ia_configuracion')->where('id', 1)->update($values);
            DB::table('ia_presupuestos_perfil')->delete();
            foreach ($data['perfiles'] as $perfil) {
                DB::table('ia_presupuestos_perfil')->insert(['perfil_id' => $perfil['perfilId'],
                    'puntos_porcentaje' => (int) round($perfil['porcentaje'] * 100),
                    'created_at' => now(), 'updated_at' => now()]);
            }
        });
        return $this->obtener($consumo);
    }

    public function consumos(Request $request): JsonResponse
    {
        $data = $request->validate(['desde' => 'required|date_format:Y-m-d', 'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
            'userId' => 'nullable|integer|min:1', 'page' => 'sometimes|integer|min:1']);
        $query = DB::table('ia_consumos as c')->whereDate('c.created_at', '>=', $data['desde'])->whereDate('c.created_at', '<=', $data['hasta'])
            ->when($data['userId'] ?? null, fn ($q, $id) => $q->where('c.user_id', $id));
        $totales = (clone $query)->selectRaw('COALESCE(SUM(tokens_entrada),0) as inputTokens, COALESCE(SUM(tokens_salida),0) as outputTokens, COALESCE(SUM(tokens_contabilizados),0) as accountedTokens')->first();
        $usuarios = (clone $query)->leftJoin('gp_usuarios as u', 'u.id', '=', 'c.user_id')
            ->select('c.user_id as userId', 'u.USERNAME as usuario')
            ->selectRaw('SUM(c.tokens_entrada) as inputTokens, SUM(c.tokens_salida) as outputTokens, SUM(c.tokens_contabilizados) as accountedTokens, COUNT(*) as llamadas')
            ->groupBy('c.user_id', 'u.USERNAME')->orderByDesc('accountedTokens')->limit(200)->get();
        $registros = $query->leftJoin('gp_usuarios as u', 'u.id', '=', 'c.user_id')->leftJoin('perfiles as p', 'p.id', '=', 'c.perfil_id')->select([
            'c.id', 'c.perfil_id as perfilId', 'p.name as perfil', 'c.user_id as userId', 'u.USERNAME as usuario',
            'c.operacion', 'c.modelo', 'c.estado', 'c.tokens_entrada as inputTokens', 'c.tokens_salida as outputTokens',
            'c.tokens_contabilizados as accountedTokens', 'c.tokens_reservados as reservedTokens', 'c.created_at as fecha',
        ])->orderByDesc('c.id')->paginate(30);
        return $this->successResponse('Consumo de IA.', ['totales' => $totales, 'usuarios' => $usuarios,
            'totalRegistros' => $registros->total(),
            'registros' => $registros->items(), 'pagina' => $registros->currentPage(), 'ultimaPagina' => $registros->lastPage()]);
    }
}
