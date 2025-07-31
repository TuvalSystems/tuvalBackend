<?php

namespace App\Services\SqlServer\Cobranza;

use App\Services\SqlServer\Cobranza\Contracts\CreditValidationServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditValidationService implements CreditValidationServiceInterface
{
    use JsonResponseTrait;
    protected string $usuario = 'wsrvmc';
    protected string $equipo = 'wserver';


    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }

    /**
     *  modo 3
     */
    public function consultCreditValidate(string $idCliente, int $modo)
    {

        try {
            $results = DB::connection('sqlsrvtuv')->select(
                '
                DECLARE @codret INT, @msj VARCHAR(200);
                EXEC sp_mc_validacion_creditica
                  @idcliente = :idcliente,
                  @userid = :userid,
                  @modo = :modo,
                  @equipo = :equipo,
                  @codret = @codret OUTPUT,
                  @msj = @msj OUTPUT;
                 SELECT @codret AS codret, @msj AS msj;
                ',
                [
                    'idcliente' => $idCliente,
                    'userid'    => $this->usuario,
                    'modo'      => $modo,
                    'equipo'    => $this->equipo,
                ]
            );
            $data = collect($results)->filter(fn($row) => !isset($row->codret))->values();

            return $data;
        } catch (\Throwable $th) {
            Log::channel('sql_errors')->error('Error al consultar el crédito en el sp (sp_mc_validation_crediticia) : ' . $th->getMessage());
            return $this->buildErrorResponse('Error al consultar el crédito en el sp (sp_mc_validation_crediticia) :' . $th->getMessage(), 500);
        }
    }
}
