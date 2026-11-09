# Preparación de QA: borrador por artículos

## Estado comprobado el 9 de noviembre de 2026

- El trabajo local se ejecuta desde `SISTEMAS6` en una copia aislada del repositorio.
- La documentación existente identifica `srv-web02`, `/var/www/webtuval`, como
  despliegue de pruebas. Esa identificación histórica no sustituye comprobar
  host, aplicación y conexión actuales.
- `172.30.1.7`, presente en los hosts SSH conocidos del equipo, respondió
  `HEAD /` con HTTP 200 y servidor Apache. No se confirmó que sea el despliegue
  de QA documentado; la resolución DNS local de `srv-web02` no devolvió dirección.
- SSH a `administrador@172.30.1.7`, con comprobación estricta del host y las llaves
  existentes, terminó con `Permission denied (publickey,password)`. No se abrió
  sesión y no se repitió con credenciales inventadas o permisos nuevos.
- Por ese bloqueo de acceso no se verificaron los IDs reales, configuración
  desplegada de piloto, conexión/DDL de QA ni estado real de la migración.
- No se ejecutó migración, configuración compartida, publicación o creación real.

Los IDs de `tests/Support/QuotationDraftSchema.php` son fixtures locales. El PV
7 y cliente 2 de esas pruebas **no son IDs verificados de QA**. El propietario
autorizado para el piloto es el usuario 30, cuya existencia/actividad en QA sigue
pendiente de comprobar. No se tomó ningún candidato de la imagen como aprobado.

## Comprobaciones pendientes de solo lectura

Ejecutar con un acceso **existente autorizado** al servidor de QA confirmado.
No copiar `.env`, contraseñas, tokens ni hashes de tokens a evidencias o al PR.
No cambiar `APP_ENV` para hacer pasar la comprobación del piloto.

1. Confirmar el host y checkout de la aplicación:

   ```bash
   hostname -f
   cd /var/www/webtuval
   git rev-parse HEAD
   git status --short
   php artisan route:list --path=cotizaciones/borrador-items -v
   ```

   La ruta nueva puede no existir hasta incorporar esta rama mediante un cambio
   de código autorizado. No se ha publicado en este trabajo.

2. Leer únicamente la identidad de conexión y configuración no sensible:

   ```bash
   php artisan tinker --execute='dump([
     "app_env" => app()->environment(),
     "app_url" => config("app.url"),
     "db_connection" => config("database.default"),
     "db_host" => config("database.connections.".config("database.default").".host"),
     "db_database" => config("database.connections.".config("database.default").".database"),
     "pilot" => config("quotation_item_drafts.pilot")
   ]);'
   ```

   Contrastar host y nombre de BD con la referencia de QA del responsable del
   entorno. `APP_ENV`, HTTP 200 o un nombre de perfil por sí solos no lo prueban.
   `pilot=null` indica que el checkout/configuración nuevos aún no están cargados.

3. En la conexión de QA ya confirmada, ejecutar estas consultas MySQL de lectura:

   ```sql
   SELECT @@hostname AS db_server, DATABASE() AS db_database;

   SELECT u.id, u.USERID, u.status, u.perfil_id, u.must_change_password,
          p.state AS perfil_state
   FROM gp_usuarios u
   LEFT JOIN perfiles p ON p.id = u.perfil_id
   WHERE u.id = 30;

   SELECT id, PTO_VTA_ID, PTO_VTA_DESCRIPCION, PTO_VTA_ESTADO
   FROM gp_punto_ventas
   WHERE UPPER(TRIM(PTO_VTA_DESCRIPCION)) LIKE '%CALIFORNIA%';

   SELECT id, CLI_RUC, CLI_RAZON_SOCIAL, CLI_ESTADO
   FROM gp_clientes
   WHERE CLI_RUC = '0990007020001';

   SELECT r.user_id, r.USER_PTO_VTA_PUNTO_VENTA,
          pv.id AS pv_id, pv.PTO_VTA_DESCRIPCION, pv.PTO_VTA_ESTADO
   FROM gp_relacion_usuario_punto_ventas r
   JOIN gp_punto_ventas pv
     ON pv.PTO_VTA_ID = r.USER_PTO_VTA_PUNTO_VENTA
   WHERE r.user_id = 30;

   SELECT pv.id AS pv_id, pv.PTO_VTA_DESCRIPCION, b.PTO_VTA_BOD_BODEGA
   FROM gp_punto_ventas pv
   LEFT JOIN gp_relacion_punto_venta_bodegas b
     ON b.PTO_VTA_BOD_PUNTO_VENTA = pv.PTO_VTA_ID
   WHERE UPPER(TRIM(pv.PTO_VTA_DESCRIPCION)) LIKE '%CALIFORNIA%';
   ```

   Usar el ID y la descripción exacta devueltos. Si hay varios PV activos
   compatibles, resolver cuál corresponde antes de crear. Repetir la consulta
   de usuario/perfil/asignación con el ID real del actor si es distinto de 30.
   El actor también debe tener el acceso al PV; no se conceden permisos aquí.

4. Comprobar esquema y migración sin ejecutarla:

   ```sql
   SELECT TABLE_NAME, ENGINE
   FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('gp_usuarios', 'gp_cotizacions',
                        'gp_cotizacion_detalles', 'quotation_item_draft_requests');

   SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
   FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND ((TABLE_NAME = 'gp_cotizacions' AND COLUMN_NAME = 'estado')
          OR TABLE_NAME = 'quotation_item_draft_requests');

   SELECT migration, batch
   FROM migrations
   WHERE migration = '2026_11_09_000001_create_quotation_item_draft_requests_table';
   ```

   Deben ser transaccionales las tablas implicadas y el enum debe admitir
   `borrador_app`. La tabla nueva requiere `user_id` (actor), `owner_id`, UUID,
   hash de contenido, FK de cotización, snapshot JSON e índice único de actor/UUID.
   Si existe una tabla parcial, revisar su DDL antes de cualquier ejecución.

5. Comprobar que un token existente del actor tiene `web` y
   `quotation-items:pilot` (o el alcance equivalente `*`), y que sigue vigente.
   Consultar solamente `tokenable_id`, `abilities` y `expires_at` en
   `personal_access_tokens`; nunca seleccionar `token` ni copiar su contenido.
   Si no existe un acceso con esos permisos, hace falta la autorización del
   administrador correspondiente; no crear token ni otorgar abilities en esta tarea.

## Cambios que requieren autorización posterior

No se han ejecutado. Después de confirmar destino, acceso y esquema:

- Incorporar el código mediante la publicación/despliegue que se autorice.
- Si la tabla/migración no están presentes, autorizar específicamente la
  migración nueva y ejecutarla aislada, revisando primero su SQL:

  ```bash
  php artisan migrate --path=database/migrations/2026_11_09_000001_create_quotation_item_draft_requests_table.php --pretend
  # Solo después de la autorización específica, en el QA ya verificado:
  php artisan migrate --path=database/migrations/2026_11_09_000001_create_quotation_item_draft_requests_table.php
  ```

  No ejecutar un `migrate` general. La FK restringe eliminar físicamente las
  cotizaciones vinculadas, para conservar su registro de idempotencia.
- Configurar el piloto usando los nombres exactos del
  [contrato](cotizacion-borrador-items.md#piloto-restringido-para-la-prueba-autorizada),
  propietario 30, actor real autorizado, URL QA verificada, descripción exacta de
  California y RUC por defecto `0990007020001`. No activar en producción.
  Si hay caché de configuración, su actualización también es un cambio compartido
  pendiente de autorización; leer `.env` nuevo no basta si sigue activo el caché.

## Creación real preparada, todavía pendiente

Cuando los puntos anteriores estén resueltos, usar el request «Piloto QA
restringido» de [Postman](postman/cotizacion-borrador-items.postman_collection.json)
con un UUID nuevo para esta operación y **solo códigos GP confirmados por el
usuario**, junto con sus cantidades. Omitir cantidad aplica 1 y lo informa.
Omitir cliente aplica el RUC del piloto; uno explícito se respeta.

No sustituir una selección por el primer candidato ni usar el código de ejemplo
`020-000006-I` como si estuviera aprobado. Enviar una sola vez; si la respuesta
se pierde, reintentar exactamente con el mismo UUID y contenido.

Resultado esperado: 201 con ID/número y estado `borrador_app`; reintento 200 con
el mismo ID y `reutilizado=true`. La API no consulta precios ni modifica la
validación del proceso de generar cotización real. No envía correos/notificaciones.

Bloqueos separados: falta confirmar selección/destino/IDs reales (datos), falta
un acceso QA existente utilizable y comprobar permisos del actor (acceso), y
siguen sin autorizarse migración/configuración/publicación compartidas (acciones).
