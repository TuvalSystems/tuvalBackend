<?php

namespace App\Console\Commands\Admin;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LogoutAllUsers extends Command
{
    protected $signature = 'logout-all-users';

    protected $description = 'Cierra todas las sesiones activas invalidando los tokens de Sanctum';

    public function handle()
    {
        $tokensEliminados = DB::table('personal_access_tokens')->delete();

        $this->info("Se invalidaron {$tokensEliminados} tokens de Sanctum.");

        return self::SUCCESS;
    }
}
