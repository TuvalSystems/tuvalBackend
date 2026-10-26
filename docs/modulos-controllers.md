# Organización de controllers

Los controllers se agrupan por responsabilidad. Las URLs y los middleware de las
rutas existentes se conservan; el cambio afecta sus imports y clases de destino.

| Módulo | Carpetas / responsabilidad |
| --- | --- |
| Admin | Auth, Usuarios, Perfiles, Secciones, Notificaciones, Aplicaciones, Sistema |
| Ventas | Aprobaciones, Clientes, Consultas, Cotizaciones (incluye Borradores), Pedidos, Reservas |
| Compras | OrdenesCompra, Proveedores, Recepciones |
| Inventario | Articulos, Dashboard, Despachos, GuiasRemision, Reposiciones, Transferencias, Transporte |
| Cobranzas | CajasController, CobrosController, DepositosController, AplicacionesController, EstadoCuentaController |
| Integraciones | Sincronizacion, Soap |
| Movil | Auth, Menu, Gestion, Inventario/GuiasRemision |
| Gestion | Sucursales |
| Compartido | FuncionesController (utilidades heredadas) |
| Devoluciones | DevolucionesController |
| Test | TestController |

Solo Controller.php permanece en la raíz. Los controllers SOAP y de funciones
conservan su implementación heredada; no se han modificado las transacciones ni
el comportamiento de los servicios empresariales.

## Separación de Cobranzas

- Cajas: apertura, estado, cierre, consultas y documentos de cajas.
- Cobros: consulta, registro, comprobantes y envío de cobros a GP.
- Depositos: revisión, aprobación, rechazo e histórico de depósitos.
- Aplicaciones: documentos para aplicación y desaplicación de pagos.
- EstadoCuenta: vencimientos, facturas y exportaciones del estado de cuenta.

Cada controller recibe únicamente los servicios que utiliza. Los métodos
auxiliares llamados entre sí permanecen en el mismo controller. Los 48 métodos
extraídos mantienen las firmas e implementación originales; no se introdujo una
clase base con dependencias compartidas entre todos los módulos.

## Nombres y Git

Namespaces y carpetas coinciden con PSR-4 y respetan mayúsculas. Se normalizaron
GuiasDeRemisionController, OrdenDeCompraController y RecibosDeCompraController.
Los cambios de devoluciones → Devoluciones y test → Test se realizaron con una
ruta intermedia para que Windows y Git registren correctamente la capitalización.
No es necesario cambiar core.ignorecase.

## Verificación

Durante la modularización se comprobaron 44 controllers y 538 contratos de rutas,
sin nuevas acciones faltantes. Las comprobaciones temporales de arquitectura se
retiraron después de ejecutarlas. Se conservan TestController, routes/test.php,
los endpoints manuales de prueba y TestUserNotification.

Al desplegar, regenerar autoload, reconstruir la caché de rutas según el proceso
del entorno y reiniciar los workers para cargar los nuevos namespaces.
