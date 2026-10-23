<?php

namespace App\Supports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SafeUploadName
{
    public static function image(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };

        if (!$extension) {
            throw ValidationException::withMessages(['imagenes_nuevas' => 'Solo se permiten imágenes JPEG o PNG.']);
        }

        return Str::uuid().'.'.$extension;
    }

    public static function pdf(UploadedFile $file): string
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = $handle ? fread($handle, 5) : false;
        if ($handle) {
            fclose($handle);
        }

        if ($file->getMimeType() !== 'application/pdf' || $header !== '%PDF-') {
            throw ValidationException::withMessages(['pdfs_nuevos' => 'El archivo debe ser un PDF válido.']);
        }

        return Str::uuid().'.pdf';
    }
}
