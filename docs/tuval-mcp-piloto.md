# Piloto MCP de cotizaciones TUVAL

## Alcance

Los usuarios trabajan en el frontend TUVAL con su inicio de sesión habitual.
No necesitan cuentas de ChatGPT ni vincularlas mediante OAuth.

El [endpoint de extracción de imágenes](cotizacion-imagenes-ia.md) añade la
recepción de archivos y la llamada a OpenAI desde el backend. La interfaz de
subida, revisión o conversación todavía debe integrarse en el frontend.
Dots/ChatGPT puede actuar como cliente de pruebas si admite el transporte y
la autenticación descritos aquí. No es el servicio de IA que invoca el frontend.

Flujo previsto de la aplicación:

```text
Frontend TUVAL (sesión, cliente y pv_id seleccionados, imagen)
  -> Backend TUVAL (autentica y conserva el contexto)
  -> API de IA (interpreta imagen y propone detalle)
  -> Backend TUVAL (valida y muestra detalle para confirmación)
  -> Herramientas TUVAL / MCP
  -> QuotationItemsDraftService
  -> API GP (resolver y precios) -> borrador
```

MCP es un protocolo para anunciar herramientas y ejecutarlas con argumentos
estructurados. No interpreta imágenes ni ejecuta por sí mismo un modelo.
`tools/list` anuncia nombres, descripciones y esquemas; `tools/call` ejecuta
la herramienta solicitada. Laravel MCP implementa el protocolo HTTP.

Para un único backend, el futuro servicio de IA también puede invocar el
servicio PHP directamente. MCP resulta útil si queremos conectar distintos
clientes o reutilizar estas herramientas desde un agente. En ambos casos las
reglas de negocio permanecen en los mismos servicios.

## Componentes preparados

| Entrada | Función |
| --- | --- |
| `POST /api/mcp/tuval` | Servidor MCP HTTP, sin sesiones MCP persistentes |
| `tuval_contexto_cotizacion` | Comprueba usuario y punto de venta; no escribe |
| `tuval_crear_borrador_cotizacion` | Crea el borrador confirmado de forma idempotente |
| `POST /api/borradores/items` | Entrada JSON convencional para el frontend o pruebas |

Las dos entradas de creación usan `QuotationDraftPilotService`, que delega en
`QuotationItemsDraftService`. No hacen llamadas HTTP del backend a sí mismo.
La ruta histórica GET `/cotizaciones/borrador-items` permanece como estaba:
es una prueba anterior con usuario fijo y no forma parte del nuevo flujo.

## Identidad y punto de venta

Ambas entradas requieren el token Sanctum del usuario, ability `web`, cuenta
y perfil activos y contraseña sin cambio pendiente. El usuario se obtiene de
`$request->user()`, nunca de un campo `user_id` ni de una cuenta compartida.

El frontend proporciona `pv_id`, que se valida contra el punto de venta activo
y su relación con el usuario. Un punto de venta ajeno responde 403 antes de
consultar API GP. Los argumentos desconocidos, incluidos usuario y precios,
se rechazan. La herramienta de contexto devuelve solo usuario y punto de venta.

En el futuro orquestador de IA, conservar usuario, RUC y pv_id en el contexto
del servidor y fijarlos al ejecutar la herramienta. No permitir que el modelo
reemplace el punto de venta seleccionado. El servidor MCP autónomo comprueba
permiso, pero no conoce el estado de selección de una pantalla Vue.

No enviar el token Sanctum como texto del prompt, argumento de herramienta,
imagen o respuesta del modelo. El backend debe controlar las credenciales de
transporte. Para este piloto no se debe configurar un MCP remoto del proveedor
con un token común para todos los usuarios.

## Activación en pruebas

El servidor queda deshabilitado por defecto. Todas sus rutas comprueban en cada
request `TUVAL_MCP_ENABLED=true` y `APP_ENV` en `local`, `testing`, `qa` o
`staging`. Aunque se cacheen rutas, `production` devuelve 404.

1. Instalar las dependencias fijadas en `composer.lock` con el procedimiento
   habitual del proyecto (`composer install`). Se añade `laravel/mcp` 1.2;
   no se requiere Passport ni una API key de OpenAI para ejecutar el MCP.
2. Ejecutar **solo** la migración nueva:

   ```bash
   php artisan migrate --path=database/migrations/2026_11_12_140000_create_quotation_draft_pilot_requests_table.php --force
   ```

3. Establecer en el `.env` del backend de pruebas:

   ```dotenv
   TUVAL_MCP_ENABLED=true
   ```

4. Actualizar la caché de configuración mediante el procedimiento habitual del
   despliegue. Si se usa configuración cacheada, un cambio de `.env` no basta.
5. Usar la URL real HTTPS del backend para llamadas con credenciales. `APP_URL`
   por sí sola no demuestra si hay un proxy TLS delante del servidor.

La tabla nueva registra `user_id`, `request_id`, hash del payload y resultado.
Debe compartir conexión transaccional MySQL/InnoDB con las cotizaciones. No
borrar estos registros mientras se deban reconocer reintentos históricos.
Para desactivar el piloto, establecer `TUVAL_MCP_ENABLED=false` y refrescar
la configuración; no hace falta borrar cotizaciones ni revertir tablas.

## Probar primero sin IA

Obtener un token mediante el inicio de sesión existente de TUVAL y guardarlo
de forma privada en la herramienta de pruebas. En Postman usar Bearer Token.
No guardar tokens en colecciones exportadas ni en el repositorio.

### Entrada convencional desde el frontend

`POST /api/borradores/items`, encabezados:

```http
Authorization: Bearer <TOKEN_DEL_USUARIO>
Accept: application/json
Content-Type: application/json
```

Cuerpo de ejemplo; sustituir cliente y punto de venta por valores válidos:

```json
{
  "request_id": "6d281c32-b872-4c4b-a62e-128561014877",
  "confirmado": true,
  "cliente_ruc": "RUC_DEL_CLIENTE_SELECCIONADO",
  "pv_id": 2,
  "detalle": [
    {"texto": "codo galvanizado 1/2 por 90", "cantidad": 2}
  ],
  "maxCandidatos": 6
}
```

El frontend genera un UUID con `crypto.randomUUID()` para una nueva operación y
conserva el mismo UUID y cuerpo al reintentar por timeout. HTTP 201 crea;
HTTP 200 con `data.replayed=true` reutiliza el resultado. Un resultado con
`id=null` no crea una cotización vacía y también se conserva para reintentos.
Cambiar cliente, punto de venta o detalle con el mismo UUID responde 409.
Para una solicitud nueva o una nueva búsqueda, generar otro UUID.

Los registros de idempotencia y la cotización se confirman en una misma
transacción. Un error revierte ambos. Solicitudes concurrentes con el mismo
usuario y UUID se serializan mediante índice único y bloqueo de fila. Un UUID
igual de otro usuario pertenece a otra operación.

### Conexión MCP con Inspector o cliente compatible

Usar Streamable HTTP, URL `<BACKEND_QA>/api/mcp/tuval` y el encabezado Bearer
del usuario. Se puede ejecutar `php artisan mcp:inspector api/mcp/tuval`.
El inspector requiere Node/npm y puede solicitar instalar su cliente.
No configurar la conexión como anónima. Los GET/DELETE responden 405 porque
este servidor solo recibe mensajes por POST; no son pruebas de salud.

Para comprobar con Postman, añadir:

```http
Accept: application/json, text/event-stream
Content-Type: application/json
MCP-Protocol-Version: 2025-11-25
```

Inicialización:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-11-25",
    "capabilities": {},
    "clientInfo": {"name": "TUVAL Piloto", "version": "1.0.0"}
  }
}
```

Después enviar la notificación `notifications/initialized` sin `id` (responde
202), consultar herramientas y comprobar el contexto:

```json
{"jsonrpc":"2.0","method":"notifications/initialized"}
```

```json
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}
```

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "tuval_contexto_cotizacion",
    "arguments": {"pv_id": 2}
  }
}
```

Para crear, usar `tools/call` con nombre `tuval_crear_borrador_cotizacion` y
el mismo cuerpo de la entrada convencional dentro de `params.arguments`.
El `id` JSON-RPC identifica el mensaje; **no** sustituye al `request_id` de
negocio que evita duplicados.

La respuesta MCP exitosa contiene `result.isError=false` y
`result.structuredContent`. Para creación, el resultado del piloto está en
`result.structuredContent.data`, con `data` (borrador) y `replayed`.
Los errores de herramienta usan `result.isError=true` y contenido JSON con
`status` 403/409/422/502, etc. Un HTTP 200 no implica éxito de negocio.
Los errores de autenticación o del protocolo se resuelven antes del servicio.

## Reglas heredadas del servicio de borradores

- De 1 a 20 líneas; descripción hasta 200 caracteres; cantidad hasta 1000000
  con máximo dos decimales. Cantidad omitida o null asume 1; confirmar esa
  suposición antes de guardar. Se conserva la validación de unidad real.
- Cliente, vendedor y bodega se validan con las reglas existentes.
- Un artículo resuelto se usa directamente; varias candidaturas se incluyen
  todas. Este piloto no incorpora selección de una sola candidatura ni
  previsualización del resultado del resolver.
- `ART_VALOR` y `ART_VALOR_ORIGINAL` se toman del nivel de precios del cliente.
  Artículos `L` o sin coincidencia de precio conservan cero, como el flujo actual.
- Se conservan `incluidos`, `omitidos` y `advertencias`. No se añaden reservas,
  correos, aprobaciones, facturación ni sincronización transaccional hacia GP.
- `confirmado=true` es una declaración del cliente; no demuestra por sí misma
  una acción humana. La futura interfaz debe pedir y gestionar la confirmación.

## Siguiente etapa: imágenes e IA dentro de TUVAL

El endpoint `POST /api/borradores/extraer-imagenes` y su servicio de extracción
están implementados. La clave de esa API se administra en el servidor; los usuarios
se autentican únicamente en TUVAL. La integración debe fijar desde el request
el usuario, cliente y pv_id; el modelo devuelve solo las líneas extraídas.

La pantalla muestra esas líneas para corregirlas y, al confirmar, usa la entrada
convencional o el orquestador ejecuta las herramientas en el contexto del usuario.
El esquema y validaciones de este piloto se reutilizan. Se usa OpenAI Responses
con modelo configurable; consultar la guía de imágenes para la configuración,
formatos, límites y estado de validación real.

## Validación automatizada

Estado verificado el 12 de noviembre de 2026: archivos instalados en
`srv-web02:/var/www/webtuval`, entorno `staging`, piloto activado y migración
aplicada. Pasaron 10 pruebas y 58 aserciones (MCP y precios), tanto localmente
como en el servidor. El servidor quedó con dependencias `--no-dev` al terminar.
Las tres tablas implicadas usan InnoDB. La petición HTTP al vhost local sin
token devuelve 401; no se creó ningún borrador real para esta comprobación.
El respaldo privado está en
`/home/administrador/tuval-mcp-20261112-1nxVPR`.

No se pudo verificar HTTPS desde el servidor porque no resolvió el dominio
configurado en `APP_URL`. Para integrar el frontend, usar su base URL real y
verificar el transporte HTTPS desde los equipos clientes.
Los cambios de código permanecen sin commit, también en el servidor; un
despliegue posterior debe incorporar estos archivos y dependencias para
conservar el piloto.

Las pruebas de integración usan SQLite en memoria, usuario simulado y un mock
de `QuotationItemsDraftServiceInterface`; bloquean peticiones HTTP inesperadas.
Verifican protocolo, identidad, permisos, entorno, campos desconocidos,
confirmación, idempotencia entre REST/MCP y rollback. No crean borradores reales
ni sustituyen una prueba funcional con API GP/MySQL y datos de QA.

```powershell
& C:\laragon\bin\php\php-8.2.35-Win32-vs16-x64\php.exe -d xdebug.mode=off -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --do-not-cache-result --bootstrap vendor/autoload.php tests/Feature/TuvalMcpPilotTest.php
```

Referencias: [Laravel MCP y Sanctum](https://laravel.com/docs/12.x/mcp#sanctum),
[servidores MCP en OpenAI](https://developers.openai.com/plugins/build/mcp-server).
