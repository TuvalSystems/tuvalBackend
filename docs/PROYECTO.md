# Documentación técnica del proyecto Tuval Backend

## 1. Propósito del sistema

Tuval Backend es una API desarrollada en Laravel 8 para soportar procesos comerciales, logísticos, de cobranzas y de operación móvil alrededor de datos sincronizados con GP/servicios externos. El proyecto expone endpoints para aplicaciones web y móviles, genera documentos PDF, procesa trabajos en cola y ejecuta tareas programadas de sincronización, facturación, reservas, devoluciones y mantenimiento.

## 2. Stack tecnológico

| Capa | Tecnología / paquete | Uso principal |
| --- | --- | --- |
| Lenguaje | PHP `^8.1` | Backend y jobs Artisan. |
| Framework | Laravel `^8.75` | API, routing, DI, Eloquent, colas y scheduler. |
| Autenticación | Laravel Sanctum `^2.11` | Tokens personales con abilities `web` y `mobile`. |
| Base de datos | MySQL por defecto; soporte `sqlite`, `pgsql`, `sqlsrv` | Persistencia principal y migraciones. |
| Redis | `predis/predis` | Driver recomendado para colas/cache cuando se configure. |
| PDF | `barryvdh/laravel-dompdf` | Generación de cotizaciones, pedidos, guías, cobranzas, etc. |
| Excel | `maatwebsite/excel` | Importación/exportación de datos. |
| Códigos de barra | `milon/barcode`, `picqer/php-barcode-generator` | Impresión/generación de códigos. |
| HTTP | `guzzlehttp/guzzle`, cliente HTTP Laravel | Integraciones con APIs GP, ventas y facturación. |
| Media | `spatie/laravel-medialibrary` | Archivos asociados a pedidos/cotizaciones/devoluciones. |
| Assets | Laravel Mix 6 | Compilación básica de `resources/js/app.js` y `resources/css/app.css`. |

## 3. Estructura general

```text
app/
  Console/Commands/        Comandos Artisan operativos y generadores internos.
  Constants/               Constantes de dominio.
  Enums/                   Estados, tipos y valores controlados de dominio.
  Events/                  Eventos broadcast/notificación.
  Exceptions/              Handler global y excepciones específicas.
  Exports/, Imports/       Integraciones Excel.
  Http/Controllers/        Controladores por módulo: Admin, ventas, bodegas, móvil, etc.
  Http/Middleware/         Middlewares HTTP y de autorización funcional.
  Http/Requests/           Form requests para validaciones complejas.
  Jobs/                    Trabajos asíncronos para sincronización, reservas, devoluciones, pedidos.
  Mail/, Notifications/    Correos y notificaciones de usuario.
  Models/                  Modelos Eloquent por dominio/tablas GP.
  Observers/               Observadores para efectos de cambios en modelos.
  Providers/               Service providers de DI, rutas, eventos, repositorios y servicios.
  Repositories/            Abstracciones de persistencia por módulo.
  Services/                Lógica de negocio, integraciones, builders y utilidades.
  Supports/, Traits/       Helpers y traits compartidos.
config/                    Configuración Laravel y de servicios externos.
database/migrations/       Esquema histórico y evolutivo de tablas.
database/seeders/          Seeders disponibles.
resources/views/           Plantillas Blade para PDFs y vistas básicas.
routes/                    Definición de rutas web, API, móvil, test y console.
tests/                     Base de pruebas PHPUnit.
```

## 4. Arquitectura y patrones usados

### 4.1 Separación por capas

El proyecto sigue una separación práctica por capas:

1. **Rutas** (`routes/api.php`, `routes/movil.php`, `routes/web.php`, `routes/test.php`) definen endpoints y middlewares.
2. **Controladores** reciben requests, validan datos simples y delegan a servicios.
3. **Servicios** concentran reglas de negocio e integraciones con APIs externas.
4. **Repositorios** encapsulan consultas/persistencia más compleja y se inyectan por interfaces.
5. **Modelos Eloquent** representan tablas locales y relaciones.
6. **Jobs y comandos** ejecutan procesos batch o asíncronos.
7. **Builders** construyen payloads para GP, ventas, bodegas y devoluciones.

### 4.2 Inyección de dependencias

Las interfaces se registran como singletons en service providers diferidos. Los grupos más importantes son:

- `RepositoryServiceProvider`: repositorios de pedidos, cotizaciones, bodegas, devoluciones, cobranzas y usuarios.
- `AprobacionServiceProvider`: servicios de reglas y aprobaciones.
- `BodegasServiceProvider`: despachos, transferencias, guías, recibos y dashboard logístico.
- `VentaServiceProvider`: cotizaciones, pedidos, reservas, consultas de inventario, importaciones y borradores.
- `CollectionServiceProvider`: cobranzas y aplicaciones de pago.
- `DevolucionServiceProvider`: devoluciones, re-facturación, ingresos y anulación.
- `Builder/BodegaBuilderServiceProvider`: builders de venta, bodega y devolución.
- `Movil/AppsServiceProvider`: servicios de app móvil y gestión de trampas de vapor.

### 4.3 Formato común de respuestas

`App\Traits\JsonResponseTrait` estandariza respuestas JSON:

```json
{
  "success": true,
  "message": "Mensaje funcional",
  "data": {}
}
```

Los errores usan:

```json
{
  "success": false,
  "error": "Mensaje de error",
  "errors": []
}
```

`App\Traits\ExecuteApiTransactions` centraliza el manejo de excepciones para acciones API/PDF. Convierte validaciones a HTTP 422, conflictos de negocio a 409, recursos no encontrados a 404 y registra errores generales en el canal indicado.

## 5. Configuración e instalación local

### 5.1 Requisitos

- PHP 8.1 o compatible con las extensiones necesarias para Laravel, PDO y las conexiones configuradas.
- Composer.
- Node.js y npm para assets.
- MySQL/MariaDB o la base que corresponda por `DB_CONNECTION`.
- Redis si se usará `QUEUE_CONNECTION=redis` o cache Redis.

### 5.2 Instalación

```bash
git clone <repo>
cd tuvalBackend
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

> Nota: en entornos con APIs GP/ventas/facturación se deben completar las variables de integración antes de ejecutar flujos reales.

### 5.3 Variables de entorno relevantes

Además de las variables estándar de Laravel, el proyecto usa:

| Variable | Descripción |
| --- | --- |
| `API_GP_URL` | URL base de la API GP. |
| `API_GP_TIMEOUT` | Timeout para llamadas a GP. |
| `API_SALES_URL` | URL base de la API de ventas. |
| `API_SEED_BILLING_URL` | URL base de Seed Billing. |
| `API_SEED_BILLING_TOKEN` | Token de Seed Billing. |
| `API_SEED_BILLING_ID_SUSCRIPTOR` | Identificador de suscriptor en Seed Billing. |
| `API_SEED_BILLING_TIMEOUT` | Timeout de Seed Billing. |
| `LOCAL_API_RETRY_ATTEMPTS` | Intentos de reintento para clientes HTTP locales. |
| `LOCAL_API_RETRY_DELAY` | Espera entre reintentos. |
| `ID_COMPANIA` | Compañía usada en integraciones GP. |
| `WEBSERVICES_URL` | URL base legacy de webservices. |
| `WEBSERVICES_COBRANZAS_URL` | URL de webservices de cobranzas. |
| `WEBSERVICES_INVENTARIO_URL` | URL de webservices de inventario. |
| `WEBSERVICES_TRANSACCIONES_URL` | URL de webservices transaccionales. |
| `WEBSERVICES_USUARIOS_URL` | URL de webservices de usuarios. |
| `COLCHON_RETENCION` | Parámetro funcional usado en cobranzas/retenciones. |
| `ULTIMO_PEDIDO_BUSQUEDA` | Parámetro funcional para búsquedas de pedidos. |
| `MEDIA_DISK` | Disco de Media Library. |
| `QUEUE_CONNECTION` | Driver de colas; por configuración se espera `redis` en entornos productivos. |
| `REDIS_QUEUE` | Cola Redis por defecto. |

## 6. Autenticación y autorización

### 6.1 Login web

- Endpoint base: `POST /api/login`.
- Campos requeridos: `username`, `password`.
- Servicio: `UserService::authenticateUserWeb`.
- Reglas funcionales:
  - Usuario activo.
  - Contraseña válida con hash Laravel.
  - Perfil web asignado y activo.
- Respuesta incluye token Sanctum con ability `web`, datos de usuario, menú, home, puntos de venta, forma/condición de pago, proveedores y bandera `must_change_password`.

### 6.2 Login móvil

- Endpoint base: `POST /api/movil/login`.
- Campos requeridos: `username`, `password`.
- Servicio: `UserService::authenticateUserMobile`.
- Usa token Sanctum con ability `mobile`.
- Requiere usuario activo, `app_access` habilitado y perfil app activo.

### 6.3 Middlewares relevantes

| Middleware | Uso |
| --- | --- |
| `auth:sanctum` | Protege endpoints autenticados. |
| `abilities:web` | Requiere token con ability web. |
| `abilities:mobile` | Requiere token con ability mobile. |
| `must.change.password` | Bloquea funcionalidades si el usuario debe cambiar contraseña. |
| `imprimir` | Usa `CheckTokenAndAddToHeaderMiddleware` para endpoints de impresión. |

## 7. Rutas y módulos funcionales

Todas las rutas definidas en `routes/api.php` se montan con prefijo `/api`; las rutas de `routes/movil.php` con prefijo `/api/movil`; las rutas de `routes/web.php` se montan sin prefijo API bajo middleware `web`; y las rutas de `routes/test.php` se montan con `/test` solo fuera de producción.

### 7.1 Administración, usuarios, perfiles y secciones

| Área | Endpoints principales | Responsabilidad |
| --- | --- | --- |
| Login/logout | `POST /api/login`, `GET /api/logout` | Autenticación web y cierre de sesión. |
| Usuario | `/api/usuario/*` | Listar usuarios, accesos app/cobranzas, estados, reset/cambio de contraseña, bodegas por punto de venta, perfiles web/app. |
| Secciones | `/api/secciones/*` | CRUD de secciones, opciones y eliminación lógica/funcional. |
| Perfiles | `/api/perfiles/*` | CRUD de perfiles y asignación de secciones. |
| Notificaciones | `/api/notifications/*`, `/notifications-web/*` | Listar y marcar notificaciones de usuario. |

### 7.2 Sincronización GP

Endpoints legacy bajo `/api` ejecutan cargas/sincronizaciones manuales de datos GP:

- `/api/llenausuariosgp`
- `/api/llenabodegasgp`
- `/api/llenacondicionpagogp`
- `/api/llenaarticulosgp`
- `/api/llenalistadepreciosgp`
- `/api/llenapuntodeventaycajasgp`
- `/api/llenadescuentoformapagogp`
- `/api/llenarelacionesgp`
- `/api/llenavendedorgp`
- `/api/llenastockarticulobodegasgp`
- `/api/llenastockarticulobodegacompartimientogp`
- `/api/llenaproveedorgp`
- `/api/llenagrupoysubgrupogp`
- `/api/llenapaisesyterritoriosgp`
- `/api/llenaclaseclientegp`

También existen comandos programados para sincronización incremental de clientes, sucursales y coordenadas.

### 7.3 Ventas: consultas, cotizaciones, pedidos y órdenes de compra

Controladores principales:

- `ventas/ConsultasController.php`
- `ventas/CotizacionesController.php`
- `ventas/PedidosController.php`
- `ventas/OrdendecompraController.php`
- `borradores/CotizacionBorradorController.php`

Responsabilidades:

- Búsqueda de clientes, artículos, stock, territorios, provincias y ciudades.
- Creación y recuperación de cotizaciones.
- Conversión/copias entre cotizaciones y pedidos.
- Generación de PDFs con o sin imágenes.
- Facturación de pedidos existentes o pendientes.
- Manejo de órdenes de compra e items L.
- Borradores de cotización.

Servicios de apoyo:

- `QuotationService`, `QuotationCreatorService`, `QuoteDraftService`.
- `OrderService`, `OrderCreatorService`, `OrderJobService`.
- `PurchaseOrderService`.
- `InventoryQueryService`, `ClientQueryService`.
- Builders: `QuotationBuilder`, `OrderBuilder`, `PurchaseOrderBuilder`, `ClientBuilder`.

### 7.4 Aprobaciones

Controlador: `ventas/AprobacionesController.php`.

Responsabilidades:

- Listar cotizaciones y pedidos pendientes.
- Aprobar/rechazar reglas comerciales/crediticias.
- Verificar stock de reservas para aprobación.
- Gestionar aprobaciones relacionadas con devoluciones y reservas.

Servicios:

- `ApprovalService`.
- `ApprovalOrderService`.
- `ApprovalQuotationService`.
- `ApprovalReservationService`.
- `ApprovalDevolutionService`.
- `ApprovalFunctionsService`.

Modelos relevantes:

- `GpReglaNegocio`.
- `GpReglaAprobacion`.
- Estados definidos en `app/Enums`.

### 7.5 Bodegas, despachos, guías y transferencias

Controladores principales:

- `bodegas/DashboardController.php`
- `bodegas/DespachosController.php`
- `bodegas/GuiasderemisionController.php`
- `bodegas/IngresosController.php`
- `bodegas/RecibosdecompraeleController.php`
- `bodegas/ReposicionController.php`
- `bodegas/TransferenciasController.php`
- `bodegas/TransportesController.php`

Responsabilidades:

- Gestión de órdenes de despacho y picking.
- Liberación, rechazo, impresión y consulta de órdenes.
- Generación, impresión, despacho y recepción de guías de remisión.
- Transferencias por venta, reposición y reserva.
- Ingresos y recibos de compra electrónica.
- Dashboard logístico y contadores operativos.

Servicios:

- `DispatchService`, `DashboardService`.
- `DeliveryNoteService`, `DeliveryNoteCommonService`.
- `DeliveryNoteDispatchService`, `DeliveryNoteSalesTransferService`, `DeliveryNoteReplenishmentTransferService`.
- `ReservationDeliveryNoteService`, `TransferService`.
- `PurchaseReceiptService`, `PurchaseReceiptLItemService`.
- `LogisticsOperationsService`.

### 7.6 Reservas

Controlador: `ventas/ReservasController.php`.

Responsabilidades:

- Solicitud, autorización, rechazo, cancelación y consulta de reservas.
- Transferencias por reserva entre bodegas.
- Generación de guías de remisión de reserva.
- Impresión de órdenes de almacenamiento.
- Notificaciones de solicitudes/recepción/vencimiento.

Jobs relevantes:

- `FanoutReservationRequest`.
- `NotifyReceivedReservationsJob`.
- `NotifyReservationRequesterOrderCreatedJob`.
- `SendApprovalReservationNotification`.
- `SendDennyReservationForStockNotification`.
- `SendDenyReservationNotification`.

Comandos relevantes:

- `reservas:notificacion-expiracion`.
- `reservas:release-stock`.

### 7.7 Devoluciones

Controlador: `devoluciones/DevolucionesController.php`.

Responsabilidades:

- Registro, procesamiento, recepción y detalle de devoluciones.
- Generación de notas de crédito y ajustes para devoluciones fallidas.
- Documentos asociados a devoluciones.
- Re-facturación/anulación cuando aplica.

Servicios:

- `DevolucionService`, `DevolucionJobService`.
- `EntryService`.
- `ReInvoiceService`.
- `ReturnCancellationService`.
- Builder `DevolucionParamsBuilder`.

Jobs:

- `EnqueueDevoluciones`.
- `ProcessDevolucion`.
- `NotifyErrorAnulationJob`.

### 7.8 Cobranzas

Controlador: `ventas/CobranzasController.php`.

Responsabilidades:

- Puntos de venta y cajas por usuario.
- Consulta y cierre de caja.
- Impresión PDF de cierres y cobranzas.
- Validación de pagos/facturas.
- Aplicación de cobros y documentos relacionados.

Servicios y repositorios:

- `CollectionService`.
- `CustomerPaymentApplicationService`.
- `CollectionRepository`.
- `CashClosureRepository`.
- `WebInvoiceApplicationRepository`.

### 7.9 Gestión operativa y móvil

- `GestionSucursalesController`: consulta de sucursales y detalles.
- `Movil/Auth/AuthMobileController`: login móvil.
- `Movil/SteamTrapController`: gestión móvil de steam traps/equipos.
- `Movil/AppService`: validación/consulta de versiones de app.

## 8. Comandos Artisan

| Comando | Descripción |
| --- | --- |
| `make:repository {name} {--interface}` | Genera repositorios bajo `app/Repositories`. |
| `make:service {name} {--interface}` | Genera servicios bajo `app/Services`. |
| `sync:clients {--date=}` | Sincroniza clientes desde GP, opcionalmente desde fecha. |
| `sync:client-branches` | Sincroniza sucursales de clientes desde GP. |
| `sync:clients-coordinates` | Sincroniza coordenadas de clientes. |
| `articulol:update` | Actualiza disponibilidad/uso de artículos tipo L. |
| `cotizaciones:notificar-noventa` | Notifica cotizaciones no vendidas o vencidas según regla. |
| `devoluciones:procesar` | Procesa devoluciones bajo criterios configurados. |
| `import:orders-mysql-history {start_date} {end_date}` | Migra histórico de órdenes MySQL a SQL Server/GP. |
| `pedidos:facturar-reservas` | Factura pedidos con origen reserva. |
| `pedidos:facturar-enviando` | Procesa facturas en estado enviando. |
| `pedidos:enviar-facturas-mes-siguiente` | Envía facturas del mes siguiente a facturar. |
| `reservas:notificacion-expiracion` | Notifica reservas próximas a expirar. |
| `reservas:release-stock` | Libera stock de reservas vencidas. |
| `media:clean-old-pdfs {--months=3} {--dry-run}` | Limpia PDFs antiguos de pedidos/cotizaciones. |
| `limpiar:log` | Vacía logs y opcionalmente limpia sesiones, cache y vistas. |
| `user:add-password` | Agrega contraseña por defecto a usuarios GP. |

## 9. Scheduler

El scheduler está en `app/Console/Kernel.php` y usa zona horaria `America/Guayaquil`.

| Frecuencia | Proceso |
| --- | --- |
| Cada minuto | `EnqueueDevoluciones(10)`. |
| Cada minuto | `sync:clients`, `sync:client-branches`, `sync:clients-coordinates`. |
| Diario 08:00 | `cotizaciones:notificar-noventa`. |
| Mensual día 1 a las 02:00, condicionado a día <= 7 | `pedidos:enviar-facturas-mes-siguiente`. |
| Cada 10 minutos entre 06:00 y 22:00 | `pedidos:facturar-reservas`. |
| Cada 2 horas | `pedidos:facturar-enviando`. |
| Diario 03:15 | `articulol:update`. |
| Diario 07:30 | `reservas:notificacion-expiracion` y `reservas:release-stock`. |
| Diario 02:00 | Prune de `CotizacionBorrador` y `SyncLog`. |
| Diario 02:00 | `media:clean-old-pdfs --months=3`. |
| Diario | `queue:prune-failed --hours=72`. |

Para producción debe existir un cron del sistema similar a:

```bash
* * * * * cd /ruta/tuvalBackend && php artisan schedule:run >> /dev/null 2>&1
```

## 10. Colas y jobs

La configuración por defecto de `config/queue.php` usa `QUEUE_CONNECTION=redis` si no se sobreescribe. Recomendaciones operativas:

```bash
php artisan queue:work redis --queue=default --tries=3 --timeout=1200
```

Si se usa Supervisor/systemd, configurar reinicio automático y ejecutar `php artisan queue:restart` después de despliegues.

Jobs destacados:

- Sincronización: `SyncClientJob`, `SyncClientBranchesJob`, `SyncClientCoordinatesJob`.
- Pedidos: `SyncGpOrdersJob`.
- Devoluciones: `EnqueueDevoluciones`, `ProcessDevolucion`, `NotifyErrorAnulationJob`.
- Bodegas: `NotifyPurchaseReceiptItemLJob`.
- Aprobaciones: `NotifyApprovalOrdersJob`.
- Cotizaciones: `NotifyUnsoldQuotesJob`.
- Reservas: jobs de fanout, aprobación, rechazo y recepción.

## 11. Base de datos y modelos

El esquema se compone de tablas estándar Laravel y numerosas tablas de dominio con prefijo `gp_`. Las migraciones cubren, entre otras:

- Usuarios y perfiles: `users`, `gp_usuarios`, `sections`, `perfiles`, `perfil_sections`.
- Catálogos GP: bodegas, condiciones de pago, artículos, precios, clientes, sucursales, puntos de venta, cajas, vendedores, proveedores, territorios, unidades de medida.
- Ventas: `gp_cotizacions`, `gp_cotizacion_detalles`, `gp_pedidos`, `gp_pedido_detalles`, stocks, proveedores, imágenes y borradores.
- Cobranzas: cobros, retenciones, efectivo, depósitos, cheques, tarjetas, aplicaciones, cierres de caja y permisos de cajas.
- Bodegas/logística: órdenes de despacho, guías de remisión, transferencias de venta/reposición/reserva, ingresos y recibos.
- Devoluciones: devoluciones, detalles, documentos, facturas anuladas y errores de jobs.
- Reservas: reservas, tipos, logs, traslados y guías de remisión de reserva.
- Aprobaciones: reglas de negocio y reglas de aprobación.
- Operación: notificaciones, jobs, batches, media, sync logs, auditorías y versiones app.

## 12. Integraciones externas

### 12.1 API GP

Clases relevantes:

- `Services/Api/ApiGpService.php`
- `Services/Api/Core/GpHttpClient.php`
- `Services/Api/Endpoints/ApiGpEndpoints.php`
- `Services/Api/Transacciones/*`

Uso: sincronización de maestros, transacciones de pedidos/facturas, inventario, guías, recibos y devoluciones.

### 12.2 API Sales

Clases relevantes:

- `Services/Api/ApiSalesService.php`
- `Services/Api/Endpoints/ApiSalesEndpoints.php`

Uso: operaciones comerciales relacionadas con ventas.

### 12.3 Seed Billing

Clases relevantes:

- `Services/Api/ApiSeedBillingService.php`
- `Services/Api/Core/SeedBillingHttpClient.php`

Uso: anulación de comprobantes y procesos de facturación electrónica asociados.

### 12.4 Webservices legacy

Controladores y servicios aún usan variables `WEBSERVICES_*` para integraciones SOAP/legacy, especialmente en cobranzas, inventario, transacciones y usuarios.

## 13. PDFs, vistas y archivos

Las plantillas PDF viven en `resources/views`. Documentos disponibles incluyen:

- Cotizaciones y cotizaciones de prospecto.
- Pedidos, pedidos RIDE y pedidos con imágenes.
- Guías de remisión y guías de reserva/transferencia.
- Picking, órdenes de despacho, órdenes de almacenamiento.
- Cierres de caja, cobranzas y estados de cuenta.
- Devoluciones y órdenes de almacenamiento de devolución.
- Órdenes de compra y recibos L.

`JsonResponseTrait::buildFileResponse` devuelve PDFs con headers robustos para descarga y lectura del nombre de archivo desde navegadores (`Content-Disposition`, `X-Filename`).

## 14. Desarrollo

### 14.1 Comandos útiles

```bash
composer install
composer dump-autoload
php artisan migrate
php artisan db:seed
php artisan route:list
php artisan test
npm run dev
npm run watch
npm run prod
```

### 14.2 Crear servicios/repositorios

El proyecto incluye generadores propios:

```bash
php artisan make:service Ventas/NuevoService --interface
php artisan make:repository Ventas/NuevoRepository --interface
```

Después de crear interfaces, registrar los bindings en el service provider correspondiente.

### 14.3 Convenciones observadas

- Controladores del dominio web/API suelen estar en español y por módulo.
- Servicios y repositorios nuevos se enlazan por interfaces.
- Se usan traits para respuestas y manejo de transacciones API.
- Los enums agrupan estados/tipos de dominio para evitar strings mágicos.
- Los procesos largos deben ejecutarse por jobs/comandos, no por requests síncronos.

## 15. Testing y verificación

La base de pruebas PHPUnit está en `tests/`. Para ejecutar:

```bash
php artisan test
# o
vendor/bin/phpunit
```

Verificaciones recomendadas antes de desplegar:

```bash
composer validate
php artisan config:clear
php artisan route:list
php artisan test
npm run prod
```

## 16. Despliegue recomendado

1. Actualizar código en el servidor.
2. Instalar dependencias sin dev si aplica:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run prod
   ```
3. Configurar `.env` productivo.
4. Ejecutar migraciones controladas:
   ```bash
   php artisan migrate --force
   ```
5. Optimizar Laravel:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
6. Reiniciar colas:
   ```bash
   php artisan queue:restart
   ```
7. Verificar cron del scheduler.
8. Validar endpoints de salud/login e integraciones críticas.

## 17. Seguridad y operación

- No versionar `.env` ni credenciales reales.
- Usar HTTPS en producción.
- Rotar tokens/credenciales de APIs externas periódicamente.
- Mantener `APP_DEBUG=false` en producción.
- Configurar logs y retención de `storage/logs`.
- Revisar endpoints legacy de sincronización manual; idealmente deben estar autenticados/autorizados estrictamente.
- Supervisar colas fallidas (`failed_jobs`) y ejecutar `queue:prune-failed` según política.
- Validar permisos de `storage/` y `bootstrap/cache/`.

## 18. Glosario funcional

| Término | Significado en el proyecto |
| --- | --- |
| GP | Sistema/servicio externo fuente de maestros y destino de transacciones. |
| Punto de venta | Unidad comercial asignada a usuarios, bodegas y cajas. |
| Bodega | Almacén físico/lógico para inventario, despachos y transferencias. |
| Items L | Artículos con tratamiento especial de compra/recibo/proveedor. |
| Reserva | Separación de stock para cotizaciones/pedidos con flujos de aprobación y transferencia. |
| Guía de remisión | Documento logístico para despacho/transferencia/recepción. |
| Orden de despacho | Documento interno para picking y despacho de pedidos. |
| Cierre de caja | Proceso de conciliación de cobranzas por usuario/caja/punto de venta. |
| Devolución | Proceso de retorno de mercadería con posible nota de crédito, ajuste o re-facturación. |
