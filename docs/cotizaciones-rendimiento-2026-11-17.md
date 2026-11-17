# Rendimiento del listado de cotizaciones — 2026-11-17

Pruebas en srv-web02, base tuvalapi, punto de venta 005 (pv_id=2).
Mediana de tres ejecuciones por escenario del servicio Laravel, incluyendo serialización; no son tiempos HTTP ni de navegador. Se comparó el servicio original sin índices nuevos contra el final con pv_id. Los 13 escenarios conservaron exactamente el hash de totales y filas completas en las tres ejecuciones.

## Resultado (milisegundos)

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

## Límites y despliegue

La búsqueda general conserva LIKE con comodines en siete columnas: sigue costando varios segundos y en esta medición empeoró. Prospectos también pasó de unos 80 a 184 ms por el plan elegido. No se declara mejora universal. El filtro parcial de número conserva una lectura costosa; no se sustituyó por igualdad o prefijo porque cambiaría resultados. Los tiempos dependen de caché y carga del servidor.

Backend y migración aplicados solo en pruebas. Frontend validado mediante ESLint, build y prueba del composable (payload pv_id, agrupación de filtros, primera página, fechas, refresh, ordenamiento y cancelación); falta publicar ese frontend y verificar navegador/HTTP completo. No se modificó producción.
