<?php

namespace App\Services\Venta;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpUsuario;
use App\Services\Api\ApiGpService;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Venta\Contracts\ClientQueryServiceInterface;
use Illuminate\Database\Eloquent\Builder;

class ClientQueryService implements ClientQueryServiceInterface
{

    public function __construct(
        protected readonly ApiGpService $apiGp,
        protected readonly FunctionsServiceInterface $functionsService,
        protected readonly ClientBuilderInterface $builder
    ) {
        // Inyecta dependencias si es necesario
    }

    public function getAllClients(string $term, ?string $type = null)
    {
        $searchTerm = '%' . str_replace(' ', '%', $term) . '%';

        $clientes = GpCliente::query()
            ->from('gp_clientes as gpc')
            ->select([
                'gpc.id',
                'gpc.CLI_RUC',
                'gpc.CLI_RAZON_SOCIAL',
                'gpc.CLI_NOMBRE_CORTO',
                'gpc.CLI_CONTACTO',
                'gpc.CLI_CIUDAD',
                'gpc.CLI_PROVINCIA',
                'gpc.CLI_TELEFONO_1',
                'gpc.CLI_CORREO_ELECT_SRI',
            ])
            ->where('CLI_RUC', '!=', '9999999999999')
            ->where(function (Builder $query) use ($searchTerm) {
                $query->where('gpc.CLI_RUC', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_RAZON_SOCIAL', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_NOMBRE_CORTO', 'LIKE', $searchTerm)
                    ->orWhere('gpc.CLI_CORREO_ELECT_SRI', 'LIKE', $searchTerm);
            })
            ->limit(200)
            ->get();

        /** verificacion de emails  */
        return $clientes->transform(function ($client) {
            $rawEmail = $client->CLI_CORREO_ELECT_SRI;
            if (empty($rawEmail) || trim($rawEmail) == '') {
                $client->emails_validated = true;
                return $client;
            }

            $emails = preg_split('/[;]/', $rawEmail, -1, PREG_SPLIT_NO_EMPTY);
            $allValid = true;


            foreach ($emails as $email) {
                $emailClear = trim($email);

                if ($emailClear == '') continue;

                if (!filter_var($emailClear, FILTER_VALIDATE_EMAIL)) {
                    $allValid = false;
                    break; // Si uno falla, ya no es válido para el Request, cortamos aquí.
                }
            }
            $client->emails_validated = $allValid;
            return $client;
        });
    }



    public function getAvailabeCreditCustomer(string $ruc): array
    {
        $cliente = GpCliente::with([
            'gpvendedor:id,VEND_ID,VEND_NOMBRES,VEND_APELLIDOS',
        ])
            ->where('CLI_RUC', $ruc)->first();

        $cupoDisponible = $this->functionsService->getClientCheckAvailableQuota($ruc);
        $cliente->suspendido = data_get($cupoDisponible, 'suspendido', 0);
        $cliente->cupo_disponible = number_format(data_get($cupoDisponible, 'cupoDisponibleGp', 0), 2, '.', ',');
        $cliente->cupo_asignado = number_format(data_get($cupoDisponible, 'cupoAsignado', 0), 2, '.', ',');
        $segmentos = $this->getSegmentClient($ruc);
        return compact('cliente', 'segmentos');
    }


    public function getSegmentClient(string $ruc)
    {
        $resp = $this->apiGp->fetchClientSegment(['ruc' => $ruc]);
        return data_get($resp, 'data', []);
    }


    public function getCustomerBranches(string $ruc): mixed
    {
        $sucursales =  GpClienteSucursal::where('CLI_SUC_RUC', $ruc)
            ->orderBy('CLI_SUC_ID_DIRECCION', 'DESC')
            ->select([
                'id',
                'CLI_SUC_ID_DIRECCION',
                'CLI_SUC_DIRECCION',
                'CLI_SUC_CORREO_ELECTRONICO_SRI',
                'coordenadas'
            ])
            ->get();
        return $sucursales->transform(function ($client) {
            $rawEmail = $client->CLI_SUC_CORREO_ELECTRONICO_SRI;
            if (empty($rawEmail) || trim($rawEmail) == '') {
                $client->emails_validated = true;
                return $client;
            }

            $emails = preg_split('/[;]/', $rawEmail, -1, PREG_SPLIT_NO_EMPTY);
            $allValid = true;


            foreach ($emails as $email) {
                $emailClear = trim($email);

                if ($emailClear == '') continue;

                if (!filter_var($emailClear, FILTER_VALIDATE_EMAIL)) {
                    $allValid = false;
                    break; // Si uno falla, ya no es válido para el Request, cortamos aquí.
                }
            }
            $client->emails_validated = $allValid;
            return $client;
        });
    }



    /** proceso para crear la nueva direccion del cliente - sucursal */

    public function storeNewAddressClient(array $data, GpUsuario $userGp)
    {
        $cliente = GpCliente::where('id', data_get($data, 'cliente_id'))->first();
        $email = strtolower(data_get($data, 'email', ''));
        $textoEnvuelto = wordwrap(data_get($data, 'direccion', ''), 61, "\n", true);
        $dir = explode("\n", $textoEnvuelto);

        $payloadGp = $this->builder->formPayloadNewAddress($data, $cliente, $dir, $email);
        $respInfo = $this->sendToGpNewAddress($payloadGp);
        $identificador = strtoupper(data_get($respInfo, 'identificador', ''));
        return $this->builder->formPayloadNewSucursal($data, $cliente, $identificador, $email, $dir, $userGp);
    }


    public function sendToGpNewAddress($params)
    {
        $resp = $this->apiGp->storeNewAddressClient($params);
        $success = data_get($resp, 'success', false);
        if (!$success) {
            abort(404, 'No se pudo crear la direccion del cliente');
        }
        return data_get($resp, 'data', []);
    }
}
