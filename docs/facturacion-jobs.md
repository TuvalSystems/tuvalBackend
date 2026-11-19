# Facturación mediante jobs

## Comportamiento

`InvoiceService::checkSendInvoice()` registra una solicitud y un `InvoiceOrderJob`; conserva los argumentos `trasladado` y `checkDemanda` de cada consumidor. `sendInvoiceGp()` registra la modalidad manual/directa, cuyo worker recalcula y ejecuta las validaciones de envío existentes sin introducir la preparación adicional del flujo general.

El pedido se guarda inmediatamente. La emisión, las consultas de respuesta y la preparación que antes ejecutaba `checkSendInvoice()` ocurren en el worker. Los endpoints conservan su estructura y códigos HTTP; los mensajes de facturación manual y las métricas de los procesos masivos distinguen solicitudes registradas de facturas emitidas. El resultado se consulta en los listados/detalles actuales; no se añadió una interfaz frontend ni un nuevo evento de actualización.

Se conservan las condiciones de aprobaciones, stock, traslados, artículos L, despacho, facturación a demanda, siguiente mes, reservas y cálculo de cabecera/detalle de GP. Los controles nuevos impiden emitir o cancelar a ciegas cuando una operación externa tiene resultado incierto.

## Flujos cubiertos

| Entrada | Preparación | trasladado | checkDemanda |
| --- | --- | --- | --- |
| Creación de pedido | General | false | true |
| Aprobación comercial y crediticia | General | false | true |
| Pedido desde reserva | General | false | true |
| Refacturación por devolución | General | false | true |
| Facturar existente | General | true | true |
| Recepción de artículos L | General | true | true |
| Recepción total/parcial de guías de traslado | General | true | true |
| Revisión de transferencias | General | true | true |
| Facturación de siguiente mes, manual y cron | General | true | false |
| Facturación programada de reservas | General | true | false |
| Pendientes espera/enviando | General | según estado original | true |
| Pendientes de órdenes de compra | General | true | true |
| Facturación manual de un pedido | Directa: recalcular y enviar | no aplica | no aplica |

## Cola y transacciones

La conexión `facturacion` utiliza el driver `database`, la tabla existente `jobs` y la misma conexión predeterminada de BD que los pedidos. Se eligió esta opción para confirmar juntos el documento, la solicitud y el job; no cambiar esta conexión a Redis ni a otra BD sin reemplazar esa garantía.

La inserción del job ocurre dentro de la transacción (`beforeCommit`), pero el worker de otro proceso solo puede verlo cuando esta se confirma. Un rollback elimina también la solicitud y el job. No se usa `afterCommit` para publicar en una cola externa: eso dejaría un intervalo entre el commit y la publicación.

Los demás workers siguen en Redis. El mutex por pedido sí utiliza explícitamente el store Redis ya existente para coordinar workers, solicitudes HTTP y comandos en distintos procesos/equipos. Todos deben apuntar al mismo Redis y prefijo de cache.

## Protecciones

- `solicitudes_facturacion`: identidad única por pedido, modalidad y argumentos. Deduplica solicitudes pendientes sin mezclar las reglas de diferentes flujos. Una señal nueva durante la ejecución incrementa la versión; el worker antiguo no puede sobrescribir su resultado ni marcarla fallida.
- `envios_facturacion`: registra el intento antes del POST de emisión, fuera de una transacción local. Después de un timeout, error o reinicio, los siguientes intentos solo consultan GP. No borran ni reenvían automáticamente una operación incierta.
- Se reutilizan los endpoints existentes de consulta, preparación/eliminación y envío. Guardar y eliminar no utilizan reintentos HTTP automáticos; la consulta puede reintentarse.
- Solo se interpreta como ausencia el error exacto actualmente emitido por el DAO de API-GP: `Error al ejecutar el SP de detalle de factura: No existe registro para los parámetros proporcionados`, con `success=false` y HTTP 500. Otro error, timeout o contrato diferente detiene la emisión. Este acoplamiento es necesario porque la API actual no publica un código de ausencia independiente.
- `success=true` sin número de factura no confirma la emisión. Tras un envío registrado se mantiene pendiente de conciliación.
- El job recarga el pedido y respeta rechazos, cancelaciones, aprobaciones y facturas ya existentes. Emisión, rechazo comercial/crediticio y cancelación manual comparten el mutex. La cancelación de un pedido en estado enviando tampoco ignora un intento de resultado incierto.
- `refacturaciones_devolucion`: cada devolución crea su pedido nuevo y este genera su factura nueva; la factura original solo se conserva como referencia en `factura_origen`. El pedido de reemplazo y el vínculo único por devolución se confirman juntos. Solo un reintento de la misma devolución reutiliza ese reemplazo. No se agrupan devoluciones diferentes ni se deduce la identidad por cliente o factura original.
- `consumos_reserva_facturacion`: registra el consumo por pedido y fila de stock dentro de la misma transacción que el ajuste. Repetir un job no descuenta nuevamente la reserva; un aumento posterior aplica solo la diferencia, conservando los límites del cálculo anterior.

La clave única de API-GP no se modificó. Estas protecciones no equivalen a haber demostrado emisión única dentro de todos los procedimientos SQL externos: no se ejecutaron facturas reales ni pruebas concurrentes contra GP.

## Activación

1. Aplicar `2026_11_18_120000_create_facturacion_jobs_tables.php` antes de ejecutar el código nuevo. En pruebas, el usuario ejecuta las migraciones manualmente desde su entorno; no se añadió una migración automática al pipeline. En producción, integrar `php artisan migrate --force` en el despliegue antes de habilitar este código.
2. Conciliar operaciones históricas que ya estén en curso en GP. No ejecutar simultáneamente workers/comandos antiguos y nuevos durante el cambio. Revisar refacturaciones históricas pendientes y asociar cada devolución a su reemplazo real cuando exista: la migración no infiere esa relación.
3. Para pedidos de reservas existentes, conciliar también los consumos ya aplicados antes de la migración y registrar su cantidad en `consumos_reserva_facturacion`. El historial anterior no identifica inequívocamente el consumo por pedido; no se hace un backfill estimado. Los pedidos nuevos quedan registrados automáticamente.
4. Actualizar configuración cacheada y reiniciar los workers existentes para que los procesos de recepción/devolución carguen los productores nuevos.
5. Instalar un worker dedicado, con PHP 8.2.35 o superior y las extensiones normales del proyecto:

   ```bash
   php artisan queue:work facturacion --queue=facturacion --sleep=1 --tries=5 --timeout=900 --max-time=3600 --max-jobs=500 --memory=512
   ```

   Usar Supervisor/systemd con reinicio automático y un tiempo de parada superior a 900 segundos, por ejemplo 1260. `retry_after=1200` supera el timeout de 900 segundos. No basta con agregar `facturacion` a la lista de colas de un worker Redis: la conexión es distinta.

6. Validar en pruebas una operación autorizada por cada familia de flujo y verificar el resultado en GP y Laravel antes de habilitar producción. Las pruebas automáticas locales no sustituyen esa validación integrada.

## Diagnóstico y recuperación

### Worker preparado en pruebas — 19 de noviembre de 2026

Se registró `queue-facturacion:queue-facturacion_00` en Supervisor de `srv-web02`, mediante `/etc/supervisor/conf.d/queue-facturacion.conf`. Usuario `www-data`, PHP `/usr/bin/php` (8.2.35), un proceso y el comando de la sección Activación. `stopwaitsecs=1260`, `autorestart=true` y, temporalmente, `autostart=false`.

Estado verificado: `STOPPED`. El servidor aún estaba en `832f2764`, sin `InvoiceOrderJob.php` ni la migración nueva. Los ocho workers Redis conservaron sus PID y continuaron `RUNNING`; no se ejecutaron migraciones ni se modificó producción.

Después de aplicar la migración y desplegar el código/configuración nuevos, cambiar únicamente este programa a `autostart=true` y ejecutar `sudo supervisorctl reread` seguido de `sudo supervisorctl update queue-facturacion`. Con `autostart=true`, el update del grupo lo inicia. Confirmar con `sudo supervisorctl status queue-facturacion:*`. El log del proceso queda en `/var/log/supervisor/queue-facturacion.log`.

Consultar `solicitudes_facturacion` (estado, versión y último error), `envios_facturacion`, `failed_jobs` y el canal de log `invoice`. `terminada` significa que se evaluó esa solicitud: puede haber quedado esperando stock, aprobación o una condición comercial; no significa necesariamente que exista una factura.

Tras agotar los intentos, corregir la causa y usar `php artisan queue:retry <uuid>` para el job fallido. Si hay un envío registrado, el reintento solo concilia. No eliminar el registro de `envios_facturacion` para forzar un reenvío sin confirmar externamente que el intento terminó y que no emitió documento. Una ausencia leída después de un timeout no demuestra que el proceso remoto haya terminado.

Si la recuperación queda sin número de factura, requiere revisión de soporte en GP. No se inventó un código de error terminal ni un reenvío automático apoyado en `NOLOCK`.

No ejecutar el `down()` de esta migración con operaciones activas ni borrar sus tablas de control como mecanismo de recuperación: contienen las identidades que evitan repeticiones.

## Validación local

La suite `Facturacion` usa SQLite en memoria, cache simulado y respuestas GP simuladas; no carga `.env` ni conecta a las bases de negocio. Cubre commit/rollback, serialización y ejecución real desde la cola database, deduplicación, versiones, exclusión mutua, parámetros de cada modalidad, rechazos, respuestas inciertas, conciliación, número vacío, ausencia confirmada, reintentos HTTP, refacturación y consumo de reservas.

Resultado local: 29 pruebas y 78 aserciones correctas. Incluye pedido nuevo/factura nueva por refacturación, devoluciones distintas para una misma factura original y rollback de traslado junto con su marca local. No se ejecutó concurrencia MySQL real ni emisión contra GP.

```powershell
& C:\laragon\bin\php\php-8.2.35-Win32-vs16-x64\php.exe -d xdebug.mode=off -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --testsuite Facturacion
```

Se mantienen avisos deprecados preexistentes sobre parámetros opcionales antes de obligatorios en clases legacy cargadas por las pruebas; no se corrigieron firmas ajenas a esta implementación.
