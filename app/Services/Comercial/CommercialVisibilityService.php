<?php

namespace App\Services\Comercial;

use App\Models\GpUsuario;
use App\Models\Usuario\UsuarioVisibilidadLista;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Models\Ventas\Vendedores\VendedorAtributoValor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CommercialVisibilityService
{
    public function __construct()
    {
        // Inyecta dependencias
    }

    private const ADMIN_PROFILE_IDS = [1, 2];
    private const VISIBILITY_ATTRIBUTE_ID = 2;


    public function getAllowedUserIds(GpUsuario $user): ?Collection
    {
        if (in_array($user->perfil_id, self::ADMIN_PROFILE_IDS, true)) {
            return null;
        }

        $user->load('vendedor.tipoVendedor');
        $vendedor = $user->vendedor;
        if (!$vendedor) {
            Log::info('el Usuario no tiene perfil vendedor', [
                'usuario' => $user
            ]);
            return null;
        }

        $hasRestriction = (bool) data_get(
            $vendedor,
            'tipoVendedor.restriccion',
            false
        );

        if (!$hasRestriction) {
            return null;
        }

        $optionId = $vendedor
            ->atributoValores()
            ->where(
                'atributo_id',
                self::VISIBILITY_ATTRIBUTE_ID
            )
            ->value('opcion_id');

        if ($optionId === null) {
            return collect();
        }

        return GpVendedor::query()
            ->whereIn('id', function ($query) use ($optionId) {
                $query
                    ->select('vendedor_id')
                    ->from(
                        (new VendedorAtributoValor())->getTable()
                    )
                    ->where(
                        'atributo_id',
                        self::VISIBILITY_ATTRIBUTE_ID
                    )
                    ->where('opcion_id', $optionId);
            })
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
    }


    public function applyUserRestriction(
        Builder $query,
        GpUsuario $user,
        string $userColumn = 'user_id'
    ): Builder {
        $allowedUserIds = $this->getAllowedUserIds($user);

        if ($allowedUserIds !== null) {
            $query->whereIn($userColumn, $allowedUserIds);
        }

        return $query;
    }


    public function applyLimitsVisibiliteList(Builder $query, GpUsuario $user, int $pvId, string $type, string $userColumn = 'user_id'): Builder
    {
        if (in_array($user->perfil_id, self::ADMIN_PROFILE_IDS, true)) {
            return $query;
        }

        $visibilityColumn = match ($type) {
            'cotizaciones'  => 'ver_cot',
            'pedidos'       => 'ver_ped',
            'devoluciones'  => 'ver_dev',
            default        => throw new InvalidArgumentException(
                "Tipo de lista no válido: {$type}"
            ),
        };

        $configurations = UsuarioVisibilidadLista::query()
            ->where('usr_pr_id', $user->id)
            ->where('pv_id', $pvId)
            ->where('estado', true)
            ->get([
                'usr_ver_id',
                $visibilityColumn
            ]);

        if ($configurations->isEmpty()) {
            return $query;
        }

        $limitsUsers =  $configurations
            ->where($visibilityColumn, true)
            ->pluck('usr_ver_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($limitsUsers !== null) {
            $query->whereIn($userColumn, $limitsUsers);
        }
        return $query;
    }
}
