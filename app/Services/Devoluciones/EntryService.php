<?php

namespace App\Services\Devoluciones;

use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;

class EntryService implements EntryServiceInterface
{
    public function registerEntity($type, $document, $detail, GpUsuario $user)
    {
        $document = $this->formatHeaderByType($type, $document);

        $entry = GpIngreso::updateOrCreate(
            [
                'tipo' => $type,
                'documento' => $document['document'],
            ],
            [
                'user_id' => $user->id,
                'USERID' => $user->USERID,
                'GUI_REM_GUIA_REMISION' => $document['GUI_REM_GUIA_REMISION'],
            ]
        );

        foreach ($detail as $key => $item) {

            $item = $this->formatDetailByType($type, $item);
            GpIngresosDetalle::updateOrCreate(
                [
                    'ingreso_id' => $entry->id,
                    'ART_CODIGO' => $item['ART_CODIGO'],
                ],
                [
                    'ingresado' => $item['ingresado'],
                ]
            );
        }
    }

    public function formatHeaderByType($type, $document)
    {
        return match ($type) {
            'devolucion' => array_merge($document, [
                'document' => $document['devolucion'] ?? null,
                'GUI_REM_GUIA_REMISION' => null,
            ]),
            'compra' => array_merge($document, [
                'document' => $document['ORDEN_COMPRA_DOCUMENTO'] ?? null,
                'GUI_REM_GUIA_REMISION' => null,
            ]),
            'traslado_reposicion', 'traslado_venta' => array_merge($document, [
                'document' => $document['pedido'] ?? null,
            ]),
            default => $document
        };
    }

    public function formatDetailByType($type, $detail)
    {
        return match ($type) {
            'devolucion' => array_merge($detail, [
                'ingresado' => $detail['devuelto'] ?? 0,
            ]),
            'compra' => array_merge($detail, [
                'ingresado' => $detail['seleccionado'] ?? 0,
                'ART_CODIGO' => $detail['ORDEN_COMPRA_DETALLE_ARTICULO']
            ]),
            'traslado_reposicion', 'traslado_venta' => array_merge($detail, [
                'ingresado' => $detail['recibido'],
                'ART_CODIGO' => $detail['GUI_REM_CODI_INTERNO']
            ]),
            default => $detail
        };
    }
}
