# Modelos por módulo

Los 132 modelos Eloquent y los dos traits de modelos se organizan por dominio.
Se conservan los nombres de clase, tablas, claves, conexiones, casts, atributos
asignables y configuración de timestamps. No se cambió el esquema de base de datos.

| Módulo | Submódulos |
| --- | --- |
| Admin | Empresas, Notificaciones, Perfiles, PuntosVenta, Secciones, Sync, Ubicaciones, Usuarios |
| Ventas | Aprobaciones, Carrito, Clientes, CondicionesPago, Cotizaciones/Borradores, Documentos, Pedidos, Reservas, Vendedores |
| Compras | OrdenesCompra, Proveedores, Recepciones |
| Inventario | Articulos, Bodegas, Despachos, GuiasRemision, Reposiciones, Transferencias, Transporte |
| Cobranzas | Aplicaciones, Bancos, Cajas, Cheques, Cobros, Depositos, Efectivo, Retenciones |
| Gestion | Auditoria |
| Movil | AppVersion, Gestion/TrampaVapor |
| Concerns | Traits compartidos de guías |

Ejemplos: GpPedido está en Ventas/Pedidos, GpCotizacion en Ventas/Cotizaciones,
GpUsuario en Admin/Usuarios y GpGuiaRemision en Inventario/GuiasRemision.

## Compatibilidad con datos existentes

La reorganización mueve 118 clases a nuevos namespaces. Los imports y referencias
de aplicación, configuración, factories y seeders se actualizaron. También se
agregaron imports explícitos para las relaciones que antes resolvían modelos por
estar juntos en la raíz.

app/Supports/legacy_model_namespaces.php conserva un mapa de nombre anterior a
nombre actual. Sus claves son identificadores históricos: no deben renombrarse.

- Composer carga app/Supports/model_aliases.php, que registra aliases de forma
  diferida para poder resolver modelos antiguos en jobs serializados y referencias
  persistidas. No carga todos los modelos al iniciar cada request.
- AppServiceProvider registra el mismo mapa como morphMap de Eloquent. De esta
  forma adjuntos de Media Library, notificaciones y auditorías conservan sus tipos
  polimórficos históricos y siguen encontrando sus registros.
- RegisterAudit utiliza getMorphClass para registrar el tipo compatible.
- User conserva su factory original mediante newFactory y UserFactory declara
  explícitamente el nuevo modelo.

No retirar el mapa ni los aliases sin una migración explícita de identificadores
persistidos y sin revisar los jobs pendientes. No se reescribieron registros de
media, notificaciones, auditorías ni colas.

## Verificación realizada

- Análisis de referencias de modelos en 823 archivos PHP, comparando los nombres
  resueltos antes y después mediante el parser de PHP: sin diferencias inesperadas.
- 132 configuraciones ORM comparadas: tablas, claves, conexiones, fillable,
  guarded, casts, timestamps e incrementing conservados.
- Tipos polimórficos y nombres antiguos comprobados para los modelos.
- Construcción de 223 relaciones Eloquent con ejecución SQL deshabilitada.
- Resolución de la factory de User y validación de Composer.

Las comprobaciones se ejecutan temporalmente fuera del repositorio. Se conservan
TestController, las APIs de prueba del usuario y sus notificaciones.
Estas comprobaciones no sustituyen una validación funcional con los servicios y
una copia de la base de datos. No se ejecutaron consultas ni transacciones reales.

## Despliegue

Regenerar el autoload de Composer y las cachés de configuración/rutas mediante el
proceso habitual del entorno. Reiniciar los workers después del despliegue para
que carguen los namespaces y aliases nuevos. No es necesario instalar drivers
SQL Server ni modificar los microservicios por esta reorganización.
