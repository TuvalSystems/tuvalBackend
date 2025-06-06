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
    protected $description = 'Comando para crear usuarios web desde la Tabla GpUsuario';

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
        $gpUsers = GpUsuario::select('USERID', 'USERNAME')->where('USERID', 'jmagallanes')->take(1)->get();

        $existingUsernames = User::pluck('username')->flip();
        $newUsers = $gpUsers->filter(fn($gpUser) => !$existingUsernames->has($gpUser->USERID));


        $insertData = $newUsers->map(function ($gpUser) {
            return [
                'username' => $gpUser->USERID,
                'name' => $gpUser->USERNAME,
                'email' => $gpUser->USERID . '@example.com',
                'password' => Hash::make('12345'),
                'must_change_password' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        if (!empty($insertData)) {
            User::insert($insertData); // 5. Insertar en lote para rendimiento
            Log::info(count($insertData) . ' nuevos usuarios sincronizados desde GpUsuario.');
        }
        return 0;
    }
}
