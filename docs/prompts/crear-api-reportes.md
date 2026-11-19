# Crear una API de reportes para web o app móvil

## Lectura obligatoria antes de implementar

Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos documentos referencien y que apliquen al cambio. Contrasta la documentación con el código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una referencia, búscala en el proyecto y comunica la limitación sin inventar su contenido.

## Mensaje para copiar

Completa lo que conozcas. Basta con describir qué necesitas consultar; el agente investigará los detalles técnicos.

```text
Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y
docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos
documentos referencien y que apliquen al cambio. Contrasta la documentación con el
código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una
referencia, búscala en el proyecto y comunica la limitación sin inventar su
contenido.

Usa docs/prompts/crear-api-reportes.md.

Consumidor: [web / app móvil].
Necesito un reporte de [qué información necesito y para qué].
Debe mostrar [datos esperados] y permitir filtrar por [filtros].
Fuente, si la conozco: [tabla, vista, SP de MySQL, consulta SQL o API-GP].
Reglas particulares: [si existen].
Entrega: [solo local / PR a develop / PR a main / PR a ambas].

Implementa únicamente el backend del reporte para el consumidor elegido.
Reutiliza la estructura y el código existentes, sin archivos innecesarios.
Respeta las rutas y autenticación de ese consumidor.
Verifica solo en pruebas. No modifiques la web ni la app móvil,
no fusiones PR ni despliegues.
```

## Cuatro ejemplos para copiar y completar

Elige solo uno y completa los campos entre corchetes. Si desconoces la fuente exacta, describe la información que necesitas.

### Web con MySQL

```text
Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y
docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos
documentos referencien y que apliquen al cambio. Contrasta la documentación con el
código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una
referencia, búscala en el proyecto y comunica la limitación sin inventar su
contenido.

Usa docs/prompts/crear-api-reportes.md.

Consumidor: web.
Fuente: MySQL.
Tabla, vista o SP: [nombre, consulta SQL o indica que lo investigue].

Necesito consultar [información y objetivo].
Debe mostrar [campos] y filtrar por [filtros].
Reglas particulares: [si existen].
Entrega: [solo local / PR a develop / PR a main / PR a ambas].

Usa las rutas de Reportes para web en routes/api.php.
La nueva API será de solo consulta, sin login.
Verifica la estructura de MySQL y la firma real del SP si corresponde.
Usa consultas parametrizadas y reutiliza modelos y repositorios existentes.
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

### Web con API-GP

```text
Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y
docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos
documentos referencien y que apliquen al cambio. Contrasta la documentación con el
código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una
referencia, búscala en el proyecto y comunica la limitación sin inventar su
contenido.

Usa docs/prompts/crear-api-reportes.md.

Consumidor: web.
Fuente: API-GP.
Endpoint o reporte de API-GP: [ruta, nombre o indica que lo localice].

Necesito consultar [información y objetivo].
Debe mostrar [campos] y filtrar por [filtros].
Reglas particulares: [si existen].
Entrega: [solo local / PR a develop / PR a main / PR a ambas].

Usa las rutas de Reportes para web en routes/api.php.
La nueva API será de solo consulta, sin login.
Verifica en api-gp el contrato y la respuesta real del endpoint.
Reutiliza el cliente HTTP, constantes e integración existentes.
No crees un repositorio que solo repita la llamada HTTP.
Usa controlador → servicio del reporte → ApiGpService → GpHttpClient.
El servicio prepara params e interpreta resultados y fallos; ApiGpService
solo recibe array $params, llama a makeRequest() y devuelve su resultado.
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

### App móvil con MySQL

```text
Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y
docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos
documentos referencien y que apliquen al cambio. Contrasta la documentación con el
código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una
referencia, búscala en el proyecto y comunica la limitación sin inventar su
contenido.

Usa docs/prompts/crear-api-reportes.md.

Consumidor: app móvil.
Fuente: MySQL.
Tabla, vista o SP: [nombre, consulta SQL o indica que lo investigue].

Necesito consultar [información y objetivo].
Debe mostrar [campos] y filtrar por [filtros].
Reglas particulares: [si existen].
Entrega: [solo local / PR a develop / PR a main / PR a ambas].

Usa routes/movil.php y los grupos protegidos existentes.
Conserva autenticación, ability mobile y controles de cuenta,
contraseña y acceso a datos. No crees una ruta pública.
Verifica la estructura de MySQL y la firma real del SP si corresponde.
Usa consultas parametrizadas y reutiliza modelos y repositorios existentes.
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

### App móvil con API-GP

```text
Antes de implementar, lee los AGENTS.md aplicables, si existen, y docs/PROYECTO.md y
docs/prompts/crear-api-reportes.md. Sigue las instrucciones adicionales que esos
documentos referencien y que apliquen al cambio. Contrasta la documentación con el
código actual, reutiliza su estructura y conserva las reglas de negocio. Si falta una
referencia, búscala en el proyecto y comunica la limitación sin inventar su
contenido.

Usa docs/prompts/crear-api-reportes.md.

Consumidor: app móvil.
Fuente: API-GP.
Endpoint o reporte de API-GP: [ruta, nombre o indica que lo localice].

Necesito consultar [información y objetivo].
Debe mostrar [campos] y filtrar por [filtros].
Reglas particulares: [si existen].
Entrega: [solo local / PR a develop / PR a main / PR a ambas].

Usa routes/movil.php y los grupos protegidos existentes.
Conserva autenticación, ability mobile y controles de cuenta,
contraseña y acceso a datos. No crees una ruta pública.
Verifica en api-gp el contrato y la respuesta real del endpoint.
Reutiliza el cliente HTTP, constantes e integración existentes.
No crees un repositorio que solo repita la llamada HTTP.
Usa controlador → servicio del reporte → ApiGpService → GpHttpClient.
El servicio prepara params e interpreta resultados y fallos; ApiGpService
solo recibe array $params, llama a makeRequest() y devuelve su resultado.
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

El consumidor y la fuente son decisiones independientes: tanto web como móvil pueden consultar MySQL o API-GP. Elegir móvil no exige que la fuente sea API-GP. Si falta el consumidor y no está claro en el contexto, pregunta antes de definir rutas o acceso; no lo deduzcas de la fuente. Implementa solo el consumidor solicitado; habilitar ambos requiere una solicitud explícita.

Los ejemplos son plantillas, no tareas que deban ejecutarse al leer este documento. Las instrucciones explícitas del usuario y el contexto vigente prevalecen sobre esta guía. El usuario no necesita indicar clases, rutas internas, conexión ni firma del SP si el agente puede obtenerlas del proyecto o del entorno autorizado.

## Instrucciones para el agente

### 1. Entender y reutilizar

- Lee AGENTS.md y las instrucciones aplicables; revisa docs/PROYECTO.md, versiones instaladas, estado de Git y un reporte equivalente. Conserva los cambios ajenos.
- Identifica la fuente real, conexión configurada, campos, tipos, nulos, filtros, orden, paginación y reglas. Comprueba si ya existe una consulta, modelo o integración reutilizable. No inventes endpoints, columnas ni capacidades.
- Determina el submódulo por la necesidad de negocio. No pidas al usuario nombres de carpetas o clases. Pregunta solo por información indispensable o decisiones de negocio que no puedas resolver con las fuentes autorizadas.
- Comunica brevemente qué recursos reutilizarás y qué archivos nuevos hacen falta. No generes un documento de planificación.

### 2. Respetar la estructura

| Responsabilidad | Ubicación existente |
| --- | --- |
| Ruta web | Grupo `reportes` de `routes/api.php` |
| Ruta móvil | `routes/movil.php`, dentro del grupo protegido correspondiente |
| Entrada y respuesta HTTP | Web: `app/Http/Controllers/Reportes/`; móvil: reutiliza el controlador compatible o sigue `app/Http/Controllers/Movil/` si requiere adaptación |
| Validación, cuando corresponda | Reutiliza el Request compatible; sigue `app/Http/Requests/Reportes/` o el patrón móvil existente cuando cambie el contrato |
| Coordinación y reglas del reporte | `app/Services/Reportes/` y sus `Contracts` |
| Consultas MySQL | `app/Repositories/Reportes/` y sus `Contracts` |
| Bindings | Providers actuales, incluidos `ReportesServiceProvider` y `RepositoryServiceProvider` según corresponda |
| Respuestas y errores | `JsonResponseTrait` y `ExecuteApiTransactions` |

Flujo para MySQL: controlador → servicio → repositorio → tabla, vista o SP. Reutiliza clases y métodos adecuados; esta tabla no obliga a crear un archivo por responsabilidad. Mantén las interfaces e inyección del módulo, registra bindings sin duplicarlos y actualiza `provides()` si el provider es diferido.

El controlador no contiene SQL ni reglas de negocio extensas. El servicio no contiene lógica HTTP. El repositorio concentra el acceso a datos. Reutiliza modelos existentes; no crees un modelo solo para ejecutar un SP ni para representar cada vista si no es necesario.

Para API-GP, reutiliza la integración en `app/Services/Integraciones/Api/`, `ApiGpService`, `GpHttpClient`, las constantes de `ApiGpEndpoints` y `config('services.api_gp')`. No crees un repositorio que solo repita la llamada HTTP ni otro cliente. Verifica el contrato en api-gp y la respuesta desplegada; no modifiques ese repositorio ni sus SP salvo que forme parte del requerimiento.

**Flujo obligatorio para reportes con API-GP:** controlador → servicio del reporte → `ApiGpService` → `GpHttpClient`.

- Reutiliza un servicio de reportes compatible; si no existe, crea uno en `app/Services/Reportes/`, siguiendo los contratos, inyección y bindings del módulo. Actualiza el provider existente y su `provides()` cuando corresponda. La instrucción de crear solo lo necesario no permite omitir esta capa ni trasladar su lógica a la integración.
- El servicio prepara `array $params`, aplica reglas, catálogos y valores predeterminados del reporte, llama a la integración e interpreta su respuesta: éxito, ausencia de resultados, respuesta inválida o fallo. Extrae o transforma los datos allí. No construye respuestas HTTP ni depende de `Request`; utiliza el manejo de excepciones existente para que el controlador y los traits conserven el contrato HTTP.
- Los métodos nuevos de `ApiGpService` reciben `array $params` y se limitan a llamar a `makeRequest()` con el endpoint, método y formato correspondientes, devolviendo su resultado sin interpretarlo. No reciben los filtros del reporte como argumentos individuales ni construyen sus parámetros.
- No coloques en `ApiGpService` catálogos de sucursales, filtros predeterminados, validaciones del reporte, extracción de `data`, mensajes específicos ni excepciones propias del reporte.
- `GpHttpClient` conserva el manejo técnico compartido de conexión, transporte y reintentos. No lo modifiques para resolver reglas o errores específicos de un reporte.
- El controlador valida la entrada, aplica la autorización correspondiente, delega al servicio y construye la respuesta mediante los traits existentes. No llama directamente a `ApiGpService` para resolver el reporte.

### 3. Consultar la fuente correctamente

- Usa conexiones existentes. Para MySQL, inspecciona en pruebas la tabla o vista; para un SP, verifica su definición, firma, orden de parámetros, tipos y comportamiento con null. No deduzcas la firma por nombres ni ejecutes un SP antes de confirmar que es de consulta.
- Parametriza valores en consultas y llamadas `CALL`. Los nombres de tablas, columnas y ordenamientos no se parametrizan como valores: deben ser fijos o seleccionarse mediante una lista permitida. Nunca aceptes SQL arbitrario desde la petición.
- Conserva agrupaciones, cálculos, precisión e interpretación de fechas. No cambies reglas para obtener resultados. No agregues paginación, totales ni filtros ficticios; no descargues todo para paginar en memoria sin evaluar el volumen.
- Para API-GP, respeta método, formato y parámetros reales. El cliente puede devolver `success=false` sin lanzar una excepción: no conviertas errores en éxito ni en una lista vacía. No alteres globalmente los reintentos o timeouts por un reporte.
- No escribas datos, ejecutes procesos comerciales ni crees o modifiques tablas, vistas o SP como parte de una consulta. Si la fuente requiere cambios, identifica qué falta y aclara el alcance antes de alterarla.

### 4. Consumidor, autenticación y contrato

- Organiza las rutas mediante grupos `Route::prefix()` según el módulo y submódulo al que pertenecen. Reutiliza el grupo existente; si falta, créalo dentro del grupo de acceso correspondiente al consumidor. Para reportes de ventas, usa el grupo `reportes` y dentro el grupo `ventas`, dejando en la ruta solo el segmento específico, por ejemplo `sucursales/comparativo`. No declares una ruta aislada con `reportes/ventas/...` ni dupliques grupos equivalentes. Determina el submódulo por su uso de negocio; no agregues niveles sin necesidad. Al reorganizar rutas existentes, conserva su URL final, método, nombre, restricciones y middleware efectivos.
- Implementa solo el backend. Para web, usa `/api/reportes/<submodulo>/<reporte>`. Para reportes móviles nuevos, reutiliza el grupo equivalente en `routes/movil.php`; si no existe, incorpora `reportes/<submodulo>/<reporte>` dentro del grupo protegido. El prefijo actual lo registra `RouteServiceProvider`: `/api/movil`, por lo que la URL resultará `/api/movil/reportes/...`. Comprueba siempre el registro actual y no dupliques `/api` ni `/api/movil` en los archivos de rutas. Omite el submódulo cuando no corresponda.
- **Web:** se conserva la autorización de esta plantilla para rutas nuevas de reportes públicas, sin login, token, API key ni `Authorization`. Ubícalas fuera de grupos de autenticación; conserva middleware generales y límites. No conviertas rutas protegidas existentes en públicas. Un FormRequest público debe permitir la solicitud y no depender de un usuario autenticado.
- **App móvil:** no hereda la regla de acceso público. Coloca los reportes dentro de los grupos existentes con `auth:sanctum`, `active.account`, `abilities:mobile` y `must.change.password`, conforme al registro vigente. No uses las excepciones de login o actualización de la app como ejemplo para un reporte. Conserva permisos y alcance por usuario, agencia, punto de venta o bodega cuando apliquen; valida los identificadores recibidos contra ese alcance. No añadas ni elimines políticas de negocio sin fundamento en el flujo existente.
- Un `authorize(): true` en el Request no sustituye la autenticación ni los permisos de la ruta. No desactives middleware, abilities o controles para hacer pasar una prueba móvil.
- **Reutilización:** web y móvil deben compartir servicios, repositorios e integración cuando consulten la misma información. No copies SQL ni crees variantes `WebService`/`MobileService` solo por el consumidor. Reutiliza también controlador y Request si contrato y acceso lo permiten; sepáralos únicamente cuando necesiten validación, contexto o presentación diferentes. Los servicios reciben filtros y contexto explícitos, sin depender de la petición HTTP ni asumir un usuario autenticado en el flujo web público.
- GET preferentemente; POST solo si la estructura de filtros lo justifica. Ambos deben ser de consulta. Conserva el contrato de respuesta de cada consumidor; no cambies una respuesta ya consumida para acomodar la otra. No crees una ruta web adicional para que móvil evite sus controles.
- Controladores, métodos propios de controladores, FormRequests, submódulos nuevos, URLs, filtros públicos y mensajes: en español. Servicios, repositorios, interfaces, métodos e identificadores internos nuevos: en inglés. Conserva nombres impuestos por Laravel. No renombres carpetas ni contratos existentes por esta tarea.
- Clases en PascalCase; métodos y variables en camelCase; URLs en kebab-case sin tildes. Campos nuevos de respuesta en camelCase o PascalCase según el contrato del módulo; conserva los identificadores reales al consultar SQL y mapea explícitamente si hace falta. No copies snake_case de SQL automáticamente al JSON ni rompas contratos existentes.
- Usa imports `use`, tipos de parámetros y retornos compatibles, y `declare(strict_types=1);` en PHP nuevo. Mantén código legible, responsabilidades claras y formato del proyecto, sin bloques comprimidos ni abstracciones especulativas.
- Usa datos validados, mediante FormRequest o la validación existente adecuada. Valida obligatoriedad, tipos, formatos, rangos y filtros opcionales; no pases `$request->all()` directamente al repositorio o API externa.
- Reutiliza las firmas reales de `successResponse()`, manejo de errores y `handleApiAction()`. No dupliques try/catch ni formatos JSON. Sin resultados: éxito con `data` vacía según el contrato; parámetros inválidos: respuesta de validación. No expongas SQL, trazas, credenciales ni URLs internas.

### 5. Crear solo lo necesario y verificar

- No añadas paquetes, migraciones, modelos duplicados, jobs, caché, exports, DTOs, providers ni utilidades genéricas sin una necesidad concreta del reporte. No refactorices otros módulos. No generes informes, scripts operativos o documentación adicional por defecto.
- Ejecuta lint PHP, resolución de dependencias y `php artisan route:list --path=reportes` para web o `php artisan route:list --path=api/movil` para móvil; inspecciona los middleware efectivos con las opciones disponibles. Confirma método, URL y el acceso esperado según el consumidor.
- Para web pública, verifica una consulta sin autenticación. Para móvil, comprueba acceso con token móvil válido y rechazos sin token, con ability incorrecta, cuenta inactiva o cambio de contraseña pendiente según los middleware existentes. Comprueba el alcance de datos cuando aplique. Usa fixtures o cuentas de prueba disponibles; no cambies cuentas ni expongas tokens para facilitar la validación.
- Si modificas lógica compartida, verifica también el contrato del consumidor existente para evitar regresiones, sin crear endpoints adicionales.
- Reutiliza pruebas. Añade solo las que verifiquen comportamiento relevante: filtros, parámetros y su orden, transformación, ausencia de resultados y errores. No generes pruebas que solo repitan la implementación.
- Realiza consultas acotadas exclusivamente en pruebas. Compara resultados con la fuente y verifica entradas válidas, inválidas y vacías. No ejecutes migraciones, escrituras ni procedimientos comerciales para probar. Distingue pruebas aisladas de integración real y no afirmes resultados no comprobados.
- Revisa el diff y elimina únicamente archivos innecesarios creados por tu tarea. Conserva el trabajo ajeno y comunica limitaciones reales en la respuesta o el PR, sin otro informe.
- Para API-GP, verifica en el diff el flujo controlador → servicio del reporte → integración: `ApiGpService` únicamente delega la llamada con `array $params`. Comprueba que la preparación de parámetros, valores predeterminados e interpretación de respuestas estén en el servicio del reporte. Una respuesta exitosa con una lista vacía debe conservarse como resultado vacío; un fallo o una respuesta inválida no deben convertirse en éxito ni en una lista vacía. Verifica también la resolución del servicio mediante su contrato en el contenedor.
- Comprueba que cada ruta esté dentro de los prefijos de su módulo y submódulo, sin segmentos duplicados. Contrasta la URL final y los middleware efectivos con `route:list`; agrupar rutas existentes no debe cambiar su contrato ni su acceso.

### 6. Entrega local o mediante PR

La elección `Entrega` controla la publicación. `Solo local` significa sin commits, push ni PR. Si falta la elección, pregunta antes de crear ramas o implementar cambios dependientes de una base; mientras tanto, revisa la estructura. No deduzcas el destino de la rama local.

Para `PR a develop`, `PR a main` o `PR a ambas`:

1. Actualiza referencias remotas. Para cada destino elegido, crea una rama `codex/reportes-<nombre>-<destino>` desde su `origin/<destino>` actualizado. Usa worktrees cuando sean necesarios para aislar trabajo previo o bases distintas.
2. Implementa y valida solo el requerimiento en cada destino. Si son ambas ramas, no promociones develop completo a main; aplica únicamente los cambios de la tarea, adapta diferencias y verifica cada base.
3. Publica exactamente un PR por destino seleccionado y solicita revisión a `josemg23`. Comprueba base, diff, estado abierto y revisión registrada; una mención en la descripción no sustituye la solicitud de revisión.
4. No hagas push directo a develop/main, no uses bypass, no modifiques protecciones, no fusiones ni actives auto-merge. Un PR hacia main no autoriza desplegar ni probar contra producción.
5. Si falta acceso, conserva los cambios e informa qué paso quedó pendiente. No inventes enlaces ni des por creado un PR o una revisión que no se registró.

Entrega un resumen breve con consumidor, autenticación efectiva, método y URL, filtros, ejemplo de petición/respuesta, recursos reutilizados, archivos nuevos necesarios y pruebas ejecutadas. Si aplica, añade enlaces reales de los PR, destinos y estado de la revisión. No despliegues sin solicitud expresa.
