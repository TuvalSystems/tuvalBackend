<?php

use App\Http\Middleware\EnsureTuvalMcpPilot;
use App\Mcp\Servers\TuvalQuotationServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Keep the environment gate at request time, including when routes are cached.
Route::middleware([
    EnsureTuvalMcpPilot::class,
    'api',
    'auth:sanctum',
    'active.account',
    'abilities:web',
    'must.change.password',
])->group(function () {
    Mcp::web('/api/mcp/tuval', TuvalQuotationServer::class)->name('mcp.tuval');
});
