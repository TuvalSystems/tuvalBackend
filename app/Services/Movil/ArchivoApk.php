<?php

namespace App\Services\Movil;

use Illuminate\Support\Str;

class ArchivoApk
{
    public static function nombre(string $version): string
    {
        $entorno = str_ends_with($version, '-qa') ? 'QA'
            : (str_ends_with($version, '-dev') ? 'DEV' : 'PROD');

        return 'Tuval-'.$entorno.'.apk';
    }

    public static function directorio(string $version, int $build): string
    {
        // La carpeta identifica la versión; el nombre de descarga se mantiene sencillo.
        return 'apks/'.Str::slug($version, '-').'-build'.$build;
    }

    public static function ruta(string $version, int $build): string
    {
        return self::directorio($version, $build).'/'.self::nombre($version);
    }
}
