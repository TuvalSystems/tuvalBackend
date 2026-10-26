<?php

namespace App\Http\Controllers\Cobranzas;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Notifications\User\UserNotification;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use App\Services\Cobranzas\Depositos\Contracts\HistoricalDepositProcessorServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DepositosController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly HistoricalDepositProcessorServiceInterface $historyDeposits,
        protected readonly CollectionGpSenderServiceInterface $sendCollectionService
    ) {}

    public function depositosporaprobar(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $depositos = GpCobrosDeposito::where('monto', '>', 0)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->leftJoin('gp_punto_ventas', 'gp_punto_ventas.id', '=', 'gp_cobros.pv_id')
            ->where('gp_cobros_depositos.estado', 'iniciado')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get(['gp_cobros_depositos.*', 'gp_cobros.numero_recibo', 'gp_cobros.fecha as fecha_creacion_cobro', 'gp_punto_ventas.PTO_VTA_DESCRIPCION as agencia', 'gp_caja_bancos.CB_DESCRIPCION', 'gp_clientes.CLI_RUC', 'gp_clientes.CLI_RAZON_SOCIAL']);

        return response([
            'success' => true,
            'depositos' => $depositos
        ]);
    }

    public function aprobardeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $request->validate([
            'id' => 'required',
        ]);
        $user = $request->user();
        $deposito = GpCobrosDeposito::where('id', $request->id)->first();
        $deposito->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $deposito->user_aprobador_rechazo_id = $user->id;
        $deposito->estado = 'revisado';
        $deposito->save();

        // $this->enviarcobro($deposito->cobro_id);

        $cobro = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_rechazo_id = $user->id;
        $cobro->save();
        $dato = GpCobrosDeposito::where('gp_cobros_depositos.id', $request->id)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get([
                'gp_cobros_depositos.*',
                'gp_cobros.numero_recibo',
                'gp_cobros.user_id',
                'gp_caja_bancos.CB_DESCRIPCION',
                'gp_clientes.CLI_RUC',
                'gp_clientes.CLI_RAZON_SOCIAL'
            ])->first();

        $userCobro = GpUsuario::where('id', $cobro->user_id)->first();
        $mensaje = 'Depósito ' . $dato->comprobante . ' a la cuenta ' . $dato->CB_DESCRIPCION . ' del cliente ' . $dato->CLI_RAZON_SOCIAL . ' con RUC: ' . $dato->CLI_RUC . ' ha sido aprobado';
        if ($userCobro) {
            $userCobro->notify(new UserNotification('Depósito aprobado', $mensaje));
        }
        // $deposito->refresh();
        // SendCollectionToGpJob::dispatch($cobro->id);
        $this->sendCollectionService->sendCollectionToGp($cobro->id);

        return response([
            'success' => true,
            'message' => 'Depósito enviado'
        ]);
    }

    public function rechazardeposito(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data       = $request->all();
        $user       = $request->user();
        $deposito   = GpCobrosDeposito::where('id', $data['id'])->first();
        $deposito->user_aprobador_rechazo_id  = $user->id;
        $deposito->fecha_aprobacion_rechazo    = Carbon::now()->format('Y-m-d H:i:s');
        $deposito->mensaje_ws       =  data_get($data, 'motivo', null);
        $deposito->estado           = 'rechazado';
        $deposito->save();

        $dato = GpCobrosDeposito::where('gp_cobros_depositos.id', $request->id)
            ->join('gp_cobros', 'gp_cobros.id', '=', 'gp_cobros_depositos.cobro_id')
            ->join('gp_caja_bancos', 'gp_caja_bancos.CB_ID', '=', 'gp_cobros_depositos.banco')
            ->join('gp_clientes', 'gp_clientes.id', '=', 'gp_cobros.cliente_id')
            ->orderBy('gp_cobros_depositos.fecha', 'DESC')
            ->get([
                'gp_cobros_depositos.*',
                'gp_cobros.numero_recibo',
                'gp_cobros.user_id',
                'gp_caja_bancos.CB_DESCRIPCION',
                'gp_clientes.CLI_RUC',
                'gp_clientes.CLI_RAZON_SOCIAL'
            ])->first();


        $mensaje = 'Depósito/Transferencia ' . $dato->comprobante . ' a la cuenta ' . $dato->CB_DESCRIPCION . ' del cliente ' . $dato->CLI_RAZON_SOCIAL . ' con RUC: ' . $dato->CLI_RUC . ' ha sido <b>RECHAZADO</b>';
        if ($data['motivo'] != '') {
            $mensaje .= '<br> Motivo: ' . $data['motivo'];
        }
        $cobro                              = GpCobro::where('id', $deposito->cobro_id)->first();
        $cobro->fecha_aprobacion_rechazo    = Carbon::now()->format('Y-m-d H:i:s');
        $cobro->user_aprobador_rechazo_id   = $user->id;
        $cobro->estado                      = 'rechazado';
        $cobro->save();
        $userCobro = GpUsuario::where('id', $cobro->user_id)->first();
        if ($userCobro) {
            $userCobro->notify(new UserNotification('Depósito/Transferencia rechazado', $mensaje));
        }
        return response([
            'success' => true,
            'message' => 'Depósito rechazado'
        ]);
    }

    public function listaHistoricaDepositos(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->input('filters', []);
            $response = $this->historyDeposits->fetchListHistoricalDeposits($filters);
            return $this->successResponse('Datos obtenidos correctamente', $response, 200);
        }, 'cobranzas', 'listaHistoricaDepositos');
    }
}
