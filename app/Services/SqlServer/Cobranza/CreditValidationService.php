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
            $sql = <<<SQL
            DECLARE @codret INT, @msj VARCHAR(200);
            EXEC sp_mc_validacion_creditica
                @idcliente = :idcliente,
                @userid    = :userid,
                @modo      = :modo,
                @equipo    = :equipo,
                @codret    = @codret OUTPUT,
                @msj       = @msj OUTPUT;
            SELECT @codret AS codret, @msj AS msj;
        SQL;

            $bindings = [
                'idcliente' => $idCliente,
                'userid'    => $this->usuario,
                'modo'      => $modo,
                'equipo'    => $this->equipo,
            ];

            $results = DB::connection('sqlsrvtuv')->select($sql, $bindings);

            // return collect($results)->reject(fn($row) => isset($row->codret))->values();
            return collect($results)
                ->flatten()
                ->reject(fn($row) => property_exists($row, 'codret'))
                ->values();
        } catch (\Throwable $th) {
            Log::channel('sql_errors')->error('Error en consultCreditValidate (sp_mc_validacion_creditica):', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return collect(); // o return $this->buildErrorResponse($message, 500);
        }
    }
}
