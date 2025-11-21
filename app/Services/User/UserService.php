<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\GpUsuario;
use App\Models\GpProveedor;
use App\Models\GpPuntoVenta;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\GpCondicionPago;
use Illuminate\Http\JsonResponse;
use App\Services\Api\ApiGpService;
use Illuminate\Support\Facades\DB;
use App\Models\GpDescuentoFormaPago;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\SectionsController;
use Illuminate\Database\Eloquent\Collection;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    use JsonResponseTrait;

    protected $apiGp;
    public function __construct(ApiGpService $apiGp)
    {
        $this->apiGp = $apiGp;
    }

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
        return GpUsuario::join('perfiles', 'perfiles.id', 'gp_usuarios.perfil_id')
            ->where('USERID', trim($username))
            ->where('perfiles.state', 'active')
            ->where('gp_usuarios.status', true)
            ->select('gp_usuarios.id', 'gp_usuarios.perfil_id', 'gp_usuarios.USERID', 'gp_usuarios.USERNAME', 'gp_usuarios.must_change_password', 'gp_usuarios.status')
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
            'username' => $credentials['username'],
            'password' => $credentials['password']
        );

        $resp = $this->apiGp->validateUser($params);
        $rSuccess = data_get($resp, 'success', false);
        if (!$rSuccess) {
            throw ValidationException::withMessages(['username' => 'El usuario no existe o la contraseña es incorrecta.']);
        }
        $data = data_get($resp, 'data', []);
        return $data;
    }

    public function isValidUser(array $validationResponse): bool
    {
        return isset($validationResponse['codRet']) && $validationResponse['codRet'] == '0';
    }



    public function buildSuccessResponseUser($user): array
    {
        return [
            'user' => $this->formatuserData($user),
            'token' => $this->generateAuthToken($user),
            'menu' => $this->getUserMenu($user->perfil_id)['arbol'],
            'home' => $this->getUserMenu($user->perfil_id)['home'],
            'puntosdeventa' => $this->getUserSalesPoints($user->USERID),
            'puntodeventaselect' => $this->getDefaultSalesPoint($user->USERID),
            'formadepago' => $this->getPaymentMethods(),
            'condiciondepago' => $this->getPaymentConditions(),
            'proveedores' => $this->getSuppliers(),
            'must_change_password' => $user->must_change_password
        ];
    }

    public function formatUserData($user): array
    {
        return [
            'id' => $user->id,
            'USERID' => $user->USERID,
            'USERNAME' => $user->USERNAME,
            'perfil_id' => $user->perfil_id,
            'status' => $user->status,
        ];
    }

    public function generateAuthToken($user): string
    {
        return $user->createToken($user->USERID)->plainTextToken;
    }

    public function getUserMenu(int $profileId)
    {
        return (new SectionsController())->listadeseccionesmenu($profileId);
    }

    public function getUserSalesPoints($userID)
    {
        $puntosdeventa = DB::table('gp_punto_ventas')
            ->join('gp_relacion_usuario_punto_ventas', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA', '=', 'gp_punto_ventas.PTO_VTA_ID')
            ->select('gp_punto_ventas.*')
            ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', '=', $userID)
            ->orderBy('gp_punto_ventas.PTO_VTA_ID')
            ->get();

        $nuevo_arr = [];
        foreach ($puntosdeventa as $puntodeventa) {
            $this->getBodegas($puntodeventa);
            $ptoVta = data_get($puntodeventa, 'PTO_VTA_ID');
            $nuevo_arr[$ptoVta] = $puntodeventa;
        }

        return array_values($nuevo_arr);
    }


    public function getDefaultSalesPoint($userID): mixed
    {
        $puntodeventaselect = DB::table('gp_punto_ventas')
            ->join('gp_relacion_usuario_punto_ventas', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA', '=', 'gp_punto_ventas.PTO_VTA_ID')
            ->select('gp_punto_ventas.*')
            ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', '=', $userID)
            ->first();

        $this->getBodegas($puntodeventaselect);

        if (!$puntodeventaselect) {
            $puntodeventaselect = GpPuntoVenta::get()->first();
        }
        return $puntodeventaselect;
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

    public function getBodegas(mixed $puntodeventa): void
    {
        $arr_bodegas = array();


        $masbodegas = GpRelacionPuntoVentaBodegaRelacionada::join('gp_bodegas', 'gp_bodegas.BOD_BODEGA', 'gp_relacion_punto_venta_bodega_relacionadas.PTO_VTA_BOD_REL_BODEGA')
            ->where('PTO_VTA_BOD_REL_PUNTO_VENTA', $puntodeventa->PTO_VTA_ID)
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA')
            ->select('gp_relacion_punto_venta_bodega_relacionadas.*', 'gp_bodegas.PERMITE_DESPACHO')
            ->get()->toArray();

        foreach ($masbodegas as $bodega) {
            $arr_bodegas[$bodega['PTO_VTA_BOD_REL_BODEGA']] = $bodega;
        }
        $puntodeventa->bodegas = array_values($arr_bodegas);
    }


    /**
     * Get the validation rules that apply to the request.
     * Cambio de contraseña
     */

    public function changePassword($data)
    {
        try {
            $user = GpUsuario::where('USERID', Auth::user()->USERID)->first();
            $user->must_change_password = false;
            $user->password = Hash::make($data['password']);
            $user->save();
            return $this->successResponse('Contraseña actualizada correctamente!', ['must_change_password' => $user->must_change_password], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validación fallida',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $th) {
            return $this->errorResponse('Error', $th->getMessage(), 500);
        }

    }

    /**
     * Reset password
     */
    public function resetPassword($data)
    {
        try {
            $user = GpUsuario::where('USERID', $data[0])->first();
            $user->must_change_password = true;
            $user->password = Hash::make($user->USERID);
            $user->process_job = 1;
            $user->save();
            return $this->buildSuccessResponse('Contraseña restaurada correctamente!', 200);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error', $th->getMessage(), 500);
        }
    }


    /***
     *  changeStatusGpUser
     *
     *
     */

    public function changeStatusGpUser($userId)
    {

        try {
            $user = GpUsuario::where('USERID', $userId[0])->first();
            $user->status = $user->status === 1 ? 0 : 1;
            $user->save();
            return $this->buildSuccessResponse('Estado actualizado correctamente!', 200);
        } catch (\Throwable $th) {
            return $this->errorResponse('Error', $th->getMessage(), 500);
        }
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
                'id' => $n->id,
                'title' => data_get($n->data, 'title', 'Notificación'),
                'message' => data_get($n->data, 'message', ''),
                'read_at' => $n->read_at,
                'time' => $n->created_at->diffForHumans(),
                'created_at' => $n->created_at->toDateTimeString()
            ];
        });

        return [
            'current_page' => $paginator->currentPage(),
            'data' => $paginator->items(), // lo que va a tu BaseTable
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'unread_count' => $gpUser->unreadNotifications()->count(),
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
}
