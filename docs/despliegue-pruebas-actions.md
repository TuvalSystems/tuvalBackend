# Despliegue de pruebas con GitHub Actions

Los repositorios `TuvalSystems/tuvalBackend` y `TuvalSystems/TuvalFront` tienen un workflow `deploy-pruebas.yml` que se ejecuta al hacer push a `develop` o manualmente sobre esa rama. No se ejecuta código de pull requests en el servidor. Los workflows y scripts deben subirse a `develop` en cada repositorio para que GitHub los utilice.

## Destinos

| Proyecto | Ruta en srv-web02 | Etiqueta del runner |
| --- | --- | --- |
| Laravel | `/var/www/webtuval` | `tuval-backend-pruebas` |
| Vue | `/var/www/TuvalFront/vue` | `tuval-frontend-pruebas` |

Los dos servicios usan el usuario `tuval-runner`, sin sudo, con acceso a los proyectos mediante ACL. El instalador guarda las ACL anteriores en `/root/tuval-<componente>-acl-before-<fecha>.txt`. Los dos despliegues comparten `/var/lib/tuval-runner/deploy.lock`; cada workflow también serializa sus ejecuciones en GitHub.

## Registrar los runners

En **Settings → Actions → Runners → New self-hosted runner → Linux x64**, generar un token temporal para cada repositorio. Guardarlos fuera de los repositorios, en archivos privados con permisos `600`:

```bash
/home/administrador/runner-backend.token
/home/administrador/runner-frontend.token
```

Copiar `scripts/install-runner-pruebas.sh` al servidor y ejecutar desde allí:

```bash
sudo bash install-runner-pruebas.sh backend /home/administrador/runner-backend.token
sudo bash install-runner-pruebas.sh frontend /home/administrador/runner-frontend.token
```

El instalador verifica hostname, arquitectura del paquete Linux x64 y checksum SHA-256 del runner oficial 2.338.2; registra cada servicio con su etiqueta y lo inicia mediante systemd. Los tokens se pasan al configurador por una variable de entorno y no se guardan en el código. Retirar los archivos temporales de tokens una vez registrado el servicio. GitHub indica que los tokens de registro vencen después de una hora.

## Configurar el frontend

En **TuvalFront → Settings → Secrets and variables → Actions → Secrets → Repository secrets**, definir `VITE_API_BASE_URL` con la URL del backend de pruebas. Configurar también `VITE_PUSHER_APP_KEY`, `VITE_PUSHER_APP_CLUSTER`, `VITE_PUSHER_HOST`, `VITE_PUSHER_PORT` y `VITE_PUSHER_SCHEME`. El workflow lee los seis valores mediante `secrets.*`, según la configuración elegida por el usuario. Vite incorpora las variables `VITE_*` al JavaScript público: no colocar secretos privados como `PUSHER_APP_SECRET`.

El workflow instala Node 22 en el entorno del runner, ejecuta `npm ci` y `npm run build`, y comprueba `vue/index.html` y `vue/version.json`. No sustituye el Node del sistema.

## Comportamiento y recuperación

El backend comprueba que no existan cambios versionados locales. Importa el commit desde el checkout local de Actions, sin guardar credenciales de GitHub en la copia servida, y pasa la rama local a `develop`. Conserva archivos no versionados; Git aborta si alguno entra en conflicto con el checkout. Mantiene el `.env` del servidor y no ejecuta migraciones ni seeders.

Laravel entra en mantenimiento antes de actualizar código y dependencias. Después se limpian cachés, se generan las de configuración y vistas, se verifica el registro de rutas y se solicita el reinicio ordenado de workers. No se genera caché de rutas para permitir las rutas con closures existentes. La sonda HTTP espera `405` en `GET /api/login`; no inicia sesión ni realiza operaciones de negocio.

Si falla un paso durante la actualización del backend, revisar el log del job. Puede quedar en mantenimiento con dependencias parcialmente instaladas. El log muestra el commit anterior. La recuperación consiste en restaurar ese commit y sus dependencias, regenerar cachés y ejecutar `php artisan up` cuando la instalación esté consistente. No hay reversión automática del backend.

El frontend guarda compilaciones en `/var/www/TuvalFront/.releases/`. En la primera publicación mueve el directorio `vue` anterior a una copia `before-actions-*`; hay un intervalo breve mientras se sustituye por un enlace. Las siguientes publicaciones cambian el enlace atómicamente. Si falla la sonda HTTP de `version.json`, se recupera el enlace anterior. Las compilaciones se conservan para recuperación; revisar espacio y retirar versiones antiguas de manera controlada.

## Validación realizada antes de activar

- Acceso SSH, hostname, rutas, PHP, permisos administrativos y acceso HTTPS a GitHub verificados.
- Sintaxis Bash y estructura YAML de los dos workflows verificadas.
- Publicación inicial, reemplazo del enlace y recuperación ante fallo HTTP probados en un directorio temporal aislado.
- `GET /api/login` devuelve `405` en el servidor actual.

El 11 de noviembre de 2026 se registraron e iniciaron los dos runners en `srv-web02`, versión 2.338.2, con el usuario `tuval-runner`. Ambos servicios quedaron habilitados para iniciar con el servidor y conectados a GitHub, escuchando trabajos. Se retiraron los archivos de tokens temporales usados para el registro.

Los workflows están publicados en `develop`. El backend completó correctamente el [despliegue 43066774221](https://github.com/TuvalSystems/tuvalBackend/actions/runs/43066774221), sirviendo el commit `1800ee6d`; la sonda HTTP devuelve `405`. Para Git 2.37 se registraron las rutas exactas de los proyectos en `safe.directory` del usuario del runner. El checkout del backend descarga historial completo y valida `FETCH_HEAD` antes de entrar en mantenimiento; se comprobó el rechazo de un fetch superficial y la importación correcta desde historial completo en repositorios temporales.

El workflow del frontend lee Repository secrets. El [intento 43066919000](https://github.com/TuvalSystems/TuvalFront/actions/runs/43066919000) se detuvo antes de compilar porque `VITE_API_BASE_URL` llegó vacío. Queda pendiente completar ese secreto y validar la primera compilación/publicación real. La web anterior sigue respondiendo `200`. El `.env` local del frontend apunta a una API de desarrollo, por lo que no se copió al servidor.

Referencias: [registro de runners](https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/add-runners) y [versión oficial del runner](https://github.com/actions/runner/releases/tag/v2.338.2).
