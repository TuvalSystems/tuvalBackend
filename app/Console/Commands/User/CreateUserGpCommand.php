<?php

namespace App\Console\Commands\User;

use App\Models\GpUsuario;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateUserGpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-user-gp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para agregegar contraseña por defecto a todos los usuarios';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $gpUsers = GpUsuario::where('USERID', 'jmagallanes')->take(1)->get();
        // $gpUsers = GpUsuario::where ('process_job', 0)->get();
        $insertData = [];
        foreach ($gpUsers as $gpUser) {
            $insertData[] = [
                'password' => Hash::make($gpUser->USERID),
                 'must_change_password' => 1,
                 'process_job' => 1
            ];

        }
        if (!empty($insertData)) {
           $gpUser->update($insertData[0]);
            Log::info(count($insertData) . 'Se realizo la actualizacion de credenciales del usuario.');
        }
        return 0;
    }
}
