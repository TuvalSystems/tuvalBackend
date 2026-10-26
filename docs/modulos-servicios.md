# Servicios organizados por módulo

La organización reúne los servicios, contratos y builders según el proceso que
atienden. Se trasladaron 170 clases/interfaces; se mantienen sus nombres cortos
y la lógica de negocio. La capa contiene ahora 112 clases y 89 contratos.

| Módulo | Responsabilidades |
| --- | --- |
| Admin | Usuarios, Perfiles, Secciones, Menu, Ubicaciones, Logs |
| Ventas | Pedidos, Cotizaciones, Clientes, Reservas, Aprobaciones, Comercial, Compartido |
| Compras | OrdenesCompra, Proveedores, Recepciones |
| Inventario | Articulos, Consultas, Stock, Dashboard, Despachos, GuiasRemision, Reposiciones, Transferencias |
| Cobranzas | Cajas, Cobros, Aplicaciones, Depositos, CobrosFacturas |
| Facturacion | InvoiceService y su contrato |
| Devoluciones | Servicios de devoluciones, anulaciones, Aprobaciones y Builders |
| Integraciones | Api, Soap y Sincronizacion |
| Gestion | Sucursales |
| Movil | Aplicaciones, Menu y Gestion |
| Compartido | FunctionsService y su contrato |

Los contratos permanecen en Contracts dentro de su módulo. Los builders están
en Builders dentro del dominio correspondiente. Las importaciones de pedidos y
cotizaciones se encuentran en Importaciones dentro de cada submódulo.

## Registros corregidos

- CashBoxRepositoryInterface apunta a CashBoxRepository, que implementa el contrato.
- ItemsLService, OrderJobService y DevolucionJobService utilizan claves explícitas
  en singletons, para que provides() anuncie los nombres correctos y permita su
  carga diferida. Se comprobó que cada uno conserva una única instancia.
- Se actualizaron los providers existentes y sus imports. Se conservaron sus
  nombres de clase para mantener el mecanismo de registro del proyecto.

## Integración SOAP

Los 31 métodos que estaban en WebserviceController se trasladaron sin modificar
sus firmas o implementación a Integraciones/Soap/LegacyGpSoapService.
Los consumidores utilizan el servicio directamente, incluido TransferRepositionService.
No quedan dependencias de controllers dentro de app/Services.

WebserviceController permanece como wrapper de compatibilidad por herencia.
El servicio SOAP no hereda del controller ni duplica su implementación.

## Compatibilidad

app/Supports/legacy_service_namespaces.php conserva el mapa de nombres anteriores
a actuales. Composer carga service_aliases.php, que registra aliases diferidos
para clases e interfaces. AppServiceProvider registra además los aliases del
contenedor para que los nombres anteriores usen los bindings y singletons actuales.

Mantener este mapa mientras puedan existir objetos serializados o consumidores
que utilicen los nombres anteriores. No se cambiaron clases de jobs, endpoints,
tablas ni contratos de las APIs empresariales.

## Comprobaciones realizadas

- Carga de las 112 clases y 89 interfaces, con namespaces y rutas consistentes.
- Comparación semántica de referencias en 825 archivos PHP: sin cambios inesperados.
- Verificación de los 109 registros de dependencias inspeccionados: sin bindings
  incompatibles ni omisiones en los servicios diferidos.
- Resolución de los 89 contratos mediante los providers en un contenedor aislado,
  utilizando configuración ficticia para clientes HTTP. Sin llamadas empresariales.
- Verificación de los tres singletons corregidos y de los 170 nombres históricos.
- Sin ciclos detectados en las dependencias de constructor de los servicios.
- Los 31 métodos SOAP conservan exactamente su implementación.
- Se mantienen las 538 rutas, con los mismos métodos HTTP, nombres y middleware.
  Las 42 acciones faltantes previas siguen fuera del alcance de esta reorganización.
- Composer validate y comprobación de whitespace correctos.

Las comprobaciones temporales se ejecutaron fuera del repositorio. Se conservaron
TestController, las APIs de prueba del usuario y las notificaciones de prueba.
No se ejecutaron operaciones reales de facturación, cobro, despacho o devolución.
Continúan los avisos previos de PHP sobre parámetros opcionales antes de obligatorios;
sus firmas no se alteraron como parte de esta reorganización.

## Despliegue

Regenerar el autoload y reconstruir los manifiestos de providers, cachés de
configuración y rutas mediante el proceso de despliegue del entorno. Reiniciar
los workers para que carguen los nuevos namespaces. Los aliases no sustituyen
la reconstrucción de un manifiesto antiguo de servicios diferidos.

Completar la validación funcional contra los servicios y datos de pruebas antes
de desplegar. No se requiere modificar los microservicios o instalar drivers SQL Server.
