# Actualización a Laravel 12

Se instaló y verificó primero Laravel 11.57.1 con Carbon 2; después se actualizó a Laravel 12.70.2 con Carbon 3. Se conserva la estructura de aplicación existente (Kernel, providers y archivos de rutas).

## Cambios de compatibilidad

- Sanctum 4 y su configuración de middlewares, conservando las excepciones CSRF y cookies de la aplicación.
- Laravel SOAP 4, necesario para soportar Laravel 12.
- Collision 8, IDE Helper 3 y PHPUnit 11.
- Las migraciones de existencias reservadas usan `decimal()->unsigned()` en lugar de `unsignedDecimal()`.
- Los cálculos de días de disponibilidad y duración de facturación conservan diferencias absolutas enteras con Carbon 3.

## Entorno local Windows

La terminal de este equipo apuntaba a PHP 8.1.9. Para usar PHP 8.2.35 en una sesión PowerShell:

```powershell
$env:PATH = 'C:\laragon\bin\php\php-8.2.35-Win32-vs16-x64;' + $env:PATH
php -v
php artisan --version
```

Configurar también PHP 8.2.35 o superior en el servidor web, scheduler y procesos de colas. El cambio de PATH anterior solo afecta a esa terminal.

## Instalación del cambio

Usar el `composer.lock` actualizado para instalar las mismas versiones:

```bash
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan route:list
```

Reiniciar los workers y el servidor PHP al desplegar, según la configuración del entorno. Revisar las migraciones pendientes antes de ejecutarlas; esta actualización no ejecuta migraciones sobre la base de datos existente. La migración de tokens de Sanctum ya está incluida en el proyecto.

