# Rendimiento del listado de cotizaciones — 2026-11-17

Pruebas en srv-web02, base tuvalapi, punto de venta 005 (pv_id=2).
Mediana de tres ejecuciones por escenario del servicio Laravel, incluyendo serialización; no son tiempos HTTP ni de navegador. Se comparó el servicio original sin índices nuevos contra el final con pv_id. Los 13 escenarios conservaron exactamente el hash de totales y filas completas en las tres ejecuciones.

## Resultado de la primera etapa (milisegundos)

| Escenario | Antes | Después | Total |
| --- | ---: | ---: | ---: |
| pendientes | 2214.50 | 111.83 | 518 |
| todos | 1301.42 | 56.40 | 196725 |
| procesadas | 2498.83 | 35.62 | 77114 |
| prospectos | 79.56 | 184.25 | 1172 |
| caducado | 3159.30 | 52.73 | 117838 |
| rechazadas | 2110.20 | 6.01 | 5 |
| pendientes_pagina_2 | 2235.48 | 106.32 | 518 |
| pendientes_fecha | 2643.86 | 18.63 | 518 |
| cotizacion | 2244.52 | 1423.43 | 1 |
| busqueda | 3044.03 | 3631.75 | 1 |
| pendientes_total | 2305.94 | 213.48 | 518 |
| restringido_pendientes | 117.60 | 2.51 | 0 |
| restringido_todos | 218.86 | 7.18 | 21 |

## Implementación

- Frontend: useQuotationsTable envía pv_id; CotizacionesView resuelve el ID desde la ruta o store.state.puntoventaselectid. Conserva el contexto del punto de venta al navegar.
- Request admite pv_id y mantiene puntodeventa_id para clientes anteriores. El servicio filtra por la llave interna y conserva visibilidad, estados, filtros parciales, fechas, ordenamiento y respuesta paginada.
- Tres índices no únicos: punto/fecha, punto/estado (cubre columnas de filtros, cotización y total), punto/usuario/fecha.
- Auditoría previa: 800439 cotizaciones; una carecía de pv_id. La migración completó únicamente ese nulo usando PTO_VTA_ID y verifica inconsistencias antes de crear índices.
- Rollback probado en MySQL: restaura el índice que necesita la FK antes de eliminar los compuestos; conserva el pv_id completado.

## Límites de la primera etapa y despliegue

La búsqueda general conserva LIKE con comodines en siete columnas: sigue costando varios segundos y en esta medición empeoró. Prospectos también pasó de unos 80 a 184 ms por el plan elegido. No se declara mejora universal. El filtro parcial de número conserva una lectura costosa; no se sustituyó por igualdad o prefijo porque cambiaría resultados. Los tiempos dependen de caché y carga del servidor.

Backend y migración aplicados solo en pruebas. Frontend validado mediante ESLint, build y prueba del composable (payload pv_id, agrupación de filtros, primera página, fechas, refresh, ordenamiento y cancelación); falta publicar ese frontend y verificar navegador/HTTP completo. No se modificó producción.


## Segunda etapa: prospectos y búsqueda general

Se volvió a medir antes de modificar esta etapa, con pv_id e índices de la primera etapa ya desplegados. Misma base srv-web02/tuvalapi, punto 005, 10 filas, mediana de tres ejecuciones del servicio (sin HTTP ni navegador).

### Causa y solución

- Prospectos combinaba los índices individuales de cliente_id y pv_id (`index_merge`, intersección), leía las cabeceras y ordenaba después. El índice nuevo `(pv_id, cliente_id, created_at, id, prospecto_id, estado, user_id)` permite buscar directamente cliente_id IS NULL en ese punto, contar con cobertura y recorrer la página en orden. EXPLAIN pasa a `ref`, unas 1318 filas candidatas, sin filesort para la fecha predeterminada.
- La búsqueda general conservó exactamente los siete LIKE, comodines, columnas, conversiones y collation. El problema era leer cabeceras completas masivamente, a veces siguiendo el índice corto de fecha con estimaciones muy optimistas. Un índice de cobertura contiene todas las columnas buscadas, filtros, usuario y total. La consulta pagina IDs; sólo después carga las cabeceras y reserva de esa página, respetando su orden.
- En MySQL se indica el índice de búsqueda de forma explícita porque EXPLAIN y las mediciones confirmaron que el optimizador seguía eligiendo índices cortos incluso con cobertura disponible. Usuarios restringidos conservan el índice por usuario, y prospectos el suyo. Para el conteo selectivo de pendientes/número, sin rango completo de fechas, se conserva el índice pequeño por estado mediante el parámetro total del paginador Laravel; no hay un conteo adicional ni cambios en el contrato.
- Fuera de prospectos se excluye ese índice del plan para evitar otra regresión en los listados existentes. Toda la lógica permanece en QuotationQueryService.

### Comparación de la segunda etapa (ms)

| Escenario | Antes de esta etapa | Después | Total |
| --- | ---: | ---: | ---: |
| pendientes | 125.69 | 113.86 | 518 |
| todos | 55.72 | 54.89 | 196725 |
| procesadas | 33.95 | 33.09 | 77114 |
| prospectos | 170.11 | 6.65 | 1172 |
| caducado | 49.91 | 49.20 | 117838 |
| rechazadas | 5.49 | 5.99 | 5 |
| pendientes_pagina_2 | 108.57 | 106.52 | 518 |
| pendientes_fecha | 19.50 | 8.24 | 518 |
| cotizacion | 1422.62 | 232.40 | 1 |
| busqueda | 3478.63 | 951.40 | 1 |
| pendientes_total | 209.34 | 244.80 | 518 |
| restringido_pendientes | 2.39 | 3.19 | 0 |
| restringido_todos | 6.06 | 6.42 | 21 |
| prospectos_pagina_2 | 166.42 | 6.39 | 1172 |
| prospectos_total | 180.34 | 23.08 | 1172 |
| texto_0 | 1835.54 | 480.02 | 80 |
| texto_1 | 1804.61 | 483.98 | 1229 |
| texto_2 | 1485.08 | 145.95 | 196725 |
| texto_3 | 2108.88 | 147.10 | 196725 |
| texto_4 | 1798.54 | 467.49 | 0 |
| texto_CLI_RAZON_SOCIAL | 1829.42 | 705.23 | 3178 |
| texto_CLI_RUC | 1841.42 | 625.25 | 704 |
| texto_USERID | 1803.46 | 491.95 | 27147 |
| texto_COND_ID | 1838.53 | 489.46 | 72229 |
| texto_pendientes | 110.89 | 110.65 | 518 |
| texto_prospectos | 180.58 | 26.00 | 69 |
| texto_fecha | 24.69 | 15.30 | 1229 |
| texto_total | 3870.44 | 996.34 | 61274 |
| texto_combined | 106.56 | 103.14 | 275 |
| restringido_texto | 11.34 | 10.59 | 21 |
| otro_pv | 1.25 | 1.40 | 0 |

La mejora no elimina el costo de buscar subcadenas: sin rango de fechas se sigue recorriendo el índice del punto. No se reemplazó LIKE por igualdad, prefijo ni FULLTEXT, ni se concatenaron columnas (eso alteraría las coincidencias). La búsqueda de número completo sigue rondando 0,95 s porque evalúa tanto conteo como página. Los tiempos fluctúan con la carga: el ordenamiento por total sin búsqueda pasó de 209 a 245 ms en esta muestra.

### Verificación y despliegue

- Los 31 escenarios conservaron filtros, IDs, orden y totales en las tres repeticiones. Como algunas filas recientes cambiaron entre mediciones, se ejecutaron además ambos servicios dentro de la misma transacción REPEATABLE READ, sin escrituras en la base: los 31 escenarios pasaron con filas completas y totales idénticos.
- Migración nueva: `2026_11_17_190000_add_quotation_search_indexes.php`. Se aplicó en pruebas y se probó rollback/reaplicación, manteniendo temporalmente el servicio anterior compatible durante esa operación. No se editaron migraciones ya aplicadas ni datos de cotizaciones.
- Ambos índices son no únicos. Ocupan aproximadamente 112 MiB (búsqueda) y 39 MiB (prospectos) en esta base; añaden mantenimiento a escrituras. No se midió el impacto en throughput de escritura.
- Aplicar primero la migración y después publicar el servicio: los hints dependen de esos índices. Para revertir, restaurar primero el servicio anterior y luego revertir únicamente esta migración.
- Sólo backend de pruebas; esta etapa no necesita cambios de frontend. No se operó producción.

El script reproducible `tests/Performance/quotation-list-benchmark.php` está restringido al servidor/base de pruebas. Ejecutar con una ruta JSON de salida; opcionalmente acepta un JSON previo y comprueba hashes usando los mismos filtros/usuarios. Si hay actividad concurrente que cambia los datos, esa comparación entre momentos puede fallar y requiere comparación dentro de una misma instantánea. Los archivos JSON incluyen filtros y deben mantenerse fuera del repositorio.
