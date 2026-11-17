# Listado y detalle de cotizaciones

## Alcance

- `QuotationQueryService` concentra filtros, visibilidad comercial, ordenamiento, paginación e indicadores para las acciones. `ListarCotizacionesRequest` valida entradas. El controlador delega y devuelve `successResponse` dentro de `handleApiAction`, como reservas.
- `POST cotizaciones/vercotizaciones` conserva su ruta, pero ahora responde `success`, `message`, `data` (paginador Laravel: `data`, `current_page`, `last_page`, `per_page`, `total`, etc.). Desplegar frontend y backend juntos.
- Página predeterminada del frontend: 10 filas (API sin parámetro: 20); opciones de UI: 5, 10, 20, 50; máximo API: 100. Búsqueda y ordenamiento se aplican antes de paginar. Orden secundario por ID para estabilizar empates. Se conserva la página al actualizar acciones; cambiar filtros devuelve a la primera.
- La lista selecciona únicamente las columnas visibles y las utilizadas por reservas, prospectos y recordatorios. Calcula indicadores agrupados para los IDs de la página; no serializa los accessors que consultaban detalles por cada fila. La forma de pago y la dirección se consultan al abrir el detalle. Al eliminar el join de forma de pago del listado, una cotización sin esa configuración ya no queda oculta.
- `POST cotizaciones/detallecotizacion` recibe `{id}`. `QuotationService` obtiene la cabecera persistida y usa su RUC y descuento para resolver el detalle. Añade `cabecera` a la respuesta y conserva los campos que utilizan aprobaciones. No acepta el contenido de la fila como fuente de datos comerciales.
- La vista usa `BaseTable` y `BaseModal`, con `useQuotationsTable`, `useQuotationDetail`, `useQuotationPrint` y `useQuotationActions`. Los composables delegan carga, errores y respuestas obsoletas a `useApiAction.run`; cancelan lecturas al cerrar o desmontar.
- Observaciones envía solamente `id`, `orden_de_compra` y `nota_interna`. Se eliminan el estado, acción y mutaciones Vuex del antiguo listado y la acción de observaciones sin consumidores. La vista conserva Vuex exclusivamente para el punto de venta global de la sesión; los catálogos y el tema siguen usando sus stores existentes.

## Comportamiento conservado

- Visibilidad comercial por usuario/punto de venta, distinción entre prospecto sin cliente y cotización con cliente, filtros por estado y rango de fechas.
- Acciones de recuperación, copia, volver a pedir, reservas, rechazos, recordatorios e impresión; indicadores y restricciones existentes.
- Cálculos de precios, descuentos, aprobaciones y flete del detalle; proveedores de artículos L.
- El flujo de creación/facturación, sincronización SQL Server, estructuras de base de datos y componentes compartidos no se modifican.
- Aprobaciones conserva su consumo Vuex compatible; su petición al detalle ahora manda solo el ID. No se migraron esas pantallas en esta etapa.
- BaseTable utiliza ordenamiento por una columna. Se retira el ordenamiento múltiple con Shift del Dataset anterior. La columna Facturación sigue siendo un indicador, sin ordenar por un campo `venta` inexistente.

## Revisión final de dependencias

- Eliminada la definición duplicada de impresión y la API antigua `cotizaciones/guardarcliente`, que apuntaba a un método inexistente. Se retiraron su acción Vuex y los estados/reset de cliente y prospecto sin consumidores.
- Retiradas cuatro rutas web que apuntaban a métodos inexistentes: llenar cotización, recalcular reserva en el controlador antiguo y las dos consultas antiguas de segmentos. Las consultas actuales de clientes permanecen.
- Observaciones y mensajes de aprobación usan Requests separados y `QuotationService::updateAnnotations`; el controlador delega y devuelve la respuesta compartida. Cada endpoint acepta únicamente sus campos, sin cuerpos completos de cotización.
- `QuotationDocumentService` centraliza renderizado, respuesta binaria y registro de impresión. La marca de impreso se guarda después de generar el PDF y conserva la fecha de primera impresión. El frontend utiliza `downloadFileFromApi` con `run()`.
- `useQuotationActions` centraliza recuperar/volver a pedir y la validación usada al copiar. Una reserva existente, un fallo HTTP o una respuesta incompleta bloquean la acción. Se elimina `useValidatesReserved`, sin otros consumidores.
- Retirados el spinner inalcanzable del detalle, las liberaciones de carga duplicadas en Vuex, imports sin uso y estilos del dropdown con un borde de color fijo. Los estados usan `statusBadge`, con 12 px como ReservasIndex.
- Se conservan el consumo Vuex de las pantallas de aprobación y el contexto global del punto de venta porque tienen consumidores activos. No se cambian las reglas de precios, aprobación, creación ni facturación.

## Validación local

- ESLint: 60 archivos de vistas/composables de cotizaciones, modal de detalle y store; cero errores.
- Compilación Vite del frontend.
- Pruebas aisladas con SQLite en memoria: 4 casos / 18 aserciones para campos permitidos, separación de observaciones y aprobación, PDF fallido sin marcar impresión y conservación de la primera fecha de impresión. Ejecutadas desde archivo temporal, sin escribir en la base de pruebas.
- Comprobación de las tres respuestas de documento y los tres errores 404 mediante dependencias simuladas del controlador.
- Verificación aislada de acciones frontend: recuperación sin reserva y bloqueo ante reserva existente, fallo HTTP o respuesta incompleta.
- Validación anterior de filtros: cambios agrupados, reinicio de página, limpiar fechas, restablecer sin doble consulta, ordenamiento y cancelación al salir.
- Comprobación anterior de solo lectura contra `172.30.1.7/tuvalapi`: 20 filas, 7 consultas y aproximadamente 17 KB; indicadores equivalentes a los anteriores y páginas sin solapamiento; búsqueda de `COT00801176` encontrada.
- Registro de rutas revisado: sin duplicados ni métodos inexistentes de CotizacionesController en las rutas activas.
- No se desplegó esta etapa ni se realizó una prueba visual autenticada. No se ejecutaron acciones que escriban datos comerciales en la base de pruebas.
