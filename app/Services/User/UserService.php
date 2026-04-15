<?php

namespace App\Services\User;

use App\Models\GpUsuario;
use App\Models\GpProveedor;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpCondicionPago;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\Admin\Contracts\MenuServiceInterface;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    use JsonResponseTrait;

    public function __construct(protected readonly ApiGpService $apiGp,
    protected readonly MenuServiceInterface $menu) {}

    public function isValidUserWeb(array $credentials): bool
    {
        $user = GpUsuario::where('USERID', $credentials['username'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
        }
        return true;
    }
    public function validateStatusGpUser($credentials): bool
    {
        $user = GpUsuario::where('USERID', $credentials['username'])->where('status', true)->first();
        if (!$user) {
            return false;
        }
        return true;
    }

    public function getActiveUser($username): ?Model
    {
        return GpUsuario::activo()
            ->where('USERID', trim($username))
            ->select([
                'id',
                'perfil_id',
                'USERID',
                'USERNAME',
                'must_change_password',
                'status'
            ])
            ->first();
    }

    public function validateCredentials(Request $request): array
    {
        return $request->validate([
            'username' => 'required|string',
            'password' => 'required'
        ]);
    }

    public function validateUserCredentials(array $credentials): array
    {
        $params = array(
            'idCompany' => config('services.api_gp.company'),
            'username'  => $credentials['username'],
            'password'  => $credentials['password']
        );

        $resp = $this->apiGp->validateUser($params);
        $rSuccess = data_get($resp, 'success', false);
        if (!$rSuccess) {
            throw ValidationException::withMessages(['username' => 'El usuario no existe o la contraseña es incorrecta.']);
        }
        return data_get($resp, 'data', []);
    }

    public function isValidUser(array $validationResponse): bool
    {
        return isset($validationResponse['codRet']) && $validationResponse['codRet'] == '0';
    }



    public function buildSuccessResponseUser($user): array
    {
        return [
            'user'                  => $this->formatuserData($user),
            'token'                 => $this->generateAuthToken($user),
            'menu'                  => $this->menu->getFullMenuLayout($user->perfil_id)['arbol'],
            'home'                  => $this->menu->getFullMenuLayout($user->perfil_id)['home'],
            'puntosdeventa'         => $this->getUserSalesPoints($user->USERID),
            'puntodeventaselect'    => $this->getDefaultSalesPoint($user->USERID),
            'formadepago'           => $this->getPaymentMethods(),
            'condiciondepago'       => $this->getPaymentConditions(),
            'proveedores'           => $this->getSuppliers(),
            'must_change_password'  => $user->must_change_password
        ];
    }

    public function formatUserData($user): array
    {
        return [
            'id'        => $user->id,
            'USERID'    => $user->USERID,
            'USERNAME'  => $user->USERNAME,
            'perfil_id' => $user->perfil_id,
            'status'    => $user->status,
        ];
    }

    public function generateAuthToken($user): string
    {
        return $user->createToken($user->USERID)->plainTextToken;
    }



    public function getUserSalesPoints($userID)
    {
        return GpPuntoVenta::join('gp_relacion_usuario_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', '=', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA')
            ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', $userID)
            ->select('gp_punto_ventas.*')
            ->with('bodegas')
            ->orderBy('gp_relacion_usuario_punto_ventas.orden', 'asc')
            ->get()
            ->unique('PTO_VTA_ID')
            ->values();
    }


    public function getDefaultSalesPoint($userId): mixed
    {
        return GpPuntoVenta::join('gp_relacion_usuario_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', '=', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA')
            ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', $userId)
            ->select('gp_punto_ventas.*')
            ->with('bodegas')
            ->orderBy('gp_relacion_usuario_punto_ventas.orden', 'asc')
            ->first();
    }

    public function getPaymentMethods(): Collection
    {
        return GpDescuentoFormaPago::orderBy('DESC_FORMA_PAGO_CODIGO')->get();
    }

    public function getPaymentConditions(): Collection
    {
        return GpCondicionPago::all();
    }

    public function getSuppliers(): Collection
    {
        return GpProveedor::select('id', 'PROV_RAZON_SOCIAL')
            ->where('PROV_CLASE', '!=', 'PR-EXT')
            ->orderBy('PROV_RAZON_SOCIAL')->get();
    }




    /**
     * Get the validation rules that apply to the request.
     * Cambio de contraseña
     */

    public function changePassword($data, GpUsuario $user)
    {
        $user->must_change_password = false;
        $user->password = Hash::make($data['password']);
        $user->save();
        return $user->refresh();
    }

    /**
     * Reset password
     */
    public function resetPassword($userID)
    {
        $user = GpUsuario::where('USERID', $userID)->first();
        $user->must_change_password = true;
        $user->password = Hash::make($user->USERID);
        $user->process_job = 1;
        $user->save();
        return $user->refresh();
    }


    /***
     *  changeStatusGpUser
     *
     *
     */

    public function changeStatusGpUser(string $userId)
    {
        $user = GpUsuario::where('USERID', $userId)->firstOrFail();
        $user->status = $user->status === 1 ? 0 : 1;
        $user->save();
    }




    /** proceso de notificaciones de usuarios */
    public function getAllNotifications(int $userId, int $perPage = 10, $search = null, $sortBy = null, $sortOrder = 'desc')
    {
        $gpUser = GpUsuario::findOrFail($userId);
        $allowedSorts = ['created_at', 'read_at', 'title'];
        $sortOrder = strtolower($sortOrder ?? 'desc');
        $sortOrder = $sortOrder === 'asc' ? 'asc' : 'desc';
        $query = $gpUser->notifications()->whereNull('read_at')
            ->when($search, function ($que) use ($search) {
                $que->where('data->title', 'like', "%{$search}%")
                    ->orWhere('data->message', 'like', "%{$search}%");
            });
        if ($sortBy && in_array($sortBy, $allowedSorts, true)) {
            // ordenar por título (campo JSON)
            if ($sortBy === 'title') {
                $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.title')) {$sortOrder}");
            } else {
                // ordenar por columnas reales de la tabla
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // orden por defecto
            $query->orderBy('created_at', 'desc');
        }
        $paginator = $query->paginate($perPage);
        $paginator->getCollection()->transform(function ($n) {
            return [
                'id'            => $n->id,
                'title'         => data_get($n->data, 'title', 'Notificación'),
                'message'       => data_get($n->data, 'message', ''),
                'read_at'       => $n->read_at,
                'time'          => $n->created_at->diffForHumans(),
                'created_at'    => $n->created_at->toDateTimeString()
            ];
        });

        return [
            'current_page'  => $paginator->currentPage(),
            'data'          => $paginator->items(), // lo que va a tu BaseTable
            'from'          => $paginator->firstItem(),
            'last_page'     => $paginator->lastPage(),
            'per_page'      => $paginator->perPage(),
            'to'            => $paginator->lastItem(),
            'total'         => $paginator->total(),
            'unread_count'  => $gpUser->unreadNotifications()->count(),
        ];
    }


    public function markAsReadNotify(string $notifyId, int $userId)
    {
        $notify = GpUsuario::find($userId)->notifications()->findOrFail($notifyId);
        if (is_null($notify->read_at)) {
            $notify->markAsRead();
        }
        return $notify;
    }


    public function markAllReadNotify(int $userId)
    {
        GpUsuario::find($userId)->unreadNotifications->markAsRead();
    }




    /** proceso para obtener las bodegas relacionadas de un usuario */

    public function getWarehouesByUser(string $userName, string $ptoVtaId)
    {
        return GpRelacionPuntoVentaBodegaRelacionada::with([
            'relacionBodega' => function ($query) {
                $query->select('id', 'BOD_BODEGA', 'PERMITE_DESPACHO');
            },
        ])
            ->where('PTO_VTA_BOD_REL_PUNTO_VENTA', $ptoVtaId)
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA')
            ->whereHas('puntoVenta.relacionUsuarioPtoVta', function ($query) use ($userName) {
                $query->where('USER_PTO_VTA_USUARIO', $userName);
            })
            ->get();
    }



    /** proceso para obtener las condiciones de pago y formas de pago  */

    public function getAllPaymentConditions()
    {
        return GpCondicionPago::select('id', 'COND_DIAS as condDias', 'COND_ID as condId', 'COND_TIPO_DESCUENTO as tipoDesc', 'COND_VALOR as condValor')
            ->orderBy('COND_ID', 'desc')->get();
    }


    public function getAllPaymentMethods($condId)
    {
        return GpDescuentoFormaPago::select(
            'id',
            'DESC_FORMA_PAGO_CODIGO as codigo',
            'DESC_FORMA_PAGO_DIAS_CREDITO as dias',
            'DESC_FORMA_PAGO_TIPO_CREDITO as tipo',
            'DESC_FORMA_PAGO_PORCENTAJE_REBAJAR as porcentaje_rebaja',
            'NIVEL as nivel'
        )
            ->where('DESC_FORMA_PAGO_CONDICION_PAGO', trim($condId))
            ->orderBy('DESC_FORMA_PAGO_CODIGO')->get();
    }
}
