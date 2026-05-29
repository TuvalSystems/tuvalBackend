# Tuval Backend

Backend Laravel para procesos comerciales, logísticos, cobranzas, reservas, devoluciones, sincronización con GP y servicios móviles.

## Documentación

La documentación técnica completa del proyecto está disponible en:

- [docs/PROYECTO.md](docs/PROYECTO.md)

Incluye:

- Arquitectura general y estructura de carpetas.
- Instalación y configuración local.
- Variables de entorno e integraciones externas.
- Autenticación web/móvil con Sanctum.
- Módulos funcionales: ventas, bodegas, reservas, devoluciones, cobranzas, aprobaciones, administración y móvil.
- Comandos Artisan, scheduler, colas y jobs.
- Base de datos, PDFs, testing, despliegue y recomendaciones operativas.

## Inicio rápido

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

> Completa las variables de integración (`API_GP_URL`, `API_SALES_URL`, `API_SEED_BILLING_*`, `WEBSERVICES_*`, etc.) antes de ejecutar flujos conectados a sistemas externos.

## Comandos frecuentes

```bash
php artisan route:list
php artisan test
php artisan queue:work redis --queue=default --tries=3 --timeout=1200
php artisan schedule:run
npm run prod
```
