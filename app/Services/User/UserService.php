<?php

namespace App\Services\User;

use App\Models\GpUsuario;
use App\Models\GpProveedor;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use App\Models\GpCondicionPago;
use App\Services\Api\ApiGpService;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Collection;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Models\Perfiles;
use App\Services\Admin\Contracts\MenuServiceInterface;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService implements UserServiceInterface
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly ApiGpService $apiGp,
        protected readonly MenuServiceInterface $menu
    ) {}


    public function listUsers(array $filters): LengthAwarePaginator
    {
        $search = data_get($filters, 'search', '');
        $perpage = data_get($filters, 'perpage', 10);
        $sortField = data_get($filters, 'sortField', 'created_at');
        $sortOrder = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [
            'id'                => 'us.id',
            'USERID'            => 'us.USERID',
            'USERNAME'          => 'us.USERNAME',
            'perfil_name'       => 'pf.name',
            'perfil_app_name'   => 'pfa.name',
            'status'            => 'us.status',
            'created_at'        => 'us.created_at',
            'is_admin_cobros'   => 'us.is_admin_cobros',
            'app_access'        => 'us.app_access'
        ];
        $orderColumn = data_get($allowedSorts, $sortField, 'us.created_at');

        $query = GpUsuario::select([
            'us.id',
            'us.USERID',
            'us.USERNAME',
            'us.status',
            'us.must_change_password',
            'pf.name as perfil_name',
            'pfa.name as perfil_app_name',
            'us.is_admin_cobros',
            'us.app_access',
            'us.created_at'
        ])
            ->from('gp_usuarios as us')
            ->join('perfiles as pf', 'pf.id', '=', 'us.perfil_id')
            ->leftJoin('perfiles as pfa', 'pfa.id', '=', 'us.perfil_app_id');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('us.USERID', 'like', "%{$search}%")
                    ->orWhere('us.USERNAME', 'like', "%{$search}%")
                    ->orWhere('pf.name', 'like', "%{$search}%")
                    ->orWhere('pfa.name', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);
    }




    /**
     * Summary of authenticateUser
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function authenticateUser(string $userName, string $password): array
    {
        $user = GpUsuario::query()
            ->activo()
            ->with([
                'perfil:id,name,state',
                'perfilApp:id,name',
            ])
            ->where('USERID', $userName)
            ->first();

        $this->isValidUserWeb($user, $password);
        return [
            'user'                  => $this->formatuserData($user),
            'token'                 => $this->generateAuthToken($user),
            'menu'                  => $this->menu->getFullMenuLayout($user->perfil_id)['arbol'],
            'home'                  => $this->menu->getFullMenuLayout($user->perfil_id)['home'],
            'puntosdeventa'         => $this->getUserSalesPoints($user->id),
            'puntodeventaselect'    => $this->getDefaultSalesPoint($user->id),
            'formadepago'           => $this->getPaymentMethods(),
            'condiciondepago'       => $this->getPaymentConditions(),
            'proveedores'           => $this->getSuppliers(),
            'must_change_password'  => $user->must_change_password
        ];
    }



    protected function isValidUserWeb(GpUsuario $user, string $password): void
    {
        if (!$user) {
            throw new \RuntimeException('Opps! Verifique las credenciales o el perfil asignado');
        }
        if (!Hash::check($password, $user->password)) {
            throw new \RuntimeException('Las credenciales no coinciden');
        }

        if (!$user->status) {
            throw new \RuntimeException('El usuario no se encuentra activo');
        }

        if (!$user->perfil_id) {
            throw new \RuntimeException('El usuario no tiene un perfil web asignado');
        }
    }


    public function formatUserData(GpUsuario $user): array
    {
        return [
            'id'            => $user->id,
            'USERID'        => $user->USERID,
            'USERNAME'      => $user->USERNAME,
            'perfil_id'     => $user->perfil_id,
            'perfil_app_id' => $user->perfil_app_id,
            'status'        => $user->status,
            'app_access'    => $user->app_access
        ];
    }

    public function generateAuthToken(GpUsuario $user): string
    {
        return $user->createToken($user->USERID)->plainTextToken;
    }



    public function getUserSalesPoints(int $userId)
    {
        return GpPuntoVenta::join('gp_relacion_usuario_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', '=', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA')
            ->where('gp_relacion_usuario_punto_ventas.user_id', $userId)
            ->select('gp_punto_ventas.*')
            ->with('bodegas')
            ->orderBy('gp_relacion_usuario_punto_ventas.orden', 'asc')
            ->get()
            ->unique('PTO_VTA_ID')
            ->values();
    }


    public function getDefaultSalesPoint(int $userId): mixed
    {
        return GpPuntoVenta::join('gp_relacion_usuario_punto_ventas', 'gp_punto_ventas.PTO_VTA_ID', '=', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA')
            ->where('gp_relacion_usuario_punto_ventas.user_id', $userId)
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

    public function getSuppliers(): mixed
    {
        return GpProveedor::select('id', 'PROV_RAZON_SOCIAL')
            ->where('PROV_CLASE', '!=', 'PR-EXT')
            ->orderBy('PROV_RAZON_SOCIAL')->get();
    }







    /**
     * Get the validation rules that apply to the request.
     * Cambio de contraseña
     */

    public function changePassword(array $data, GpUsuario $user)
    {
        $user->must_change_password = false;
        $user->password = Hash::make($data['password']);
        $user->save();
        return $user->refresh();
    }

    /**
     * Reset password
     */
    public function resetPassword(int $id)
    {
        $user = GpUsuario::find($id);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado');
        }
        $user->must_change_password = true;
        $user->password             = Hash::make($user->USERID);
        $user->process_job          = 1;
        $user->save();
        return $user->refresh();
    }


    /***
     *  changeStatusGpUser
     *
     *
     */

    public function changeStatusGpUser(int $userId)
    {
        $user = GpUsuario::find($userId);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado');
        }
        $user->status = $user->status === true ? false : true;
        $user->save();
    }


    public function updateMobileAppAccess(int $id)
    {
        $user = GpUsuario::find($id);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado');
        }
        $user->app_access = $user->app_access === true ? false : true;
        $user->save();
    }

    public function updateAdminCollectionAccess(int $id)
    {
        $user = GpUsuario::find($id);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado');
        }
        $user->is_admin_cobros = $user->is_admin_cobros === true ? false : true;
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


    public function getAllPaymentMethods(string $condId)
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




    public function getDetailUser(int $id): ?GpUsuario
    {
        $user = GpUsuario::with([
            'perfil:id,name',
            'perfilApp:id,name',
        ])->find($id);
        if (!$user) {
            throw new \RuntimeException("Usuario no encontrado");
        }
        return $user;
    }


    public function getProfilesWeb()
    {
        return Perfiles::where('state', 1)->where('platform', '=', 'web')->get();
    }

    public function getProfilesApp()
    {
        return Perfiles::where('state', 1)->where('platform', '=', 'app')->get();
    }


    public function updateUserProfiles(array $payload)
    {
        $id = data_get($payload, 'user_id');
        $user = GpUsuario::find($id);
        if (!$user) {
            throw new \RuntimeException("Usuario no encontrado");
        }

        if (array_key_exists('perfil_id', $payload)) {
            $user->perfil_id = data_get($payload, 'perfil_id');
        }

        if (array_key_exists('perfil_app_id', $payload)) {
            $user->perfil_app_id = data_get($payload, 'perfil_app_id');
        }

        $user->save();
    }
}
