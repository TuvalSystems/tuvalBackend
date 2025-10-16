<?php

namespace App\Services\Venta;


use App\Jobs\Venta\Reservas\FanoutReservationRequest;
use App\Jobs\Venta\Reservas\NotifyReservationRequesterOrderCreatedJob;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Carbon\Carbon;
use App\Models\GpUsuario;
use App\Models\GpArticulo;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use Illuminate\Support\Facades\DB;
use App\Models\GpCotizacionDetalle;
use App\Models\reservas\GpTipoReservas;
use App\Models\GpCotizacionDetalleStock;
use Illuminate\Validation\ValidationException;
use App\Jobs\Venta\Reservas\SendDenyReservationNotification;
use App\Jobs\Venta\Reservas\SendApprovalReservationNotification;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Models\reservas\GpReserva;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class InventoryReservationService implements InventoryReservationServiceInterface
{
    protected $approvalService;
    protected $orderAndQuotationService;
    protected $quotationBuilder;
    protected $orderBuilder;
    protected $invoiceService;
    protected $stockWarehouseService;
    protected $reservaBuilder;


    public function __construct(
        ApprovalServiceInterface $approvalService,
        OrderAndQuotationServiceInterface $orderAndQuotationServiceInterface,
        QuotationBuilderInterface $quotationBuilderInterface,
        OrderBuilderInterface $orderBuilderInterface,
        InvoiceServiceInterface $invoiceServiceInterface,
        StockWarehouseServiceInterface $stockWarehouseService,
        ReservedBuilderInterface $reservedBuilderInterface
    ) {
        $this->approvalService = $approvalService;
        $this->orderAndQuotationService = $orderAndQuotationServiceInterface;
        $this->quotationBuilder = $quotationBuilderInterface;
        $this->orderBuilder = $orderBuilderInterface;
        $this->invoiceService = $invoiceServiceInterface;
        $this->stockWarehouseService = $stockWarehouseService;
        $this->reservaBuilder = $reservedBuilderInterface;
    }


    public function hasAnyReserveState(int $quotationId)
    {
        return GpReserva::where('cotizacion_id', $quotationId)->where('estado', '!=', '')->exists();
    }


    public function saveQuotationHeaderReserved(array $data, GpUsuario $user)
    {

        $reserved = $this->reservaBuilder->fromPayloadReserved($data, $user);
        $reserved->save();
        DB::afterCommit(function () use ($data, $user) {
            FanoutReservationRequest::dispatch(
                $data['cotizacion_id'],
                $user->id
            )->onQueue('notifications');
        });
    }



    public function fetchListTipeReserved()
    {
        return GpTipoReservas::where('estado', 1)->get();
    }


    public function fetchListReservedPending($perpage, $search, $sortBy = 'created_at', $sortOrder = 'desc', $tipoReserva = null, $ptoVtaId = null, $clienteRuc)
    {
        $query = GpReserva::select([
            'gp_reservas.id',
            'gp_cotizacions.id as cotizacion_id',
            'gp_cotizacions.cotizacion',
            'gp_cotizacions.PTO_VTA_DESCRIPCION',
            'gp_cotizacions.CLI_RAZON_SOCIAL',
            'gp_cotizacions.created_at',
            'gp_cotizacions.total',
            'gp_reservas.fe_solicitud_reserva',
            'gp_reservas.estado',
            'gp_tipo_reservas.id as tipo_reserva_id',
            'gp_tipo_reservas.descripcion as tipo_reserva',
            'gp_reserva_tipo_traslado.descripcion as tipo_traslado',
            'gp_reservas.obs_solicitud_reserva',
            'gp_usuarios.USERID as usuario_solicita',
        ])->from('gp_reservas')
            ->join('gp_cotizacions', 'gp_reservas.cotizacion_id', '=', 'gp_cotizacions.id')
            ->join('gp_usuarios', 'gp_reservas.user_ingreso_reserva_id', '=', 'gp_usuarios.id')
            ->join('gp_tipo_reservas', 'gp_reservas.tipo_reserva_id', '=', 'gp_tipo_reservas.id')
            ->leftjoin('gp_reserva_tipo_traslado', 'gp_reservas.tipo_traslado_reserva_id', '=', 'gp_reserva_tipo_traslado.id')
            ->where('gp_reservas.estado', 'pendiente');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_cotizacions.cotizacion', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.PTO_VTA_DESCRIPCION', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.CLI_RUC', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.CLI_RAZON_SOCIAL', 'like', '%' . $search . '%');
            });
        }
        if ($clienteRuc) {
            $query->where('gp_cotizacions.CLI_RUC', $clienteRuc);
        }
        if ($ptoVtaId) {
            $query->where('gp_cotizacions.PTO_VTA_ID', $ptoVtaId);
        }
        if ($tipoReserva) {
            $query->where('gp_reservas.tipo_reserva_id', (int) $tipoReserva);
        }


        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_cotizacions.cotizacion');
        }

        return $query->paginate($perpage);
    }






    public function getQuotationWithDetails(int $id)
    {
        $reserva = GpReserva::with(['cotizacion', 'cotizacion.detallecotizacion', 'tipoReserva', 'tipoTraslado', 'usuarioIngreso'])->find($id);
        // $cotizacion = GpCotizacion::with('detallecotizacion', 'tiporeserva','reserva')->find($id);
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($reserva->cotizacion->CLI_RUC);
        return [
            'reserva' => $reserva,
            'segmento' => $segmento
        ];
    }


    public function getQuotationWithDetailsAndStock(int $id)
    {

        $reserva = GpReserva::with(['cotizacion', 'tipoTraslado', 'tipoReserva', 'usuarioIngreso'])->find($id);

        $cotizacion = GpCotizacion::with([
            'detallecotizacion' => function ($q) {
                $q->select([
                    'id',
                    'cotizacion_id',
                    'ART_CODIGO',
                    'ART_CODIGO_TRES_LETRAS',
                    'ART_DESCRIPCION',
                    'ART_PLAN_UMEDIDA',
                    'ART_PESO',
                    'ART_TIPO_COMPRA',
                    'ART_CANTIDAD'
                ]);
            },
            'detallecotizacion.stock' => function ($q) {
                $q->select(['id', 'detalle_id', 'cotizacion_id', 'STO_ART_BODEGA', 'seleccionado'])
                    ->where('seleccionado', '>', 0);
            },
            'detallecotizacion.articulo:id,ART_CODIGO_ZAFIRO'
        ])->find($reserva->cotizacion_id);
        $rows = $cotizacion->detallecotizacion->flatMap(function ($detalle) use ($cotizacion) {
            if (in_array($detalle->ART_TIPO_COMPRA, ['SERV', 'L'])) {
                return [
                    [
                        'detalle_id' => $detalle->id,
                        'stock_id' => null,
                        'ART_CODIGO' => $detalle->ART_CODIGO,
                        'ART_CODIGO_TRES_LETRAS' => $detalle->ART_CODIGO_TRES_LETRAS,
                        'ART_DESCRIPCION' => $detalle->ART_DESCRIPCION,
                        'ART_PLAN_UMEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                        'ART_PESO' => $detalle->ART_PESO,
                        'STO_ART_BODEGA' => $cotizacion->BOD_BODEGA,
                        'ART_TIPO_COMPRA' => $detalle->ART_TIPO_COMPRA,
                        'ART_CANTIDAD' => $detalle->ART_CANTIDAD,
                    ]
                ];
            }
            return $detalle->stock->map(function ($stock) use ($detalle) {
                return [
                    'detalle_id' => $detalle->id,
                    'stock_id' => $stock->id,
                    'ART_CODIGO' => $detalle->ART_CODIGO,
                    'ART_CODIGO_TRES_LETRAS' => $detalle->ART_CODIGO_TRES_LETRAS,
                    'ART_DESCRIPCION' => $detalle->ART_DESCRIPCION,
                    'ART_PLAN_UMEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                    'ART_PESO' => $detalle->ART_PESO,
                    'STO_ART_BODEGA' => $stock->STO_ART_BODEGA,
                    'ART_TIPO_COMPRA' => $detalle->ART_TIPO_COMPRA,
                    'ART_CANTIDAD' => $stock->seleccionado,
                ];
            });
        })->values();
        $segmento = $this->orderAndQuotationService->getSegmentoCliente($cotizacion->CLI_RUC);

        return [
            'reserva' => $reserva,
            'rows' => $rows,
            'segmento' => $segmento
        ];
    }

    public function getQuotationWithDetailsAndActiveStockById(int $id)
    {
        $reserva = GpReserva::with([
            'cotizacion',
            'cotizacion.detallecotizacion',
            'cotizacion.detallecotizacion.stock' => function ($q) {
                $q->where('seleccionado', '>', 0);
            },
            'cotizacion.detallecotizacion.articulo',
            'cotizacion.detallecotizacion.proveedores',
            'tipoTraslado',
            'tipoReserva',
            'usuarioIngreso'
        ])->find($id);

        $segmento = $this->orderAndQuotationService->getSegmentoCliente($reserva->cotizacion->CLI_RUC);
        $quoteList = $this->getQuotationsCreatedByReservedQuotationOrigin($id);
        $list = $quoteList->filter()->unique()->values()->implode(', ');
        return [
            'reserva' => $reserva,
            'segmento' => $segmento,
            'quoteList' => $list
        ];
    }

    public function getQuotationsCreatedByReservedQuotationOrigin(int $id)
    {
        return GpCotizacionReservaLog::query()->from('gp_cotizacion_reserva_logs as gprs')
            ->select('gpd.cotizacion')
            ->join('gp_cotizacions as gpd', 'gpd.id', '=', 'gprs.cotizacion_destino_id')
            ->join('gp_reservas', 'gp_reservas.id', '=', 'gprs.reserva_id')->where('gp_reservas.id', $id)->pluck('gpd.cotizacion');
    }


    public function denyApplicationReservedQuotation(array $data, $gpUser)
    {
        GpReserva::find($data['id'])->update([
            'estado' => 'rechazada',
            'estado_autorizacion_reserva' => 0,
            'fe_autorizacion_reserva' => $data['fe_autorizacion_reserva'] ?? Carbon::now()->format('Y-m-d'),
            'obs_autorizacion_reserva' => $data['obs_autorizacion_reserva'] ?? 'Sin observaciones',
            'user_autorizacion_reserva' => $gpUser->id,
            'fe_ingreso_autorizacion_reserva' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        $reserva = GpReserva::find($data['id']);
        $this->cleanStockQuotationForDeny($reserva->cotizacion_id);

        DB::afterCommit(function () use ($reserva, $gpUser) {
            SendDenyReservationNotification::dispatch($reserva->id, $gpUser->id);
        });

        return $reserva;
    }


    public function approvalApplicationReservedQuotation(array $data, $gpUser)
    {
        $reserva = GpReserva::find($data['id']);
        $estado = ($reserva->tipo_traslado_reserva_id == 1) ? 'aprobado' : 'traslado';


        // UPDATE idempotente (solo si aún no fue autorizada)
        $affected = GpReserva::query()
            ->whereKey($reserva->id)
            ->where('estado_autorizacion_reserva', '=', 0)
            ->update([
                'estado' => $estado,
                'estado_autorizacion_reserva' => 1,
                'fe_autorizacion_reserva' => Arr::get($data, 'fe_autorizacion_reserva'),
                'obs_autorizacion_reserva' => Arr::get($data, 'obs_autorizacion_reserva'),
                'user_autorizacion_reserva_id' => $gpUser->id,
                'fe_ingreso_autorizacion_reserva' => now()->format('Y-m-d H:i:s'),
            ]);

        if ($affected > 0) {
            if ($reserva->tipo_traslado_reserva_id == 1) {
                $this->syncSelectedToAuthorizedForQuotation($reserva->cotizacion_id);
            } else {
                $this->syncSelectedToAuthorizedAndTransferredForQuotation($reserva->cotizacion_id);
            }
        }

        DB::afterCommit(callback: function () use ($reserva, $gpUser) {
            SendApprovalReservationNotification::dispatch($reserva->cotizacion_id, $gpUser->id);
        });

        return [
            'reserva_id' => $reserva->id,
            'cotizacion_id' => $reserva->cotizacion_id,
            'updated' => (bool) $affected,
            'tipo_traslado_reserva_id' => $reserva->tipo_traslado_reserva_id,
            'estado_set' => $estado,
        ];
    }

    //sin traslado

    public function syncSelectedToAuthorizedForQuotation(int $quotationId)
    {
        $sameWhaAffected = GpCotizacionDetalleStock::query()
            ->where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->update([
                'cantidad_autorizada_reserva' => DB::raw('seleccionado'),
                'updated_at' => now(),
            ]);
        Log::channel('reservas_log')->info("Se actualizaron {$sameWhaAffected} registros en 'cantidad_autorizada_reserva' para cotización ID {$quotationId}");
    }

    //con traslado
    public function syncSelectedToAuthorizedAndTransferredForQuotation(int $quotationId)
    {
        $warehouse = GpCotizacion::whereKey($quotationId)
            ->lockForUpdate()
            ->value('BOD_BODEGA');
        $sameWhaAffected = GpCotizacionDetalleStock::query()
            ->where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->where('STO_ART_BODEGA', $warehouse)
            ->update([
                'cantidad_autorizada_reserva' => DB::raw('seleccionado'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        $otherWhaAffected = GpCotizacionDetalleStock::query()
            ->where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->where('STO_ART_BODEGA', '!=', $warehouse)
            ->update([
                'cantidad_autorizada_reserva' => DB::raw('seleccionado'),
                'cantidad_traslado' => DB::raw('seleccionado'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        Log::channel('reservas_log')->info("Se actualizaron {$sameWhaAffected} registros en 'cantidad_autorizada_reserva' y {$otherWhaAffected} en 'cantidad_traslado' para cotización ID {$quotationId}");
    }




    /** funciones para la consolidacion y movimiento de stock a bodegas, comentadas porque aun no se usan por ahora */
    // public function updateQuotationDetatilStockMovements(GpCotizacion $quotation, $type)
    // {
    //     $bodegaDespacho = $quotation->BOD_BODEGA;
    //     $detailIds = GpCotizacionDetalle::where('cotizacion_id', $quotation->id)
    //         ->pluck('id');

    //     $stocksByDetail = GpCotizacionDetalleStock::whereIn('detalle_id', $detailIds)
    //         ->lockForUpdate()
    //         ->get()
    //         ->groupBy('detalle_id');

    //     foreach ($stocksByDetail as $detailId => $rows) {

    //         //bodega despacho
    //         $own = $rows->firstWhere('STO_ART_BODEGA', $bodegaDespacho);
    //         if (!$own) {
    //             throw new \DomainException(
    //                 "No existe fila de stock en bodega de despacho [$bodegaDespacho] para detalle $detailId"
    //             );
    //         }
    //         //otras bodegas
    //         $others = $rows->where('STO_ART_BODEGA', '!=', $bodegaDespacho)
    //             ->filter(fn($r) => (float)$r->seleccionado > 0);


    //         // Suma exacta a 2 decimales (si tus datos son enteros igual aplica)
    //         $cents = 0;
    //         foreach ($others as $r) {
    //             $cents += (int) round(((float)$r->seleccionado) * 100);
    //         }
    //         $moveQty = number_format($cents / 100, 2, '.', '');

    //         //actualizar los seleccionado de otras bodegas a 0
    //         $othersIds = $others->pluck('id')->all();
    //         if (!empty($othersIds)) {
    //             if ($type === 'reserva') {
    //                 GpCotizacionDetalleStock::whereIn('id', $othersIds)
    //                     ->update([
    //                         'seleccionado' => 0,
    //                         'cantidad_autorizada_reserva' => 0
    //                     ]);
    //             } else {
    //                 GpCotizacionDetalleStock::whereIn('id', $othersIds)
    //                     ->update([
    //                         'seleccionado' => 0,
    //                     ]);
    //             }
    //         }
    //         $ownSelected = (float) ($own->seleccionado ?? 0);
    //         $finalSelected = $ownSelected + $moveQty;
    //         //sumar el consolidado al seleccionado de la bodega de despacho
    //         GpCotizacionDetalleStock::where('id', $own->id)->update([
    //             'seleccionado'                => $finalSelected,
    //             'cantidad_autorizada_reserva' => $finalSelected,
    //         ]);
    //     }
    // }




    public function cleanStockQuotationForDeny($quotationId)
    {
        $ids = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->lockForUpdate()
            ->pluck('id');

        if ($ids->isEmpty())
            return 0;

        // 2) Update masivo solo a esas filas
        $affected = GpCotizacionDetalleStock::whereIn('id', $ids)
            ->update(['seleccionado' => 0]);
        return $affected;
    }




    public function fetchListApprovedQuotation($perPage, $search, $sortBy = 'created_at', $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $estado = null, $ptoVtaId = null, $clienteRuc, $userId)
    {

        $query = GpReserva::select([
            'gp_reservas.id',
            'gp_cotizacions.cotizacion',
            'gp_cotizacions.PTO_VTA_DESCRIPCION',
            'gp_cotizacions.CLI_RAZON_SOCIAL',
            'gp_cotizacions.created_at',
            'gp_cotizacions.total',
            'gp_reservas.fe_solicitud_reserva',
            'gp_reservas.estado',
            'gp_tipo_reservas.id as tipo_reserva_id',
            'gp_tipo_reservas.descripcion as tipo_reserva',
            'gp_reserva_tipo_traslado.descripcion as tipo_traslado',
            'gp_reservas.obs_solicitud_reserva',
            'gp_reservas.fe_autorizacion_reserva',
        ])->from('gp_reservas')
            ->join('gp_tipo_reservas', 'gp_reservas.tipo_reserva_id', '=', 'gp_tipo_reservas.id')
            ->join('gp_reserva_tipo_traslado', 'gp_reservas.tipo_traslado_reserva_id', '=', 'gp_reserva_tipo_traslado.id')
            ->join('gp_usuarios', 'gp_reservas.user_ingreso_reserva_id', '=', 'gp_usuarios.id')
            ->join('gp_cotizacions', 'gp_reservas.cotizacion_id', '=', 'gp_cotizacions.id')
            ->where('gp_reservas.user_ingreso_reserva_id', $userId);

        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_cotizacions.created_at', '>=', $fecha_desde)
                ->where('gp_cotizacions.created_at', '<=', $fecha_hasta);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_cotizacions.cotizacion', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.PTO_VTA_DESCRIPCION', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.CLI_RUC', 'like', '%' . $search . '%')
                    ->orWhere('gp_cotizacions.CLI_RAZON_SOCIAL', 'like', '%' . $search . '%');
            });
        }

        if ($clienteRuc) {
            $query->where('gp_cotizacions.CLI_RUC', $clienteRuc);
        }
        if ($estado) {
            $query->where('gp_reservas.estado', $estado);
        }
        if ($ptoVtaId) {
            $query->where('gp_cotizacions.PTO_VTA_ID', $ptoVtaId);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_cotizacions.cotizacion');
        }

        return $query->paginate($perPage)->through(function ($row) {
            $row->cot_created_at = Carbon::parse($row->created_at)->timezone('America/Guayaquil')
                ->format('Y-m-d H:i:s');
            return $row;
        });
    }



    /**
     *Liberar o facturar cotización reservada
     */

    public function processReservedQuotation(array $payload, $user)
    {
        return DB::transaction(function () use ($payload, $user) {
            $items = collect(data_get($payload, 'cotizacion.detallecotizacion', []))->flatMap(function ($det) {
                $d = is_array($det) ? $det : (array) $det;
                return collect(data_get($d, 'stock', []))->map(function ($st) use ($d) {
                    $s = is_array($st) ? $st : (array) $st;
                    return [
                        'detalle_id' => $d['id'],
                        'stock_id' => $s['id'],
                        'STO_ART_BODEGA' => $s['STO_ART_BODEGA'],
                        'ART_CODIGO' => $d['ART_CODIGO'],
                        'to_cancel' => $s['a_cancelar'],
                        'to_quote' => $s['a_cotizar'],
                    ];
                });
            })->filter(fn($i) => $i['to_cancel'] > 0 || $i['to_quote'] > 0)->values();

            if ($items->isEmpty()) {
                throw new \Exception('No se encontraron items para liberar o cotizar');
            }

            $stocksIds = $items->pluck('stock_id')->toArray();
            $rows = GpCotizacionDetalleStock::whereIn('id', $stocksIds)->lockForUpdate()->get()->keyBy('id');
            $toQuote = [];
            $totalLiberado = 0.0;
            $totalCotizar = 0.0;
            foreach ($items as $itm) {
                $row = $rows[$itm['stock_id']];
                if (!$row) {
                    throw ValidationException::withMessages(["stock:{$itm['stock_id']}" => 'Stock no existe.']);
                }
                $validatedQty = $itm['to_cancel'] + $itm['to_quote'];
                $decrease = $itm['to_cancel'];
                if ($validatedQty <= 0) {
                    continue;
                }
                $currentSelected = (float) $row->seleccionado;
                if ($currentSelected < $validatedQty) {
                    throw ValidationException::withMessages([
                        "stock:{$itm['stock_id']}" => "Cantidad solicitada ({$validatedQty}) excede lo seleccionado ({$currentSelected})."
                    ]);
                }

                $affected = DB::table($row->getTable())
                    ->where('id', $row->id)
                    ->where('seleccionado', '>=', $decrease)
                    ->decrement('seleccionado', $decrease);

                if ($affected === 0 && $decrease > 0) {
                    $freshSelected = (float) DB::table($row->getTable())->where('id', $row->id)->value('seleccionado');
                    throw ValidationException::withMessages([
                        "stock:{$itm['stock_id']}" => "Cantidad solicitada ({$decrease}) excede lo seleccionado ({$freshSelected})."
                    ]);
                }

                $row->seleccionado = $currentSelected - $decrease;
                if ($itm['to_cancel'] > 0) {
                    $this->toCancelStockQuotationReserved($row, $itm, $user);
                    $totalLiberado += $itm['to_cancel'];
                }

                if ($itm['to_quote'] > 0) {
                    $this->toInvoiceStockQuotationReserved($row, $itm, $user);
                    $totalCotizar += $itm['to_quote'];
                    $toQuote[] = [
                        'detalle_id' => $itm['detalle_id'],
                        'stock_id' => $itm['stock_id'],
                        'STO_ART_BODEGA' => $itm['STO_ART_BODEGA'],
                        'ART_CODIGO' => $itm['ART_CODIGO'],
                        'cantidad' => $itm['to_quote'],
                    ];
                }
            }

            $new = null;
            if (!empty($toQuote)) {
                $new = $this->createQuotationReserveWithDetailAndStock($payload, $toQuote, $user);
                $this->recalculateQuotation($new->id); // para recacular la cotizacion
            }
            $this->closeReservationIfEmpty(Arr::get($payload, 'id'));
            return [
                'cotizacion' => $new->cotizacion ?? null,
                'liberado_total' => $totalLiberado,
                'facturado_total' => $totalCotizar,
                'lineas_facturar' => count($toQuote)
            ];
        });
    }



    public function toCancelStockQuotationReserved($row, $itm, $user)
    {
        DB::table($row->getTable())->where('id', $row->id)->increment(
            'cantidad_cancelada',
            $itm['to_cancel']
        );
        $this->storeQuotationReservedStockLogs($row, $user, 'liberar', $itm['to_cancel']);
    }

    public function toInvoiceStockQuotationReserved($row, $itm, $user)
    {
        DB::table($row->getTable())->where('id', $row->id)->increment(
            'cantidad_cotizada',
            $itm['to_quote']
        );
        $this->storeQuotationReservedStockLogs($row, $user, 'cotizar', $itm['to_quote']);
    }

    public function storeQuotationReservedStockLogs($row, $user, $type, $qty)
    {
        DB::table('gp_cotizacion_detalle_stocks_logs')->insert([
            'stock_id' => $row->id,
            'user_id' => $user->id,
            'tipo' => $type,
            'cantidad' => $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function createQuotationReserveWithDetailAndStock(array $payload, array $linesToQuote, GpUsuario $user)
    {
        // proceso de creación de nueva cotizacion
        $linesGroupedDetail = collect($linesToQuote)->groupBy('detalle_id');
        $linesGroupStock = collect($linesToQuote)->groupBy('stock_id');

        $linesDetailIds = collect($linesToQuote)->pluck('detalle_id')->unique()->toArray();
        $lineStocksIds = collect($linesToQuote)->pluck('stock_id')->toArray();

        $oldDetails = GpCotizacionDetalle::whereIn('id', $linesDetailIds)->get();
        $oldStocks = GpCotizacionDetalleStock::whereIn('id', $lineStocksIds)->orderBy('detalle_id')->get()
            ->groupBy('detalle_id');


        $cotizacion = $this->createHeaderQuotationReserve($payload, $user);
        $this->createDetailAndStockQuotationReserve($cotizacion, $linesGroupedDetail, $linesGroupStock, $oldStocks, $oldDetails);
        //al final por si acaso xd
        $this->storeQuotationReservedLogs($payload['id'], $payload['cotizacion']['id'], $cotizacion->id, $payload['cotizacion']['BOD_BODEGA'], $linesToQuote, $user);
        return $cotizacion;
    }


    public function createHeaderQuotationReserve(array $payload, GpUsuario $user)
    {
        $cotOld = [];
        $cotOld = Arr::get($payload, 'cotizacion') ?? [];
        $cotOld['registro_copia'] = null;
        $cotOld['cotizacion'] = Arr::get($payload, 'cotizacion.detallecotizacion');
        $requiereAprobacionComercial = $this->approvalService->checkOutApprovalCommercial($cotOld, 'cotizacion');
        $requiereAprobacionComercial['estado'] = $requiereAprobacionComercial['requiere'] === 'yes' ? 'espera' : 'aprobado';
        $requiereAprobacionComercial['aprobado'] = 'no';
        $cotizacion = $this->quotationBuilder->fromPayloadReservedToQuotation($cotOld, $requiereAprobacionComercial, $user, 'reserva');
        $cotizacion->save();
        return $cotizacion;
    }


    public function createDetailAndStockQuotationReserve(GpCotizacion $cotizacion, $linesGroupedDetail, $linesGroupStock, $oldStocks, $oldDetails)
    {
        foreach ($oldDetails as $oldDetail) {
            $line = $linesGroupedDetail->get($oldDetail->id);
            if (empty($line)) {
                continue;
            }
            $totalLines = collect($line)->sum('cantidad');
            $newDetail = $this->quotationBuilder->formPayloadReservedToDetail($cotizacion->id, $oldDetail, $totalLines);
            $newDetail->save();
            $oldStocksByDetail = $oldStocks->get($oldDetail->id);
            foreach ($oldStocksByDetail as $stkOld) {
                $lineStock = $linesGroupStock->get($stkOld->id)->first();
                if (empty($lineStock)) {
                    continue;
                }
                $newStock = $this->quotationBuilder->formPayloadReservedToDetaiLStock($cotizacion->id, $newDetail->id, $stkOld, $lineStock);
                $newStock->save();
            }
        }
    }


    public function storeQuotationReservedLogs($reservedId, $quotationIdOld, $quotationIdNew, $bodega, $linesToQuote, $user)
    {
        $ctRsLog = $this->reservaBuilder->fromPayloadQuoteReservedLog($reservedId, $quotationIdOld, $quotationIdNew, $user->id, $bodega);
        $ctRsLog->save();

        foreach ($linesToQuote as $value) {
            $detail = $this->reservaBuilder->fromPayloadQuoteReservedDetailLog($ctRsLog->id, $value['stock_id'], $value['ART_CODIGO'], $value['STO_ART_BODEGA'], $value['cantidad']);
            $detail->save();
        }
    }


    public function recalculateQuotation($quotationId)
    {
        $qt = GpCotizacion::where('id', $quotationId)->first();
        $qtds = GpCotizacionDetalle::where('cotizacion_id', $qt->id)->get();
        if ($qtds->isEmpty()) {
            return;
        }
        $articulosImpuesto = GpArticulo::whereIn('ART_CODIGO', $qtds->pluck('ART_CODIGO'))->pluck('ART_IMP_VTA', 'ART_CODIGO');
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $netSubtotal = 0.0;
        $totalIva = 0.0;

        foreach ($qtds as $item) {
            $valorUnitario = (float) $item->ART_VALOR;
            $cantidad = (float) $item->ART_CANTIDAD;
            $descuentoUnitario = (float) $item->ART_DESCUENTO;

            $lineaSubtotal = $valorUnitario * $cantidad;
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineNet = round(($valorUnitario - $descuentoUnitario) * $cantidad, 2);
            $impuestoPorcentaje = (float) $articulosImpuesto[$item->ART_CODIGO] ?? 0;
            $lineaIva = round($lineNet * ($impuestoPorcentaje / 100), 2);

            $subtotal += $lineaSubtotal;
            $totalDiscount += $lineaDiscount;
            $netSubtotal += $lineNet;
            $totalIva += $lineaIva;

            $item->ART_IMP_VTA = $impuestoPorcentaje;
            $item->ART_IVA = round($lineaIva, 2);
            $item->save();
        }
        $total = $netSubtotal + $totalIva;

        $qt->subtotal = round($subtotal, 2);
        $qt->descuento = round($totalDiscount, 2);
        $qt->subtotalmenosdescuento = round($netSubtotal, 2);
        $qt->iva = round($totalIva, 2);
        $qt->total = round($total, 2);
        $qt->save();
        return $qt;
    }


    public function closeReservationIfEmpty($reserveId)
    {
        $reserve = GpReserva::find($reserveId);
        if (empty($reserve)) {
            return false;
        }
        $hayPendientes = GpCotizacionDetalleStock::where('cotizacion_id', $reserve->cotizacion_id)
            ->where('seleccionado', '>', 0)
            ->exists();
        if ($hayPendientes) {
            return false;
        }

        $affected = GpCotizacion::query()
            ->whereKey($reserve->cotizacion_id)
            ->where('estado', '!=', 'procesado')
            ->update([
                'estado' => 'procesado',
                'updated_at' => now(),
            ]);

        // Idempotente para la reserva
        if ($reserve->estado !== 'procesado') {
            $reserve->update([
                'estado' => 'procesado',
                'updated_at' => now(),
            ]);
        }

        return $affected > 0;
    }


    /**
     * Summary of processReservedQuotationInvoiceDemand  proceso factura demanada
     * @param mixed $payload
     * @param mixed $user
     */
    public function processReservedQuotationInvoiceDemand($payload, $user)
    {
        $this->updateQuotationStocksAuthorizedDontConsolidate($payload['cotizacion']['id']);
        $order = $this->storeReservedHeaderOrder($payload['cotizacion'], $user, $payload['fe_autorizacion_reserva']);
        $this->storeReservedDetailOrder($payload['cotizacion']['detallecotizacion'], $order);
        $rows = $this->formPayloadReservedQuotationLog($payload['id'], $payload['cotizacion'], $order, $user->id);

        $this->insertReservedOrderLogs($rows);
        $this->updateQuotationReservedStatus($payload['id'], $payload, $user);
        $this->cleanStockQuotationForDeny($payload['cotizacion']['id']);

        DB::afterCommit(function () use ($order, $payload) {
            NotifyReservationRequesterOrderCreatedJob::dispatch($order->pedido, $payload['user_ingreso_reserva_id']);
            $this->invoiceService->checkSendInvoice($order);
        });
        return $order;
    }


    public function updateQuotationStocksAuthorizedDontConsolidate(int $quotationId)
    {
        $stocks = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)
            ->lockForUpdate()
            ->get();
        foreach ($stocks as $stock) {
            $stock->update([
                'cantidad_autorizada_reserva' => $stock->seleccionado,
                'cantidad_facturada' => $stock->seleccionado
            ]);
        }
    }


    public function storeReservedHeaderOrder($payload, $user, $dateAuthoReserved)
    {

        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $payload['PTO_VTA_ID'])->first();
        $approvalComercial = $this->approvalService->checkOutApprovalCommercialReservedOrder($payload);
        $approvalCredit = $this->approvalService->checkoutApprovalCredit($payload);

        $requieresApproval = $approvalCredit != 'no';
        $motivoCrediticio = $requieresApproval ? $approvalCredit : null;
        $requiereAprobacionCrediticia = $requieresApproval ? 'yes' : 'no';

        $fechaEntrega = $payload['despacho'] == 'Despacho en ruta' ? $payload['fecha_entrega'] : null;
        $bodega = $payload['BOD_BODEGA'];
        $newOrder = $this->orderBuilder->fromPayloadOrder($payload['id'], $payload, $approvalComercial, $user, $puntoVenta, $requiereAprobacionCrediticia, $motivoCrediticio, $fechaEntrega, 'reserva', $dateAuthoReserved, $bodega);
        $newOrder->save();
        return $newOrder;
    }

    public function storeReservedDetailOrder($detailOrder, $order)
    {
        $usedItemLExcluded = [];
        foreach ($detailOrder as $i => $item) {
            $isTypeL = $item['ART_TIPO_COMPRA'] === 'L';
            $hasStock = $this->stockWarehouseService->checkValidateDisponibleItemL($item['ART_CODIGO']);
            if ($isTypeL && !$hasStock) {
                $replacementCodeL = $this->stockWarehouseService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
                if (!$replacementCodeL) {
                    throw new \DomainException("No se encontró un artículo tipo L disponible para el código {$item['ART_CODIGO']}");
                }
                $item['ART_CODIGO'] = $replacementCodeL;
                $usedItemLExcluded[] = $replacementCodeL;
            }
            $this->stockWarehouseService->markArticleUnavailable($item['ART_CODIGO']);
            $newOrderDetail = $this->orderBuilder->formPayloadOrderDetail($order->id, $item, $i);
            $newOrderDetail->save();

            if ($item['proveedores']) {
                $this->storeReservedDetailOrderProviders($item['proveedores'], $order->id, $newOrderDetail->id);
            }
            if ($item['stock']) {
                $currentStocks = $this->fetchCurrentStocksForQuotationDetail($item['id']);
                $this->storeReservedDetailOrderStock($currentStocks, $order, $newOrderDetail);
            }
        }
    }
    public function fetchCurrentStocksForQuotationDetail($detailId)
    {
        return GpCotizacionDetalleStock::where('detalle_id', $detailId)
            ->lockForUpdate()
            ->get();
    }

    public function storeReservedDetailOrderProviders($providers, $orderId, $detailOrderId)
    {

        foreach ($providers as $prov) {
            $recibido = isset($prov['recibido']) ? $prov['recibido'] : 'no';
            $newProvider = $this->orderBuilder->formPayloadOrderDetailProvider($prov, $orderId, $detailOrderId, $recibido);
            $newProvider->save();
        }
    }

    public function storeReservedDetailOrderStock($stock, $order, $detailOrder)
    {

        $requiereAprobacion = $order->requiere_aprobacion_crediticia === 'yes'
            || $order->requiere_aprobacion_comercial === 'yes';
        foreach ($stock as $sto) {

            $newFormatStock = [
                'STO_ART_STOCK_DISPONIBLE' => $sto['STO_ART_STOCK_DISPONIBLE'],
            ];
            if ($sto['seleccionado'] > 0) {
                $newFormatStock = $this->orderAndQuotationService->getAndValidateOrderDetailStock($detailOrder, $sto, $order->id, $order->cotizacion_id);
                if (!$newFormatStock) {
                    throw new \DomainException("No se pudo obtener el stock del artículo {$detailOrder->ART_CODIGO} en la bodega {$sto['STO_ART_BODEGA']}");
                }
                if ($newFormatStock['STO_ART_STOCK_DISPONIBLE'] < $sto['seleccionado']) {
                    throw new \DomainException("El stock disponible del artículo {$detailOrder['ART_CODIGO']} en la bodega {$sto['STO_ART_BODEGA']} es insuficiente para la cantidad seleccionada.");
                }
            }
            $reservado = $requiereAprobacion ? 0 : $sto['seleccionado'];
            $newStock = $this->orderBuilder->formPayloadOrderDetailStock($sto, $order->id, $detailOrder->id, $newFormatStock['STO_ART_STOCK_DISPONIBLE'], $reservado);
            $newStock->save();
        }
    }



    public function updateQuotationReservedStatus(int $reservedId, $payload, GpUsuario $user)
    {
        GpCotizacion::whereKey($payload['cotizacion_id'])->update(['estado' => 'procesado']);
        return GpReserva::whereKey($reservedId)
            ->update([
                'estado_autorizacion_reserva' => true,
                'fe_autorizacion_reserva' => $payload['fe_autorizacion_reserva'],
                'obs_autorizacion_reserva' => $payload['obs_autorizacion_reserva'],
                'user_autorizacion_reserva_id' => $user->id,
                'fe_ingreso_autorizacion_reserva' => Carbon::now()->format('Y-m-d H:i:s'),
                'estado' => 'procesado',
                'updated_at' => now()
            ]);
    }


    public function formPayloadReservedQuotationLog($reservedId, $quotation, $order, $userId)
    {
        $header = [
            'reserva_id' => $reservedId,
            'cotizacion_origen_id' => data_get($quotation, 'id'),
            'pedido_destino_id' => data_get($order, 'id'),
            'user_id' => $userId,
            'bodega' => data_get($quotation, 'BOD_BODEGA'),
        ];
        $detalleQuotation = GpCotizacionDetalle::with('stock')->where('cotizacion_id', $quotation['id'])->get();
        $detalles = collect($detalleQuotation)->flatMap(function ($detalle) use ($reservedId, $quotation, $order, $userId) {
            $tipo = data_get($detalle, 'ART_TIPO_COMPRA');

            if (in_array($tipo, ['SERV', 'L'], true)) {
                return [
                    [
                        'stock_id' => null,
                        // 'STO_BODEGA'              => data_get($quotation, 'BOD_BODEGA'),
                        'ART_CODIGO' => data_get($detalle, 'ART_CODIGO'),
                        'STO_ART_BODEGA' => data_get($quotation, 'BOD_BODEGA'),
                        'STO_ART_CANTIDAD' => (float) data_get($detalle, 'ART_CANTIDAD', 0),
                    ]
                ];
            }

            return collect(data_get($detalle, 'stock', []))->map(function ($stock) use ($reservedId, $detalle, $quotation, $order, $userId) {
                return [

                    'stock_id' => data_get($stock, 'id'),
                    // 'STO_BODEGA'              => data_get($quotation, 'BOD_BODEGA'),
                    'ART_CODIGO' => data_get($detalle, 'ART_CODIGO'),
                    'STO_ART_BODEGA' => data_get($stock, 'STO_ART_BODEGA', data_get($quotation, 'BOD_BODEGA')),
                    'STO_ART_CANTIDAD' => (float) data_get($stock, 'seleccionado', 0),
                ];
            });
        })->values()->all();

        return [
            'header' => $header,
            'detalles' => $detalles,
        ];
    }

    public function insertReservedOrderLogs($rows)
    {
        $haderOrderLog = $rows['header'];
        $details = $rows['detalles'];
        $odRsLog = $this->reservaBuilder->fromPayloadOrderReservedLog($haderOrderLog['reserva_id'], $haderOrderLog['cotizacion_origen_id'], $haderOrderLog['pedido_destino_id'], $haderOrderLog['user_id'], $haderOrderLog['bodega']);
        $odRsLog->save();
        foreach ($details as $value) {
            $detail = $this->reservaBuilder->formPayloadOrderReservedDetailLog($odRsLog->id, $value['stock_id'], $value['ART_CODIGO'], $value['STO_ART_BODEGA'], $value['STO_ART_CANTIDAD']);
            $detail->save();
        }
    }


    public function fetchListTypeReservations()
    {
        return GpTipoReservas::where('estado', 1)->get();
    }
}
