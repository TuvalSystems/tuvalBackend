# Cotización borrador desde artículos confirmados

La [preparación de QA](qa-cotizacion-borrador-items.md) registra qué se comprobó,
las consultas pendientes de solo lectura y las acciones todavía no autorizadas.

`POST /api/cotizaciones/borrador-items` crea una cabecera en `gp_cotizacions`
con estado `borrador_app` y sus registros en `gp_cotizacion_detalles`.
Reutiliza los métodos de borrador de `QuotationBuilder`.
La numeración `COT...` la asigna el observer existente.

Requiere token Sanctum con ability `web`, cuenta y perfil web activos, contraseña
sin cambio pendiente y asignación del usuario al punto de venta. En modo normal,
el propietario es el usuario autenticado. `user_id` solo se admite en el piloto
de pruebas restringido descrito abajo; no habilita suplantación en producción.
La ruta móvil de trampas y `/api/borradores/sync` conservan sus contratos.

## Entrada

```json
{
  "request_id": "5af09cd3-c181-461f-8637-c468f02d9638",
  "cliente_ruc": "RUC_DEL_CLIENTE_SELECCIONADO",
  "pv_id": 123,
  "items": [
    {"codigo": "CODIGO_GP_CONFIRMADO", "cantidad": 2, "unidad": "UN"},
    {"codigo": "OTRO_CODIGO_GP_CONFIRMADO"}
  ]
}
```

Los códigos e IDs anteriores son marcadores, no una selección de artículos ni
IDs reales de California. Deben sustituirse antes de usar el ejemplo.

| Campo | Regla |
| --- | --- |
| `request_id` | UUID obligatorio; conservarlo en todos los reintentos de la misma operación |
| `cliente_ruc` | Obligatorio en modo normal; en piloto puede omitirse y se aplica el default configurado. Un valor explícito siempre se respeta, incluso si es inválido: no se sustituye |
| `pv_id` | Obligatorio en modo normal; en piloto omitido se resuelve por la descripción configurada. Debe ser un ID real activo permitido al actor y al propietario |
| `modo_prueba` | Opcional, false por defecto; true requiere todas las restricciones del piloto |
| `user_id` | Solo en piloto: debe coincidir con el único propietario configurado; si se omite, se usa ese propietario |
| `items` | Lista de 1 a 20 renglones; códigos duplicados se rechazan |
| `codigo` | `ART_CODIGO` GP exacto, activo y único; no es código Zafiro ni código 3L |
| `cantidad` | Omitida: 1, con `cantidad_asumida=true` y advertencia; explícita: >0, máximo 1000000, hasta 2 decimales, sin notación exponencial; null se rechaza |
| `unidad` | Opcional: debe coincidir con `UMEDIDA_BASE`; omitida: se usa esa unidad base; no convierte cajas, metros, etc. |

Se comprueba además el menor número de decimales permitido por artículo, unidad
y columna de detalle (`decimal(11,2)`). Se rechaza una cantidad incompatible antes
de guardarla; no se redondea. El buscador permite hasta 3 decimales, pero este
endpoint de persistencia solo admite la precisión realmente soportada aquí.
Se rechazan campos desconocidos, incluidos precios, estado, candidatos
del buscador y descuentos enviados por el llamante.

## Resolución y selección

La consulta de api-gp `POST /api/reportes/resolucion-articulos/consultar` permanece
separada y no escribe. Para cada renglón se debe usar `articulo.codigoGP` solamente
cuando `estado=identificado`, `busquedaCompleta=true`, el artículo y el código
sean no vacíos y no exista una observación de búsqueda incompleta o timeout.
Un HTTP 200 del buscador no basta. Los renglones ambiguos requieren selección
explícita; nunca usar `candidatos[0]` automáticamente. No elegir por cuenta propia
material, clase 150/300, refuerzo u otra variante.

Este endpoint recibe la selección final, no texto libre ni respuestas del buscador.
No puede comprobar cómo obtuvo el llamante un código exacto; esa confirmación
corresponde al flujo que presenta y selecciona los resultados. No elimina ni
descarta silenciosamente ningún renglón enviado.

## Datos mínimos del borrador

El servidor conserva cliente, usuario, punto de venta, bodega y metadatos locales
de los artículos. Valida los códigos seleccionados, cantidades y unidades antes
de guardar; el llamante no envía un formulario comercial completo.

Completa los datos locales disponibles de vendedor, sucursal `PRINCIPAL`, forma
de pago y tipo `Retiro en bodega`. Si esos datos no están configurados, los IDs
opcionales quedan vacíos para completarlos en el flujo existente. La bodega del
PV debe ser inequívoca porque es un dato obligatorio de la cabecera persistida.

La creación del borrador no consulta listas de precios ni exige nivel de precios
o precio disponible. Los importes quedan en cero como valores iniciales del
builder de `borrador_app`, sin presentarse en la respuesta como una cotización
calculada. Al seleccionar el borrador para generar la cotización, se usa la
validación de precios del proceso existente, conforme a la instrucción del
usuario. Esta API no modifica ese proceso ni añade reglas de conversión,
aprobación o impresión.

No se crea una aprobación ni se reserva stock, factura, envía correo,
notificación, cotización al cliente o sincronización transaccional hacia GP.

## Atomicidad e idempotencia

Cabecera, detalles y respuesta de idempotencia se guardan en una sola transacción.
Las filas del actor y del propietario se bloquean por ID ascendente antes de
consultar `quotation_item_draft_requests`: en MySQL/InnoDB serializa escrituras
de este endpoint que comparten esos usuarios. En la tabla, `user_id` identifica
al actor autenticado y `owner_id` al propietario de la cotización.
El índice único `(user_id, request_id)` proporciona una segunda protección.
Se reintentan hasta tres veces conflictos de concurrencia reconocidos por Laravel.
La transacción solo usa los maestros locales y las filas del borrador; no hace
consultas HTTP a GP ni llamadas de cálculo comercial.

- Primera creación: HTTP 201, `reutilizado=false`.
- Mismo actor, clave y contenido: HTTP 200, mismo ID/número y snapshot de la
  creación, `reutilizado=true`, sin crear filas adicionales.
- Misma clave con distinto propietario, modo de pruebas, cliente, PV, orden, códigos, cantidades, unidades o
  marca de cantidad asumida: HTTP 409. Usar un nuevo UUID para otra operación.
- `2`, `2.0` y `"2.00"` representan la misma cantidad explícita. Una cantidad
  omitida y una cantidad 1 explícita conservan diferente marca de asumida.
- Una operación fallida revierte todo y no consume la clave.
- Los permisos se verifican de nuevo también en un reintento. El snapshot no
  pretende representar cambios hechos después a la cotización en el editor.
- Conservar los registros de idempotencia: eliminarlos permitiría recreaciones
  con claves antiguas. Su FK impide eliminar físicamente una cotización vinculada.

Errores: 401 sin autenticación; 403 sin ability/cuenta/perfil/PV permitidos;
422 entrada o configuración incompleta; 409 reutilización incompatible o
conflicto de concurrencia; 500 fallo interno sin detalle sensible en producción.
La respuesta no serializa modelos completos ni expone costos o márgenes.

Ejemplo abreviado de respuesta:

```json
{
  "success": true,
  "message": "Cotización borrador disponible para revisión.",
  "data": {
    "id": 42,
    "numero": "COT00000042",
    "estado": "borrador_app",
    "request_id": "5af09cd3-c181-461f-8637-c468f02d9638",
    "reutilizado": false,
    "usuario": {"id": 30, "codigo": "USUARIO_AUTENTICADO"},
    "actor": {"id": 30, "codigo": "USUARIO_AUTENTICADO"},
    "modo_prueba": false,
    "punto_venta": {"id": 123, "codigo": "PV_REAL", "descripcion": "PV seleccionado"},
    "cliente": {"id": 456, "ruc": "RUC_SELECCIONADO"},
    "incluidos": [{"indice": 0, "codigo": "CODIGO_GP_CONFIRMADO", "art_id": 789, "cantidad": 1, "cantidad_asumida": true, "unidad": "UN"}],
    "advertencias": [{"indice": 0, "codigo": "cantidad_asumida", "mensaje": "Se asumió cantidad 1."}]
  }
}
```

## curl y Postman

Verificar primero el destino de QA y la identidad del token existente. Guardar
el cuerpo con la selección confirmada en `borrador-items.json`. No escribir
tokens en archivos de colección, historial de shell o ejemplos versionados.

```bash
curl --request POST "$TUVAL_QA_URL/api/cotizaciones/borrador-items" \
  --header "Authorization: Bearer $TUVAL_TOKEN" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-binary @borrador-items.json
```

Importar [la colección Postman](postman/cotizacion-borrador-items.postman_collection.json).
Configurar `base_url`, `token`, `request_id`, `cliente_ruc`, `pv_id`, `codigo_gp`
y `cantidad` en un entorno local. Conservar `request_id` al pulsar Send de nuevo;
generar otro UUID solo para una operación distinta. La colección no hace login,
genera credenciales, selecciona candidatos ni ejecuta efectos posteriores.

## Preparación y pruebas

El repositorio no traía `tests/` ni `phpunit.xml`; se añade una base mínima.

```bash
composer install
composer validate --no-check-publish
php artisan test
# alternativa equivalente
vendor/bin/phpunit
```

Los procesos de prueba cargan `tests/Support/testing.env`, sin leer el `.env`
real de la aplicación. El runner fuerza `APP_ENV=testing`, SQLite en memoria, URLs externas locales
inutilizables, correo en memoria y bloqueo de llamadas HTTP no simuladas.
Usa fixtures sintéticos y un subconjunto del esquema; no ejecuta las migraciones
históricas sobre una BD existente. La prueba de concurrencia abre dos procesos
con una SQLite temporal, verifica un solo borrador y elimina sus archivos.
Esto no reemplaza una validación de bloqueo/transacciones en MySQL/InnoDB de QA.

Para habilitar la ruta en un entorno compartido se necesita aplicar primero la
migración `2026_11_09_000001_create_quotation_item_draft_requests_table.php`.
**No se ejecutó sobre ninguna base compartida**; su ejecución requiere autorización
específica y revisión de destino/migraciones pendientes. No lanzar un `migrate`
general sin revisar las demás migraciones de ese entorno.

Comprobar también que `gp_cotizacions.estado` admita `borrador_app` y que las
tablas involucradas usen transacciones (MySQL/InnoDB). La migración inicial del
repositorio no incluye ese valor del enum, aunque el servicio móvil actual ya lo
utiliza; no se verificó el DDL real de QA. SQLite no valida ese enum, por lo que
las pruebas locales no certifican esa condición del servidor. No se altera aquí
el enum ni se adivinan los valores que ya tenga el entorno compartido.

## Piloto restringido para la prueba autorizada

Está deshabilitado por defecto y no tiene IDs, cliente ni punto de venta
predeterminados en producción. Para habilitarlo se requiere verificar primero
el destino real de QA y configurar en el servidor (no desde el cuerpo):

```dotenv
QUOTATION_ITEMS_PILOT_ENABLED=true
QUOTATION_ITEMS_PILOT_VERIFIED_APP_URL=https://DESTINO_QA_VERIFICADO
QUOTATION_ITEMS_PILOT_OWNER_USER_ID=30
QUOTATION_ITEMS_PILOT_ACTOR_USER_IDS=ID_ACTOR_AUTORIZADO
QUOTATION_ITEMS_PILOT_DEFAULT_PV_DESCRIPTION="California"
QUOTATION_ITEMS_PILOT_DEFAULT_CLIENT_RUC=0990007020001
```

Los marcadores deben sustituirse tras comprobar el entorno. La URL debe coincidir
con `APP_URL` y `APP_ENV` debe ser `local`, `testing`, `qa` o `staging`.
`production` y cualquier otro entorno rechazan el piloto aun con los flags
habilitados. La comparación de URL es una segunda condición explícita; no
certifica por sí sola que una BD sea QA. No activar el piloto basándose solo en
un perfil: verificar host y conexiones antes de configurarlo. No se activó aquí.

El actor debe autenticarse con Sanctum, tener cuenta/perfil web activos,
contraseña sin cambio pendiente, ability `web` y `quotation-items:pilot`, y estar
en la lista de actores configurada. Se reutiliza un token existente autorizado;
este trabajo no crea credenciales ni otorga abilities automáticamente. Si el
token actual solo tiene `web`, no puede ejecutar el piloto hasta que un
administrador autorice el permiso adicional mediante el mecanismo habitual.

El propietario debe ser el único ID configurado (30 para esta prueba), existir,
estar activo y tener perfil web activo. **Actor y propietario deben estar
asignados al PV.** Se rechazan otros `user_id` antes de guardar. Omitir `user_id`
en piloto conserva al propietario configurado, no al actor. La respuesta separa
`actor` y `usuario` (propietario), y ambos quedan auditados en la idempotencia.

```json
{
  "request_id": "5af09cd3-c181-461f-8637-c468f02d9638",
  "modo_prueba": true,
  "user_id": 30,
  "items": [{"codigo": "CODIGO_GP_CONFIRMADO"}]
}
```

Solo en este modo, un cliente omitido usa el RUC configurado; un cliente
explícito se respeta y nunca se reemplaza por el default. Omitir el PV busca la
descripción configurada en los maestros actuales y exige exactamente un registro
activo: no adivina IDs. Configurar la descripción exacta de California verificada
en QA. La cantidad omitida sigue siendo 1 y queda marcada como asumida.

La colección Postman incluye este request de piloto. No usar códigos de ejemplo
como selección aprobada del cliente. Antes de crear un borrador real se requiere
la selección inequívoca, destino QA verificado, permisos y migración autorizada.
