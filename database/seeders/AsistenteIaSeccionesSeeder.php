<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsistenteIaSeccionesSeeder extends Seeder
{
    public function run(): void
    {
        $padre = DB::table('sections')->where('route_name', 'sistemas')->first();
        if (!$padre) {
            $this->command?->warn('No existe Sistemas. Cree la sección antes de ejecutar este seeder.');
            return;
        }
        DB::table('sections')->updateOrInsert(['route_name' => 'sistemas-ia-configuracion'], [
            'parent_id' => $padre->id, 'name' => 'Inteligencia artificial', 'url' => '/sistemas/inteligencia-artificial',
            'icon' => 'fa fa-robot', 'platform' => 'web', 'status' => 1, 'sort_order' => 90, 'updated_at' => now(),
        ]);
        // No automatic grants: assign this section using the existing profile administration.
        $this->command?->info('Sección creada. Asigne sistemas-ia-configuracion a los perfiles autorizados.');
    }
}
