<?php

namespace App\Services\Movil\Gestion;

use App\Services\Api\Bodegas\ApiGestionArticleService;
use App\Services\Movil\Gestion\Contracts\SteamTrapServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SteamTrapService implements SteamTrapServiceInterface
{
    public function __construct(protected readonly ApiGestionArticleService $api) {}

    public function generateSizingPdf(array $data): string
    {
        $params  = data_get($data, 'spParams', []);
        $info = $this->getSteamTrapItems($params);
        $selectedItem = data_get($info, 'selectedItem', null);
        $originItems = data_get($info, 'originItems', []);
        $viewData = [
            'requestData'  => $data,
            'spParams'     => $params,
            'selectedItem' => $selectedItem,
            'originItems'  => $originItems,
            'rawResponse'  => $info,
        ];
        $html = view('pdf.movil.steam_trap_sizing', $viewData)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $fileName = 'dimensionamiento-trampa-' . now()->format('YmdHis') . '-' . Str::random(6) . '.pdf';
        $path = 'trampas-vapor/' . $fileName;

        Storage::disk('public')->put($path, $pdf->output());
        return asset('storage/' . $path);
    }

    public function getSteamTrapItems(array $params = [])
    {
        $resp =  $this->api->getTrampasVaporItems($params);
        return data_get($resp, 'data', []);
    }
}
