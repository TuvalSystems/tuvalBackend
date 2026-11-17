# Cotizaciones de prospectos

El prospecto es un borrador comercial previo al registro del cliente. No requiere
configuración de cliente, vendedor, direcciones registradas, reservas de stock,
proveedores de artículos L ni aprobaciones para guardarse.

## Creación

La ruta `POST cotizaciones/prospecto/guardar` delega a
`ProspectQuoteService`, mediante `GuardarCotizacionProspectoRequest`.
Prospecto, cabecera y detalles se guardan en una sola transacción.
La impresión utiliza `GET cotizaciones/prospecto/imprimir/{id}`. Las consultas
`recuperar` y `obtener-lista-precio-defecto` permanecen bajo el mismo prefijo.
Se retiraron las rutas antiguas de selección, llenado y recuperación que ya no
tenían consumidores; el llenado además apuntaba a un método inexistente.

- Identificación y razón social del prospecto; contacto y dirección opcionales.
- `pv_id` se resuelve desde el punto de venta. Se admite el código legado
  `PTO_VTA_ID`; si llegan ambos deben coincidir.
- Bodega perteneciente al punto de venta.
- `tipo_despacho_id` del catálogo y su descripción. Si no se especifica, se usa
  **Retiro en bodega**. Se conserva la opción anterior de despacho en ruta cuando
  la bodega lo permite. No se añaden modalidades de flete al borrador.
- Artículos, cantidades, precios y descuentos; se admiten precios cero para
  completar después. No se reservan existencias.
- Peso e importes calculados desde los detalles, lista de precios del punto de
  venta y precio sin flete en cada detalle. Se reutiliza el builder de detalles.
- Notas y orden de compra opcionales.

Se conserva el estado histórico `aprobado` para compatibilidad con la bandeja;
esto no activa aprobaciones. `prospecto_id` con `cliente_id` nulo identifica el
borrador pendiente de cliente. No se cambia el proceso de facturación.

El frontend usa `useProspectQuotation` para estado y comportamiento local y
`useQuoteProspectActions` para las consultas y el guardado. Las rutas se encuentran
en `QUOTE_API_ROUTES`. Ya no usa las acciones globales de selección/guardado de
prospectos de `store/index.js`; limpiar el formulario no borra otra cotización.

## Recuperación después de crear al cliente

1. Se registra el cliente con el flujo existente.
2. Se abre directamente la cotización (`restore_quote`), sin llamar al endpoint
   intermedio que antes actualizaba cliente, sucursales y vendedor en el borrador.
   Esa API y sus referencias se retiraron de backend y frontend.
3. La recuperación consulta al cliente por identificación y prepara sus datos y
   una dirección activa, si existe. Los faltantes se completan en la pantalla
   normal de cotización.
4. Al guardar mediante el flujo actual, se persiste la vinculación y se aplican
   las reglas de la cotización definitiva.

Para prospectos antiguos, la respuesta completa `pv_id`, tipo de despacho,
lista de precios, precio sin flete y peso cuando faltan. La recuperación no
modifica el registro histórico en la base. Si aún no existe el cliente, informa
que debe registrarse primero, sin intentar consultar su crédito.

## Validación local

`tests/Feature/ProspectQuoteServiceTest.php`: persistencia en SQLite, valores por
defecto sin IDs de catálogo fijos, reutilización del prospecto, artículos L sin
precio, despacho en ruta, rollback ante fallo de detalle, catálogo faltante,
validación mínima y recuperación del cliente sin actualización intermedia.

Las pruebas usan servicios locales y dobles para consultas externas. No equivalen
a una prueba HTTP completa contra GP/MySQL ni a una revisión manual del navegador.
Los cambios de frontend y backend deben publicarse juntos; no requieren migración.
