<?php

namespace App\Services\Movil;

class VersionApkQa
{
    public function siguiente(iterable $versiones): string
    {
        $mayor = '1.0.0';
        foreach ($versiones as $version) {
            // Las versiones sin sufijo QA (incluida producción) no intervienen.
            if (preg_match('/^(\d{1,9})\.(\d{1,9})\.(\d{1,9})-qa$/', $version, $partes)) {
                $candidata = ((int) $partes[1]).'.'.((int) $partes[2]).'.'.((int) $partes[3]);
                if (version_compare($candidata, $mayor, '>')) {
                    $mayor = $candidata;
                }
            }
        }
        [$major, $minor, $patch] = array_map('intval', explode('.', $mayor));

        return $major.'.'.$minor.'.'.($patch + 1);
    }
}
