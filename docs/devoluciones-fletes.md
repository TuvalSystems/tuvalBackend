# Solicitud de devolución: paquetería y flete al cobro

## Instalación

Ejecutar `php artisan migrate` antes de desplegar el backend y el frontend.
La migración crea `gp_devolucion_responsables_pago_transporte`, con CLIENTE y TUVAL,
agrega la FK nullable `gp_devoluciones.responsable_pago_transporte_id` y el indicador
`gp_devoluciones_detalles.es_servicio_logistico` para excluir servicios de los movimientos físicos.
No modifica las devoluciones históricas: su responsable permanece en null.

## Consulta inicial

`POST /api/devoluciones/detalle-solicitud-devolucion`, con `{ "id": 123 }`.
`getOrderReturnData` sigue recibiendo exclusivamente el identificador del pedido.
Obtiene los detalles considerando guías enviadas, devoluciones previas y bloqueos existentes.
El tipo se consulta mediante el `tipo_despacho_id` persistido en el pedido.

La respuesta mantiene `detalle`, `motivos`, `clientes`, `devolucion_anterior` e `items_devueltos`,
y agrega `reglas_devolucion`:

```json
{
  "tipo_despacho": "paqueteria",
  "mostrar_responsable_pago_transporte": true,
  "responsable_pago_transporte_id": 1,
  "responsables_pago_transporte": [
    { "id": 1, "codigo": "cliente", "descripcion": "CLIENTE" },
    { "id": 2, "codigo": "tuval", "descripcion": "TUVAL" }
  ],
  "tipos_devolucion_permitidos": ["parcial", "total"],
  "material_despachado": true,
  "incluye_flete": false
}
```

Los IDs del ejemplo son ilustrativos: el frontend utiliza los IDs recibidos del catálogo.
`detalle` conserva todas las líneas disponibles y los precios originales. El formulario muestra
CLIENTE por defecto y recalcula localmente al elegir TUVAL, sin otra consulta del detalle.
`incluye_flete` describe el valor inicial; el formulario lo actualiza según el responsable elegido.
Para otros tipos se devuelve `mostrar_responsable_pago_transporte: false`, ID null y lista vacía.
El catálogo también se puede consultar en `GET /api/devoluciones/responsables-pago-transporte`,
con la misma autenticación del módulo.

## Creación

`POST /api/devoluciones/enviardevolucion` mantiene el payload existente. Agregar:

```json
{
  "devolucion": {
    "responsable_pago_transporte_id": 2
  }
}
```

Este fragmento no reemplaza los demás campos obligatorios del payload.
Para otros tipos de despacho el backend guarda null aunque se envíe un ID.
Si se omite para paquetería/flete al cobro, el backend aplica CLIENTE.
Un ID inexistente o inactivo se rechaza con HTTP 422.

| Material | Responsable | Paquetería | Flete al cobro |
| --- | --- | --- | --- |
| Sin despacho | Cualquiera | Solo total, incluye servicio logístico | Solo total, incluye flete |
| Con despacho | CLIENTE | Parcial o total, excluye servicio logístico | Parcial o total, precio sin flete |
| Con despacho | TUVAL | Parcial o total, incluye servicio logístico | Parcial o total, precio con flete |

El backend vuelve a calcular disponibilidad y precios bajo bloqueo del pedido. No utiliza
precios ni marcas de despacho suministrados por el navegador para estos dos tipos.
Se rechazan cantidades negativas, fraccionarias no permitidas, duplicados y totales incompletos.
Una devolución requiere al menos un material seleccionado. El servicio se agrega automáticamente
por su cantidad disponible cuando corresponde, incluso en una devolución parcial (sin prorrateo).
No se vuelve a acreditar si ya no tiene saldo disponible.

Para los ítems L se comprueba la cantidad de sus guías de despacho enviadas; una guía pendiente
no acredita despacho. En refacturación se consultan las guías del detalle de origen si no hay
guías del detalle actual. Los despachos mixtos impiden refacturar, como en el flujo existente.

El precio sin flete proviene de `precio_sin_flete`, manteniendo el porcentaje de descuento original.
El precio y descuento calculados se guardan en el detalle de devolución, por lo que la NC,
reintentos y totales usan el mismo importe. El servicio logístico no requiere recepción física.
La selección se consulta con la relación `responsablePagoTransporte` (JSON: `responsable_pago_transporte`).

## Verificación local

Con PHP 8.3 y PDO SQLite habilitado solo para pruebas:

```powershell
php -d extension=pdo_sqlite vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/ReturnFreightTest.php
```

Las pruebas usan SQLite en memoria; no ejecutan migraciones sobre la base de la aplicación
ni llaman a GP/SRI. Incluyen la matriz de fletes, descuentos, validaciones, catálogo,
migración reversible y cantidades de ítems L descontando devoluciones anteriores.

En `TuvalFront`, ejecutar `node tests/returnFreight.test.cjs`. La prueba compila el
componente Vue y comprueba el valor inicial, los cambios de responsable, precios,
cantidades, exclusión del servicio y payload de envío. El build de producción también
se verificó con Vite.
