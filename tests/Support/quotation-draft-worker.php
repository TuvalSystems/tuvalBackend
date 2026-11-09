<?php

// Child process for the local concurrency test. Refuse any non-test database path.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationItemsDraftServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

$database = $argv[1] ?? '';
if (!str_starts_with($database, sys_get_temp_dir().DIRECTORY_SEPARATOR.'quotation-draft-test-') || !is_file($database)) {
    exit(2);
}
putenv('APP_ENV=testing');
putenv('API_GP_URL=http://127.0.0.1:9');
putenv('API_SALES_URL=http://127.0.0.1:9');
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('tests/Support/testing.env');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
    'database.connections.sqlite.database' => $database, 'cache.default' => 'array']);
DB::purge('sqlite');
DB::statement('PRAGMA busy_timeout=5000');
Http::preventStrayRequests(); Mail::fake(); Notification::fake();
\App\Models\Ventas\Cotizaciones\GpCotizacion::creating(static function () {
    usleep(100000); // Force overlap in the independent draft transactions.
});
$result = $app->make(QuotationItemsDraftServiceInterface::class)->create([
    'request_id' => '5af09cd3-c181-461f-8637-c468f02d9638', 'cliente_ruc' => '0990007020001',
    'pv_id' => 7, 'items' => [['codigo' => 'TEST-ITEM', 'cantidad' => 2]],
], GpUsuario::findOrFail(30));
echo json_encode($result, JSON_THROW_ON_ERROR);
