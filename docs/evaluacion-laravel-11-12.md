# Evaluación de migración Laravel 10 → 11 → 12

Fecha: 2026-10-26. Alcance: verificación previa, sin actualizar composer.json,
composer.lock, vendor ni datos del proyecto. SQL Server/Dynamics GP se consumen
mediante microservicios; no se requieren drivers SQL Server en PHP.

## Resultado comprobado

- Base actual: Laravel 10.50.3.
- PHP requerido por el proyecto: ^8.2.35. La consola por defecto usa 8.1.9.
  La evaluación utilizó explícitamente PHP 8.2.35 de Laragon.
- Laravel 11: Composer dry-run resolvió 11.57.0 al actualizar Sanctum a ^4.0,
  Collision a ^8.1 e IDE Helper a ^3.0. No se instaló ni arrancó Laravel 11.
- Laravel 12: la primera resolución falló por codedredd/laravel-soap 3.*.
  Cambiarlo a ^4.0 resolvió el conflicto sin ignorar requisitos de plataforma.
- Se instaló el conjunto de Laravel 12 en una carpeta temporal independiente,
  con scripts y plugins de Composer deshabilitados. No se ejecutó Artisan,
  scheduler, workers, migraciones ni llamadas a los servicios empresariales.
- Se cargaron 545 declaraciones de app (clases/interfaces/traits/enums)
  contra Laravel 12.70.1 sin errores fatales. Esto no prueba el arranque completo.
- SecurityValidator: correo válido aceptado; separadores de cabeceras rechazados.
- Composer no reportó avisos de vulnerabilidades para el conjunto candidato 12.
  Reportó paquetes abandonados azjezz/psl y php-http/message-factory.

## Dependencias candidatas

| Paquete | Paso a 11 | Paso a 12 | Resolución instalada de 12 |
| --- | --- | --- | --- |
| laravel/framework | ^11.0 | ^12.0 | 12.70.1 |
| laravel/sanctum | ^4.0 | ^4.0 | 4.3.3 |
| nunomaduro/collision | ^8.1 | ^8.1 | 8.9.5 |
| barryvdh/laravel-ide-helper | ^3.0 | ^3.0 | 3.7.0 |
| codedredd/laravel-soap | 3.* | ^4.0 | 4.0.0 |
| phpunit/phpunit | ^10.0 | ^11.0 | 11.5.57 |
| nesbot/carbon | transitiva | 3.x obligatorio | 3.15.1 |

Estas resoluciones proceden de manifiestos de evaluación sin el lock original;
el cambio real debe regenerar y revisar el lock de cada etapa. Las dependencias
no listadas conservan las restricciones del composer.json actual.

## Ajustes y riesgos del código

1. **Carbon 3:** diffInDays ahora devuelve float y usa diferencias con signo por
   defecto. Se comprobó que 2026-10-27 → 2026-10-26 devuelve -1.0.
   Revisar app/Services/Inventario/Reposiciones/ItemsLService.php:96 y
   app/Console/Commands/Articulos/ActualizacionUsoItemLCommand.php:93.
   Definir explícitamente el tratamiento de fechas futuras y convertir a entero
   donde el contrato/API o columna lo requiera. No añadir abs() sin decidir la regla.
   InvoiceCollectionService:230 ya solicita diferencia absoluta y convierte a int.
   SendInvoiceNextMonth:52 usa diffInSeconds únicamente para registrar duración.
2. **Sanctum:** config/sanctum.php conserva verify_csrf_token; adaptar las claves
   a authenticate_session, encrypt_cookies y validate_csrf_token de Sanctum 4.
   El middleware stateful está comentado, pero deben probarse tokens web/móvil,
   expiración, logout y permisos. Ya existen migraciones de tokens y expires_at;
   no publicar otra creación de tabla sin revisar duplicados.
3. **Cache y Redis:** Laravel 11 cambia el separador del prefijo de caché.
   Planificar compatibilidad de claves, bloqueos y jobs únicos; detener y reiniciar
   workers de forma coordinada. No ejecutar limpieza global sobre Redis compartido.
4. **Migraciones:** revisar up/down de
   2025_08_14_104128_alter_ocgd_detalle_proveedor_fk_set_null.php (change).
   No se demostró pérdida de atributos; comparar esquema antes/después en copia MySQL.
   Las llamadas double encontradas no utilizan precisión/escala antiguos.
5. **SOAP:** se utiliza Soap::baseWsdl en WebserviceController y MswService.
   Probar métodos, serialización y excepciones con respuestas simuladas y después
   con el entorno de pruebas del servicio al actualizar a SOAP 4.
6. **PHP:** se observaron deprecaciones de parámetros opcionales antes de requeridos
   en CobranzasController, GpHttpClient, DevolucionService y su interfaz,
   DispatchService y su interfaz, UserServiceInterface, OrderService y su interfaz.
   Corregir firma y llamadas conjuntamente, conservando el contrato funcional.
7. **Laravel 12:** revisar merge de requests en pedidos/cotizaciones mediante
   pruebas con arrays anidados. No se demostró una regresión en estas rutas.
   Filesystems define explícitamente storage/app, por lo que no depende del nuevo
   directorio local por defecto. No se encontraron usos de HasUuids, Concurrency,
   constructores manuales de Blueprint/Grammar ni APIs Doctrine en el código buscado.
8. Conservar estructura de Laravel 10: kernels, providers y bootstrap/app.php.

## Validación pendiente antes de desplegar

- Alinear PHP web, Composer, scheduler y workers en 8.2.35 o una versión compatible.
- Crear entorno aislado con copia de la base propia de Laravel, Redis separado y
  endpoints de pruebas; desactivar envíos reales, scheduler y workers inicialmente.
- Aplicar etapa 11, revisar lock, ejecutar descubrimiento de paquetes y verificar
  arranque/rutas. Después repetir con 12 y PHPUnit 11.
- Añadir pruebas de regresión de fechas futuras/pasadas, autenticación, validación,
  respuestas HTTP y errores SOAP. No se encontró suite tests/ ni phpunit.xml.
- Probar pedidos/cotizaciones, despachos/OC sin duplicación, PDF, Excel, archivos,
  notificaciones, trabajos únicos, reintentos y tareas programadas.
- Ejecutar migraciones únicamente contra una copia; comparar índices, FK y columnas.
- Preparar respaldo, reversión y reinicio coordinado de procesos para el despliegue.

## Referencias

- https://laravel.com/docs/11.x/upgrade
- https://laravel.com/docs/12.x/upgrade
- https://github.com/CodeDredd/laravel-soap/releases/tag/v4.0.0

Conclusión: viable a nivel de dependencias; no aprobado todavía para producción.
