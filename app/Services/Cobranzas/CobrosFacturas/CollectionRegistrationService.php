<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Models\Cobranzas\GpCobro;
use App\Models\GpCaja;
use App\Models\GpCajaBancos;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaCaja;
use App\Models\GpRelacionUsuarioCaja;
use App\Models\GpUsuario;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;

use Illuminate\Support\Facades\DB;

class CollectionRegistrationService implements CollectionRegistrationServiceInterface
{
    public function __construct(
        protected readonly CollectionRegistrationBuilderInterface $collectBuilder
    ) {}

    public function registerCollection(array $collectionData, GpUsuario $user): GpCobro
    {
        $boxId          = data_get($collectionData, 'caja_id');
        $pvId           = data_get($collectionData, 'pv_id');
        $pvCode         = data_get($collectionData, 'pvCodigo');
        $clientId       = data_get($collectionData, 'cliente_id');
        $boxed          = GpCaja::select(['id', 'CAJA_CODIGO as cajaCodigo'])->where('id', $boxId)->where('estado', 1)->first();
        $pvDescription  = GpPuntoVenta::where('id', $pvId)->value('PTO_VTA_DESCRIPCION');
        if (!$boxed) {
            throw new \Exception("La caja con ID {$boxId} no existe o no está activa.");
        }

        $existingRelation = GpRelacionPuntoVentaCaja::where('caja_id', $boxId)
            ->where('pv_id', $pvId)
            ->where('estado', 1)
            ->exists();

        if (!$existingRelation) {
            throw new \RuntimeException("La caja {$boxed->CAJA_CODIGO}no está asociada al punto de venta con ID {$pvCode}.");
        }
        $userBoxed = GpRelacionUsuarioCaja::where('caja_id', $boxId)->where('user_id', $user->id)->where('estado', 1)->first();
        if (!$userBoxed) {
            throw new \RuntimeException("El usuario {$user->USERID} no tiene acceso a la caja {$boxed->cajaCodigo} en la {$pvDescription}.");
        }

        $collectionRegister = DB::transaction(function () use ($collectionData, $user, $boxed, $userBoxed, $clientId) {
            $headerCollection = $this->collectBuilder->createHeaderCollection($collectionData, $userBoxed->user_id, $boxed);

            // Crear detalles de retención
            $this->createCollectionRetentionDetail(data_get($collectionData, 'retenciones'), $headerCollection->id);

            // Crear detalles de efectivo
            $this->createCollectionCashDetail(data_get($collectionData, 'efectivo'),  $headerCollection->id);

            // Crear detalles de depósitos
            $this->createCollectionDepositDetail(data_get($collectionData, 'depositos'),  $headerCollection->id);

            //crear detalle de cheque
            $this->createCollectionCheckDetail(data_get($collectionData, 'cheque'),  $headerCollection->id, $clientId);

            //crear detalle de tarjetas
            $this->createCollectionCardsDetail(data_get($collectionData, 'tarjeta'),  $headerCollection->id);

            //crear detalle de aplicaciones
            $this->createCollectionApplicationsDetail(data_get($collectionData, 'aplicaciones'),  $headerCollection->id);
            return $headerCollection;
        });

        


        return $collectionRegister;



        // return $collectionRegister;
    }

    private function createCollectionRetentionDetail(array $retentionData, int $collectionId): void
    {
        foreach ($retentionData as $retentionDetail) {
            $this->collectBuilder->createCollectionRetentionDetail($retentionDetail,  $collectionId);
        }
    }

    private function createCollectionCashDetail(array $cashCollection, int $collectionId): void
    {
        $this->collectBuilder->createCollectionCashHeader($cashCollection, $collectionId);
        foreach (data_get($cashCollection, 'facturas', []) as $cashDetail) {
            $this->collectBuilder->createCollectionCashDetail($cashDetail, $collectionId);
        }
    }


    private function createCollectionDepositDetail(array $depositCollection, int $collectionId): void
    {
        foreach ($depositCollection as $deposit) {
            $backString = GpCajaBancos::where('id', data_get($deposit, 'banco_id'))->value('CB_ID');
            $depositHeader = $this->collectBuilder->createCollectionDepositHeader($deposit,  $collectionId, $backString);

            foreach (data_get($deposit, 'facturas', []) as $depositDetail) {
                $this->collectBuilder->createCollectionDepositDetail($depositDetail, $collectionId, $depositHeader->id);
            }
        }
    }



    private function createCollectionCheckDetail(array $checkCollection, int $collectionId, int $clientId): void
    {
        $backString = GpCajaBancos::where('id', data_get($checkCollection, 'banco_id'))->value('CB_ID');
        $numCheck   = $this->validateNumberCheck($clientId, data_get($checkCollection, 'cheque'));
        $this->collectBuilder->createCollectionCheckAmount($checkCollection, $collectionId);
        $this->collectBuilder->createCollectionCheckHeader($checkCollection, $collectionId, $backString, $numCheck);
        foreach (data_get($checkCollection, 'facturas', []) as $checkDetail) {
            $this->collectBuilder->createCollectionCheckDetail($checkDetail, $collectionId);
        }
    }


    private function validateNumberCheck(
        int $clientId,
        string $numCheckOrigin
    ): string {
        $numCheckFinal = $numCheckOrigin;

        $numCheckCount = GpCobro::where('cliente_id', $clientId)
            ->whereHas('cheques', function ($query) use ($numCheckOrigin) {
                $query->where('cheque', 'like', '%' . $numCheckOrigin);
            })
            ->count();

        if ($numCheckCount > 0) {
            do {
                $largeDynamic = strlen($numCheckOrigin) + $numCheckCount;

                $numCheckFinal = str_pad(
                    $numCheckOrigin,
                    $largeDynamic,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = GpCobro::where('cliente_id', $clientId)
                    ->whereHas('cheques', function ($query) use ($numCheckFinal) {
                        $query->where('cheque', $numCheckFinal);
                    })
                    ->exists();

                $numCheckCount++;
            } while ($exists);
        }

        return $numCheckFinal;
    }



    private function createCollectionCardsDetail(array $cardsCollection, int $collectionId): void
    {
        $bankDescription = GpCajaBancos::where('id', data_get($cardsCollection, 'banco_id'))->value('CB_ID');
        $this->collectBuilder->createCollectionCardsHeader($cardsCollection, $collectionId, $bankDescription);
        foreach (data_get($cardsCollection, 'facturas', []) as $cardDetail) {
            $this->collectBuilder->createCollectionCardsInvoice($cardDetail, $collectionId);
        }
    }

    private function createCollectionApplicationsDetail(array $applicationsCollection, int $collectionId): void
    {
        foreach ($applicationsCollection as $application) {
            $applicationHeader = $this->collectBuilder->createCollectionApplicationHeader($application, $collectionId);
            foreach (data_get($application, 'facturas', []) as $applicationDetail) {
                $this->collectBuilder->createCollectionApplicationDetail($applicationDetail, $collectionId, $applicationHeader->id);
            }
        }
    }
}
