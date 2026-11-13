<?php

namespace App\Http\Controllers\Sistemas\InteligenciaArtificial;

use App\Http\Controllers\Controller;
use App\Services\Sistemas\InteligenciaArtificial\ConsumoIaService;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionIaController extends Controller
{
    use JsonResponseTrait;

    private const CAMPOS = ['habilitado' => 'habilitado', 'limiteDiarioUsuario' => 'limite_diario_usuario',
        'limiteMensualUsuario' => 'limite_mensual_usuario', 'limiteMensualGlobal' => 'limite_mensual_global',
        'maxEntrada' => 'max_entrada', 'maxSalida' => 'max_salida'];

    public function obtener(ConsumoIaService $consumo): JsonResponse
    {
        $config = $consumo->configuracion();
        $data = [];
        foreach (self::CAMPOS as $json => $columna) $data[$json] = $json === 'habilitado' ? (bool) $config->$columna : (int) $config->$columna;
        $data['actualizadoPor'] = $config->actualizado_por;
        $data['actualizadoEn'] = $config->updated_at;
        return $this->successResponse('Configuración de IA.', $data);
    }

    public function actualizar(Request $request, ConsumoIaService $consumo): JsonResponse
    {
        $data = $request->validate([
            'habilitado' => 'required|boolean', 'limiteDiarioUsuario' => 'required|integer|min:0|max:1000000000',
            'limiteMensualUsuario' => 'required|integer|gte:limiteDiarioUsuario|max:1000000000',
            'limiteMensualGlobal' => 'required|integer|gte:limiteMensualUsuario|max:2000000000',
            'maxEntrada' => 'required|integer|min:1000|max:100000', 'maxSalida' => 'required|integer|min:256|max:6000',
        ]);
        $values = ['actualizado_por' => $request->user()->id, 'updated_at' => now()];
        foreach (self::CAMPOS as $json => $columna) $values[$columna] = $data[$json];
        DB::transaction(function () use ($values) {
            DB::table('ia_configuracion')->where('id', 1)->lockForUpdate()->first();
            DB::table('ia_configuracion')->where('id', 1)->update($values);
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
        $registros = $query->leftJoin('gp_usuarios as u', 'u.id', '=', 'c.user_id')->select([
            'c.id', 'c.user_id as userId', 'u.USERNAME as usuario',
            'c.operacion', 'c.modelo', 'c.estado', 'c.tokens_entrada as inputTokens', 'c.tokens_salida as outputTokens',
            'c.tokens_contabilizados as accountedTokens', 'c.tokens_reservados as reservedTokens', 'c.created_at as fecha',
        ])->orderByDesc('c.id')->paginate(30);
        return $this->successResponse('Consumo de IA.', ['totales' => $totales, 'usuarios' => $usuarios,
            'totalRegistros' => $registros->total(),
            'registros' => $registros->items(), 'pagina' => $registros->currentPage(), 'ultimaPagina' => $registros->lastPage()]);
    }
}
