# QR para firma de guías

Las guías serializadas incluyen `codigo_qr` desde que tienen ID. No requiere migración: el código se deriva del tipo e ID y se verifica con HMAC-SHA256 usando APP_KEY. Es estable al reimprimir y también funciona para guías anteriores. Cambiar APP_KEY invalida los QR impresos anteriormente. No es la clave de acceso tributaria.

Los tres formatos PDF incluyen una imagen PNG generada localmente con milon/barcode, sin servicios externos.

## Integración móvil pendiente

1. Leer el texto completo del QR con el escáner de la app.
2. Enviar `POST /api/movil/inventario/guias-remision/resolver-qr` con la sesión móvil habitual y JSON `{"codigo":"<texto escaneado>"}`.
3. Con respuesta 200, usar `data.accion = firmar_guia`, `data.tipo` y `data.guia_id` para navegar a la pantalla de firma. Mostrar `data.numero_guia` y advertir si `data.tiene_firma` es true: guardar otra firma reemplaza la anterior según el comportamiento existente.
4. Subir multipart a `data.firma_endpoint`: `firma` (PNG, máximo 2 MB), `nombre_firmante` (obligatorio, máximo 200 caracteres) e `identificacion_firmante` (obligatoria, máximo 30 caracteres, enviada como texto para conservar ceros iniciales y permitir pasaportes). La identificación se guarda en los metadatos de la firma, se devuelve en `archivos.firmas` y se muestra en el PDF. Las firmas anteriores sin identificación siguen siendo consultables.
5. Ante 401 solicitar inicio de sesión; ante 403 mostrar falta de acceso; ante 422 mostrar el error de código o estado; ante 404 indicar guía inexistente. No navegar a firma si falla la resolución.

El QR no concede acceso. Se conservan los permisos por bodega y los estados actuales: despacho enviada; reserva, reposición y transferencia recibida. Escanear no recibe ni despacha la guía automáticamente.

Este formato es para el lector interno de la app. La cámara del sistema no abre la app con este texto; eso requiere implementar enlaces de Android/iOS en el proyecto móvil.
