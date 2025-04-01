<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Perfiles;
use App\Models\GpUsuario;
use App\Models\PerfilSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionsController extends Controller
{
    /*
     * Envía la lista de secciones completa
     */
    public function listadeseccionesmenu($perfil_id): array
    {

        $sections = DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->select('sections.*')
            ->where('perfil_sections.perfil_id', '=', $perfil_id)
            ->orderBy('sections.order')
            ->get()->toArray();

        $sections = json_decode(json_encode($sections), true);

        $sections_arr = array();
        //sections list array
        if ($sections) {
            foreach ($sections as $sec) {
                if($sec['parent_id'] == ''){
                    $sec['subActivePaths'] = $sec['url'];
                }
                unset($sec['url']);
                unset($sec['order']);
                unset($sec['created_at']);
                unset($sec['updated_at']);
                $sections_arr[] = $sec;
            }
        }

        $tree = $this->buildSectionTree($sections_arr);

        $arbol = $this->setdatamenu($tree);

        $home = DB::table('sections')
            ->join('perfil_sections', 'sections.id', '=', 'perfil_sections.section_id')
            ->select('sections.*')
            ->where('perfil_sections.perfil_id', '=', $perfil_id)
            ->where('perfil_sections.home', '=', 'yes')
            ->first();
        return array('arbol'=>$arbol, 'home' => $home);
    }

    public function listadesecciones(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $sections = Section::orderBy('order')->get()->toArray();

        $tree = $this->buildSectionTree($sections);

        return response([
            'success' => true,
            'sections' => $tree
        ]);
    }
    /*
     * Ordena la lista de secciones
     */
    public static function buildSectionTree(array $elements, $parentId = 0): array
    {
        $branch = array();
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = self::buildSectionTree($elements, $element['id']);
                if ($children) {
                    $element['sub'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
    public static function setdatamenu(array $elements): array
    {
        foreach ($elements as $key => $element) {
            unset($elements[$key]['id']);
            unset($elements[$key]['parent_id']);

            if (isset($element['sub']) && count($element['sub']) > 0) {
                unset($elements[$key]['to']);
                $elements[$key]['sub'] = self::setdatamenu($element['sub']);
            }else{
                unset($elements[$key]['icon']);
            }
        }
        return $elements;
    }

    public function updateseccion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $credentials = $request->validate([
            'icon' => '',
            'id' => '',
            'name' => 'required|string',
            'order' => '',
            'parent_id' => '',
            'to' => '',
            'url' => '',
        ]);
        if(isset($credentials['id']) && $credentials['id'] != ''){
            $section = Section::where('id', $credentials['id'])->first();
        }else{
            $section = new Section;
        }
        if(!isset($credentials['icon'])){
            $credentials['icon'] = '';
        }
        $section->icon = $credentials['icon'];
        $section->name = $credentials['name'];
        $section->order = $credentials['order'];
        $section->parent_id = $credentials['parent_id'];
        $section->to = $credentials['to'];
        $section->url = $credentials['url'];
        if($section->save()){
            return response([
                'success' => true,
                'message' => 'Datos correctamente guardados'
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }

    }

    public function perfiles(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $perfiles = Perfiles::all();

        foreach($perfiles as $perfil){
            $perfilsections = PerfilSection::where('perfil_id', $perfil->id)->get()->toArray();
            $array_perfil_sections = array();
            foreach($perfilsections as $perfilsection){
               $array_perfil_sections[$perfilsection['section_id']] = $perfilsection;
            }
            $perfil['sections'] = $array_perfil_sections;
        }

        $sections = Section::orderBy('order')->get()->toArray();
        $tree = $this->buildSectionTree($sections);

        $usuarios = GpUsuario::orderBy('USERID')
            ->get();

        return response([
            'success' => true,
            'perfiles' => $perfiles,
            'sections' => $tree,
            'usuarios' => $usuarios,
        ]);
    }

    /*
     * Eliminar Perfil
     */

    public function eliminarperfil($id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        Perfiles::where('id', $id)->delete();
        return response([
            'success' => true,
            'message' => 'EL perfil ha sido eliminado',
        ]);
    }

    public function updateperfil(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'id' => '',
            'name' => 'required|string',
            'state' => 'required',
        ]);
        if(isset($data['id']) && $data['id'] != ''){
            $perfil = Perfiles::where('id', $data['id'])->first();
        }else{
            $perfil = new Perfiles;
        }
        $perfil->name = $data['name'];
        $perfil->state = $data['state'];

        if($perfil->save()){
            return response([
                'success' => true,
                'message' => 'Perfil correctamente guardado'
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }

    }

    public function updateperfilsection(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'perfil_id' => 'required',
            'section_id' => 'required',
        ]);
        $perfilsection = PerfilSection::where([
            ['section_id', '=', $data['section_id']],
            ['perfil_id', '=', $data['perfil_id']]
        ])->first();
        if($perfilsection){
            PerfilSection::where('id', $perfilsection->id)->delete();
            return response([
                'success' => true,
                'message' => 'Seccion de perfil correctamente guardado'
            ]);
        }else{
            PerfilSection::where('perfil_id', $data['perfil_id'])->update(['home' => 'no']);
            $perfilsection = new PerfilSection;
            $perfilsection->section_id = $data['section_id'];
            $perfilsection->perfil_id = $data['perfil_id'];
            $perfilsection->home = 'yes';
            if($perfilsection->save()){
                return response([
                    'success' => true,
                    'message' => 'Seccion de perfil correctamente guardado'
                ]);
            }else{
                return response([
                    'error' => 'Hubo un error al guardar'
                ], 401);
            }
        }
    }

    public function updateusuarioperfil(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'usuario_id' => 'required',
            'perfil_id' => 'required',
        ]);
        if(GpUsuario::where('USERID', $data['usuario_id'])->update(['perfil_id' => $data['perfil_id']])){
            return response([
                'success' => true,
                'message' => 'Perfil de usuario correctamente guardado'
            ]);
        }else{
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }
    }
}
