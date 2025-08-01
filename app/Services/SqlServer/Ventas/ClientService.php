<?php

namespace App\Services\SqlServer\Ventas;

use App\Repositories\SqlServer\Venta\Contracts\ClientSqlRepositoryInterface;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use Exception;


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
            ->sortBy('segmento')
            ->map(function ($subseg) {
                return [
                    'co_atrHijo' => $subseg->co_atrHijo,
                    'subsegmento' => $subseg->subsegmento,
                ];
            })->values()->all();
    }
}
