# Revisión de seguridad de Tuval — 23 de octubre de 2026

## Resultado y alcance

Se revisaron el backend Laravel de este repositorio y su despliegue de pruebas en `srv-web02`, `/var/www/webtuval`. Se aplicaron las correcciones descritas abajo en ambos lugares y se comprobó el servicio después del despliegue. Apache conserva MPM event, PHP-FPM y PHP 8.2.35.

La revisión incluyó inventario de rutas y middleware, autenticación y autorización administrativa, notificaciones, validación y almacenamiento de archivos, generación de PDF, manejo de errores, dependencias Composer, consultas SQL raw, patrones de ejecución/inclusión de código, permisos de archivos y configuración HTTP. Es una revisión técnica con pruebas controladas, no una certificación de ausencia de vulnerabilidades ni una prueba de todos los flujos de negocio.

Se respetó la instrucción de mantener sin token las APIs y rutas internas existentes. No se modificaron las credenciales, la base de datos de negocio, los puertos de MySQL ni la Calculadora de Gracia. No se enviaron correos ni se ejecutaron acciones reales de facturación, cobro o devolución para probar vulnerabilidades. Las pruebas de escritura usan SQLite en memoria y almacenamiento temporal local.

## Correcciones aplicadas

| Hallazgo | Riesgo | Corrección y evidencia |
|---|---|---|
| Operaciones administrativas accesibles con cualquier token web, sin comprobar los permisos del perfil | Alto | Se exige la sección activa correspondiente para administrar usuarios, perfiles, secciones, versiones de la app y consultar logs. Se reutilizan los permisos que ya construyen el menú, sin inventar perfiles administrativos. En el servidor: perfil sin permiso `403`, perfil autorizado `200`. La excepción de rutas internas legacy queda explícita. |
| API de notificaciones confiaba en `userId` enviado por el cliente | Alto | En solicitudes autenticadas se usa la identidad del token. Se agruparon las condiciones `OR` de búsqueda para conservar el filtro del propietario. Pruebas de dos usuarios confirman que no se leen ni marcan notificaciones ajenas. Las rutas legacy internas conservan su política anterior. |
| Desactivar una cuenta/perfil no impedía inmediatamente usar un token ya emitido | Alto | Se comprueba cuenta, perfil y acceso móvil en cada solicitud autenticada de web/móvil. Se revocan tokens al desactivar la cuenta o resetear su contraseña. |
| Login web y móvil compartían únicamente un límite general muy amplio | Medio | Inicialmente se añadió y comprobó un límite específico de cinco solicitudes por minuto por usuario/IP y treinta por IP. El 24 de octubre de 2026 se retiró por solicitud expresa del usuario; se conserva el límite general de la API. |
| Respuestas de login diferentes y un error de tipo para usuarios inexistentes | Medio | Se corrigió el parámetro nullable y se unificaron credenciales inválidas como error de autenticación `401`. No equivale a eliminar todos los posibles canales temporales de enumeración. |
| Se aceptaba `application/octet-stream` como PDF y se conservaban nombres/extensiones del cliente | Alto potencial | Se exige MIME de PDF y cabecera `%PDF-`; se generan nombres UUID con extensión fija. Las imágenes reciben extensión JPEG/PNG determinada por contenido. Las colecciones PDF también restringen MIME. Se conserva el nombre original como metadato. No se afirma haber demostrado ejecución remota contra la versión anterior. |
| PHP podía quedar habilitado en ubicaciones de archivos públicos | Alto potencial | Bloqueo en `.htaccess` y Apache para scripts distintos de `index.php`; protección adicional en imágenes y almacenamiento público, sin overrides en esas carpetas. Sondas PHP inocuas recibieron `403` en `/storage/` y `/images/`; se retiraron inmediatamente. |
| `public/info.php` devolvía información de PHP sin autenticación | Medio | Se retiró del repositorio y del servidor. Antes: `200`. Después: `403` por el bloqueo adicional de Apache. |
| `APP_DEBUG=true` en el servidor de pruebas | Alto | Se estableció `false`. Se ocultan detalles internos en las respuestas de error `5xx` centralizadas. Prueba confirma que una marca sensible de una excepción no llega a la respuesta. |
| `.env` y directorio raíz permitían escritura por el grupo de PHP | Alto | `.env` pasó a `640`, raíz del proyecto a `755`; se retiró escritura de grupo de código y dependencias. `storage` y `bootstrap/cache` conservan permisos de trabajo. |
| Exposición de versiones y propagación de URL/referrer | Bajo/medio | `expose_php=Off`, `ServerTokens Prod`, `ServerSignature Off`, `nosniff`, `Referrer-Policy: no-referrer` y `private, no-store` para respuestas con token. |
| Validación de correo vulnerable en el framework antiguo | Alto, dependiente del flujo | Se añadió una validación de aplicación que rechaza CR, LF y NUL antes de delegar al validador de Laravel. Se conservan sus parámetros de validación. Es una mitigación, no una actualización del framework. |

La raíz de archivos locales permitida a Dompdf quedó limitada a `public`, `storage/app` y `storage/fonts`, en lugar de todo el proyecto. Se conservó el protocolo de imágenes embebidas y se comprobó la generación de PDF. Las vistas inspeccionadas escapan datos de negocio; el HTML sin escape encontrado corresponde principalmente a códigos de barras generados.

## Dependencias

| Paquete | Antes | Después |
|---|---|---|
| `barryvdh/laravel-dompdf` | 2.2.0 | 3.1.2 |
| `dompdf/dompdf` | 2.0.8 | 3.1.6 |
| `league/commonmark` | 2.10.1 | 2.10.4 |
| `spatie/laravel-medialibrary` | 10.15.0 | 11.23.9 |
| `laravel/framework` | 10.50.3 | 10.50.3; pendiente de migración |

El `composer.lock` fija las versiones probadas y el proyecto exige PHP `^8.2.35`. Se regeneró el descubrimiento de paquetes para retirar referencias de caché a proveedores transitivos eliminados. Las pruebas incluyen guardar y recuperar metadatos de un PDF con Media Library 11, usando la migración actual en SQLite, sin migrar tablas del servidor.

El auditor final conserva **cinco registros de aviso en Laravel**, correspondientes a cuatro avisos distintos; CRLF figura duplicado entre fuentes. El catálogo de avisos cambió durante la revisión, por lo que los conteos antes/después no deben interpretarse como nuevas vulnerabilidades introducidas por el cambio.

- [TokenGuard: CVE-2026-114509](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6): el guard afectado no está configurado en Tuval. La API protegida usa Sanctum. Se probaron credenciales `api_token` no textuales sin conseguir autenticación.
- [CRLF en correo: CVE-2026-48019](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq): mitigación de aplicación descrita arriba; el paquete sigue afectado según el auditor.
- [XSS en información de depuración](https://github.com/advisories/GHSA-jh5r-qr3c-85q8): depuración desactivada en el servidor.
- [Confusión de rutas de URL temporal firmada](https://github.com/laravel/framework/security/advisories/GHSA-crmm-hgp2-wgrp): no se encontraron llamadas a URL temporal firmada en el código revisado. No se considera parcheado el framework.

También siguen marcados como abandonados `azjezz/psl` y `php-http/message-factory`; abandono no equivale por sí solo a una vulnerabilidad confirmada.

## Pruebas y verificaciones

- **17 pruebas funcionales, 49 aserciones: todas pasan**, incluidas las nuevas pruebas negativas de seguridad.
- Suite completa, tras actualizar las pruebas de flete: **20 pruebas, 62 aserciones; todas pasan**. Los dos fallos inicialmente observados en `QuotationFreightHistoryTest` se debían a nombres de campos antiguos. Se actualizaron a `fle_usuario_id`, `fle_fecha`, `fle_usuario_aut_id` y `fle_fecha_aut`, conforme a la migración y al código vigente; no se modificó la lógica de negocio.
- Verificación de requisitos Composer y lint de PHP después del despliegue: correctos.
- Laravel con PHP CLI y PHP-FPM: `SELECT 1` correcto, Redis `PONG`, PDF correcto, rechazo de CRLF correcto.
- Autorización administrativa en el servidor: `403` sin sección, `200` con sección activa. Las comprobaciones de servidor fueron de lectura y no emitieron tokens.
- API: `GET /api/login` conserva `405`; preflight CORS devuelve `204`; `.env` y `.git/HEAD` devuelven `404`.
- Apache y PHP-FPM activos; ocho workers Supervisor `RUNNING`, todos usando PHP 8.2.35 después de reinicio al terminar sus trabajos.
- Cabeceras HTTP verificadas y ausencia de `X-Powered-By` tras la configuración.
- `git diff --check` sin errores de whitespace, aparte del aviso de conversión de fin de línea de Git para `composer.lock`.

Comando local de pruebas funcionales, con SQLite habilitado solo para esa ejecución:

```powershell
& C:\laragon\bin\php\php-8.2.35-Win32-vs16-x64\php.exe -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --testsuite Feature --do-not-cache-result
```

## Pendientes y riesgos conservados

1. **Alta prioridad: migrar Laravel 10 a una versión con soporte y los parches publicados.** Las mitigaciones y la falta de uso de un componente no sustituyen la actualización. Referencia: [política de versiones de Laravel](https://laravel.com/docs/12.x/releases).
2. **Alta prioridad: migrar Ubuntu 22.10 y su Apache antiguo a una LTS con soporte.** La instalación actual de PHP es compilada manualmente y sus próximas actualizaciones no las resolverá `apt upgrade`. No se hizo una actualización de sistema operativo compartido durante esta intervención.
3. **APIs/rutas internas sin autenticación: riesgo aceptado expresamente por el usuario.** El inventario inicial encontró 176 rutas sin middleware `auth:*`, incluyendo logins, consulta de versión móvil y numerosas rutas legacy. Algunas permiten operaciones de negocio. No todas son vulnerabilidades, pero una red interna no sustituye control de acceso. Se recomienda segmentación, restricción por red y autenticación futura. No se modificó esa política ni se ejecutaron sus acciones peligrosas como prueba.
4. **MySQL y red:** UFW estaba inactivo y MySQL escucha en todas las interfaces en 3306/33060. No se comprobó exposición desde Internet ni se modificó el firewall, porque no se establecieron todos los consumidores legítimos. Inventariar consumidores antes de restringir.
5. **TLS:** el vhost Apache inspeccionado no activa TLS y `APP_URL` usa HTTP. No se confirmó si existe un proxy anterior que termine HTTPS. Verificar cifrado del recorrido completo antes de forzar redirecciones o HSTS.
6. **Credenciales y ciclo de vida:** rotar la contraseña SSH compartida en la conversación y las claves API que habían aparecido durante un diagnóstico anterior de VS Code. No se rotaron automáticamente ni se cambiaron claves de aplicación.
7. **Reset y tokens:** el reset legacy aún establece como contraseña inicial el identificador del usuario; ahora requiere permiso administrativo en la API y revoca tokens, pero debe reemplazarse por un mecanismo de recuperación con secreto aleatorio, vencimiento y entrega segura. Sanctum sigue sin vencimiento global; definir TTL para web/móvil y renovación es un pendiente de diseño. Los tokens en query string de rutas legacy también deben retirarse gradualmente.
8. **Autorización de objetos de negocio:** no se simularon operaciones reales entre vendedores, bodegas o puntos de venta. Se encontraron restricciones de visibilidad de listados; deben extenderse y probarse en lectura/escritura por objeto con una matriz de roles y datos de prueba. No se certifica que todos los endpoints estén libres de IDOR.
9. **Calculadora de Gracia:** conserva su runtime independiente PHP 8.5.11 por la instrucción previa de trabajar solo en Tuval. Requiere actualización de seguridad separada.

## Evidencias y reversión

En el servidor se conservaron evidencias y respaldos bajo `/home/administrador/tuval-security-20261023`, con directorio privado. Incluye inventarios antes/después, auditorías Composer, pruebas CLI/FPM, logs del despliegue, respaldo de configuración y respaldo de los archivos modificados y `vendor`. Los archivos de respaldo sensibles están protegidos.

Para revertir código se debe entrar en mantenimiento, restaurar `code-vendor-before.tar.gz` desde la raíz del proyecto, retirar las clases nuevas únicamente si fuera necesario, regenerar el descubrimiento de paquetes, recargar PHP-FPM y reiniciar las colas de manera ordenada. No debe deshacerse indiscriminadamente la desactivación de depuración ni restaurarse `info.php`. La reversión de código vuelve a dependencias con avisos conocidos y es solo una medida de recuperación, no una solución de seguridad.
