<?php

namespace App\Services\Venta;


use Carbon\Carbon;
use App\Models\GpBodega;
use App\Models\GpUsuario;
use App\Models\GpArticulo;
use App\Models\GpVehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use App\Models\GpTransportista;
use App\Models\reservas\GpReserva;
use Illuminate\Support\Facades\DB;
use App\Models\GpCotizacionDetalle;
use Illuminate\Support\Facades\Log;
use App\Models\reservas\GpTipoReservas;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\reservas\GpTrasladosReservas;
use Illuminate\Validation\ValidationException;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Jobs\Venta\Reservas\FanoutReservationRequest;
use App\Jobs\Venta\Reservas\NotifyReceivedReservationsJob;
use App\Jobs\Venta\Reservas\SendDenyReservationNotification;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Jobs\Venta\Reservas\SendApprovalReservationNotification;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Jobs\Venta\Reservas\NotifyReservationRequesterOrderCreatedJob;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Models\reservas\GpGuiaRemisionReservaMovimiento;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use App\Repositories\Venta\Contracts\InventoryReservationRepositoryInterface;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class InventoryReservationService implements InventoryReservationServiceInterface
{

    public function __construct(
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly QuotationBuilderInterface $quotationBuilder,
        protected readonly OrderBuilderInterface $orderBuilder,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly ReservedBuilderInterface $reservaBuilder,
        protected readonly InventoryReservationRepositoryInterface $reservaRepository,
        protected readonly ApiDeliverNoteService $apiDelivery,
        protected readonly FunctionsServiceInterface $functionService
    ) {

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


    public function fetchListReservedPending($perpage, $search, $sortBy = 'gp_reservas.created_at', $sortOrder = 'desc', $tipoReserva = null, $ptoVtaId = null, $clienteRuc)
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
            // 1. AGREGAMOS LA SUBCONSULTA REUTILIZABLE
            DB::raw($this->getWarehouseUsageSubqueryPending()),
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
        // return $query->paginate($perpage);
        return $query->paginate($perpage)->through(function ($row) {
            $row->cot_created_at = Carbon::parse($row->created_at)->timezone('America/Guayaquil')
                ->format('Y-m-d H:i:s');
            $row->bodegas_uso = collect(explode(',', (string) $row->bodegas_usadas))
                ->filter()
                ->unique()
                ->values();

            // Limpiamos la variable temporal
            unset($row->bodegas_usadas);
            return $row;
        });
    }



    public function getWarehouseUsageSubqueryPending(): string
    {
        return "(
              SELECT GROUP_CONCAT(DISTINCT s.STO_ART_BODEGA ORDER BY s.STO_ART_BODEGA SEPARATOR ',')
              FROM gp_cotizacion_detalle_stocks s
                INNER JOIN gp_cotizacion_detalles d ON d.id = s.detalle_id
                WHERE d.cotizacion_id = gp_cotizacions.id
                AND s.seleccionado > 0
            ) as bodegas_usadas";
    }


    public function cancelReservedQuotation(int $id): Builder|GpReserva
    {
        $reserva = GpReserva::find($id);
        $reserva->update([
            'estado' => 'cancelado'
        ]);
        return $reserva;
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
            'usuarioIngreso',
            'traslados.detalleguia.guia',
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

        // UPDATE idempotente (solo si aún no fue autorizada)
        $affected = GpReserva::query()
            ->whereKey($reserva->id)
            ->where('estado_autorizacion_reserva', '=', 0)
            ->update([
                'estado' => 'aprobado',
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
        $this->createReservationTransferIfValid($reserva);
        DB::afterCommit(callback: function () use ($reserva, $gpUser) {
            SendApprovalReservationNotification::dispatch($reserva->cotizacion_id, $gpUser->id);
        });

        return [
            'reserva_id' => $reserva->id,
            'cotizacion_id' => $reserva->cotizacion_id,
            'updated' => (bool) $affected,
            'tipo_traslado_reserva_id' => $reserva->tipo_traslado_reserva_id,
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
                'cantidad_reservada' => DB::raw('seleccionado'),
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
                'cantidad_reservada' => DB::raw('seleccionado'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        $otherWhaAffected = GpCotizacionDetalleStock::query()
            ->where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->where('STO_ART_BODEGA', '!=', $warehouse)
            ->update([
                'cantidad_autorizada_reserva' => DB::raw('seleccionado'),
                'cantidad_reservada' => DB::raw('seleccionado'),
                'cantidad_traslado' => DB::raw('seleccionado'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        Log::channel('reservas_log')->info("Se actualizaron {$sameWhaAffected} registros en 'cantidad_autorizada_reserva' y {$otherWhaAffected} en 'cantidad_traslado' para cotización ID {$quotationId}");
    }


    public function createReservationTransferIfValid(GpReserva $reserved): void
    {
        if ((int) $reserved->tipo_traslado_reserva_id !== 2) {
            return;
        }
        $quotation = GpCotizacion::find($reserved->cotizacion_id);
        if (!$quotation) {
            return;
        }

        $hayTraslado = $this->reservaRepository->checkExistingReservationTransfer($quotation);
        $newStatus = 'aprobado';
        if ($hayTraslado) {
            $detail = $this->reservaRepository->getDetailsForTransferReservation($quotation);
            $hasDetail = $detail->isNotEmpty();
            if ($hasDetail) {
                $this->createTransferReservation($detail);
                $newStatus = 'traslado';
            }
        }
        if ($reserved->estado !== $newStatus) {
            $reserved->update(['estado' => $newStatus]);
        }
    }





    public function createTransferReservation($details)
    {
        foreach ($details as $det) {
            $tr = $this->reservaBuilder->formPayloadTransferReserved($det);
            $tr->save();
        }
    }





    public function cleanStockQuotationForDeny($quotationId)
    {
        return GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)
            ->where('seleccionado', '>', 0)
            ->update(['seleccionado' => 0]);
    }




    public function fetchListApprovedQuotation($perPage, $search, $sortBy = 'gp_reservas.created_at', $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $estado = null, $ptoVtaId = null, $clienteRuc, $userId)
    {
        $gpUser = GpUsuario::find($userId);


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
            DB::raw($this->getWarehouseUsageSubquery()),
        ])->from('gp_reservas')
            ->join('gp_tipo_reservas', 'gp_reservas.tipo_reserva_id', '=', 'gp_tipo_reservas.id')
            ->leftJoin('gp_reserva_tipo_traslado', 'gp_reservas.tipo_traslado_reserva_id', '=', 'gp_reserva_tipo_traslado.id')
            ->join('gp_usuarios', 'gp_reservas.user_ingreso_reserva_id', '=', 'gp_usuarios.id')
            ->join('gp_cotizacions', 'gp_reservas.cotizacion_id', '=', 'gp_cotizacions.id');
        if ($gpUser->perfil_id !== 2) {
            $query->where('gp_reservas.user_ingreso_reserva_id', $userId);
        }

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
            $row->bodegas_uso = collect(explode(',', (string) $row->bodegas_usadas))
                ->filter()
                ->unique()
                ->values()
                ->all();
            unset($row->bodegas_usadas);
            return $row;
        });
    }


    public function getWarehouseUsageSubquery()
    {
        return "(
              SELECT GROUP_CONCAT(DISTINCT s.STO_ART_BODEGA ORDER BY s.STO_ART_BODEGA SEPARATOR ',')
              FROM gp_cotizacion_detalle_stocks s
                INNER JOIN gp_cotizacion_detalles d ON d.id = s.detalle_id
                WHERE d.cotizacion_id = gp_cotizacions.id
                AND s.cantidad_autorizada_reserva > 0
            ) as bodegas_usadas";
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

                if ($affected > 0) {
                    if ($row->cantidad_traslado > 0) {
                        // AQUÍ QUITAMOS EL WHERE DE 'seleccionado' PORQUE YA SABEMOS QUE SE COBRÓ
                        DB::table($row->getTable())
                            ->where('id', $row->id)
                            // Solo nos aseguramos que cantidad_traslado tenga saldo si es necesario
                            ->decrement('cantidad_traslado', $decrease);

                        $this->updateQtyTransferReserved($row, $decrease);
                    }
                }




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

    public function updateQtyTransferReserved($row, $qty)
    {
        GpTrasladosReservas::where('stock_id', $row->id)->where('cantidad', '>=', $qty)->decrement('cantidad', $qty);
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
        $requiereAprobacionComercial = $this->approvalService->checkoutApprovalCommercialQuotations($cotOld);
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

            $lineNet = round((($valorUnitario - $descuentoUnitario) * $cantidad) + 0.000001, 2);
            $lineaDiscount = $descuentoUnitario * $cantidad;
            $lineaSubtotal = $lineNet + $lineaDiscount;
            $impuestoPorcentaje = (float) $articulosImpuesto[$item->ART_CODIGO] ?? 0;
            $lineaIva = $lineNet * ($impuestoPorcentaje / 100);


            $subtotal += $lineaSubtotal;
            $totalDiscount += $lineaDiscount;
            $netSubtotal += $lineNet;
            $totalIva += $lineaIva;

            $item->ART_IMP_VTA = $impuestoPorcentaje;
            $item->ART_IVA = $lineaIva;
            $item->save();
        }
        $qt->subtotal = round($subtotal, 2);
        $qt->descuento = round($totalDiscount, 2);
        $qt->subtotalmenosdescuento = round($netSubtotal, 2);
        $qt->iva = round($totalIva, 2);
        $qt->total = round($netSubtotal + $totalIva, 2);
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
        $quoteBlock = GpCotizacion::where('id', $payload['cotizacion']['id'])->lockForUpdate()->first();
         if ($quoteBlock && ($quoteBlock->estado === 'procesado' || $quoteBlock->facturado == 1)) {
            throw new \DomainException('la cotizacion ya se encuentra procesada o facturada');
        }
        $reservedBlock = GpReserva::where('id', $payload['id'])->lockForUpdate()->first();
        if($reservedBlock && ($reservedBlock->estado === 'procesado')) {
            throw new \DomainException('Esta reserva ya fue procesada anteriormente.');
        }
        $userReserva = GpUsuario::where('id', $payload['user_ingreso_reserva_id'])->first();
        $this->updateQuotationStocksAuthorizedDontConsolidate($quoteBlock->id);
        $order = $this->storeReservedHeaderOrder($payload['cotizacion'], $userReserva, $payload['fe_autorizacion_reserva']);
        $this->storeReservedDetailOrder($payload['cotizacion']['detallecotizacion'], $order);
        $rows = $this->formPayloadReservedQuotationLog($payload['id'], $payload['cotizacion'], $order, $user->id);

        $this->insertReservedOrderLogs($rows);
        $this->updateQuotationReservedStatus($reservedBlock->id, $payload, $user, $quoteBlock);
        $this->cleanStockQuotationForDeny($quoteBlock->id);

        DB::afterCommit(function () use ($order, $payload) {
            NotifyReservationRequesterOrderCreatedJob::dispatch($order->pedido, $payload['user_ingreso_reserva_id']);
            $this->invoiceService->checkSendInvoice($order);
        });
        return $order;
    }


    public function updateQuotationStocksAuthorizedDontConsolidate(int $quotationId)
    {
        $stocks = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)
            ->get();
        foreach ($stocks as $stock) {
            $stock->update([
                'cantidad_autorizada_reserva' => $stock->seleccionado,
                'cantidad_reservada' => $stock->seleccionado,
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
                if ($sto['STO_ART_STOCK_DISPONIBLE'] < $sto['seleccionado']) {
                    throw new \DomainException("El stock disponible del artículo {$detailOrder['ART_CODIGO']} en la bodega {$sto['STO_ART_BODEGA']} es insuficiente para la cantidad seleccionada.");
                }
            }
            $reservado = $requiereAprobacion ? 0 : $sto['seleccionado'];
            $newStock = $this->orderBuilder->formPayloadOrderDetailStock($sto, $order->id, $detailOrder->id, $newFormatStock['STO_ART_STOCK_DISPONIBLE'], $reservado);
            $newStock->save();
        }
    }



    public function updateQuotationReservedStatus(int $reservedId, $payload, GpUsuario $user, GpCotizacion $quoteBlock)
    {
        $quoteBlock->update(['estado' => 'procesado', 'facturado' => 1]);
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



    /**
     *
     *         PROCESO PARA TRNASFERENCIAS DE RESERVAS
     *
     */

    public function fetchReservedTransferSummaries($ptoVtaId): array
    {
        $bodOrigin = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVtaId)->value('PTO_VTA_BOD_BODEGA');
        $traslados = $this->reservaRepository->getAllTransferReservedByWarehouses($bodOrigin);
        $items = $traslados->filter(fn($t) => $t->pending_transfer > 0)->map(function ($item) {
            $qty = (float) $item->pending_transfer;
            $kg = (float) $item->art_peso;
            $item->art_pesoKg = $qty * $kg;
            return $item;
        })->groupBy('bodega_destino');

        $guiasGrouped = $this->reservaRepository->findCountPendingGuidesByOriginWarehouse($bodOrigin);
        $allDestinosIds = $items->keys()->merge($guiasGrouped->keys())->unique();

        return $allDestinosIds->map(function ($destId) use ($items, $guiasGrouped) {
            $items = $items->get($destId); // Puede ser null
            $guiaInfo = $guiasGrouped->get($destId); // Puede ser null

            $bodegaNombre = $items ? $items->first()->bodega_destino_desc : ($guiaInfo->bodega_destino_desc ?? 'Desconocida');

            return [
                'bodega' => $bodegaNombre,
                'ptovtaId' => $items ? $items->first()->ptovtaId : $guiaInfo->ptovtaId, // Usamos el que entra por parámetro, es más seguro
                'pesoTon' => $items ? round($items->sum('art_pesoKg') / 1000, 2) : 0,
                'totalItems' => $items ? $items->count() : 0,
                'totalGuidePending' => $guiaInfo ? $guiaInfo->total : 0, // Tomamos el total pre-calculado
            ];
        })->values()->all();
    }

    public function fectchReservedTransferDetailsHeader($ptoVtaOrigen, $ptoVtaDestino): array
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$ptoVtaOrigen, $ptoVtaDestino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');
        $waOrigin = $map[$ptoVtaOrigen] ?? null;
        $waDestination = $map[$ptoVtaDestino] ?? null;
        if (!$waOrigin || !$waDestination) {

            return [];
        }
        $bodegaDestinoDescripcion = GpBodega::where('BOD_BODEGA', $waDestination)->value('BOD_DESCRIPCION');
        $traslados = $this->reservaRepository->getAllTransferReservedByOriginAndDestination($waOrigin, $waDestination);
        if (!$traslados) {
            return [];
        }
        $items = $traslados->filter(fn($t) => $t->pending_transfer > 0)->map(function ($item) {
            $qty = (float) $item->pending_transfer;
            $kg = (float) $item->art_peso;
            $item->art_pesoKg = $qty * $kg;
            return $item;
        })->values();
        $listQuotations = $this->getfilterListQuotationsByTransfered($items);
        $pesoTotal = round($items->sum('art_pesoKg') / 1000, 2);
        return [
            'bodOrigin' => $waOrigin,
            'ptoOrigin' => $ptoVtaOrigen,
            'origin' => $waOrigin,
            'bodDestination' => $waDestination,
            'ptoDestination' => $ptoVtaDestino,
            'destination' => $bodegaDestinoDescripcion,
            'pesoTotal' => $pesoTotal,
            'listaQuotations' => $listQuotations
        ];
    }

    public function fetchReservedTransfersByDestinationWarehouses($ptoVtaOrigen, $ptoVtaDestino, $quotation = null): array
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$ptoVtaOrigen, $ptoVtaDestino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');
        $waOrigin = $map[$ptoVtaOrigen] ?? null;
        $waDestination = $map[$ptoVtaDestino] ?? null;
        if (!$waOrigin || !$waDestination) {
            return [];
        }
        $traslados = $this->reservaRepository->getAllTransferReservedByOriginAndDestination($waOrigin, $waDestination, $quotation);
        if (!$traslados) {
            return [];
        }
        return $traslados->filter(function ($t) {
            return (float) ($t->pending_transfer) > 0;
        })->values()
            ->map(function ($item) use ($waOrigin) {
                $createdAt = $item->created_at instanceof Carbon ? $item->created_at : Carbon::parse($item->created_at);
                $item->fecha = $createdAt->timezone('America/Guayaquil')->format('Y-m-d H:i:s');
                $artApi = $this->fetchItemCompartmentByWarehouse($item->art_code, $waOrigin);
                $item->compartimiento = Arr::get($artApi, 'COMPARTIMIENTO', '');
                $item->existencia = round(Arr::get($artApi, 'STOCK_DISPONIBLE', 0), 2);
                return $item;
            })->toArray();
    }

    public function getfilterListQuotationsByTransfered($items)
    {
        return collect($items)
            ->pluck('cotizacion')
            ->filter()                // quita null/'' si los hubiera
            ->unique()                // deja únicos
            ->values()                // reindexa 0..n
            ->map(fn($c) => $c);
    }


    public function fetchItemCompartmentByWarehouse($artCode, $warehouse)
    {
        $params = ['articulo' => $artCode, 'bodega' => $warehouse, 'compartimento' => ''];
        $resp = $this->stockWarehouseService->fetchStockWarehouseCompartiment($params);
        return $resp;
    }


    /**
     *
     *
     *  proceso de actualizacion de pickign de items seleccionados
     *
     *
     *
     * */


    public function updatePickingItemsSelected($items): int
    {
        $ids = collect($items)->pluck('id')->filter()->unique();
        if ($ids->isEmpty()) {
            return 0;
        }
        return GpTrasladosReservas::whereIn('id', $ids)->update(['picking' => 1]);
    }


    public function cancelPickingOneItemSelected($item): int
    {
        $id = (int) Arr::get($item, 'id', 0);
        if ($id <= 0) {
            return 0;
        }
        return GpTrasladosReservas::whereKey($id)->update([
            'picking' => 0,
            'updated_at' => now(),
        ]);
    }



    public function generatePickingPdf($payload): array
    {
        $itemsPayload = collect($payload['items']);
        $puntodeventa = GpPuntoVenta::join('gp_relacion_punto_venta_bodegas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
            ->where('gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA', $payload['origen'])->first()->toarray();

        $viewData = [
            'origen' => $payload['origen'],
            'destino' => $payload['destino'],
            'pesototal' => (float) str_replace(',', '.', (string) ($payload['pesototal'] ?? 0)) ?? 0,
            'rows' => $itemsPayload->map(function ($item) {
                $pesoUnitGr = (float) str_replace(',', '.', (string) ($item['art_peso'] ?? 0));
                $qty = (int) ($item['seleccionado'] ?? 0);
                $item['peso_kg'] = round(($pesoUnitGr * $qty), 2);
                return $item;
            })->values()
                ->all(),
            'direccion' => $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'],
        ];

        // 3) Render Blade → PDF (Dompdf)
        $html = view('pdf.picking-reserva', $viewData)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();

        $filename = 'picking-' . Str::slug($payload['destino'], '-') . '-' . now()->format('Ymd-His') . '.pdf';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'content_type' => 'application/pdf',
        ];
    }


    public function fetchVehicles()
    {
        return [
            'vehiculos' => GpVehiculo::get(),
            'transportistas' => GpTransportista::get()
        ];

    }



    /** proceso de listado de guias de remision Reservas - pendientes */
    public function fetchPendingReservationdeliveryNotes($ptoVtaOrigen, $ptoVtaDestino)
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$ptoVtaOrigen, $ptoVtaDestino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');
        $waOrigin = $map[$ptoVtaOrigen] ?? null;
        $waDestination = $map[$ptoVtaDestino] ?? null;
        if (!$waOrigin || !$waDestination) {

            return [];
        }
        return $this->reservaRepository->findGuidesPendingsByOriginWarehouse($waOrigin, $waDestination);
    }


    /***
     *
     *
     * proceso de recibir reservas
     *  -- ya me carga la lista de cotizaciones basada en attributos de la guia de remision
     */

    public function paginateTransfersToReceive(string $ptoVta, int $perpage = 10, ?string $search = null, ?string $sortBy = null, string $sortOrder = 'desc'): LengthAwarePaginator
    {

        // 1) Resolver bodega destino
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVta)->value('PTO_VTA_BOD_BODEGA');

        // 2) Lista blanca para ordenar (evita SQL injection en orderBy)

        $sortable = [
            'id',
            'numero_guia',
            'bodega_origen',
            'razon_social_transportista',
            'placa',
            'fecha_salida',
            'detalle_guia_reservas_count'
        ];
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        $sortBy = in_array($sortBy, $sortable, true) ? $sortBy : 'fecha_salida';
        $q = GpGuiaRemisionReserva::
            where('estado', 'enviada')
            ->where('bodega_destino', $bodega)
            ->has('detalleGuiaReservas')
            ->with(['cotizaciones:id,cotizacion'])
            ->withCount('detalleGuiaReservas')
            ->select(['id', 'numero_guia', 'bodega_origen', 'razon_social_transportista', 'placa']);

        $q->when($search, function ($que) use ($search) {
            $que->where(function ($w) use ($search) {
                $w->where('gp_guia_remision_reservas.numero_guia', 'like', "%{$search}%")
                    ->orWhere('gp_guia_remision_reservas.bodega_origen', 'like', "%{$search}%")
                    ->orWhere('gp_guia_remision_reservas.razon_social_transportista', 'like', "%{$search}%")
                    ->orWhere('gp_guia_remision_reservas.placa', 'like', "%{$search}%")
                    // Si el search es numérico, permite coincidencia exacta por id
                    ->orWhere('gp_guia_remision_reservas.id', is_numeric($search) ? '=' : 'like', is_numeric($search) ? $search : "%{$search}%");
            });
        });
        // 5) Orden y paginación
        $paginator = $q->orderBy($sortBy, $sortOrder)->paginate($perpage);
        // 6) Limpiar relaciones del payload (dejamos solo el accessor "quotes")
        foreach ($paginator->items() as $g) {
            $g->unsetRelation('detalleGuiaReservas');
            $g->makeHidden(['cotizaciones']);
        }

        return $paginator;
    }



    public function getTransferToReceiveDetails(int $id)
    {
        return GpGuiaRemisionReservaDetalle::query()
            ->with(['cotizacion:id,cotizacion'])
            ->where('guia_id', $id)
            ->select([
                'id',
                'guia_id',
                'traslado_id',
                'cotizacion_id',
                'detalle_id',
                'stock_id',
                'art_code',
                'art_descripcion',
                'art_cod_tres_letras',
                'cantidad',
                'art_plan_umedida',
                'recibido',
            ])
            ->orderBy('id')->get()->filter(fn($item) => $item->pending_received > 0)->values();
    }




    public function printOrderStorageReceivedTranfered(array $payload)
    {
        $id = (int) Arr::get($payload, 'id', 0);
        $header = GpGuiaRemisionReserva::findOrFail($id);
        $detail = $this->getTransferToReceiveDetails($id);

        $viewData = [
            'destino' => $header->bodega_destino,
            'origen' => $header->bodega_origen,
            'numero_guia' => $header->numero_guia,
            'detail' => $detail->each(function ($item) {
                $item->co_cotizacion = $item->cotizacion->cotizacion;
            }),
        ];

        $html = view('pdf.orden-almacenamiento-recibir', $viewData)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();

        $filename = 'orden-almacenamiento-' . Str::slug($header->bodega_destino, '-') . '-' . Str::slug($header->numero_guia) . '.pdf';

        return [
            'binary' => $binary,
            'filename' => $filename,
            'content_type' => 'application/pdf',
        ];
    }


    /** proceso de recibir items  */

    public function receiveItemsFromTransferred(int $guiaId, array $items)
    {
        $processed = false;
        $user = GpUsuario::whereKey(Auth::id())->value('USERID') ?: 'adminWeb';
        $gpUser = GpUsuario::whereKey(Auth::id())->first() ?? GpUsuario::where('USERID', 'adminWeb')->first();
        $guia = GpGuiaRemisionReserva::find($guiaId);
        if (!$guia) {
            throw new \Exception("Guia de reserva no encontrada");
        }
        $movimientos = $this->getMovimentsByGuiaId($guia->id, $guia->quotes); // me obtiene todos los movimientos de la guia tomando la cotizacion como clave
        $itmTrIds = collect($items)->pluck('traslado_id')->filter()->unique()->toArray();
        $traslados = GpTrasladosReservas::whereIn('id', $itmTrIds)->get()->keyBy('id');
        $groupsItems = $this->reservaBuilder->formPayloadReservationReceivedDetail($items, $movimientos, $traslados);

        foreach ($groupsItems as $quote => $group) {
            $params = $this->reservaBuilder->buildReservationDeliveryNoteReceivedPayload($guia, $quote, $group['ws_items'], $group['transferencia'], $user);
            $processed = $this->processReceivedReservationAndCreateAdjustment($guia, $params, $group['items'], $quote, $gpUser);
        }
        if (!$processed) {
            throw new \Exception('No se pudo procesar la guia de reserva');
        }
        $exists = $this->validateExistsForReceivedUpdateStatusGuide($guia);
        $this->sendNotificacionReceivedItems($guia, $exists, $items, $gpUser);
        return $guia->refresh();
    }


    public function getMovimentsByGuiaId(int $guiaId, array $quotes)
    {
        return GpGuiaRemisionReservaMovimiento::where('guia_id', $guiaId)->where('tipo_movimiento', 'TRC')->whereIn('cotizacion', $quotes)->get()->keyBy('cotizacion');
    }


    // public function processReceivedReservationAndCreateAdjustment(GpGuiaRemisionReserva $guia, $params, $items, $quote, GpUsuario $user): bool
    // {
    //     $cabecera = data_get($params, 'cabecera', []);
    //     $pyValidate = $this->reservaBuilder->buildRecievedDeliveryNoteValidationPayload($cabecera);
    //     $resValidate = $this->apiDelivery->checkReceiveGuideReservationAjustment($pyValidate);
    //     $headerRespValidate = data_get($resValidate, 'data');
    //     $validateSuccess = data_get($resValidate, 'success');
    //     $docGpValidate = trim(data_get($headerRespValidate, 'docGp', ''));

    //     /** en caso de que ya se encuentre el registro */
    //     if (!empty($headerRespValidate) && filled($docGpValidate)) {
    //         $already = $this->movementExistsWeb($guia->id, $quote, $docGpValidate);
    //         DB::transaction(function () use ($guia, $items, $headerRespValidate, $quote, $already, $user) {
    //             $this->updateQtyTransferedItems($guia, $items);
    //             if (!$already) {
    //                 $this->createMovementReservation($guia, $headerRespValidate, $quote);
    //                 $this->createRegisterHistoryIng($guia, $items, $user, $quote);
    //             }
    //         });
    //         return true;
    //     }

    //     /** en caso de que no exista el movimiento lo crea */
    //     if (empty($headerRespValidate) && $validateSuccess === false) {
    //         $resp = $this->apiDelivery->createReceiveGuideReservationAjustment($params);
    //         $success = data_get($resp, 'success', false);
    //         $data = data_get($resp, 'data', []);
    //         $msg = data_get($resp, 'message');
    //         $docGp = trim(data_get($resp, 'data.docGp', ''));

    //         if (!$success) {
    //             throw new \Exception($msg ?: 'Error creando el ajuste de guía en GP.');
    //         }
    //         if ($docGp === '') {
    //             throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
    //         }
    //         DB::transaction(function () use ($items, $guia, $data, $quote, $user) {
    //             $this->updateQtyTransferedItems($guia, $items);
    //             $this->createMovementReservation($guia, $data, $quote);
    //             $this->createRegisterHistoryIng($guia, $items, $user, $quote);
    //         });
    //         return true;
    //     }
    //     return false;
    // }

    public function processReceivedReservationAndCreateAdjustment(GpGuiaRemisionReserva $guia, $params, $items, $quote, GpUsuario $user): bool
    {

        $resp = $this->apiDelivery->createReceiveGuideReservationAjustment($params);
        $success = data_get($resp, 'success', false);
        $msg = data_get($resp, 'message');
        $docGp = trim(data_get($resp, 'data.docGp', ''));

        if (!$success) {
            $cabecera = data_get($params, 'cabecera', []);
            $pyValidate = $this->reservaBuilder->buildRecievedDeliveryNoteValidationPayload($cabecera);
            $resValidate = $this->apiDelivery->checkReceiveGuideReservationAjustment($pyValidate);
            $headerRespValidate = data_get($resValidate, 'data');
            $docGpValidate = trim(data_get($headerRespValidate, 'docGp', ''));
            if (!empty($headerRespValidate) && filled($docGpValidate)) {
                // Usamos el documento que encontró la validación
                $docGp = $docGpValidate;
                $data = $headerRespValidate;
            } else {
                // Si falló la creación y la validación tampoco trajo nada útil, lanzamos el error.
                throw new \Exception($msg ?: 'Error creando el ajuste de guía en GP.');
            }
        } else {
            $data = data_get($resp, 'data', []);
        }
        if ($docGp === '') {
            throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
        }

        $already = $this->movementExistsWeb($guia->id, $quote, $docGp);
        DB::transaction(function () use ($guia, $items, $data, $quote, $already, $user) {
            if (!$already) {
                $this->updateQtyTransferedItems($guia, $items);
                $this->createMovementReservation($guia, $data, $quote);
                $this->createRegisterHistoryIng($guia, $items, $user, $quote);
            }
        });
        return true;
    }


    public function movementExistsWeb($guiaId, $quote, $docGp): bool
    {
        return GpGuiaRemisionReservaMovimiento::query()
            ->where('guia_id', $guiaId)
            ->where('tipo_movimiento', 'RTC')
            ->where('cotizacion', $quote)
            ->where('codigo', trim((string) $docGp))
            ->exists();
    }


    public function createMovementReservation($guia, $responseApi, $quote): GpGuiaRemisionReservaMovimiento
    {
        return GpGuiaRemisionReservaMovimiento::firstOrCreate([
            'guia_id' => $guia->id,
            'tipo_movimiento' => 'RTC',
            'cotizacion' => $quote,
            'id_movimiento' => data_get($responseApi, 'idMovimiento'),
            'codigo' => trim((string) data_get($responseApi, 'docGp', '')),
        ]);
    }


    public function updateQtyTransferedItems(GpGuiaRemisionReserva $guia, array $items)
    {
        foreach ($items as $itm) {
            $trasladoId = data_get($itm, 'traslado_id');
            $traslado = GpTrasladosReservas::with('reserva')->find($trasladoId);
            $liberarStock = in_array($traslado->reserva->estado, ['cancelado', 'rechazada']);
            $this->updateQtyReceivedDetailGuide($itm);
            $this->updateQtyStockQuotationTransferred($guia, $itm, $liberarStock);
            $this->updateStatusReservation($itm, $traslado->reserva);
        }
    }

    public function updateQtyReceivedDetailGuide($item)
    {
        $recibir = max(0, (float) ($item['recibir'] ?? 0));
        $gd = GpGuiaRemisionReservaDetalle::find($item['id']);
        $pend = max(0, (float) $gd->cantidad - (float) $gd->recibido);
        if ($pend <= 0)
            return;
        $gd->increment('recibido', min($recibir, $pend));

    }

    public function updateQtyStockQuotationTransferred(GpGuiaRemisionReserva $guia, array $item, bool $liberarStock)
    {
        $detalleId = (int) Arr::get($item, 'detalle_id');
        $qty = (float) max(0, Arr::get($item, 'recibir', 0));
        if ($qty <= 0 || !$detalleId)
            return;
        $stockOrigen = GpCotizacionDetalleStock::where('detalle_id', $detalleId)->where('STO_ART_BODEGA', $guia->bodega_origen)->first();
        $stockDestino = GpCotizacionDetalleStock::where('detalle_id', $detalleId)->where('STO_ART_BODEGA', $guia->bodega_destino)->first();

        // Evita negativos: limita lo que vas a descontar
        $decTras = min($qty, (float) $stockOrigen->cantidad_traslado);
        $decSel = min($qty, (float) $stockOrigen->seleccionado);
        $stockOrigen->cantidad_reservada = max(0, (float) $stockOrigen->cantidad_reservada - $decTras);
        $stockOrigen->cantidad_traslado = max(0, (float) $stockOrigen->cantidad_traslado - $decTras);
        $stockOrigen->seleccionado = max(0, (float) $stockOrigen->seleccionado - $decSel);
        $stockOrigen->cantidad_transito = (float) max(0, $stockOrigen->cantidad_transito - $qty);
        $stockOrigen->save();

        if ($liberarStock) {
            $stockDestino->cantidad_reservada = 0;
            $stockDestino->seleccionado = 0;
            $stockOrigen->cantidad_transito = 0;
        } else {
            // En destino sumas lo recibido a "seleccionado" (ajusta si tu modelo espera otro campo)
            $stockDestino->cantidad_reservada = (float) $stockDestino->cantidad_reservada + $qty;
            $stockDestino->seleccionado = (float) $stockDestino->seleccionado + $qty;
        }

        $stockDestino->save();

    }

    public function updateStatusReservation($itm, $reserva)
    {
        if (!$reserva)
            return;

        // BLOQUEO DE SEGURIDAD:
        // Si la reserva está cancelada o rechazada, NO la tocamos.
        // Ya cumplió su ciclo de vida y el stock se liberó en el paso anterior.
        if (in_array($reserva->estado, ['cancelado', 'rechazada'])) {
            return;
        }

        // $trasladoId = data_get($itm, 'traslado_id');
        // $tras = GpTrasladosReservas::find($trasladoId);
        // $reserva = GpReserva::find($tras->reserva_id);

        $existePendiente = GpCotizacionDetalleStock::query()
            ->from('gp_cotizacion_detalle_stocks as ds')
            ->join('gp_traslados_reservas as tr', 'tr.cotizacion_id', '=', 'ds.cotizacion_id')
            ->where('tr.reserva_id', $reserva->id)
            ->where('ds.cantidad_traslado', '>', 0)
            ->exists();
        if (!$existePendiente && $reserva->estado !== 'aprobado') {
            $reserva->update(['estado' => 'aprobado']); // GpReserva
        }

    }



    public function createRegisterHistoryIng($guia, $items, GpUsuario $user, $quote)
    {
        $ing = $this->reservaBuilder->createRegisterHistortyHeader($guia, $user, $quote);
        $ing->save();
        foreach ($items as $item) {
            $de = $this->reservaBuilder->createRegisterHistoryDetail($item, $ing);
            $de->save();
        }
    }


    public function validateExistsForReceivedUpdateStatusGuide(GpGuiaRemisionReserva $guia): bool
    {
        $completa = !GpGuiaRemisionReservaDetalle::where('guia_id', $guia->id)
            ->whereColumn('recibido', '<', 'cantidad')->exists();
        if ($completa) {
            $guia->update(['estado' => 'recibida']);
            return true;
        }
        return false;

    }


    public function sendNotificacionReceivedItems(GpGuiaRemisionReserva $guia, bool $exists, array $items, GpUsuario $userReceived)
    {
        $idsItems = collect($items)->pluck('id')->toArray();
        $detalles = GpGuiaRemisionReservaDetalle::with(['cotizacion:id,cotizacion', 'traslado.reserva:id,user_ingreso_reserva_id'])->whereIn('id', $idsItems)->where('guia_id', $guia->id)->get()->keyBy('id');
        foreach ($items as $dt) {
            $detGuia = $detalles->get($dt['id']);
            $qtyFmt = number_format((float) $dt['recibir'], 2, '.', '');
            $title = $exists ? 'Articulo recibido completo' : 'Articulo recibido parcial';
            $msg = sprintf(
                'Se ha recibido la cantidad de %s del artículo %s en la bodega %s (cotización %s) por parte de %s.',
                $qtyFmt,
                $detGuia->art_code,
                $guia->bodega_destino,
                $detGuia->cotizacion?->cotizacion ?? '-',
                $userReceived->USERID ?? '-'
            );
            /** notificacion al usuario que su item fue recibido */
            NotifyReceivedReservationsJob::dispatch($title, $msg, $detGuia->traslado->reserva->user_ingreso_reserva_id);
        }
    }


    /**
     *  funcion para la verificacion del stock disponible de una cotizacion que quiere nacer como reserva
     *
     */

    public function validateAndProcessQuotationStock($quoteId)
    {
        $quote = GpCotizacion::find($quoteId);
        if (!$quote) {
            throw new \DomainException("Cotización no encontrada");
        }
        // $warehouses = $this->functionService->getBodegas($quote->PTO_VTA_ID, 1);
        $detWithStock = GpCotizacionDetalle::with('stock')->where('cotizacion_id', $quoteId)->get();
        $normalizeStock = $this->normalizeStockDataForReservation($detWithStock);
        $stock = $this->validateStockAgainstSelection($normalizeStock);
        return $stock;
    }


    public function normalizeStockDataForReservation($detailStock)
    {
        return collect($detailStock)->flatMap(function ($item) {
            return $item->stock->map(function ($sto) use ($item) {
                return [
                    'cotizacion_id' => data_get($item, 'cotizacion_id'),
                    'detalle_id' => data_get($item, 'id'),
                    'stock_id' => data_get($sto, 'id'),
                    'articulo' => data_get($item, 'ART_CODIGO'),
                    'bodega' => data_get($sto, 'STO_ART_BODEGA'),
                    'STO_ART_STOCK_DISPONIBLE' => data_get($sto, 'STO_ART_STOCK_DISPONIBLE', 0),
                    'seleccionado' => data_get($sto, 'seleccionado', 0),
                    'ART_DECIMAL_CANTIDAD' => data_get($item, 'ART_DECIMAL_CANTIDAD', 0),
                ];
            });

        })
            ->keyBy('stock_id');
    }

    public function validateStockAgainstSelection($normalize): string|null
    {
        $selectedStock = $normalize->filter(function ($item) {
            return data_get($item, 'seleccionado', 0) > 0;
        });

        if ($selectedStock->isEmpty()) {
            throw new \DomainException("No se ha seleccionado cantidades para esta cotización");
        }

        $stockRequests = $selectedStock->map(function ($item) {
            return ['articulo' => $item['articulo'], 'bodega' => $item['bodega']];
        })->unique() // Evita consultar el mismo Articulo/Bodega varias veces
            ->values()
            ->all();

        $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
        $currentStocksLookup = $this->stockWarehouseService->transformStockReservationResponse($stockResults);
        $messages = [];
        $articulosOverStock = [];

        $updatedStock = $selectedStock->map(function ($item, $stockId) use ($currentStocksLookup, &$messages, &$articulosOverStock) {
            $articulo = data_get($item, 'articulo');
            $bodega = data_get($item, 'bodega');
            $reservado = data_get($item, 'seleccionado');
            $articleForReservedCheck = [
                'ART_CODIGO' => $articulo,
                'ART_DECIMAL_CANTIDAD' => data_get($item, 'ART_DECIMAL_CANTIDAD', 0),
            ];

            //generamos una key
            $lockUpKey = "{$articulo}_{$bodega}";
            $currentStockData = data_get($currentStocksLookup, $lockUpKey);
            $this->stockWarehouseService->applyReservedQtyToAvailableStock($currentStockData, $articleForReservedCheck, null, null);

            $currentStock = data_get($currentStockData, 'STO_ART_STOCK_DISPONIBLE', 0);
            $stockRestante = $currentStock - $reservado;

            if ($stockRestante < 0) { //ser coloca menor a 0 porque si tiene disponible 1 y seleccionado 1 me da 0
                $articulosOverStock["{$articulo} ({$bodega})"] = $articulo;
                $messages[] = [
                    'ART_CODIGO' => $articulo,
                    'ART_DESCRIPCION' => '',
                    'BODEGA' => $bodega,
                    'STOCK_DISPONIBLE' => $stockRestante,
                    'STOCK_RESERVADO' => $reservado,
                    'MENSAJE' => "El stock disponible para el articulo $articulo en la bodega $bodega es de $currentStock, pero se quiere reservar $reservado",
                ];
                $item['STOCK_DISPONIBLE_AJUSTADO'] = 0.00;
            } else {
                $item['STOCK_DISPONIBLE_AJUSTADO'] = $stockRestante;
            }
            $this->updateDisponibleStockReserved($stockId, $currentStock);
            return $item;
        });

        $finalMessage = null;
        if (!empty($articulosOverStock)) {
            $listaArticulos = implode(', ', array_keys($articulosOverStock));
            $totalArticulos = count($articulosOverStock);
            $finalMessage = "⚠️ Se encontraron **{$totalArticulos} artículos** que superan el stock disponible. Revise el detalle de los siguientes ítems: **{$listaArticulos}** antes de solicitar una reserva.";
        }
        return $finalMessage;
    }


    public function updateDisponibleStockReserved($stockId, $disponible)
    {
        $stock = GpCotizacionDetalleStock::find($stockId);
        $stock->STO_ART_STOCK_DISPONIBLE = $disponible;
        $stock->save();
    }
}
