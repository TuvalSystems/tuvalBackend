<?php

namespace App\Exceptions\Movil;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AppVersionException extends HttpException
{
    public static function notFound(): self
    {
        return new self(404, 'Versión no encontrada.');
    }

    public static function noVersionConfigured(): self
    {
        return new self(404, 'No hay versión configurada.');
    }

    public static function invalidApkFile(): self
    {
        return new self(422, 'El archivo debe ser una APK.');
    }

    public static function duplicatedBuildNumber(): self
    {
        return new self(
            409,
            'Ya existe una versión registrada con ese build number para esta plataforma.'
        );
    }

    public static function invalidVersionName(): self
    {
        return new self(422, 'Debe ingresar la versión de la aplicación.');
    }

    public static function invalidBuildNumber(): self
    {
        return new self(422, 'El build number debe ser mayor a cero.');
    }

    public static function invalidMinimumRequiredBuild(): self
    {
        return new self(
            422,
            'El build mínimo requerido no puede ser mayor al build publicado.'
        );
    }
}
