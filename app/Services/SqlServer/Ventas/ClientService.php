<?php

namespace App\Services\SqlServer\Ventas;

use App\Repositories\SqlServer\Venta\Contracts\ClientSqlRepositoryInterface;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientService implements ClientServiceInterface
{


    protected $clienTSqlRepository;
    public function __construct(ClientSqlRepositoryInterface $clienTSqlRepository)
    {
        $this->clienTSqlRepository = $clienTSqlRepository;
    }


    /**
     *  Valida si el cliente tiene un segmento asignado en la base de datos
     * @param mixed $ruc
     * @return object|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public function validateClientforRucSegment($ruc)
    {
        return $this->clienTSqlRepository->getCheckoutClientSegment($ruc);
    }


    public function getListSegments()
    {
        $segmentos =  $this->clienTSqlRepository->getQueryListSegments();
        if (empty($segmentos)) {
            throw new Exception("No se encontraron segmentos", 500);
        }
        return $segmentos
            ->unique('segmento')
            ->sortBy('segmento')
            ->map(function ($seg) {
                return [
                    'cot_artPadre' => $seg->co_atrPadre,
                    'segmento' => $seg->segmento,
                ];
            })->values()->all();
    }


    public function getLisSubSegmentos($segmento)
    {
        $subSegmentos =  $this->clienTSqlRepository->getQueryListSubSegmentsBySegment($segmento);
        if (empty($subSegmentos)) {
            throw new Exception("No se encontraron subsegmentos", 500);
        }
        return $subSegmentos
            ->sortBy('subsegmento')
            ->map(function ($subseg) {
                return [
                    'cot_artPadre' => $subseg->co_atrPadre,
                    'co_atrHijo' => $subseg->co_atrHijo,
                    'subsegmento' => $subseg->subsegmento,
                ];
            })->values()->all();
    }


    public function syncToSqlServerModifyOrCreateClient($params)
    {

        // 1. Deshabilitamos el query log para no acumular consultas en memoria
        DB::disableQueryLog();
        // Fuerza que no haya nulos en grupo/subgrupo
        $params['CLI_GRUPO']    = $params['CLI_GRUPO'] ?? '';
        $params['CLI_SUBGRUPO'] = $params['CLI_SUBGRUPO'] ?? '';

        $sql = <<<'SQL'
            SET NOCOUNT ON;
            EXEC MSW_SP_CREACION_MODIFICACION_CLIENTE
                        @ruc                                        = :ruc,
                        @razon_social                               = :razon_social,
                        @nombre_corto                               = :nombre_corto,
                        @clase_cliente                              = :clase_cliente,
                        @prioridad                                  = :prioridad,
                        @contacto                                   = :contacto,
                        @direccion1                                 = :direccion1,
                        @direccion2                                 = :direccion2,
                        @ciudad                                     = :ciudad,
                        @provincia                                  = :provincia,
                        @pais                                       = :pais,
                        @territorio                                 = :territorio,
                        @telefono1                                  = :telefono1,
                        @telefono2                                  = :telefono2,
                        @telefono3                                  = :telefono3,
                        @telefono4                                  = :telefono4,
                        @correo_electronico_sri                     = :correo_electronico_sri,
                        @correo_electronico_notificacion_interna    = :correo_electronico_notificacion_interna,
                        @vendedor                                   = :vendedor,
                        @grupo                                      = :grupo,
                        @subgrupo                                   = :subgrupo,
                        @descuento_comercial                        = :descuento_comercial,
                        @condicion_pago                             = :condicion_pago,
                        @lista_precio                               = :lista_precio,
                        @punto_venta                                = :punto_venta,
                        @tipo_id                                    = :tipo_id;
            SQL;

        $bindings = [
            'ruc'                                       => $params['CLI_RUC'],
            'razon_social'                              => $params['CLI_RAZON_SOCIAL'],
            'nombre_corto'                              => $params['CLI_NOMBRE_CORTO'],
            'clase_cliente'                             => $params['CLI_CLASE'],
            'prioridad'                                 => $params['CLI_PRIORIDAD'],
            'contacto'                                  => $params['CLI_CONTACTO'],
            'direccion1'                                => $params['CLI_DIRECCION_1'],
            'direccion2'                                => $params['CLI_DIRECCION_2'],
            'ciudad'                                    => $params['CLI_CIUDAD'],
            'provincia'                                 => $params['CLI_PROVINCIA'],
            'pais'                                      => $params['CLI_PAIS'],
            'territorio'                                => $params['CLI_TERRITORIO'],
            'telefono1'                                 => $params['CLI_TELEFONO_1'],
            'telefono2'                                 => $params['CLI_TELEFONO_2'],
            'telefono3'                                 => $params['CLI_TELEFONO_3'],
            'telefono4'                                 => $params['CLI_TELEFONO_4'],
            'correo_electronico_sri'                    => $params['CLI_CORREO_ELECT_SRI'],
            'correo_electronico_notificacion_interna'   => $params['CLI_CORREO_ELECT_NOTIFICACION_INTERNA'],
            'vendedor'                                  => $params['CLI_VENDEDOR'],
            'grupo'                                     => $params['CLI_GRUPO'],
            'subgrupo'                                  => $params['CLI_SUBGRUPO'],
            'descuento_comercial'                       => $params['CLI_DESC_COMERCIAL'],
            'condicion_pago'                            => $params['CLI_COND_PAGO'],
            'lista_precio'                              => $params['CLI_NIVEL_PRECIO'],
            'punto_venta'                               => $params['CLI_PUNTO_VENTA'],
            'tipo_id'                                   => $params['CLI_TIPO_ID'],
        ];

        // 4. Ejecutamos y traemos sólo la PRIMERA fila (selectOne es más ligero que select + foreach)
        $record = DB::connection('sqlsrvtuv')
            ->selectOne($sql, $bindings);

        // 5. Extraemos y retornamos ESTADO_TRANSACCION de forma segura
        $estado = $record->ESTADO_TRANSACCION ?? null;
        // 6. Logueamos sólo el estado (no el array completo)
        Log::channel('sql_errors')
            ->info("SP MSW_SP_CREACION_MODIFICACION_CLIENTE devolvió estado: {$estado}");
        return $estado;
    }


    public function sendToSegmentAndRucToSqlServer($ruc, $subsegmentoId)
    {

        try {
            DB::connection('sqlsrvdyn')
                ->table('tbr_atrcli')
                ->insert([
                    'tx_cli'    => $ruc,
                    'co_atrCli' => $subsegmentoId,
                ]);
        } catch (Throwable $e) {
            // Opcional: loguear el error para debugging
            Log::channel('sql_errors')->error("Error al insertar en tbr_atrcli (SQL Server): {$e->getMessage()}");
            throw $e;
        }
    }

    public function getValidateCLientWithSegment($ruc): bool
    {
        return  $this->clienTSqlRepository->checkQueryClientWithSegment($ruc);
    }
}
