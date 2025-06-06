<?php

namespace App\Http\Controllers;

use App\Models\Notificaciones;
use App\Models\GpUsuario;
use App\Models\GpPuntoVenta;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpCondicionPago;
use App\Models\GpProveedor;
use App\Models\GpRelacionPuntoVentaBodegaRelacionada;
use App\Services\Api\ApiGpService;
use App\Traits\JsonResponseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\SectionsController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use JsonResponseTrait;
    protected $apiGp ;

    public function __construct(ApiGpService $apiGp)
    {
        $this->apiGp = $apiGp;
    }

    public function login(Request $request)
    {
        $credentials = $this->validateCredentials($request);
        $validationResponse = $this->validateuserCredentials($credentials);

        if (!$this->isValidUser($validationResponse)) {
            return $this->buildErrorResponse($validationResponse['msj'] ?? 'Usuario no encontrado', 404);
        }
        if (!$this->isValidUserWeb($credentials)) {
            return $this->buildErrorResponse('Las credenciales no son validas', 401);
        }
        $user = $this->getActiveUser($credentials['username']);
        $userWeb = $this->getUserWeb($credentials['username']);
        if (!$user) {
            return $this->buildErrorResponse('El perfil asignado se encuentra inactivo', 404);
        }
        return $this->buildSuccessResponse($user, $userWeb);
    }

    public function isValidUserWeb(array $credentials): bool
    {
         $user = User::where('username', $credentials['username'])->where('status', true)->first();
         if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
         }
         return true;
    }

    public function getUserWeb($username): ?Model
    {
        return User::where('username', $username)->first();
    }
    public function getActiveUser($username): ?Model
    {
        return  GpUsuario::join('perfiles', 'perfiles.id', 'gp_usuarios.perfil_id')
            ->where('USERID', trim($username))
            ->where('perfiles.state', 'active')
            ->select('gp_usuarios.id', 'gp_usuarios.perfil_id', 'gp_usuarios.USERID', 'gp_usuarios.USERNAME')
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
        );
        return  $this->apiGp->validateUser($params);
    }

    private function isValidUser(array $validationResponse): bool
    {
        return isset($validationResponse['codret']) && $validationResponse['codret'] == '0';
    }



    public function buildSuccessResponse($user, $userWeb): JsonResponse
    {

        return  response()->json([
            'user' => $this->formatuserData($user),
            'token' => $this->generateAuthToken($user),
            'menu' =>  $this->getUserMenu($user->perfil_id)['arbol'],
            'home' =>  $this->getUserMenu($user->perfil_id)['home'],
            'puntosdeventa' => $this->getUserSalesPoints($user->USERID),
            'puntodeventaselect' => $this->getDefaultSalesPoint($user->USERID),
            'formadepago' =>  $this->getPaymentMethods(),
            'condiciondepago' => $this->getPaymentConditions(),
            'proveedores' => $this->getSuppliers(),
            'userWeb' => $userWeb

        ]);
    }

    public function formatUserData($user): array
    {
        return [
            'USERID' => $user->USERID,
            'USERNAME' => $user->USERNAME,
            'perfil_id' => $user->perfil_id
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
            $nuevo_arr[$puntodeventa->PTO_VTA_ID] = $puntodeventa;
        }

        return  array_values($nuevo_arr);
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
        return  GpDescuentoFormaPago::orderBy('DESC_FORMA_PAGO_CODIGO')->get();
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



    // public function login2(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    // {
    //     $credentials = $request->validate([
    //         'username' => 'required|string',
    //         'password'=> [
    //             'required',
    //         ]
    //     ]);
    //     $ws = new WebserviceController;
    //     $valida = $ws->validarusuario($credentials);


    //     if(isset($valida['Codigo']) && $valida['Codigo'] == '0'){ //$valida['Codigo']) && $valida['Codigo'] == '0'
    //         $usuario = GpUsuario::join('perfiles', 'perfiles.id', 'gp_usuarios.perfil_id')
    //             ->where('USERID', trim($credentials['username']))
    //             ->where('perfiles.state','active')
    //             ->select('gp_usuarios.id', 'gp_usuarios.perfil_id', 'gp_usuarios.USERID', 'gp_usuarios.USERNAME')
    //             ->first();

    //         if(!$usuario){
    //             return response([
    //                 'error' => 'El perfil asignado se encuentra inactivo'
    //             ], 401);
    //         }

    //         $token = $usuario->createToken($usuario->USERID)->plainTextToken;

    //         $sections = new SectionsController();
    //         $menu = $sections->listadeseccionesmenu($usuario['perfil_id']);

    //         $puntosdeventa = DB::table('gp_punto_ventas')
    //         ->join('gp_relacion_usuario_punto_ventas', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA', '=', 'gp_punto_ventas.PTO_VTA_ID')
    //         ->select('gp_punto_ventas.*')
    //         ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', '=', $usuario['USERID'])
    //         ->orderBy('gp_punto_ventas.PTO_VTA_ID')
    //         ->get();

    //         $nuevo_arr = [];
    //         foreach($puntosdeventa as $puntodeventa){
    //             $this->getBodegas($puntodeventa);
    //             $nuevo_arr[$puntodeventa->PTO_VTA_ID] = $puntodeventa;
    //         }

    //         $puntosdeventa = array_values($nuevo_arr);

    //         $puntodeventaselect = DB::table('gp_punto_ventas')
    //         ->join('gp_relacion_usuario_punto_ventas', 'gp_relacion_usuario_punto_ventas.USER_PTO_VTA_PUNTO_VENTA', '=', 'gp_punto_ventas.PTO_VTA_ID')
    //         ->select('gp_punto_ventas.*')
    //         ->where('gp_relacion_usuario_punto_ventas.USER_PTO_VTA_USUARIO', '=', $usuario['USERID'])
    //         ->first();

    //         $this->getBodegas($puntodeventaselect);


    //         if(!$puntodeventaselect){
    //             $puntodeventaselect = GpPuntoVenta::get()->first();
    //         }

    //         $formadepago = GpDescuentoFormaPago::orderBy('DESC_FORMA_PAGO_CODIGO')->get();

    //         $condiciondepago = GpCondicionPago::get();

    //         $proveedores = GpProveedor::select('id', 'PROV_RAZON_SOCIAL')
    //                 ->where('PROV_CLASE','!=','PR-EXT')
    //                 ->orderBy('PROV_RAZON_SOCIAL')->get();

    //         return response([
    //             'user' => [
    //                 'USERID' => $usuario['USERID'],
    //                 'USERNAME' => $usuario['USERNAME'],
    //                 'perfil_id' => $usuario['perfil_id'],
    //             ],
    //             'token' => $token,
    //             'menu' => $menu['arbol'],
    //             'home' => $menu['home'],
    //             'puntosdeventa' => $puntosdeventa,
    //             'puntodeventaselect' => $puntodeventaselect,
    //             'formadepago' => $formadepago,
    //             'condiciondepago' => $condiciondepago,
    //             'proveedores' => $proveedores,
    //         ]);


    //     }else{
    //         return response([
    //             'error' => $valida['Mensaje']
    //         ], 401);
    //     }
    // }



    /**
     * Función de salida del sistema
     */

    public function logout(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();

        $user->currentAccessToken()->delete();
        return response([
            'success' => true
        ]);
    }

    public function notificaciones(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();

        $notificacion = Notificaciones::where('user_id', $user->id)
            ->where('estado', 'nuevo')->first();

        if($notificacion){

            $notificacion->estado = 'enviado';
            $notificacion->save();

            return response([
                'notificacion' => true,
                'titulo' => $notificacion->titulo,
                'mensaje' => $notificacion->mensaje,

            ]);
        }

        return response([
            'notificacion' => false,
        ]);
    }
    /**
     * @param mixed $puntodeventa
     * @return void
     */
    public function getBodegas(mixed $puntodeventa): void
    {
        $arr_bodegas = array();
//        $bodega = GpRelacionPuntoVentaBodega::join('gp_bodegas','gp_bodegas.BOD_BODEGA','gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA')
//            ->where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa->PTO_VTA_ID)
//            ->select('gp_relacion_punto_venta_bodegas.*', 'gp_bodegas.PERMITE_DESPACHO')
//            ->get()->first()->toArray();
//        if ($bodega)
//            $arr_bodegas[$bodega['PTO_VTA_BOD_BODEGA']] = $bodega;


        $masbodegas = GpRelacionPuntoVentaBodegaRelacionada::join('gp_bodegas','gp_bodegas.BOD_BODEGA','gp_relacion_punto_venta_bodega_relacionadas.PTO_VTA_BOD_REL_BODEGA')
            ->where('PTO_VTA_BOD_REL_PUNTO_VENTA', $puntodeventa->PTO_VTA_ID)
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA')
            ->select('gp_relacion_punto_venta_bodega_relacionadas.*', 'gp_bodegas.PERMITE_DESPACHO')
            ->get()->toArray();

        foreach ($masbodegas as $bodega) {
            $arr_bodegas[$bodega['PTO_VTA_BOD_REL_BODEGA']] = $bodega;
        }
        $puntodeventa->bodegas = array_values($arr_bodegas);
    }
}
