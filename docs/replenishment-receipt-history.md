# Historial de reposiciones, guías e ingresos

Consulta pública de solo lectura:

`GET /api/reportes/inventory/replenishment-receipt-history?periodo=202611`

Filtro opcional, conservando ceros iniciales:

`GET /api/reportes/inventory/replenishment-receipt-history?periodo=202611&numero_reposicion=000000000012672`

- `periodo`: obligatorio, string `YYYYMM` con mes entre 01 y 12.
- `numero_reposicion`: opcional, string de hasta 255 caracteres; omitido, vacío o null se consulta sin número (los middleware generales normalizan vacío a null).
- No requiere Authorization ni usuario autenticado. Conserva los middleware generales del grupo API.
- Responde con `successResponse()` y los registros del procedimiento sin renombrar ni convertir columnas. Sin resultados devuelve `data: []`.
- Validaciones inválidas devuelven HTTP 422 con `errorResponse()`. Los errores de ejecución usan el manejo de excepciones existente.

## Contrato SQL pendiente de verificar en pruebas

La referencia funcional muestra `CALL tuvalapi.sp_historial_reposiciones_guia_ingreso('000000000012672', '202611')`, pero enumera los parámetros IN en orden inverso. La implementación sigue el ejemplo ejecutable: número primero, período segundo. Ambos valores se envían mediante bindings.

El procedimiento no está definido en este repositorio. Antes de integrar, verificar en un entorno de pruebas que ese orden es correcto y que `NULL` como primer argumento elimina el filtro por reposición. Si el procedimiento exige otro valor para omitir el filtro, confirmar su contrato y ajustar el binding; no se modifica el procedimiento ni se consultan datos de producción.

Verificar también que devuelve las columnas solicitadas: documento_reposicion, codigo_articulo, codigo_3l, descripcion, peso_unitario, cantidad_reposicion_actual, reserva_rep_actual, numero_guia, fecha_guia, bodega_origen, bodega_destino, transito, ingreso_id, cantidad_ingresada, fecha_registro_ingreso, pendiente_segun_historial, pendiente_actual_guia, detalles_guia, detalles_reposicion_en_guia, vinculo_ingreso_detalle.

## Verificación

Con las dependencias de desarrollo instaladas:

```sh
php vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/Reportes/Inventory/ReplenishmentReceiptHistoryTest.php
php artisan route:list --path=reportes/inventory/replenishment-receipt-history -v
```

Las pruebas unitarias cubren validación, acceso permitido, bindings SQL (incluyendo null y texto hostil), conservación de ceros y resultados vacíos. No sustituyen la verificación del procedimiento almacenado ni una llamada HTTP real sin autenticación en pruebas.
