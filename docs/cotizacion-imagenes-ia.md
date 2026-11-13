# Extracción de artículos desde imágenes

`POST /api/borradores/extraer-imagenes` recibe imágenes, llama a OpenAI Responses
y devuelve un detalle editable. Es la etapa de lectura anterior a la creación
del borrador del [piloto MCP](tuval-mcp-piloto.md).

El usuario inicia sesión en TUVAL; no necesita cuenta de ChatGPT. El backend
conserva usuario, cliente y punto de venta. El modelo solo interpreta imágenes.
La confirmación posterior usa `POST /api/borradores/items` y el servicio
compartido del piloto, sin una llamada HTTP al propio servidor MCP.

## Configuración

| Variable | Uso |
| --- | --- |
| `OPENAI_API_KEY` | Clave privada del proyecto de OpenAI, solo en el backend |
| `OPENAI_QUOTATION_MODEL` | Modelo con visión y Structured Outputs; por defecto `gpt-4.1-mini` |
| `OPENAI_TIMEOUT` | Espera en segundos; por defecto 60, limitada a 120 |
| `TUVAL_MCP_ENABLED` | Debe ser `true`, solo local/testing/qa/staging |

Guardar la clave en `.env` ignorado, nunca en `.env.example`, Vue, colecciones
exportadas, prompts o logs. Tras cambiar variables, refrescar la configuración
cacheada de Laravel. El cliente no puede elegir el modelo mediante parámetros.
La integración usa el cliente HTTP existente, sin SDK nuevo, y HTTPS fijo a
`https://api.openai.com/v1/responses`, sin redirecciones ni reintentos automáticos.
La salida está limitada a 6000 tokens.

La clave creada conserva el nombre `Codex` y el proyecto seleccionados en el
formulario del usuario; su vigencia solicitada es de 30 días. Planificar su
renovación si continúa el piloto. Nunca registrar aquí su valor.

La primera prueba real del 12 de noviembre de 2026 fue rechazada con HTTP 429,
`insufficient_quota` / `credit_balance_exhausted`: falta saldo/cuota en el
proyecto. Aún no se ha verificado una extracción real completada. Revisar
[facturación API](https://platform.openai.com/settings/organization/billing) y,
si corresponde, [límites](https://platform.openai.com/settings/organization/limits).
La facturación API es independiente de la suscripción de ChatGPT.
Para experimentos sencillos se puede evaluar `gpt-6-luna` como modelo inicial,
comprobando capacidades y acceso antes de cambiarlo; cambiar de modelo no
resuelve un proyecto sin saldo.

## Entrada del frontend

Enviar `multipart/form-data` con el token Sanctum habitual, ability `web`.

| Campo | Requisito |
| --- | --- |
| `cliente_ruc` | RUC del cliente seleccionado, activo |
| `pv_id` | Punto de venta seleccionado, activo y autorizado para el usuario |
| `imagenes[]` | 1–3 archivos JPEG, PNG o WEBP; hasta 2 MiB cada uno |
| `maxCandidatos` | Opcional, entero 1–6; se conserva para crear después |

Se comprueban contenido de imagen y MIME; dimensiones máximas 10000 por 10000.
No se aceptan PDF, GIF, SVG ni URLs. Límite local: 10 solicitudes/minuto por
usuario. Siguen aplicando los límites de carga del servidor web/PHP.

Ejemplo con el cliente Axios autenticado y su base `/api` existentes:

```javascript
const form = new FormData();
form.append('cliente_ruc', clienteSeleccionado.CLI_RUC);
form.append('pv_id', String(puntoVentaSeleccionado.id));
for (const archivo of imagenesSeleccionadas) form.append('imagenes[]', archivo);
// No fijar Content-Type: el navegador genera el boundary multipart.
const respuesta = await api.post('/borradores/extraer-imagenes', form);
const { payload, revisiones, observaciones } = respuesta.data.data;
```

Respuesta abreviada dentro del formato habitual `success/message/data`:

```json
{
  "usuario": {"id": 179, "codigo": "USUARIO_AUTENTICADO"},
  "punto_venta": {"id": 2, "codigo": "005", "descripcion": "PV seleccionado"},
  "payload": {
    "cliente_ruc": "RUC_DEL_CLIENTE_SELECCIONADO",
    "pv_id": 2,
    "detalle": [{"texto": "codo galvanizado 1/2 por 90", "cantidad": 2}],
    "maxCandidatos": 6
  },
  "revisiones": [],
  "observaciones": [],
  "requiere_confirmacion": true
}
```

Presentar todas las líneas para revisión y corrección. `revisiones` indica los
índices con dudas. Una cantidad desconocida es `null`; no se inventa. Advertir
que, si luego se confirma null, el servicio de borradores actual asume 1.

Tras confirmar, enviar el payload corregido a la entrada ya existente:

```javascript
const solicitud = { ...payloadCorregido, request_id: crypto.randomUUID(), confirmado: true };
const borrador = await api.post('/borradores/items', solicitud);
// Conservar solicitud y su UUID al reintentar un timeout; no generar otro UUID.
```

La extracción no crea cotizaciones ni resuelve candidatos o precios. Esto
ocurre al confirmar, con las reglas existentes. El frontend dispone del flujo
de imágenes y del asistente conversacional descrito al final de esta guía.

## Controles y errores

Se comprueban usuario, perfil, contraseña pendiente, punto de venta y cliente
antes de compartir imágenes. Usuario, RUC y pv_id no se envían al modelo y se
conservan desde el request. Las imágenes sí pueden contener datos personales
visibles: no se redacta su contenido automáticamente.

Los archivos se usan temporalmente, sin guardarlos como adjuntos en TUVAL.
Responses lleva `store:false`; esto no promete retención cero del proveedor.
Se exige JSON Schema estricto y se vuelve a validar el resultado en Laravel.
El formato correcto no garantiza una lectura exacta; se requiere revisión.
Si hay más de 20 líneas se pide dividirlas, y si no hay artículos legibles se
devuelve `detalle:[]` con observaciones. No se crea un borrador vacío.

| HTTP | Significado |
| --- | --- |
| 401/403 | Autenticación, cuenta o permisos |
| 404 | Piloto deshabilitado o entorno no permitido |
| 422 | Campos/archivos/cliente inválidos, rechazo de lectura o exceso de líneas |
| 429 | Límite local de solicitudes |
| 502 | Respuesta incompleta, malformada o incompatible de la IA |
| 503 | Configuración, credencial, modelo, cuota o servicio no disponible |
| 504 | Conexión o timeout del proveedor |

No se devuelven errores crudos del proveedor, tokens o imágenes en errores.
Se conserva la ocultación estándar de mensajes 5xx cuando `APP_DEBUG=false`.
El servicio distingue el saldo insuficiente internamente. Reintentar la extracción
puede volver a consumir tokens; esta etapa no cachea imágenes. La creación
posterior sí conserva la idempotencia del piloto.

## Validación

Instalado el 12 de noviembre de 2026 en `srv-web02:/var/www/webtuval`, con la
misma clave local configurada en QA tras autorización explícita del usuario.
La configuración efectiva confirma modelo `gpt-4.1-mini`, clave presente y
piloto habilitado. La ruta HTTP devuelve 401 sin token. Los respaldos privados
de esta instalación están en `/home/administrador/tuval-images-20261112-eLSCnj`.
La clave no forma parte del código ni del paquete desplegado.

Pasaron 21 pruebas y 150 aserciones localmente (extracción, MCP y precios).
No se crearon cotizaciones reales. La lectura real sigue pendiente de saldo
en el proyecto de OpenAI; no se reintentó automáticamente tras detectarlo.

Las pruebas bloquean HTTP externo y usan SQLite en memoria. Cubren identidad,
permisos, archivos, límites, cuota, timeouts, rechazos, respuestas malformadas,
cantidades desconocidas y ausencia de creación. La prueba real usó una imagen
sintética; no se enviaron documentos de clientes.

```powershell
& C:\laragon\bin\php\php-8.2.35-Win32-vs16-x64\php.exe -d xdebug.mode=off -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --do-not-cache-result --bootstrap vendor/autoload.php tests/Feature/QuotationImageExtractionTest.php tests/Feature/TuvalMcpPilotTest.php tests/Unit/Ventas/Cotizaciones/QuotationItemsDraftPricesTest.php
```

Referencias: [visión](https://developers.openai.com/api/docs/guides/images-vision),
[Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs),
[GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini).

## Registro de consumo

Cada respuesta del proveedor se registra en `storage/logs/quotation-ai-AAAA-MM-DD.log`
con retención de 90 días: usuario, punto de venta, cantidad de imágenes, modelo,
identificador de respuesta, estado HTTP, duración y tokens de entrada/salida/total.
Los tokens en caché y de razonamiento son subconjuntos, no se suman al total.
Se registra antes de validar la extracción porque una respuesta inválida también
puede consumir tokens. Si el proveedor no informa uso, los campos quedan null;
un error de conexión deja el consumo desconocido. No se registran imágenes,
texto extraído, RUC ni credenciales. El estado es el del proveedor, no una garantía
de que la extracción haya pasado las validaciones de TUVAL.

La respuesta de lectura agrega `data.usage` con `model`, `inputTokens`,
`cachedInputTokens`, `outputTokens`, `reasoningTokens` y `totalTokens`.
La vista muestra entrada, salida y total de la última lectura vigente.
Este registro empieza con el despliegue: no reconstruye consumos anteriores,
no es un reporte histórico en base de datos ni calcula importes facturados.

## Presupuesto compartido por perfil

La vista `/sistemas/inteligencia-artificial` conserva el flujo de imágenes y
el histórico por usuario. El apartado **Límites por perfil** permite distribuir
porcentajes del presupuesto diario y mensual global. Por ejemplo, 30% para
Ventas y 70% para Administrador. La suma admite hasta 100%, con dos decimales;
la parte no asignada no está disponible. Un perfil sin asignación o con 0%
no puede generar. El límite en tokens se redondea hacia abajo.

Todos los usuarios de un perfil comparten la bolsa. Se conserva el límite
global y los máximos de entrada/salida por llamada. Las reservas cuentan para
los límites y se ajustan al uso real; el consumo desconocido conserva su reserva.
Cambiar porcentajes no reinicia consumos ni cancela llamadas ya reservadas.

- `ia_configuracion`: presupuestos globales y límites por llamada. Los antiguos
  campos de cuota individual quedan solo para compatibilidad de esquema.
- `ia_presupuestos_perfil`: porcentaje por perfil, en centésimas de porcentaje.
- `ia_consumos`: histórico individual con el perfil capturado al reservar.
  Cambiar el perfil de un usuario no mueve sus consumos anteriores.
- `ia_alertas_tokens`: un aviso por cuota, perfil y período. Los avisos al 80%
  siguen llegando a usuarios activos con perfil 1 o 2 por las colas existentes.

El GET/PUT de configuración incluye `limiteDiarioGlobal`, `limiteMensualGlobal`,
`maxEntrada`, `maxSalida`, `habilitado` y `perfiles` (perfilId y porcentaje).
Al guardar se reemplaza la distribución completa. La respuesta añade nombre,
estado, límites calculados y consumos diario/mensual por perfil.
El histórico individual mantiene sus filtros y expone el perfil de cada llamada.

### Instalación de esta ampliación

Aplicar `2026_11_13_190000_create_ia_presupuestos_perfil.php` después de las
migraciones de configuración/consumo y alertas. Desplegar backend y frontend
juntos y reiniciar los workers. Configurar y guardar la distribución antes de
habilitar nuevas lecturas: no se asignan porcentajes automáticamente.
El presupuesto diario inicial copia el anterior límite diario; revisar su valor
como presupuesto global. El mensual global conserva su valor.

La migración no elimina consumos. Para registros anteriores atribuye el perfil
actual del usuario, porque no se disponía de la asignación histórica; si no
existe el usuario quedan sin perfil y siguen contando para el presupuesto global.
Las alertas antiguas se conservan. No se revierte automáticamente esta transición.

Validación local: pruebas de bolsa compartida, independencia entre perfiles,
límite mensual/global, cambios de perfil, porcentajes inválidos, alertas sin
duplicados y extracción con HTTP simulado. No equivale a una prueba de
concurrencia real en MySQL.
