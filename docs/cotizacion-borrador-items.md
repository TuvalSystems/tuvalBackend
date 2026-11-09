# Cotización borrador desde artículos confirmados

La [preparación de QA](qa-cotizacion-borrador-items.md) registra qué se comprobó,
las consultas pendientes de solo lectura y las acciones todavía no autorizadas.

`GET /cotizaciones/borrador-items` recibe un cuerpo JSON y crea una cabecera en `gp_cotizacions`
con estado `borrador_app` y sus registros en `gp_cotizacion_detalles`.
Reutiliza los métodos de borrador de `QuotationBuilder`.
La numeración `COT...` la asigna el observer existente.

La ruta acepta solicitudes sin autenticación en `local`, `testing`, `qa` y
`staging`. El borrador se crea con `adminWeb` (usuario 6). La cuenta y el
perfil web deben estar activos, con contraseña sin cambio pendiente y acceso
al punto de venta.

## Entrada

```json
{
  "cliente_ruc": "RUC_DEL_CLIENTE_SELECCIONADO",
  "pv_id": 1,
  "items": [
    {"codigo": "CODIGO_GP_CONFIRMADO", "cantidad": 2},
    {"codigo": "OTRO_CODIGO_GP_CONFIRMADO", "cantidad": 2}
  ]
}
```

Sustituir el RUC y los códigos por los valores confirmados. Para usar
California (005), enviar `pv_id: 2`.

| Campo | Regla |
| --- | --- |
| `cliente_ruc` | Obligatorio. RUC de un cliente activo |
| `pv_id` | Obligatorio. ID numérico del punto de venta activo asignado al usuario |
| `items` | Lista de 1 a 20 renglones; códigos duplicados se rechazan |
| `codigo` | `ART_CODIGO` GP exacto, activo y único; no es código Zafiro ni código 3L |
| `cantidad` | Obligatoria. Mayor que 0, máximo 1000000, hasta 2 decimales y sin notación exponencial |

La API obtiene `UMEDIDA_BASE` del plan `ART_PLAN_UMEDIDA` de cada artículo.
La unidad aplicada aparece en `data.incluidos[].unidad`.

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

El vendedor se obtiene de la configuración activa del cliente para el punto
de venta seleccionado. Si falta la configuración o su vendedor, la API responde
HTTP 422. Para `sucursal_id` y `sucursal_facturacion_id` se usa la dirección
activa `PRINCIPAL` del cliente. Si hay varias, se usa la de menor ID. Si falta,
ambos campos quedan vacíos. El servidor completa la forma de pago disponible.
El tipo de despacho es `Retiro en bodega` (`tipo_despacho_id = 1`).
Para la bodega, usa la primera relación de `gp_relacion_punto_venta_bodega_relacionadas`
por `orden` e `id`, filtrada por el código GP del punto de venta. Si no hay una
bodega relacionada, devuelve HTTP 422.

La creación del borrador no consulta listas de precios ni exige nivel de precios
o precio disponible. Los importes quedan en cero como valores iniciales del
builder de `borrador_app`, sin presentarse en la respuesta como una cotización
calculada. Al seleccionar el borrador para generar la cotización, se usa la
validación de precios del proceso existente, conforme a la instrucción del
usuario. Esta API no modifica ese proceso ni añade reglas de conversión,
aprobación o impresión.

No se crea una aprobación ni se reserva stock, factura, envía correo,
notificación, cotización al cliente o sincronización transaccional hacia GP.

## Persistencia y respuesta

Cabecera y detalles se guardan en una sola transacción. Cada solicitud exitosa
crea un borrador nuevo y responde HTTP 201. Si falla el guardado, se revierte
toda la operación.

La API devuelve 403 si el entorno o los permisos impiden crear el borrador, 422 si
la entrada o los datos requeridos son inválidos y 409 si hay un conflicto de
concurrencia.
La respuesta no serializa modelos completos ni expone costos o márgenes.

Ejemplo abreviado de respuesta:

```json
{
  "success": true,
  "message": "Cotización borrador COT00000042 creada.",
  "data": {
    "id": 42,
    "numero": "COT00000042",
    "estado": "borrador_app",
    "usuario": {"id": 6, "codigo": "adminWeb"},
    "actor": {"id": 6, "codigo": "adminWeb"},
    "punto_venta": {"id": 123, "codigo": "PV_REAL", "descripcion": "PV seleccionado"},
    "cliente": {"id": 456, "ruc": "RUC_SELECCIONADO"},
    "incluidos": [{"indice": 0, "codigo": "CODIGO_GP_CONFIRMADO", "art_id": 789, "cantidad": 2, "cantidad_asumida": false, "unidad": "UN"}],
    "advertencias": []
  }
}
```

## curl y Postman

Verificar el destino de QA y guardar el cuerpo con la selección confirmada en
`borrador-items.json`.

```bash
curl --request GET "$TUVAL_QA_URL/cotizaciones/borrador-items" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-binary @borrador-items.json
```

Importar [la colección Postman](postman/cotizacion-borrador-items.postman_collection.json).
Configurar `base_url`, `cliente_ruc`, `pv_id`, `codigo_gp` y `cantidad`
en un entorno local.

## Preparación del entorno

Comprobar que `gp_cotizacions.estado` admita `borrador_app` y que las
tablas involucradas usen transacciones (MySQL/InnoDB). La migración inicial del
repositorio no incluye ese valor del enum, aunque el servicio móvil actual ya lo
utiliza. Verificar el DDL real de QA antes de habilitar la ruta.
