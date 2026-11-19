# Guía de prompts de los proyectos TUVAL

Documento consolidado con las seis plantillas disponibles y sus ejemplos para tuvalBackend, TuvalFront, api-gp y appmovil. Revisión de archivos locales: 19 de noviembre de 2026.

## Cómo utilizar este documento

1. Abre el repositorio donde se realizará el cambio.
2. Busca el caso correspondiente en el índice y copia su mensaje o ejemplo, completando los campos entre corchetes.
3. El mensaje `Usa docs/...` se refiere al archivo dentro de ese repositorio. No uses la ruta de otro proyecto aunque el nombre del archivo sea igual.
4. Indica la necesidad y las reglas de negocio. El agente investigará archivos, contratos y recursos existentes.
5. Si una funcionalidad requiere cambios en varios proyectos, solicita cada parte explícitamente. Crear una API no implica crear automáticamente una pantalla ni modificar la app.

Las secciones siguientes incluyen las plantillas completas, no solo enlaces. Conservan sus reglas específicas. Esta recopilación es una copia de consulta: ante cambios posteriores, revisa la plantilla del repositorio y las instrucciones actuales. Leer o editar este documento no autoriza implementar sus ejemplos ni publicar cambios.

## Elegir el proyecto

| Necesidad | Proyecto y plantilla |
| --- | --- |
| API Laravel que consulta MySQL o consume API-GP para web o móvil | [TUVAL Backend](#tuvalbackend) |
| Nueva pantalla web conectada al backend | [Frontend crear pantalla](#frontend-crear) |
| Corregir o ampliar una pantalla web existente | [Frontend modificar](#frontend-modificar) |
| Crear o ampliar una API Java que consulta SQL Server | [API-GP reportes](#apigp) |
| Nueva pantalla o funcionalidad Flutter | [App móvil crear](#movil-crear) |
| Corregir o ampliar una funcionalidad Flutter | [App móvil modificar](#movil-modificar) |

La fuente y el consumidor son independientes: web o móvil pueden consumir un reporte Laravel basado en MySQL o API-GP. API-GP consulta SQL Server; la app Flutter y la web utilizan las APIs del backend conforme a sus contratos. No confundas crear una ruta móvil en Laravel con implementar una pantalla Flutter.

## Ramas y revisiones según la plantilla

| Proyecto | Destino establecido | Revisión |
| --- | --- | --- |
| tuvalBackend | Solo local o PR a develop, main o ambas, según selección explícita | josemg23 cuando hay PR |
| TuvalFront | PR a qa o develop, según selección; main excluida por la plantilla actual | Revisión humana según el flujo y reglas del repositorio |
| api-gp | PR a develop | Responsables de .github/CODEOWNERS con acceso y distintos del autor |
| appmovil | PR a develop; ambiente qa, no rama qa | TuvalSystems, devtuval o josemg23, distinto del autor |

No trasladar las opciones de ramas de un proyecto a otro. Las excepciones para trabajo solo local o publicación por el propietario se conservan en cada plantilla. Un PR hacia main en backend no autoriza desplegar ni operar producción. Estas reglas describen los prompts; no certifican las protecciones técnicas de GitHub.

## Convenciones que se deben conservar

Reutilizar código, arquitectura, contratos y recursos existentes; crear solo archivos necesarios; respetar el alcance de cada tarea y no ejecutar operaciones comerciales para comprobar consultas. Los ejemplos son ilustrativos y deben contrastarse con el código y el entorno autorizado.

En TUVAL Backend, la plantilla distingue rutas públicas nuevas de reportes web y rutas móviles protegidas. En Frontend y Flutter conserva diseño, tema, estado y contexto del punto de venta. En API-GP conserva conexiones y SQL parametrizado. Las verificaciones y políticas de pruebas específicas se detallan en cada sección: no las sustituyas por una regla global de esta guía.

<a id="tuvalbackend"></a>

## TUVAL Backend

Repositorio: `F:/www/tuvalBackend`. Plantilla original: [docs/prompts/crear-api-reportes.md](F:/www/tuvalBackend/docs/prompts/crear-api-reportes.md).

## Crear una API de reportes para web o app móvil

### Mensaje para copiar

Completa lo que conozcas. Basta con describir qué necesitas consultar; el agente investigará los detalles técnicos.

```text
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

### Cuatro ejemplos para copiar y completar

Elige solo uno y completa los campos entre corchetes. Si desconoces la fuente exacta, describe la información que necesitas.

#### Web con MySQL

```text
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

#### Web con API-GP

```text
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
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

#### App móvil con MySQL

```text
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

#### App móvil con API-GP

```text
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
Reutiliza servicios y lógica compartida con el otro consumidor si existen,
sin duplicar código ni crear archivos innecesarios.
Implementa solo el backend y verifica únicamente en pruebas.
No modifiques la web ni la app móvil, no fusiones PR ni despliegues.
```

El consumidor y la fuente son decisiones independientes: tanto web como móvil pueden consultar MySQL o API-GP. Elegir móvil no exige que la fuente sea API-GP. Si falta el consumidor y no está claro en el contexto, pregunta antes de definir rutas o acceso; no lo deduzcas de la fuente. Implementa solo el consumidor solicitado; habilitar ambos requiere una solicitud explícita.

Los ejemplos son plantillas, no tareas que deban ejecutarse al leer este documento. Las instrucciones explícitas del usuario y el contexto vigente prevalecen sobre esta guía. El usuario no necesita indicar clases, rutas internas, conexión ni firma del SP si el agente puede obtenerlas del proyecto o del entorno autorizado.

### Instrucciones para el agente

#### 1. Entender y reutilizar

- Lee AGENTS.md y las instrucciones aplicables; revisa docs/PROYECTO.md, versiones instaladas, estado de Git y un reporte equivalente. Conserva los cambios ajenos.
- Identifica la fuente real, conexión configurada, campos, tipos, nulos, filtros, orden, paginación y reglas. Comprueba si ya existe una consulta, modelo o integración reutilizable. No inventes endpoints, columnas ni capacidades.
- Determina el submódulo por la necesidad de negocio. No pidas al usuario nombres de carpetas o clases. Pregunta solo por información indispensable o decisiones de negocio que no puedas resolver con las fuentes autorizadas.
- Comunica brevemente qué recursos reutilizarás y qué archivos nuevos hacen falta. No generes un documento de planificación.

#### 2. Respetar la estructura

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

#### 3. Consultar la fuente correctamente

- Usa conexiones existentes. Para MySQL, inspecciona en pruebas la tabla o vista; para un SP, verifica su definición, firma, orden de parámetros, tipos y comportamiento con null. No deduzcas la firma por nombres ni ejecutes un SP antes de confirmar que es de consulta.
- Parametriza valores en consultas y llamadas `CALL`. Los nombres de tablas, columnas y ordenamientos no se parametrizan como valores: deben ser fijos o seleccionarse mediante una lista permitida. Nunca aceptes SQL arbitrario desde la petición.
- Conserva agrupaciones, cálculos, precisión e interpretación de fechas. No cambies reglas para obtener resultados. No agregues paginación, totales ni filtros ficticios; no descargues todo para paginar en memoria sin evaluar el volumen.
- Para API-GP, respeta método, formato y parámetros reales. El cliente puede devolver `success=false` sin lanzar una excepción: no conviertas errores en éxito ni en una lista vacía. No alteres globalmente los reintentos o timeouts por un reporte.
- No escribas datos, ejecutes procesos comerciales ni crees o modifiques tablas, vistas o SP como parte de una consulta. Si la fuente requiere cambios, identifica qué falta y aclara el alcance antes de alterarla.

#### 4. Consumidor, autenticación y contrato

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

#### 5. Crear solo lo necesario y verificar

- No añadas paquetes, migraciones, modelos duplicados, jobs, caché, exports, DTOs, providers ni utilidades genéricas sin una necesidad concreta del reporte. No refactorices otros módulos. No generes informes, scripts operativos o documentación adicional por defecto.
- Ejecuta lint PHP, resolución de dependencias y `php artisan route:list --path=reportes` para web o `php artisan route:list --path=api/movil` para móvil; inspecciona los middleware efectivos con las opciones disponibles. Confirma método, URL y el acceso esperado según el consumidor.
- Para web pública, verifica una consulta sin autenticación. Para móvil, comprueba acceso con token móvil válido y rechazos sin token, con ability incorrecta, cuenta inactiva o cambio de contraseña pendiente según los middleware existentes. Comprueba el alcance de datos cuando aplique. Usa fixtures o cuentas de prueba disponibles; no cambies cuentas ni expongas tokens para facilitar la validación.
- Si modificas lógica compartida, verifica también el contrato del consumidor existente para evitar regresiones, sin crear endpoints adicionales.
- Reutiliza pruebas. Añade solo las que verifiquen comportamiento relevante: filtros, parámetros y su orden, transformación, ausencia de resultados y errores. No generes pruebas que solo repitan la implementación.
- Realiza consultas acotadas exclusivamente en pruebas. Compara resultados con la fuente y verifica entradas válidas, inválidas y vacías. No ejecutes migraciones, escrituras ni procedimientos comerciales para probar. Distingue pruebas aisladas de integración real y no afirmes resultados no comprobados.
- Revisa el diff y elimina únicamente archivos innecesarios creados por tu tarea. Conserva el trabajo ajeno y comunica limitaciones reales en la respuesta o el PR, sin otro informe.

#### 6. Entrega local o mediante PR

La elección `Entrega` controla la publicación. `Solo local` significa sin commits, push ni PR. Si falta la elección, pregunta antes de crear ramas o implementar cambios dependientes de una base; mientras tanto, revisa la estructura. No deduzcas el destino de la rama local.

Para `PR a develop`, `PR a main` o `PR a ambas`:

1. Actualiza referencias remotas. Para cada destino elegido, crea una rama `codex/reportes-<nombre>-<destino>` desde su `origin/<destino>` actualizado. Usa worktrees cuando sean necesarios para aislar trabajo previo o bases distintas.
2. Implementa y valida solo el requerimiento en cada destino. Si son ambas ramas, no promociones develop completo a main; aplica únicamente los cambios de la tarea, adapta diferencias y verifica cada base.
3. Publica exactamente un PR por destino seleccionado y solicita revisión a `josemg23`. Comprueba base, diff, estado abierto y revisión registrada; una mención en la descripción no sustituye la solicitud de revisión.
4. No hagas push directo a develop/main, no uses bypass, no modifiques protecciones, no fusiones ni actives auto-merge. Un PR hacia main no autoriza desplegar ni probar contra producción.
5. Si falta acceso, conserva los cambios e informa qué paso quedó pendiente. No inventes enlaces ni des por creado un PR o una revisión que no se registró.

Entrega un resumen breve con consumidor, autenticación efectiva, método y URL, filtros, ejemplo de petición/respuesta, recursos reutilizados, archivos nuevos necesarios y pruebas ejecutadas. Si aplica, añade enlaces reales de los PR, destinos y estado de la revisión. No despliegues sin solicitud expresa.

<a id="frontend-crear"></a>

## TUVAL Frontend crear pantalla

Repositorio: `F:/www/TuvalFront`. Plantilla original: [docs/prompts/crear-pantalla-api.md](F:/www/TuvalFront/docs/prompts/crear-pantalla-api.md).

## Crear una pantalla conectada al API en TUVAL

### Cómo usar esta plantilla

Envía al agente este mensaje, completando lo que conozcas:

> Usa docs/prompts/crear-pantalla-api.md. Necesito [describir lo que quiero hacer]. Lo usarán [personas o cargos, si los conozco] para [objetivo del trabajo]. Debe mostrar [información] y permitir [acciones]. Debe respetar [reglas de negocio]. Si conozco una pantalla relacionada o el API, te lo indicaré. Identifica tú dónde corresponde incorporarlo en TUVAL.

No necesitas conocer toda la respuesta del API ni los archivos internos. Investiga primero el proyecto y el API; pregunta solo por decisiones de negocio o información que no puedas obtener allí.

### Ejemplo para copiar y adaptar

Este ejemplo es una guía de solicitud, no una tarea pendiente que el agente deba ejecutar al leer este archivo. Sustituye la necesidad por la tuya y pega el mensaje en el chat del agente con el proyecto TUVAL abierto.

```text
Usa docs/prompts/crear-pantalla-api.md.

Necesito consultar las cotizaciones que todavía no se han convertido
en pedidos. Lo utilizarán los vendedores para dar seguimiento a sus clientes.

Debe mostrar número de cotización, fecha, cliente, vendedor, total y estado.
Debe permitir buscar por cliente y filtrar por fechas y punto de venta.
Desde el listado quiero abrir el detalle de la cotización.

No conozco el API. Revisa si ya existe una consulta y si esta funcionalidad
puede incorporarse en una pantalla actual antes de crear otra.
Si falta soporte del API, indícalo; no inventes datos ni endpoints.

Reutiliza componentes y métodos. Conserva el diseño y la estructura de
TUVAL, con código legible y sin archivos innecesarios. Verifica que al
cambiar de punto de venta se limpien los datos anteriores sin recargar.

Destino del PR: [qa o develop; elige uno].
Crea una rama desde el destino actualizado y entrega el PR hacia esa misma rama.
Aunque uses devtuval, josemg23 o TuvalSystems, no subas directamente
ni uses la excepción. No fusiones, no despliegues y no toques main.
```

### Dónde consultar esta guía

En GitHub, selecciona la rama de destino (qa o develop) y abre docs/prompts/crear-pantalla-api.md. GitHub muestra el Markdown con formato y permite copiar el ejemplo. En el proyecto local, abre este mismo archivo en tu editor. Los cambios nuevos estarán disponibles en la rama de destino después de aprobar y fusionar su PR; antes puedes consultarlos en la rama del PR. Esta guía no aparece automáticamente dentro de la aplicación TUVAL.

### Identificar la ubicación a partir de la necesidad

No pidas al usuario que identifique un módulo, carpeta, componente o ruta técnica. Interpreta su necesidad en términos del proceso de negocio: qué quiere hacer, con qué información, quién lo usa y en qué momento del trabajo.

Antes de crear una pantalla, revisa el menú, las rutas, los permisos, las pantallas relacionadas, los composables y las constantes API existentes. Determina si corresponde ampliar una pantalla actual, añadir una opción dentro de una sección existente o crear una pantalla nueva. No decidas únicamente por una palabra de la solicitud ni por el nombre del endpoint.

Si la ubicación es clara, comunica en lenguaje del usuario dónde aparecerá y por qué, y continúa sin pedir confirmación rutinaria. Ejemplo: «Lo incorporaré en Cobranzas, junto a Cobros, porque permite consultar los pagos recibidos». Sustituye este ejemplo por nombres que realmente existan en el menú inspeccionado.

Si hay dos ubicaciones razonables y la elección cambia el flujo o los permisos, formula una pregunta de negocio concreta, por ejemplo: «¿Lo usarán al vender al cliente o al revisar los cobros?». No preguntes «¿en qué módulo lo pongo?». Continúa las comprobaciones independientes mientras esperas la respuesta; no inventes reglas o permisos que dependan de ella.

La clasificación interna y la elección de archivos corresponden al agente. No crees una sección nueva del menú, dupliques una funcionalidad ni amplíes permisos solo porque la solicitud no menciona una ubicación. Si la necesidad realmente exige una sección nueva, explica esa decisión antes de implementarla.

### Instrucciones para el agente

Implementa la pantalla solicitada siguiendo estas reglas:

1. Lee las instrucciones aplicables del repositorio. Localiza una pantalla equivalente, el cliente HTTP, autenticación, constantes API, rutas, menú, permisos, componentes, composables y métodos reutilizables. Revisa si la funcionalidad ya existe y se puede extender antes de crear otra.
2. Usa la arquitectura, convenciones, nombres y estilo de código actuales. No introduzcas reingeniería, nuevas capas, dependencias, patrones ni refactorizaciones ajenas al objetivo. No escribas código que duplique un componente, método, validación o utilidad existente. Extiende recursos compartidos solo cuando sea necesario y preserva sus consumidores actuales.
3. Crea únicamente los archivos necesarios para integrar la pantalla según el patrón del módulo. Reutiliza archivos adecuados cuando existan. No generes informes, ejemplos, archivos auxiliares, copias, nuevas plantillas ni pruebas que solo repitan la implementación. No crees documentación por defecto. Si hace falta una nueva abstracción, justifica su necesidad concreta; no abstraigas para casos futuros hipotéticos.
4. Antes de definir columnas y controles, revisa el contrato y consulta el API con una sesión autorizada y operaciones de lectura. Comprueba estructura real, identificador, campos, tipos, nulos, fechas, totales, paginación, filtros, orden y errores. Relaciona cada dato de la pantalla con su campo real. No supongas que una muestra representa todos los casos ni inventes campos o capacidades del API.
5. Usa el cliente HTTP, autenticación y constantes existentes. No expongas secretos. Si falta acceso o contrato, informa lo que falta y avanza solo en lo que no dependa de ello; no presentes una simulación como integración verificada. Pruebas usa http://tuvalweb.cosenco.ec; identifica la URL del API en la configuración, no la supongas a partir de la URL del frontend.
6. Conserva estructura visual, diseño, fuente y tema de TUVAL. Usa src/components/BaseTable.vue para listados y toma como referencia src/views/cobranzas/cobros-facturas/CobrosFacturasIndex.vue. Reutiliza formularios, modales, botones, iconos y mensajes existentes. Usa colores del tema, sin valores estáticos. Sigue el patrón actual de composables para consultas, acciones y estado de tablas.
7. Implementa solo las columnas, filtros, acciones y reglas necesarias para el objetivo. Respeta la paginación y el orden soportados por el API. Incluye carga, lista vacía, errores y reintento con los mecanismos existentes. Integra ruta, menú y permisos según el proyecto, sin ampliar permisos por cuenta propia.
8. Si depende del punto de venta, utiliza el mecanismo existente para limpiar datos y selecciones anteriores, impedir respuestas atrasadas y actualizar sin recargar la página. Respeta también el contexto de usuario, cliente y bodega cuando corresponda.
9. Ejecuta las verificaciones proporcionales al cambio y los controles requeridos del proyecto. Reutiliza pruebas existentes; añade pruebas solo si cubren un riesgo o comportamiento relevante. Verifica la integración con una sesión real cuando esté disponible. No registres operaciones de negocio ni alteres datos reales solo para probar sin autorización específica.
10. Revisa el diff: elimina duplicaciones y archivos creados por tu tarea que no sean necesarios. No elimines ni reviertas trabajo ajeno. Resume la funcionalidad, los campos del API utilizados, las verificaciones y los pendientes en tu respuesta, sin generar un informe adicional. No despliegues salvo solicitud expresa.

### Estructura y legibilidad obligatorias

Antes de implementar, identifica una pantalla equivalente del mismo módulo y comunica brevemente qué archivos y recursos reutilizarás. No generes un documento adicional ni detengas el trabajo por esta revisión.

| Responsabilidad | Ubicación o recurso existente |
| --- | --- |
| Pantalla y coordinación de la interfaz | src/views/<modulo>/ y sus componentes locales |
| Consultas, acciones y estado reactivo del flujo | src/composables/<modulo>/ siguiendo los nombres reales del módulo |
| Endpoints | src/constants/apiRoutes/<modulo>/ |
| Peticiones autenticadas | src/axios.js; revisa useApiAction en src/composables/core antes de repetir su manejo de carga y errores |
| Tablas, modales y búsqueda | BaseTable.vue, BaseModal.vue, BaseSearchSelect.vue y componentes del módulo; comprueba sus props, eventos y slots antes de usarlos |
| Rutas y acceso | src/router/routes/<modulo>.js y los guards existentes |
| Alertas y formatos | src/functions/swal-alert.js y las funciones/utilidades existentes |
| Estado compartido | El store que ya use el flujo; conviven src/store y src/stores, no migres entre Vuex y Pinia por esta tarea |

Estas rutas orientan la búsqueda; no son una orden de crear un archivo o carpeta por cada responsabilidad. Conserva nombres reales como venta, ventas o cobranzas según el recurso existente. No reorganices carpetas para uniformarlos.

- Escribe funciones con una responsabilidad clara y nombres que expresen la intención. Evita abreviaturas ambiguas, bloques comprimidos en una línea, ternarios anidados y condiciones difíciles de seguir. Usa variables intermedias y retornos tempranos cuando aclaren el flujo.
- Mantén el idioma y las convenciones de nombres del módulo. No renombres APIs públicas ni archivos existentes para imponer otro estilo.
- Deja en el template la presentación y enlaces a acciones; coloca las transformaciones o reglas complejas en funciones o computed con nombres claros. Evita peticiones HTTP y reglas de negocio extensas dentro de handlers del template.
- No conviertas la vista en un archivo que concentre API, validaciones, tablas y lógica de negocio. Usa los composables y componentes adecuados sin fragmentar operaciones sencillas en archivos innecesarios.
- No copies un bloque existente para cambiar solo un endpoint, etiqueta o columna cuando un recurso actual ya admite configurarlo. Antes de ampliar un componente compartido, revisa sus consumidores y conserva compatibilidad.
- Reutiliza el manejo de carga, errores y concurrencia cuando corresponda. No añadas catch vacíos, errores silenciados, valores por defecto que oculten respuestas inválidas ni logs de depuración en la entrega.
- Mantén el formato del código circundante y las reglas ESLint del proyecto. No desactives reglas, añadas exclusiones ni rebajes verificaciones para hacer pasar el cambio. Distingue errores anteriores de los introducidos y comunica los bloqueos sin ampliar el alcance.
- Comenta decisiones o restricciones que no sean evidentes; evita comentarios que repiten literalmente el código, código comentado y tareas pendientes sin resolver.

Antes de entregar, revisa el diff y confirma en la respuesta qué recursos reutilizaste, por qué fue necesario cada archivo nuevo y qué verificaciones ejecutaste. Ejecuta npm run lint y npm run build para cambios de aplicación, además de las comprobaciones específicas pertinentes; para cambios solo de documentación revisa contenido y diff. La aprobación humana del PR sigue siendo necesaria: una plantilla orienta el trabajo, pero no garantiza por sí sola la calidad del código.

### Ramas y pull requests obligatorios

Este flujo forma parte de la tarea al invocar esta plantilla:

Destino permitido: qa o develop. El usuario puede indicar «Destino del PR: qa» o «Destino del PR: develop». Usa el destino explícito de la solicitud o del contexto vigente; no lo deduzcas únicamente de la rama local, del módulo o de la URL del entorno de pruebas. Si no está definido, pregunta «¿El cambio debe ir a qa o a develop?» antes de crear la rama o implementar cambios dependientes de su versión. Puedes revisar la estructura mientras se define el destino.

PR obligatorio para todas las cuentas: publica en una rama de trabajo y crea o actualiza un pull request hacia el destino elegido, incluso si se ejecuta como devtuval, josemg23 o TuvalSystems. Sus excepciones en GitHub no autorizan al agente a subir directamente ni a omitir revisiones o verificaciones. No uses bypass, privilegios de administrador, cambios de protección, actualizaciones de referencias ni escrituras mediante API para eludir este flujo. Si el usuario solicita expresamente trabajo solo local o indica que publicará por su cuenta, deja los cambios locales y no hagas push ni crees PR; esta instrucción no autoriza un push directo del agente.

Restricción temporal: solo se permiten qa y develop como ramas de destino. No crees ni redirijas PR hacia main, no prepares ramas basadas en main, no modifiques su protección ni integres cambios en main. Esta restricción se mantiene hasta que el usuario autorice expresamente retomar su actualización; no la levantes por iniciativa propia.

1. Antes de editar código, revisa el estado de Git y actualiza con fetch la referencia del destino. Comprueba que origin/qa u origin/develop exista según lo solicitado; no crees la rama de destino ni la sustituyas por otra si falta. Crea una rama codex/<descripcion-breve> desde origin/<destino> actualizado. No trabajes ni hagas commits o push directamente en qa, develop o main.
2. Conserva los cambios locales y el trabajo ajeno. Si el checkout tiene cambios de otra tarea, utiliza un worktree aislado cuando sea necesario; no los descartes, mezcles ni incluyas en el PR.
3. Implementa y valida en la rama de trabajo. Antes de publicar, vuelve a consultar origin/<destino> e incorpora sus cambios si la rama quedó atrasada. Sigue la estrategia del repositorio; si no existe una, integra origin/<destino> mediante merge en la rama de trabajo para evitar reescribir historial publicado. Resuelve conflictos y repite las verificaciones afectadas. No mezcles qa y develop ni promociones cambios entre ellas sin una solicitud explícita.
4. Haz commits únicamente con los cambios de la tarea, publica la rama de trabajo y crea un PR con base exactamente igual al destino elegido. Antes de crearlo o actualizarlo, comprueba base, diff y commits para excluir cambios ajenos. Si ya existe un PR para esa misma tarea y destino, actualízalo; no lo redirijas a la otra rama por iniciativa propia. Incluye problema, comportamiento resultante, validación y limitaciones. Entrega el enlace real del PR.
5. El PR debe mantenerse actualizado respecto de su rama de destino y tener los controles requeridos aprobados antes de fusionarse. Si esa rama avanza, actualiza la misma rama de trabajo y PR y repite las verificaciones afectadas. No fusiones por iniciativa propia, no actives auto-merge y no despliegues sin solicitud expresa.
6. Si no hay acceso para publicar o crear el PR, conserva el trabajo y comunica el bloqueo exacto; nunca lo sustituyas por un push directo a qa o develop. No afirmes que el PR existe si no se creó.

La exigencia anterior es parte del flujo del agente. Esta plantilla no configura protecciones del repositorio: no afirmes que GitHub bloquea ramas desactualizadas o pushes directos sin verificar sus reglas. No modifiques esas reglas como parte de una tarea de pantalla.

### Mejora de esta plantilla

Mantén este mismo archivo como plantilla reutilizable. Cuando el usuario indique una corrección que deba aplicarse a futuras tareas, incorpórala aquí; actualiza la otra plantilla solo si también le corresponde. No generes versiones numeradas ni copias. No conviertas una regla particular de una pantalla en una regla global. Si detectas una posible mejora sin una preferencia confirmada, propónla brevemente antes de incorporarla como requisito permanente.

<a id="frontend-modificar"></a>

## TUVAL Frontend modificar funcionalidad

Repositorio: `F:/www/TuvalFront`. Plantilla original: [docs/prompts/modificar-funcionalidad.md](F:/www/TuvalFront/docs/prompts/modificar-funcionalidad.md).

## Modificar una funcionalidad de TUVAL

### Cómo usar esta plantilla

Envía al agente este mensaje, completando tu necesidad:

> Usa docs/prompts/modificar-funcionalidad.md para modificar [nombre de la pantalla o función que uso, o enlace]. Actualmente [comportamiento actual] y necesito [resultado esperado]. API relacionada, si la conozco: [endpoint].

No necesitas copiar toda la plantilla ni conocer los archivos internos. Investiga primero el proyecto y pregunta solo por decisiones de negocio o información que no puedas obtener allí.

Identifica la implementación existente a partir del nombre visible, enlace o descripción del usuario. No le pidas el módulo técnico ni la carpeta. Si varias pantallas coinciden, pregunta qué opción del menú utiliza o en qué paso observa el problema. Conserva la ubicación del flujo existente salvo que el cambio solicitado requiera expresamente modificarla.

### Ejemplo para copiar y adaptar

Este ejemplo es una guía de solicitud, no una tarea pendiente que el agente deba ejecutar al leer este archivo. Sustituye la necesidad por la tuya y pega el mensaje en el chat del agente con el proyecto TUVAL abierto.

```text
Usa docs/prompts/modificar-funcionalidad.md.

En la pantalla de cotizaciones necesito agregar un filtro por vendedor.
Actualmente puedo consultar las cotizaciones, pero quiero seleccionar
un vendedor y ver únicamente las que le corresponden.

El filtro debe combinarse con los filtros existentes y tener la opción
“Todos los vendedores”. Al cambiarlo, la tabla debe volver a la primera
página. Al cambiar de punto de venta, debe limpiar la selección anterior
y cargar los vendedores correspondientes.

Conserva las columnas, acciones, permisos y diseño actuales.
Revisa si el API ya admite este filtro. Reutiliza el selector, la tabla
y los métodos existentes; no dupliques lógica ni crees archivos innecesarios.

Verifica el filtro individual, su combinación con otros filtros y el
cambio de punto de venta.

Destino del PR: [qa o develop; elige uno].
Crea una rama desde el destino actualizado y entrega el PR hacia esa misma rama.
Aunque uses devtuval, josemg23 o TuvalSystems, no subas directamente
ni uses la excepción. No fusiones, no despliegues y no toques main.
```

### Dónde consultar esta guía

En GitHub, selecciona la rama de destino (qa o develop) y abre docs/prompts/modificar-funcionalidad.md. GitHub muestra el Markdown con formato y permite copiar el ejemplo. En el proyecto local, abre este mismo archivo en tu editor. Los cambios nuevos estarán disponibles en la rama de destino después de aprobar y fusionar su PR; antes puedes consultarlos en la rama del PR. Esta guía no aparece automáticamente dentro de la aplicación TUVAL.

### Instrucciones para el agente

Implementa el cambio solicitado siguiendo estas reglas:

1. Lee las instrucciones aplicables del repositorio. Revisa el flujo actual completo: pantalla, componentes, composables, métodos, estado, rutas, permisos y API. Identifica la causa y los consumidores afectados antes de editar.
2. Busca implementaciones equivalentes y recursos reutilizables. Usa los componentes, métodos, validaciones, utilidades y clientes HTTP existentes. No dupliques funcionalidad ni crees alternativas para algo que el proyecto ya resuelve.
3. Realiza el cambio mínimo necesario. Conserva arquitectura, convenciones, nombres, contratos y estilo de código del módulo. No introduzcas reingeniería, nuevas capas, dependencias, patrones o refactorizaciones ajenas al objetivo. Si un cambio estructural resulta imprescindible, explica el motivo concreto y limita su alcance.
4. Edita los archivos existentes cuando corresponda. Crea un archivo solo si la funcionalidad lo requiere, no hay uno adecuado y la separación sigue el patrón actual. No generes informes, ejemplos, archivos auxiliares, copias, nuevas plantillas ni pruebas que solo repitan la implementación. No crees documentación por defecto.
5. Conserva estructura visual, diseño, fuente y tema de TUVAL. Reutiliza componentes y colores del tema; evita colores estáticos. Para listados nuevos o cambios que requieran una tabla, usa src/components/BaseTable.vue y consulta src/views/cobranzas/cobros-facturas/CobrosFacturasIndex.vue. No migres tablas ajenas al alcance. Sigue la separación existente de consultas, acciones y estado en composables sin reorganizar todo el módulo.
6. Si depende del API, revisa el contrato y una respuesta real mediante acceso autorizado y operaciones de lectura. Verifica parámetros, campos, tipos, nulos, errores, filtros y paginación; no inventes formatos. Reutiliza autenticación y constantes API. No leas ni expongas credenciales innecesariamente. Si el acceso falta, informa el bloqueo sin presentar datos simulados como verificación real. Pruebas usa http://tuvalweb.cosenco.ec; identifica la URL del API en la configuración, no la supongas a partir de la URL del frontend.
7. Respeta permisos y reglas de negocio. Gestiona carga, errores y ausencia de resultados según los patrones existentes. Cuando dependa del punto de venta, utiliza el mecanismo actual para limpiar el contexto anterior, evitar respuestas atrasadas y actualizar sin recargar la página.
8. Ejecuta las verificaciones proporcionales al cambio y los controles requeridos del proyecto. Reutiliza pruebas existentes; añade pruebas solo si cubren un riesgo o comportamiento relevante. Verifica los flujos afectados con una sesión real cuando esté disponible. No registres operaciones de negocio ni alteres datos reales solo para probar sin autorización específica.
9. Revisa el diff: elimina duplicaciones y archivos creados por tu tarea que no sean necesarios. No elimines ni reviertas trabajo ajeno. Informa qué cambió, qué verificaste y qué quedó pendiente. No despliegues salvo solicitud expresa.

### Estructura y legibilidad obligatorias

Antes de implementar, identifica una pantalla equivalente del mismo módulo y comunica brevemente qué archivos y recursos reutilizarás. No generes un documento adicional ni detengas el trabajo por esta revisión.

| Responsabilidad | Ubicación o recurso existente |
| --- | --- |
| Pantalla y coordinación de la interfaz | src/views/<modulo>/ y sus componentes locales |
| Consultas, acciones y estado reactivo del flujo | src/composables/<modulo>/ siguiendo los nombres reales del módulo |
| Endpoints | src/constants/apiRoutes/<modulo>/ |
| Peticiones autenticadas | src/axios.js; revisa useApiAction en src/composables/core antes de repetir su manejo de carga y errores |
| Tablas, modales y búsqueda | BaseTable.vue, BaseModal.vue, BaseSearchSelect.vue y componentes del módulo; comprueba sus props, eventos y slots antes de usarlos |
| Rutas y acceso | src/router/routes/<modulo>.js y los guards existentes |
| Alertas y formatos | src/functions/swal-alert.js y las funciones/utilidades existentes |
| Estado compartido | El store que ya use el flujo; conviven src/store y src/stores, no migres entre Vuex y Pinia por esta tarea |

Estas rutas orientan la búsqueda; no son una orden de crear un archivo o carpeta por cada responsabilidad. Conserva nombres reales como venta, ventas o cobranzas según el recurso existente. No reorganices carpetas para uniformarlos.

- Escribe funciones con una responsabilidad clara y nombres que expresen la intención. Evita abreviaturas ambiguas, bloques comprimidos en una línea, ternarios anidados y condiciones difíciles de seguir. Usa variables intermedias y retornos tempranos cuando aclaren el flujo.
- Mantén el idioma y las convenciones de nombres del módulo. No renombres APIs públicas ni archivos existentes para imponer otro estilo.
- Deja en el template la presentación y enlaces a acciones; coloca las transformaciones o reglas complejas en funciones o computed con nombres claros. Evita peticiones HTTP y reglas de negocio extensas dentro de handlers del template.
- No conviertas la vista en un archivo que concentre API, validaciones, tablas y lógica de negocio. Usa los composables y componentes adecuados sin fragmentar operaciones sencillas en archivos innecesarios.
- No copies un bloque existente para cambiar solo un endpoint, etiqueta o columna cuando un recurso actual ya admite configurarlo. Antes de ampliar un componente compartido, revisa sus consumidores y conserva compatibilidad.
- Reutiliza el manejo de carga, errores y concurrencia cuando corresponda. No añadas catch vacíos, errores silenciados, valores por defecto que oculten respuestas inválidas ni logs de depuración en la entrega.
- Mantén el formato del código circundante y las reglas ESLint del proyecto. No desactives reglas, añadas exclusiones ni rebajes verificaciones para hacer pasar el cambio. Distingue errores anteriores de los introducidos y comunica los bloqueos sin ampliar el alcance.
- Comenta decisiones o restricciones que no sean evidentes; evita comentarios que repiten literalmente el código, código comentado y tareas pendientes sin resolver.

Antes de entregar, revisa el diff y confirma en la respuesta qué recursos reutilizaste, por qué fue necesario cada archivo nuevo y qué verificaciones ejecutaste. Ejecuta npm run lint y npm run build para cambios de aplicación, además de las comprobaciones específicas pertinentes; para cambios solo de documentación revisa contenido y diff. La aprobación humana del PR sigue siendo necesaria: una plantilla orienta el trabajo, pero no garantiza por sí sola la calidad del código.

### Ramas y pull requests obligatorios

Este flujo forma parte de la tarea al invocar esta plantilla:

Destino permitido: qa o develop. El usuario puede indicar «Destino del PR: qa» o «Destino del PR: develop». Usa el destino explícito de la solicitud o del contexto vigente; no lo deduzcas únicamente de la rama local, del módulo o de la URL del entorno de pruebas. Si no está definido, pregunta «¿El cambio debe ir a qa o a develop?» antes de crear la rama o implementar cambios dependientes de su versión. Puedes revisar la estructura mientras se define el destino.

PR obligatorio para todas las cuentas: publica en una rama de trabajo y crea o actualiza un pull request hacia el destino elegido, incluso si se ejecuta como devtuval, josemg23 o TuvalSystems. Sus excepciones en GitHub no autorizan al agente a subir directamente ni a omitir revisiones o verificaciones. No uses bypass, privilegios de administrador, cambios de protección, actualizaciones de referencias ni escrituras mediante API para eludir este flujo. Si el usuario solicita expresamente trabajo solo local o indica que publicará por su cuenta, deja los cambios locales y no hagas push ni crees PR; esta instrucción no autoriza un push directo del agente.

Restricción temporal: solo se permiten qa y develop como ramas de destino. No crees ni redirijas PR hacia main, no prepares ramas basadas en main, no modifiques su protección ni integres cambios en main. Esta restricción se mantiene hasta que el usuario autorice expresamente retomar su actualización; no la levantes por iniciativa propia.

1. Antes de editar código, revisa el estado de Git y actualiza con fetch la referencia del destino. Comprueba que origin/qa u origin/develop exista según lo solicitado; no crees la rama de destino ni la sustituyas por otra si falta. Crea una rama codex/<descripcion-breve> desde origin/<destino> actualizado. No trabajes ni hagas commits o push directamente en qa, develop o main.
2. Conserva los cambios locales y el trabajo ajeno. Si el checkout tiene cambios de otra tarea, utiliza un worktree aislado cuando sea necesario; no los descartes, mezcles ni incluyas en el PR.
3. Implementa y valida en la rama de trabajo. Antes de publicar, vuelve a consultar origin/<destino> e incorpora sus cambios si la rama quedó atrasada. Sigue la estrategia del repositorio; si no existe una, integra origin/<destino> mediante merge en la rama de trabajo para evitar reescribir historial publicado. Resuelve conflictos y repite las verificaciones afectadas. No mezcles qa y develop ni promociones cambios entre ellas sin una solicitud explícita.
4. Haz commits únicamente con los cambios de la tarea, publica la rama de trabajo y crea un PR con base exactamente igual al destino elegido. Antes de crearlo o actualizarlo, comprueba base, diff y commits para excluir cambios ajenos. Si ya existe un PR para esa misma tarea y destino, actualízalo; no lo redirijas a la otra rama por iniciativa propia. Incluye problema, comportamiento resultante, validación y limitaciones. Entrega el enlace real del PR.
5. El PR debe mantenerse actualizado respecto de su rama de destino y tener los controles requeridos aprobados antes de fusionarse. Si esa rama avanza, actualiza la misma rama de trabajo y PR y repite las verificaciones afectadas. No fusiones por iniciativa propia, no actives auto-merge y no despliegues sin solicitud expresa.
6. Si no hay acceso para publicar o crear el PR, conserva el trabajo y comunica el bloqueo exacto; nunca lo sustituyas por un push directo a qa o develop. No afirmes que el PR existe si no se creó.

La exigencia anterior es parte del flujo del agente. Esta plantilla no configura protecciones del repositorio: no afirmes que GitHub bloquea ramas desactualizadas o pushes directos sin verificar sus reglas. No modifiques esas reglas como parte de una tarea de pantalla.

### Mejora de esta plantilla

Mantén este mismo archivo como plantilla reutilizable. Cuando el usuario indique una corrección que deba aplicarse a futuras tareas, incorpórala aquí; actualiza la otra plantilla solo si también le corresponde. No generes versiones numeradas ni copias. No conviertas una regla particular de una pantalla en una regla global. Si detectas una posible mejora sin una preferencia confirmada, propónla brevemente antes de incorporarla como requisito permanente.

<a id="apigp"></a>

## API GP crear o ampliar reportes

Repositorio: `F:/www/api-gp`. Plantilla original: [docs/prompt-apis-reportes.md](F:/www/api-gp/docs/prompt-apis-reportes.md).

## Prompt para crear o ampliar APIs de consulta

api-gp es un microservicio que Laravel consume: **Laravel → api-gp → SQL Server**.
Laravel administra cotizaciones, borradores y sus procesos de negocio. Estas APIs
consultan y devuelven información; no llaman a Laravel ni crean borradores.

### Estructura y reutilización obligatorias

El proyecto usa Java 21, Spring Boot 3.4.4 y Maven. Revisar primero el código actual
y [estructura-modular.md](F:/www/api-gp/docs/estructura-modular.md). Organizar los reportes por área
de negocio y funcionalidad, no por nombre de vista, SP, endpoint o prompt generado.

Ejemplo vigente:

```text
reportes/inventario/articulos/
├── controller/ArticulosController.java
├── service/dynamics/ArticulosService.java
├── service/dynamics/imp/ArticulosServiceImp.java
├── dao/dynamics/ArticulosDao.java
├── dto/
└── request/

reportes/inventario/varianza/
├── controller/VarianzaController.java
├── service/dynamics/VarianzaService.java
├── service/dynamics/imp/VarianzaServiceImp.java
├── dao/dynamics/VarianzaDao.java
├── dto/
└── request/
```

- Buscar la funcionalidad existente antes de crear carpetas o clases.
- Dentro del mismo módulo y conexión, agregar métodos al controller, interfaz de
  service, implementación y DAO existentes. **No crear uno de cada uno por API**.
- Artículos comparte `ArticulosController`, `ArticulosService`,
  `ArticulosServiceImp` y `ArticulosDao` para maestro, listado y resolución.
- Crear DTO/request específicos cuando sus datos sean distintos; no forzar todas
  las respuestas o entradas en una sola clase ni añadir campos sin relación.
- Una nueva funcionalidad justificada puede necesitar sus propias capas. El agente
  decide el área y la funcionalidad mediante el análisis descrito abajo; no delega
  al usuario la elección de carpetas.
- No crear capas vacías, clientes HTTP ni infraestructura para una consulta SQL.
  Las reglas de interpretación pueden separarse en un helper dentro del módulo.
- No mover ni refactorizar módulos ajenos para implementar un endpoint.

Las clases se nombran por su función: `ArticulosDao`, `VarianzaService`, etc.
Evitar nombres técnicos como `Inv60Reporte...` y clases por cada endpoint.

#### Clasificación automática por función de negocio

Antes de escribir, el agente analiza la definición SQL, tablas/vistas/SP usados,
joins, filtros, agregaciones, entradas y campos de salida. Deduce qué información
ofrece o qué operación realiza y la compara con las funcionalidades del proyecto.
El nombre técnico del objeto es solo una pista, no determina el módulo.

1. Identificar el área: inventario, ventas, cobranzas, clientes u otra sustentada
   por la consulta y las convenciones existentes.
2. Identificar la funcionalidad y buscar su controller, service y DAO actuales.
   Reutilizarlos aunque el endpoint o el SP tengan otro nombre.
3. Ubicar la consulta en `reportes/<area>/<funcionalidad>` y añadir un método con
   nombre de negocio. Crear capas únicamente si la funcionalidad aún no existe.
4. Explicar brevemente en la entrega la ubicación elegida y qué datos del SQL
   respaldan esa clasificación. No pedir al usuario que decida la carpeta.

Ejemplos: `spInv60`, que consulta varianzas, pertenece a
`reportes/inventario/varianza`; el maestro, listado y resolución de artículos
pertenecen a `reportes/inventario/articulos`; `spVen11`, que compara ventas y
costos por sucursal, pertenece a `reportes/ventas/sucursales`.
Este último usa `SucursalesController`, `SucursalesService`, `SucursalesServiceImp`
y `SucursalesDao`, con `GET /api/reportes/ventas/sucursales/comparativo`.

Si la clasificación tiene varias opciones razonables, elegir la más coherente
con el proyecto y explicar la decisión. Preguntar solo por información funcional
indispensable que no esté en las fuentes y cuya ausencia impida definir el
comportamiento o la conexión; una preferencia de carpeta no es ese bloqueo.
Esta decisión la realiza el agente durante la generación, sin añadir IA externa,
API keys ni dependencias de IA a la aplicación.

### Prompt reutilizable

Copiar el siguiente bloque y completar únicamente los datos de la consulta:

```text
Crea o amplía una API de consulta en git@github.com:TuvalSystems/api-gp.git
siguiendo docs/prompt-apis-reportes.md y las instrucciones del repositorio.

Contexto de negocio opcional: [si se conoce; el agente deduce área y funcionalidad]
Conexión: [tuval | dynamics]
Entradas: [filtros/parámetros, o ninguna]
Salidas: [campos esperados, o según definición SQL]
Definición SQL de la tabla, vista o SP:
[pegar aquí]

Implementa y valida. No crees controller, service ni DAO por endpoint: reutiliza
las capas de la funcionalidad. Decide su ubicación analizando lo que consulta o
hace el SQL y los módulos existentes; no pidas al usuario elegir la carpeta.
Los DTO de respuesta deben seguir la convención
camelCase o PascalCase existente de forma consistente; en artículos, camelCase.
No implementes creación de borradores ni llamadas HTTP desde api-gp a Laravel.

Conserva las pruebas existentes. Registra y ejecuta las pruebas temporales que
crees; retira únicamente esas pruebas después de validar y antes del commit.
Actualiza la documentación del módulo y los inventarios existentes que apliquen.
Abre un PR hacia develop y solicita revisión según .github/CODEOWNERS.
No hagas merge ni despliegues.
```

Esta plantilla autoriza la entrega por PR cuando el usuario la invoca para una
implementación. Leer o editar este documento por sí solo no autoriza commits,
push, PR, merge ni despliegues de los cambios actuales.

### 1. Revisar antes de implementar

Leer los `AGENTS.md` aplicables, `pom.xml`, configuración de las dos bases,
`ResponseJsonUtils`, y controller/service/DAO comparables. `CollectorController`
es un ejemplo de múltiples operaciones en un mismo controller.

Revisar Git y preservar cambios ajenos. Inventariar los tests y recursos de prueba
existentes, incluidos los no seguidos. No asumir que existen `src/test` o los
inventarios de arquitectura solo porque una documentación histórica los menciona.

Derivar tipos y parámetros de la definición proporcionada. No inventar tablas,
columnas, joins, claves, valores por defecto ni firmas de procedimientos.
Verificar que el SP sea de lectura antes de ejecutarlo. Si falta una definición
o contrato esencial, preguntar únicamente lo que falta y avanzar con lo demás.
No mostrar credenciales ni modificar perfiles privados para implementar reportes.

### 2. Conexión y persistencia

| Conexión | JDBC | JDBC con parámetros nombrados | Gestor transaccional |
|---|---|---|---|
| Dynamics | `dynamicsJdbcTemplate` | `dynamicsNamedParameterJdbcTemplate` | `dynamicsTransactionManager` |
| Tuval | `tuvalJdbcTemplate` | `tuvalNamedParameterJdbcTemplate` | `tuvalTransactionManager` |

Usar inyección por constructor y `@Qualifier` explícito. No depender de `@Primary`
ni agregar DataSource, credenciales o propiedades de conexión. La base y los
objetos SQL se fijan en código, nunca se eligen desde la petición HTTP.

Cada consulta usa una conexión. Separar `dao/dynamics` y `dao/tuval` si una
funcionalidad necesita ambas; no suponer transacciones distribuidas ni joins entre
servidores. No copiar catálogos hardcodeados sin verificar su necesidad.

Preferir JDBC y DTO. Consultar una vista o un SP no requiere JPA: no agregar
`@Entity`, `@Id` ficticios ni tocar escaneo JPA sin necesidad. Si se justifica JPA,
registrar exclusivamente los paquetes de su base y actualizar los inventarios y
pruebas existentes. `@Transactional`, si se necesita, declara el gestor correcto y
`readOnly = true`; esta anotación no demuestra que el SQL no escriba.

### 3. DAO y SQL

El DAO contiene el SQL; el service valida y aplica reglas; el controller recibe
la petición y construye la respuesta. Reutilizar métodos y mappers del módulo.

- Vista/tabla: consulta parametrizada con `JdbcTemplate` o
  `NamedParameterJdbcTemplate`, columnas explícitas y mapper por tipo de resultado.
- SP: seguir el patrón existente de `SimpleJdbcCall`, configurado en el constructor,
  con esquema, procedimiento, firma explícita, metadatos de parámetros desactivados
  y `returningResultSet` cuando corresponda. Declarar y validar IN, OUT y retorno.
- En SQL Server, si se usa `withNamedBinding()`, declarar los nombres SQL con `@`
  y usar las mismas claves en `MapSqlParameterSource`. No cambiar por ello los
  nombres HTTP. Si se usa enlace posicional, respetar el orden de la firma.
- Nunca concatenar valores recibidos. Para orden configurable, usar una lista
  cerrada de columnas/direcciones; no admitir SQL ni identificadores libres.
- Cantidades e importes: `BigDecimal`. Fechas: `java.time` según SQL.
  Preservar nulos, precisión, ceros y negativos; no normalizar valores sin contrato.
- No cambiar la semántica de esquemas de unidades ni atributos crudos de las vistas:
  no asumir que equivalen a unidad de venta, marca o material comerciales.

Para tablas/vistas nuevas, si no hay contrato de paginación, usar página 0 y tamaño
100, máximo 500, con orden estable justificado. Reutilizar límites existentes al
ampliar una API. Paginar en SQL; no descargar todo para recortar en Java.
Si falta una clave única, no inventarla: justificar un orden por valores y sus
límites. Los SP conservan sus parámetros y límites reales; no añadir paginación
ficticia. Si su resultado no tiene límite, resolver el alcance antes de publicar.

El timeout y los límites son locales a la consulta; no mutar los templates
compartidos. Para consultas nuevas, timeout de 30 segundos salvo requisito distinto;
las existentes conservan sus límites específicos. `fetchSize` no es paginación.

### 4. HTTP y DTO

Las rutas comienzan con `/api/reportes/<area>/<funcionalidad>`. Agregar métodos
bajo el `@RequestMapping` existente del módulo y conservar los contratos vigentes
salvo cambio expresamente solicitado. No crear alias o rutas paralelas sin motivo.

Ejemplos actuales, todos en `ArticulosController`:

```http
GET /api/reportes/inventario/articulos
GET /api/reportes/inventario/articulos/listado-items
POST /api/reportes/inventario/articulos/resolver
```

Usar GET con query para filtros simples. Para lotes/filtros complejos, POST con
JSON y justificarlo. No usar body en GET. Validar con Jakarta Validation y `@Valid`
antes de acceder a la base; mantener validación de reglas en el service.

Los DTO de respuesta siguen camelCase o PascalCase de forma consistente con el
módulo; en artículos se usa camelCase, por ejemplo `txItm`, `descripcion`,
`txAtrMar`, `txAtrMat`. No copiar snake_case de SQL ni forzar nombres SQL con
`JsonProperty`. El DAO traduce entre las columnas reales y los campos del DTO.
No renombrar parámetros HTTP ni columnas SQL como efecto secundario de este ajuste.

Reutilizar `ResponseEntity<Map<String,Object>>` y `ResponseJsonUtils`:

- Éxito 200: `success`, `message`, `data`.
- Sin coincidencias 200: `data: []` según el contrato de la consulta.
- Validación 400: `success: false`, mensaje público; `buildNoFound` devuelve 400.
- Error interno 500: `buildError` con mensaje genérico y excepción registrada en
  el servidor. No devolver `e.getMessage()`, SQL ni detalles de conexión al cliente.

En controllers compartidos, conservar el mensaje de validación que corresponda a
cada request. El manejo local no debe alterar los errores de otros endpoints.
Respetar el acceso existente: no inventar autenticación ni ampliar permisos.

### 5. Responsabilidad de Laravel

La resolución solo interpreta renglones y devuelve identificaciones, candidatos,
ambigüedades y datos faltantes. Laravel decide las selecciones y crea el borrador
con su propio proceso. api-gp no conoce ni replica ese proceso.

No crear endpoints `crear-borrador`, carpetas `client` o `service/tuvalbackend`,
clientes HTTP, reintentos, registros de idempotencia o dependencias para llamar a
Laravel. Los antiguos puentes de borradores fueron retirados. No regenerarlos a
partir de documentación histórica ni reutilizar sus excepciones antiguas.

### 6. Validación y entrega

Probar lo que cambie: registro de rutas sin colisiones, un controller/service/DAO
por funcionalidad y base, selección de conexión, parámetros/tipos SQL, nulos,
precisión, mappers, límites y paginación, respuestas 200/400/500 y validación sin
llamada al DAO. Para SP, verificar firma, enlace SQL Server, retorno y result sets.
Las simulaciones no prueban permisos, datos ni funcionamiento real de SQL Server.

Si se crean pruebas temporales, mantener una lista explícita y retirarlas solo
tras corregir y validar todos sus fallos. No borrar tests ajenos, preexistentes o
sin seguimiento, ni carpetas completas de pruebas. Reutilizar la infraestructura
existente; no agregar dependencias permanentes solo para comprobaciones temporales.

Si existen inventarios exactos de componentes/JPA, actualizarlos; no generar una
estructura de tests ausente solo por copiar un registro histórico. Ejecutar
`mvn clean verify` (o `mvnw.cmd clean verify` si corresponde), limpiar clases viejas
al mover o eliminar componentes y comprobar el JAR final. Declarar explícitamente
si Maven no encontró tests. Ejecutar `git diff --check` y revisar todo el diff.

Documentar ubicación, ruta, entradas, JSON, conexión, fuente SQL, límites, cambios
para consumidores y validaciones realmente ejecutadas. Distinguir comprobaciones
históricas de actuales y mocks de pruebas con base real. No afirmar despliegue ni
funcionamiento en otro servidor basándose en una prueba local.

Cuando la tarea incluya entrega por PR: crear rama `codex/reporte-<funcionalidad>`
desde `origin/develop` actualizado sin perder cambios locales; incluir solo su
alcance, commit, push y PR hacia `develop`. Usar `.github/CODEOWNERS` para solicitar
revisión a responsables con acceso de escritura distintos del autor. Si falta
acceso, informar el bloqueo; no inventar revisores, aprobación ni una URL de PR.
No hacer merge ni desplegar salvo autorización explícita posterior.

### Ejemplo de solicitud para API GP

Este ejemplo pide revisar una funcionalidad existente antes de ampliarla; no presupone que falte un endpoint.

```text
Usa docs/prompt-apis-reportes.md.

Necesito consultar el comparativo de ventas y costos por sucursal.
Referencia SQL: spVen11.
Revisa si el reporte ya existe y reutiliza la funcionalidad de sucursales.
Determina la conexión configurada a partir del código y del procedimiento.
Verifica la firma real, parámetros, columnas y tipos antes de implementar.
No inventes filtros ni cambies cálculos o reglas del procedimiento.
Si ya cubre la necesidad, indícame cómo consumirlo y no dupliques la API.
Si falta soporte, realiza solo la ampliación necesaria según la plantilla.

Prueba únicamente con lecturas en el entorno de pruebas.
Conserva el formato de respuesta y las convenciones de los DTO existentes.
No agregues llamadas a Laravel ni generes documentos comerciales.
Para cambios necesarios, crea PR a develop y solicita revisión según CODEOWNERS.
No fusiones ni despliegues.
```

<a id="movil-crear"></a>

## App Móvil crear funcionalidad

Repositorio: `F:/www/Apps/appmovil`. Plantilla original: [docs/prompts/crear-funcionalidad.md](F:/www/Apps/appmovil/docs/prompts/crear-funcionalidad.md).

## Crear una funcionalidad en TUVAL App Móvil

### Mensaje para copiar

```text
Usa docs/prompts/crear-funcionalidad.md.
Necesito [pantalla o funcionalidad] para [personas y objetivo].
Debe mostrar [información], permitir [acciones] y respetar [reglas].
Pantalla relacionada o API, si lo conozco: [referencia opcional].
Identifica el módulo responsable e integra allí el cambio reutilizando lo existente.
No generes archivos, funciones ni abstracciones innecesarias.
Respeta estructura, diseño y convenciones actuales de Flutter.
Solo pruebas: PR hacia develop, aprobado por TuvalSystems, devtuval
o josemg23. No fusiones ni despliegues.
```

El ejemplo es para completar, no una tarea a ejecutar al leerlo.

### Ejemplo completo: consulta de existencias

Copia este mensaje completo en el chat del agente con appmovil abierto y develop actualizado. Es una solicitud ilustrativa; el agente debe comprobar qué existe antes de implementar.

```text
Usa docs/prompts/crear-funcionalidad.md y aplica todas sus instrucciones.

Necesito consultar existencias de artículos desde la app móvil.
Lo utilizarán los vendedores para revisar la disponibilidad por bodega
antes de ofrecer un producto al cliente.

Debe permitir:
- Buscar artículos por código o descripción.
- Seleccionar un artículo y consultar sus existencias por bodega.
- Consultar usando el punto de venta activo.
- Volver a buscar otro artículo sin salir de la pantalla.

Muestra únicamente información que realmente entregue el API.
La consulta será de solo lectura: no debe generar reservas, ajustes
ni movimientos de inventario. Respeta los permisos del usuario.

No conozco el endpoint. Investiga si ya existe en el proyecto o en el
backend disponible. No inventes endpoints, campos ni permisos.
Si falta soporte del backend, informa qué se necesita y no presentes
datos simulados como una integración terminada.

Antes de implementar, identifica el módulo y submódulo responsables.
Por su finalidad parece corresponder a Inventario, pero confírmalo
revisando el menú, las rutas y la organización actual.
Comprueba si puedes ampliar una pantalla existente antes de crear otra.
Comunica dónde lo integrarás y qué recursos reutilizarás.

Respeta la estructura Flutter de appmovil:
- Pantallas y widgets para presentación.
- Providers para estado y acciones.
- Datasources para llamadas al API.
- Modelos para conversión de datos.
- Configuración existente para endpoints, rutas, tema y autenticación.

Reutiliza buscadores, widgets, funciones, métodos y servicios existentes.
No generes archivos, funciones, clases, carpetas ni capas innecesarias.
Crea piezas nuevas solo si tienen una responsabilidad concreta y
justifica los archivos y abstracciones nuevos.
No reorganices otros módulos ni introduzcas otro gestor de estado.

Conserva el diseño, fuente, espaciado y tema de la aplicación.
Incluye estados de carga, resultados vacíos, error y reintento.
Al cambiar de punto de venta, limpia los resultados anteriores
y evita que una respuesta atrasada muestre información del contexto previo.

Verifica:
- Búsqueda por código y descripción.
- Selección del artículo y consulta por bodega.
- Búsqueda sin resultados.
- Error del API y reintento.
- Cambio de punto de venta.
- Salida de la pantalla mientras existe una consulta pendiente.

Ejecuta las comprobaciones indicadas en la plantilla y revisa el diff.
No realices operaciones comerciales para probar.

Crea una rama propia desde origin/develop actualizado.
Publica esa rama y crea un PR hacia develop, únicamente para pruebas.
Solicita aprobación a TuvalSystems, devtuval o josemg23,
distinto del autor del PR. Solo su aprobación cuenta como autorización.

No hagas push directo a develop, no uses bypass, no fusiones,
no actives auto-merge, no despliegues y no toques main ni producción.

Entrega el enlace del PR, el módulo elegido, los recursos reutilizados,
la justificación de archivos nuevos, las verificaciones y los pendientes.
```

### Alcance

Estas instrucciones aplican a colaboradores que invoquen esta plantilla. No son una política global ni imponen PR al trabajo directo del propietario fuera de este flujo. El propietario puede publicar por su cuenta. Leer el archivo como referencia no equivale a invocarlo.

Solo pruebas: rama `develop`, ambiente `qa`. No trabajar sobre `main` ni producción. No modificar protecciones, permisos, CODEOWNERS o workflows para imponer estas reglas. Este prompt define un procedimiento; no configura restricciones técnicas en GitHub.

### Estructura e implementación

1. Lee las instrucciones aplicables y `docs/guia_desarrollador.md`; contrasta la guía con el código. Revisa una pantalla equivalente, rutas, menú y permisos. Identifica dónde corresponde la necesidad y si basta ampliar algo existente. No pidas carpetas técnicas al usuario; pregunta solo por decisiones de negocio que no puedas investigar.
2. Comunica brevemente los archivos y recursos que reutilizarás. Conserva arquitectura, nombres, idioma, formato y patrones del módulo. No introduzcas gestores de estado, dependencias, capas ni refactorizaciones ajenas al objetivo.
3. Respeta esta distribución real; no crees un archivo por cada fila ni fuerces repositorios en flujos que no los usan:

   | Responsabilidad | Ubicación |
   | --- | --- |
   | Pantallas y widgets del módulo | `lib/presentation/screens/<modulo>/` |
   | Estado y acciones del módulo | Providers del módulo con `ChangeNotifier` y `provider` |
   | Sesión y punto de venta global | `lib/presentation/providers/` |
   | Widgets, controladores y helpers compartidos | `lib/presentation/shared/` |
   | Acceso al API | `lib/infrastructure/datasources/` |
   | Modelos y conversión JSON | `lib/infrastructure/models/` |
   | Servicios nativos y almacenamiento | `lib/infrastructure/services/` |
   | Entidades y contratos cuando el flujo los use | `lib/domain/` |
   | Endpoints, HTTP y errores | `lib/config/http/` |
   | Tema, navegación y ambiente | `lib/config/theme/`, `router/` y `environment/` |

4. Separa UI, estado, HTTP y conversión. No hagas peticiones en `build()` ni concentres todo en una pantalla. Reutiliza `DioClient`, `ApiHandler`, `ApiException`, constantes y modelos. Sigue la inyección de Dio y servicios de `lib/main.dart`; no crees clientes que omitan autenticación o expiración de sesión.
5. Conserva diseño, fuente, espaciado y tema. Reutiliza `AppTheme`, `TuvalAppBar`, `AppActionButton`, `AppToast`, `CurrentPointSaleBadge` y recursos compartidos de búsqueda cuando correspondan. Usa colores del tema, sin duplicar estilos ni añadir colores fijos.
6. Integra navegación mediante `AppRouter`, `MobileMenuMapper` y el menú real del backend. No inventes opciones ni amplíes permisos. Si falta soporte backend, informa el requisito sin presentar simulaciones como integración completa.
7. Revisa contratos reales: parámetros, identificadores, tipos, nulos, filtros, fechas, paginación y errores. Usa lecturas autorizadas en QA. No inventes endpoints o campos ni expongas credenciales o datos personales. Mantén las convenciones Dart y mapea las claves reales del JSON.
8. Respeta sesión, punto de venta, cliente y bodega. Limpia datos dependientes al cambiar contexto. Evita respuestas atrasadas, notificaciones tras `dispose` y uso de contextos desmontados después de operaciones asíncronas. Reutiliza mecanismos existentes.
9. Incluye carga, vacío, error y reintento. Preserva validaciones, biometría y permisos nativos afectados. No registres firmas, cotizaciones u otras operaciones de negocio solo para probar sin autorización específica, incluso en QA.
10. Usa funciones claras, nombres descriptivos y condiciones legibles. Revisa consumidores antes de cambiar recursos compartidos. No silencies errores, desactives lints ni dejes código comentado. Crea solo archivos necesarios, sin copias, informes, abstracciones hipotéticas o documentación adicional por defecto. No reviertas trabajo ajeno.

### Modularización obligatoria y alcance mínimo

Esta es una regla fundamental para crear y modificar funcionalidades, no una recomendación opcional.

1. Antes de implementar, identifica el módulo y submódulo responsables por el proceso de negocio, menú y código existente. Comunica dónde integrarás el cambio y qué archivos, funciones y métodos reutilizarás. No decidas la ubicación solo por una palabra de la solicitud o el nombre del endpoint.
2. Incorpora la funcionalidad en su módulo, respetando las capas actuales. Por ejemplo, guías de remisión corresponde a `inventory/delivery_note` y trampas de vapor a `equipment/steam_traps` en presentación; revisa sus recursos relacionados en infraestructura y configuración. Estos ejemplos orientan la búsqueda, no obligan a crear rutas nuevas.
3. Mantén widgets, providers y helpers exclusivos dentro del módulo según su patrón vigente. Usa los archivos de modelos, datasources y endpoints que ya correspondan a ese flujo. No coloques lógica específica en recursos globales solo por comodidad ni dupliques servicios entre módulos.
4. Reutiliza o amplía una función, método, clase o archivo existente cuando su responsabilidad sea adecuada. Crea uno nuevo únicamente cuando el comportamiento solicitado lo necesite y la separación tenga una responsabilidad concreta. Justifica cada archivo nuevo y cualquier nueva abstracción en la entrega.
5. No generes funciones, métodos, clases, wrappers, parámetros, interfaces, carpetas, archivos vacíos ni capas para usos futuros hipotéticos. Evita helpers de un solo paso que no aporten claridad, funciones que solo reenvían llamadas sin una responsabilidad propia y fragmentación innecesaria. Una función nueva sí puede justificarse por claridad, reglas de negocio, reutilización real o aislamiento de un comportamiento relevante; no concentres todo en un bloque gigante para evitar crear funciones.
6. Promueve código a compartido solo cuando exista reutilización real o una responsabilidad transversal demostrable. Revisa los consumidores y conserva compatibilidad; no diseñes un sistema genérico para resolver una única necesidad local.
7. Modulariza progresivamente el alcance solicitado. No migres todo el proyecto, reorganices otros módulos ni crees automáticamente un árbol de carpetas por cada funcionalidad. Si falta un módulo y el proceso realmente lo requiere, explica su ubicación y crea únicamente las piezas necesarias, siguiendo las capas existentes.
8. Antes de entregar, revisa el diff para retirar código sin uso, duplicaciones, funciones o archivos innecesarios introducidos por esta tarea. No elimines trabajo ajeno. Confirma el módulo elegido, los recursos reutilizados y la necesidad de las nuevas piezas.

### Verificación

- Formatea archivos Dart modificados con `dart format`. Para cambios de aplicación ejecuta `flutter analyze`, `flutter test` si existen pruebas y `flutter build apk --debug --flavor qa --dart-define=APP_ENV=qa`, además de los controles requeridos del proyecto. Distingue fallos previos de los introducidos y no rebajes verificaciones.
- Respeta SDK y dependencias de `pubspec.yaml`, `pubspec.lock` y CI. No actualices dependencias, versión, firma o configuración nativa salvo necesidad de la tarea. No ejecutes scripts de publicación para validar una compilación.
- En dispositivo usa `flutter run --flavor qa --dart-define=APP_ENV=qa`. Confirma el ambiente efectivo en `AppEnvironment`; no uses producción ni confíes en el valor por defecto.
- Reutiliza pruebas y añade solo las que cubran riesgos o comportamientos relevantes. Comprueba visualmente el flujo si hay dispositivo e informa si no lo hubo. Para documentación basta revisar contenido y diff.
- Revisa el diff final. Resume cambios, recursos reutilizados, motivos de archivos nuevos, verificaciones y limitaciones. No afirmes comprobaciones que no ejecutaste.

### PR y aprobación de colaboradores

1. Antes de implementar revisa Git, actualiza con `fetch` y comprueba `origin/develop`. Crea `codex/<descripcion-breve>` desde esa referencia actualizada. No inventes una rama `qa`: este proyecto publica QA desde `develop`. Si falta acceso o destino, informa el bloqueo sin sustituirlo.
2. Conserva cambios ajenos; usa un worktree aislado si hace falta. Incluye únicamente cambios de la tarea en commits y PR.
3. Publica la rama de trabajo y crea o actualiza un PR hacia `develop`. El colaborador y su agente no deben subir directamente al destino ni usar bypass, force-push o cambios de reglas, aunque la sesión tenga privilegios.
4. Solo cuenta como autorización una revisión aprobatoria humana en GitHub de `TuvalSystems`, `devtuval` o `josemg23`. Basta una de esas cuentas, distinta del autor. Comentarios, controles automáticos y aprobaciones de otras personas no sustituyen esa revisión. Solicita revisión a una cuenta permitida distinta del autor; si no puedes, informa el pendiente.
5. Antes de entregar para integración, actualiza la rama de trabajo con `origin/develop`, resuelve conflictos y repite las verificaciones afectadas. Conserva el historial publicado. Si cambia el código tras la aprobación, solicita nueva revisión de la versión final. No declares autorizado un PR con cambios solicitados pendientes.
6. Entrega el enlace real, base, verificaciones y estado de revisión. Deja el PR abierto para integración humana autorizada. El agente no aprueba, fusiona, activa auto-merge ni despliega. El workflow actual publica QA al entrar cambios en `develop`; abrir un PR no autoriza esa publicación.
7. Si solicitan trabajo solo local o indican que publicarán por su cuenta, entrega los cambios sin push ni PR e indica que la revisión del flujo de colaboradores queda pendiente. Esto no autoriza al agente del colaborador a hacer push directo. El propietario conserva su flujo habitual en su trabajo directo fuera de esta plantilla.
8. Si falta acceso, conserva el trabajo e informa el bloqueo. No reemplaces el PR por un push directo ni afirmes que GitHub impide otras aprobaciones: estas reglas pertenecen al prompt.

<a id="movil-modificar"></a>

## App Móvil modificar funcionalidad

Repositorio: `F:/www/Apps/appmovil`. Plantilla original: [docs/prompts/modificar-funcionalidad.md](F:/www/Apps/appmovil/docs/prompts/modificar-funcionalidad.md).

## Modificar o actualizar una funcionalidad en TUVAL App Móvil

### Mensaje para copiar

```text
Usa docs/prompts/modificar-funcionalidad.md.
En [pantalla o funcionalidad], actualmente [comportamiento actual].
Necesito [resultado esperado] y conservar [reglas o comportamientos].
Pasos para reproducir o API, si los conozco: [referencia opcional].
Revisa el flujo completo y corrige la causa con el menor cambio necesario.
Identifica el módulo responsable y reutiliza sus archivos y funciones.
No generes archivos, funciones ni abstracciones innecesarias.
Respeta estructura, diseño y convenciones actuales de Flutter.
Solo pruebas: PR hacia develop, aprobado por TuvalSystems, devtuval
o josemg23. No fusiones ni despliegues.
```

El ejemplo es para completar, no una tarea a ejecutar al leerlo.

### Ejemplo completo: limpiar el cliente en guías

Copia este mensaje completo en el chat del agente con appmovil abierto y develop actualizado. Es una solicitud ilustrativa; el agente debe comprobar qué existe antes de implementar.

```text
Usa docs/prompts/modificar-funcionalidad.md y aplica todas sus
instrucciones, incluidas las reglas compartidas que referencia.

En el listado de guías de remisión necesito poder quitar el cliente
seleccionado desde el mismo buscador para volver a consultar las guías
de todos los clientes que mis permisos permitan ver.

Primero comprueba si esta acción ya existe y funciona correctamente.
Si ya está resuelta, informa cómo se utiliza y no dupliques la solución.

Si falta, agrega una acción visible y coherente con el diseño actual
para limpiar el cliente seleccionado.

El comportamiento esperado es:
- Selecciono un cliente y consulto sus guías.
- Pulso la acción para quitar ese cliente.
- El buscador queda sin cliente seleccionado.
- Se conservan los demás filtros activos.
- La paginación vuelve al inicio.
- Se consulta nuevamente sin enviar el filtro de cliente.
- Los resultados anteriores se reemplazan, sin mezclarlos con los nuevos.

Si una consulta anterior termina después de limpiar el cliente,
su respuesta no debe sobrescribir los resultados actuales.
La acción debe respetar los permisos y el punto de venta activo.

No cambies las funciones de firma, lectura QR, adjuntos ni las reglas
de negocio de las guías.

Antes de implementar, revisa el flujo completo e identifica el módulo
responsable, la pantalla, el buscador, su controlador, el provider,
el datasource y los parámetros del API.

Revisa especialmente los recursos existentes de:
- lib/presentation/screens/inventory/delivery_note/
- lib/presentation/shared/widgets/customer_search_card.dart
- lib/presentation/shared/controllers/customer_search_controller.dart
- lib/infrastructure/datasources/delivery_notes_datasource.dart

Estas rutas orientan la revisión; comprueba la implementación actual.
Comunica la causa o el alcance del cambio y qué recursos reutilizarás.

Realiza el menor cambio que resuelva completamente la necesidad.
Respeta las responsabilidades de las capas Flutter y conserva
estructura, diseño, tema, navegación y convenciones de appmovil.

Reutiliza archivos, widgets, funciones y métodos existentes.
No generes archivos, funciones, clases, carpetas ni capas innecesarias.
No reconstruyas la pantalla ni reorganices otros módulos.
Si modificas el buscador o controlador compartido, revisa sus demás
consumidores y conserva su comportamiento.
Justifica cualquier archivo o abstracción nuevos.

Verifica:
- Seleccionar un cliente y consultar sus guías.
- Limpiar el cliente y volver a consultar sin ese filtro.
- Conservar los demás filtros.
- Reiniciar correctamente la paginación.
- Limpiar el cliente mientras hay una consulta pendiente.
- Resultados vacíos, errores y reintento.
- Que los demás consumidores del buscador sigan funcionando.

Ejecuta las comprobaciones de la plantilla y revisa el diff.
No registres firmas, adjuntos ni otras operaciones de negocio para probar.

Crea una rama propia desde origin/develop actualizado.
Publica esa rama y crea un PR hacia develop, únicamente para pruebas.
Solicita aprobación a TuvalSystems, devtuval o josemg23,
distinto del autor del PR. Solo su aprobación cuenta como autorización.

No hagas push directo a develop, no uses bypass, no fusiones,
no actives auto-merge, no despliegues y no toques main ni producción.

Entrega el enlace del PR, el comportamiento anterior y resultante,
los recursos reutilizados, las verificaciones y los pendientes.
```

### Reglas compartidas obligatorias

Lee y aplica las secciones **Alcance**, **Estructura e implementación**, **Modularización obligatoria y alcance mínimo**, **Verificación** y **PR y aprobación de colaboradores** de [crear-funcionalidad.md](F:/www/Apps/appmovil/docs/prompts/crear-funcionalidad.md). Forman parte de esta plantilla; no ejecutes su ejemplo de creación. Si falta ese archivo, informa el bloqueo antes de implementar.

Estas reglas se limitan a colaboradores que invoquen el prompt. No imponen PR ni bloquean subidas del propietario en su trabajo directo fuera del flujo. No configures protecciones globales ni modifiques CODEOWNERS o workflows.

El único destino es `develop` y el ambiente es `qa`, sin tocar `main` ni producción. Solo cuenta como aprobación una revisión humana de `TuvalSystems`, `devtuval` o `josemg23`, distinta del autor. El agente entrega el PR abierto; no fusiona ni despliega.

La modularización es obligatoria: integra el cambio en el módulo responsable, reutiliza sus piezas y justifica cualquier archivo o abstracción nuevos. No crees funciones sin una responsabilidad necesaria ni reorganices módulos ajenos a la tarea.

### Instrucciones específicas para modificar

1. Localiza la implementación por su nombre visible, menú o descripción funcional. Sigue el flujo completo: pantalla, widgets, provider, datasource, modelo y API. No pidas al usuario carpetas técnicas.
2. Para errores, reproduce o identifica la causa antes de corregir. Para ampliaciones, determina qué cambia y qué debe conservarse. Comunica brevemente causa o alcance y recursos reutilizables, sin crear un informe adicional.
3. Realiza el menor cambio que resuelva la necesidad completa. Edita archivos existentes cuando corresponda; crea uno solo si hace falta y sigue el patrón del módulo. No reconstruyas pantallas ni reorganices capas para un ajuste puntual.
4. Mantén contratos, permisos, navegación, filtros, paginación, tema y comportamientos ajenos a la solicitud. Revisa consumidores de widgets, providers y servicios compartidos. No migres de gestor de estado ni dupliques lógica.
5. Comprueba el resultado solicitado y los comportamientos que puedan sufrir regresiones. Si afecta estado asíncrono, revisa cambio de punto de venta, sesión, respuestas atrasadas y cierre de pantalla. Si afecta capacidades nativas, revisa permisos y ciclo de vida correspondientes.
6. Sigue la verificación y entrega compartidas. Resume comportamiento anterior y resultante, archivos nuevos justificados, pruebas, limitaciones y enlace real del PR cuando exista. Si es trabajo solo local, indícalo sin afirmar publicación o aprobación.

