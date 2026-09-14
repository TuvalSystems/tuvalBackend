# Notificación de pedido listo por WhatsApp

El servicio permite texto libre (`WHATSAPP_ORDER_READY_TYPE=text`) o una plantilla
registrada en Meta (`WHATSAPP_ORDER_READY_TYPE=template`, valor predeterminado).
Para probar texto libre, primero escribe desde el teléfono destinatario al número
emisor de WhatsApp Business y realiza la prueba dentro de las 24 horas posteriores
al último mensaje del cliente. Fuera de esa ventana necesitas una plantilla aprobada.
El modo texto construye el mensaje de pedido en el servicio y no requiere nombre
ni idioma de plantilla. No hay cambio automático de modo ante errores.
El servicio no consulta el estado de la ventana: Meta valida el envío y puede
notificar el rechazo mediante webhook aunque la solicitud haya sido aceptada.

El error 132001 indica que no se encuentra la plantilla para el nombre e idioma enviados.

## Crear la plantilla en WhatsApp Manager

Selecciona la cuenta de WhatsApp Business a la que pertenece el número emisor y crea:

- Nombre: `pedido_listo_retiro`.
- Categoría propuesta: Utility (actualización de un pedido existente).
- Idioma: Spanish (`es`). Si eliges otra variante, configura su código exacto.
- Variables: posicionales, tres parámetros de texto en el cuerpo.
- Encabezado de tipo Imagen si se enviará `imagen_url` o se configurará una imagen predeterminada. Sin imagen, utiliza una plantilla sin encabezado.
- Sin botones ni otros componentes con variables.

Cuerpo propuesto:

```text
📦 *¡Tu pedido está listo para retirar!*

Hola {{1}}, ya puedes recoger tu pedido.

*Número de pedido:* {{2}}
*Bodega de retiro:* {{3}}

Presenta tu número de pedido al momento del retiro.

Gracias por confiar en *Tuval*.
```

Ejemplos para la revisión: `María`, `PED-12345`, `Bodega Principal`.
El orden enviado por el servicio es nombre del cliente, número de pedido y bodega.
Espera a que Meta apruebe la plantilla antes de enviarla.
Si ya existe una plantilla, usa su nombre e idioma exactos y comprueba que tenga
estos mismos tres parámetros en el cuerpo.

## Configuración

```dotenv
WHATSAPP_TOKEN=<token con permiso whatsapp_business_messaging>
WHATSAPP_PHONE_NUMBER_ID=<identificador del número emisor, no del negocio>
WHATSAPP_API_VERSION=v25.0
WHATSAPP_ORDER_READY_TYPE=template
WHATSAPP_ORDER_READY_TEMPLATE=pedido_listo_retiro
WHATSAPP_ORDER_READY_LANGUAGE=es
WHATSAPP_ORDER_READY_IMAGE_URL=https://www.w3.org/People/mimasa/test/imgformat/img/w3c_home.jpg
```

Después de cambiar `.env`, ejecuta `php artisan config:clear`.
Si el despliegue usa caché de configuración, regénérala con `php artisan config:cache`.
Reinicia los workers persistentes si usan este servicio.

## Imagen por envío

El endpoint de prueba acepta `imagen_url` opcional con una URL pública HTTP/HTTPS
de una imagen. Meta debe poder descargarla sin autenticación. Por ejemplo:

```json
{
  "telefono": "593999999999",
  "nombre": "María",
  "pedido": "PED-12345",
  "bodega": "Principal",
  "imagen_url": "https://example.com/pedido-listo.jpg"
}
```

También puedes pasar la URL como quinto argumento de `sendOrderReadyNotification`.
Si se omite, se usa `WHATSAPP_ORDER_READY_IMAGE_URL`; si ambas están vacías,
se envía solo el componente body. La plantilla seleccionada debe coincidir con
los componentes enviados: una plantilla con encabezado de imagen requiere imagen
en cada envío. Una URL por envío reemplaza la predeterminada.

Se incluye una imagen pública de prueba (logo W3C) por defecto. Reemplázala por
una URL pública propia para producción. Define `WHATSAPP_ORDER_READY_IMAGE_URL=`
para desactivarla o pasa una cadena vacía como quinto argumento.
El modo `text` envía un mensaje de tipo `image` con el body como `caption` cuando
hay imagen; conserva la ventana de atención de 24 horas y admite hasta 1024 caracteres
en el pie. Sin imagen, sigue enviando texto.
El diseño del body se aplica directamente en modo texto. En modo plantilla,
Meta usa el cuerpo aprobado en WhatsApp Manager: debes registrar allí el cuerpo
propuesto; cambiar el código no modifica una plantilla ya aprobada.

Referencia del formato de encabezado: [Meta: TemplateObject](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/types/TemplateObject/).

En el entorno de prueba de Meta, agrega y verifica el destinatario autorizado.
El teléfono debe incluir el código del país sin espacios; se admite un `+` inicial.
La plantilla de ejemplo `hello_world` no sustituye esta plantilla: no recibe los
tres parámetros de pedido que envía este método.

La respuesta de envío confirma aceptación por la API; la entrega final se confirma
con los estados recibidos por webhook.

Referencia: https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/

## Envío de PDF de prueba

Para adjuntar un PDF sin enviar `pdf_url`, configura
`WHATSAPP_ORDER_READY_PDF_URL=https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf`
y ejecuta `php artisan config:clear`. Una URL recibida en `pdf_url` reemplaza
este valor. Deja la variable vacía para volver al comportamiento de imagen/texto.
La configuración incluye esa URL como valor predeterminado de prueba si la
variable no existe en `.env`.

Agrega `pdf_url` y, opcionalmente, `pdf_nombre` al mismo endpoint:

```json
{
  "telefono": "593999999999",
  "nombre": "María",
  "pedido": "PED-12345",
  "bodega": "Principal",
  "pdf_url": "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf",
  "pdf_nombre": "prueba.pdf"
}
```

El PDF tiene prioridad sobre cualquier imagen. Su contenido puede ser cualquiera;
debe ser un PDF real accesible públicamente por Meta sin autenticación. El backend
valida la URL, no descarga ni inspecciona el archivo. Se permiten URLs firmadas
y rutas sin extensión. Sin nombre se utiliza `documento.pdf`.

En modo `text`, envía `document` con el body como pie (máximo 1024 caracteres),
dentro de la ventana de 24 horas. En modo `template`, envía un encabezado
`document`: configura una plantilla aprobada con encabezado Documento y las mismas
tres variables del body. Una plantilla con encabezado Imagen no sirve para PDF.
Desde PHP, `pdfUrl` y `pdfFilename` son los argumentos sexto y séptimo.

Formato: [Meta: envío de documentos](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/messages/document/).
