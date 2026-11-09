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
- Por ese bloqueo de acceso quedaron pendientes los IDs reales, la conexión
  y el esquema de QA.

## Comprobaciones pendientes de solo lectura

Ejecutar con un acceso **existente autorizado** al servidor de QA confirmado.
No copiar `.env`, contraseñas, tokens ni hashes de tokens a evidencias o al PR.

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
     "db_database" => config("database.connections.".config("database.default").".database")
   ]);'
   ```

   Contrastar host y nombre de BD con la referencia de QA del responsable del
   entorno. `APP_ENV`, HTTP 200 o un nombre de perfil por sí solos no lo prueban.

3. En la conexión de QA ya confirmada, usar el ID 6 de `adminWeb` como
   `ID_USUARIO` y sustituir `ID_PV` y `RUC_CLIENTE` por los valores de la operación.

   ```sql
   SELECT @@hostname AS db_server, DATABASE() AS db_database;

   SELECT u.id, u.USERID, u.status, u.perfil_id, u.must_change_password,
          p.state AS perfil_state
   FROM gp_usuarios u
   LEFT JOIN perfiles p ON p.id = u.perfil_id
   WHERE u.id = ID_USUARIO;

   SELECT id, PTO_VTA_ID, PTO_VTA_DESCRIPCION, PTO_VTA_ESTADO
   FROM gp_punto_ventas
   WHERE id = ID_PV;

   SELECT id, CLI_RUC, CLI_RAZON_SOCIAL, CLI_ESTADO
   FROM gp_clientes
   WHERE CLI_RUC = 'RUC_CLIENTE';

   SELECT cc.cliente_id, cc.pv_id, cc.vendedor_id, v.VEND_ID
   FROM clientes_configuraciones cc
   JOIN gp_clientes c ON c.id = cc.cliente_id
   JOIN gp_vendedors v ON v.id = cc.vendedor_id
   WHERE c.CLI_RUC = 'RUC_CLIENTE' AND cc.pv_id = ID_PV AND cc.estado = 1;

   SELECT r.user_id, r.USER_PTO_VTA_PUNTO_VENTA,
          pv.id AS pv_id, pv.PTO_VTA_DESCRIPCION, pv.PTO_VTA_ESTADO
   FROM gp_relacion_usuario_punto_ventas r
   JOIN gp_punto_ventas pv
     ON pv.PTO_VTA_ID = r.USER_PTO_VTA_PUNTO_VENTA
   WHERE r.user_id = ID_USUARIO AND pv.id = ID_PV;

   SELECT pv.id AS pv_id, pv.PTO_VTA_DESCRIPCION, b.PTO_VTA_BOD_REL_BODEGA
   FROM gp_punto_ventas pv
   JOIN gp_relacion_punto_venta_bodega_relacionadas b
     ON b.PTO_VTA_BOD_REL_PUNTO_VENTA = pv.PTO_VTA_ID
   WHERE pv.id = ID_PV
   ORDER BY b.orden, b.id
   LIMIT 1;
   ```

   Comprobar que el usuario y su perfil web estén activos, que el usuario esté
   asignado al punto de venta y que ese punto tenga una bodega relacionada.
   Verificar que la configuración activa del cliente para ese punto tenga vendedor.

4. Comprobar el esquema:

   ```sql
   SELECT TABLE_NAME, ENGINE
   FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('gp_cotizacions', 'gp_cotizacion_detalles');

   SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
   FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'gp_cotizacions'
     AND COLUMN_NAME = 'estado';
   ```

   Deben ser transaccionales las tablas implicadas y el enum debe admitir
   `borrador_app`.

5. Comprobar que el entorno de la aplicación sea `local`, `testing`, `qa`
   o `staging`. En Postman, seleccionar `No Auth` y enviar el cuerpo como JSON.

## Creación real preparada, todavía pendiente

Cuando los puntos anteriores estén resueltos, usar la solicitud «Crear
borrador confirmado» de [Postman](postman/cotizacion-borrador-items.postman_collection.json).
Enviar el RUC del cliente, el `pv_id` del punto de venta y los códigos GP
confirmados con sus cantidades. Para California (005), enviar `pv_id: 2`.
Cada artículo debe incluir una cantidad mayor que cero.

No sustituir una selección por el primer candidato ni usar el código de ejemplo
`020-000006-I` como si estuviera aprobado.

Cada solicitud exitosa crea un borrador nuevo y devuelve HTTP 201 con su ID,
número y estado `borrador_app`.
