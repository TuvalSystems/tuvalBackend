# Cotización borrador desde resolución de artículos

El [piloto MCP autenticado](tuval-mcp-piloto.md) añade herramientas MCP y
`POST /api/borradores/items` usando el usuario del request, `pv_id` explícito
y protección contra duplicados. La ruta GET descrita abajo sigue siendo
la prueba histórica con usuario fijo; no es la entrada del nuevo frontend.

La [preparación de QA](qa-cotizacion-borrador-items.md) registra comprobaciones
históricas anteriores a la integración del resolver.

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
  "detalle": [
    {"texto": "codo galvanizado 1 por 90", "cantidad": 2},
    {"texto": "neplo galvanizado 1/2 por 2", "cantidad": 3}
  ],
  "maxCandidatos": 6
}
```

Sustituir el RUC por el del cliente seleccionado. Para usar
California (005), enviar `pv_id: 2`.

| Campo | Regla |
| --- | --- |
| `cliente_ruc` | Obligatorio. RUC de un cliente activo |
| `pv_id` | Obligatorio. ID numérico del punto de venta activo asignado al usuario |
| `detalle` | Lista de 1 a 20 líneas de texto |
| `texto` | Obligatorio, máximo 200 caracteres |
| `cantidad` | Opcional o null: 1. Mayor que 0, máximo 1000000, hasta 2 decimales y sin notación exponencial |
| `maxCandidatos` | Opcional, entero de 1 a 6; este servicio envía 6 por defecto |

La API obtiene `UMEDIDA_BASE` del plan `ART_PLAN_UMEDIDA` de cada artículo.
La unidad aplicada aparece en `data.incluidos[].unidad`.

Se comprueba además el menor número de decimales permitido por artículo, unidad
y columna de detalle (`decimal(11,2)`). Se rechaza una cantidad incompatible antes
de guardarla; no se redondea. El buscador permite hasta 3 decimales, pero este
endpoint de persistencia solo admite la precisión realmente soportada aquí.
Se rechazan campos desconocidos, incluidos precios, estado, candidatos
del buscador y descuentos enviados por el llamante.

## Resolución y selección

Antes de abrir la transacción de creación, el servicio consulta
`POST /api/reportes/inventario/articulos/resolver` mediante `ApiGpService`,
reutilizando `API_GP_URL` y `API_GP_TIMEOUT`. Envía `detalle` y `maxCandidatos`.

Por cada línea usa `articulo.codigoGP` si hay un artículo identificado; en caso
contrario incluye todos los elementos de `candidatos`. Cada candidato conserva
la cantidad de la línea original. Cuatro candidatos para una línea con cantidad 2
generan cuatro detalles con cantidad 2 cada uno. Un código repetido en distintas
líneas conserva cada aparición y su cantidad, sin agruparlas.

Las líneas sin candidatos válidos se omiten sin errores ni advertencias. Si ninguna
línea tiene artículos, responde 200 con `id` y `numero` nulos e `incluidos` vacío,
sin crear el borrador. Las búsquedas truncadas
también se indican en `advertencias`; se incluyen los candidatos recibidos, hasta
el máximo admitido por API GP. Los candidatos sin código GP válido también se omiten.
Los fallos de la consulta a API GP responden 502 y no crean el borrador.
No se acepta la respuesta del resolver
enviada por el llamante: Laravel realiza la consulta.

Los artículos inactivos, de clase `BNS-INACT` o `CMP-GASTOS`, marcados como
servicio logístico o de compra `L` sin disponibilidad se omiten. La respuesta
incluye `omitidos`, con `indice`, `codigo`, `art_id`, `cantidad` y `razones`
(todas las restricciones de disponibilidad que aplican al artículo).
El borrador se crea con los artículos disponibles. Si todos se omiten, responde
200 con `id` y `numero` nulos, sin guardar una cabecera vacía, y conserva los
omitidos y sus razones. Las validaciones de unidad y cantidad siguen vigentes.

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

Antes de guardar el borrador se consultan las listas de precios mediante
`StockWarehouseServiceInterface::fetchListPricesArticle`, agrupadas por código GP.
Se selecciona `idLista` según `CLI_NIVEL_PRECIO` del cliente y se asigna `valor`
a `ART_VALOR` y `ART_VALOR_ORIGINAL`, redondeado a cuatro decimales, como en
`generateQuoteDraft`. Si el cliente no tiene nivel de precio, responde HTTP 422
sin crear el borrador. Los artículos de compra `L` no se consultan y conservan
precio cero; si no hay precio para el nivel seleccionado, también se usa cero,
siguiendo el flujo de referencia.
Después de guardar los detalles se recalculan descuentos, impuestos y totales
mediante el servicio compartido, dentro de la misma transacción.
La validación de precios al generar la cotización sigue en el proceso existente.
Esta API no añade reglas de conversión, aprobación o impresión.

No se crea una aprobación ni se reserva stock, factura, envía correo,
notificación, cotización al cliente o sincronización transaccional hacia GP.

## Persistencia y respuesta

Cabecera y detalles se guardan en una sola transacción. Cada solicitud exitosa
crea un borrador nuevo y responde HTTP 201. Si falla el guardado, se revierte
toda la operación.

La API devuelve 403 si el entorno o los permisos impiden crear el borrador, 422 si
la entrada o los datos requeridos son inválidos y 409 si hay un conflicto de
concurrencia, y 502 si falla la resolución en API GP.
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

Verificar el destino de QA y guardar el cuerpo con el detalle de texto en
`borrador-items.json`.

```bash
curl --request GET "$TUVAL_QA_URL/cotizaciones/borrador-items" \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-binary @borrador-items.json
```

En Postman usar GET, cuerpo raw JSON y el ejemplo de entrada anterior.
La colección histórica del repositorio corresponde al contrato anterior.

## Preparación del entorno

Comprobar que `gp_cotizacions.estado` admita `borrador_app` y que las
tablas involucradas usen transacciones (MySQL/InnoDB). La migración inicial del
repositorio no incluye ese valor del enum, aunque el servicio móvil actual ya lo
utiliza. Verificar el DDL real de QA antes de habilitar la ruta.
