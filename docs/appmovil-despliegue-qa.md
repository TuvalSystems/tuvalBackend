# Publicación automática de appmovil en pruebas

Cada push a `develop` de `TuvalSystems/appmovil` ejecuta `.github/workflows/deploy-qa.yml`.
GitHub compila con Flutter 3.44.1, Java 17, flavor `qa`, `APP_ENV=qa` y
`--target-platform android-arm64` (solo teléfonos Android ARM64, sin emuladores x86
ni dispositivos ARM de 32 bits). El número de
compilación son los segundos UTC transcurridos desde 2020-01-01. Antes de compilar,
el job `prepare` consulta `app:siguiente-version-qa` en el servidor de pruebas:
toma la mayor versión Android con formato `X.Y.Z-qa` registrada (incluso inactiva)
e incrementa `Z`. Por ejemplo, `1.0.17-qa` pasa a `1.0.18-qa`.
El resultado se pasa a Flutter mediante `--build-name`, y Gradle añade `-qa`.
No se lee ni se modifica la versión de `pubspec.yaml` para calcular la versión QA;
las versiones de producción tampoco intervienen. Sin historial QA se inicia en
`1.0.1-qa`. La concurrencia del workflow serializa preparación, compilación y
publicación. Un build fallido sin publicación no consume la versión; reintentar
solo la publicación conserva la misma APK y versión. Las compilaciones manuales posteriores deben usar un build mayor al
último publicado. No se utiliza `--split-per-abi`, que modifica el versionCode.

El análisis bloquea errores; advertencias e información se reportan sin bloquear.
Se ejecuta `flutter test` cuando existen archivos `*_test.dart`.

## Servidor y firma

- Destino: `srv-web02`, MySQL `tuvalapi`, Laravel `/var/www/webtuval`.
- Runner de publicación: `/var/lib/tuval-runner/appmovil`, cuenta `administrador`,
  etiquetas `self-hosted,Linux,X64,tuval-appmovil-pruebas`.
- Herramientas: PHP, Python 3, curl, flock, aapt y apksigner con Java.
- El runner descarga únicamente el artefacto del run actual. No compila Flutter.
- La APK de compilación utiliza la firma provisional del build; `apksigner` la
  vuelve a firmar con la clave de distribución antes de publicarla.
- La configuración privada está en `/var/lib/tuval-runner/appmovil-signing`,
  directorio 0700 y archivos 0600, fuera de Git y del directorio público:
  `release.jks`, `key-alias`, `store-password`, `key-password`, `certificate-sha256`.
  El certificado debe coincidir con la APK distribuida para permitir actualizarla.
- No subir la clave ni las contraseñas a GitHub. La configuración de firma se
  provisiona por SSH con autorización explícita del propietario.

## Publicación

El job ejecuta `scripts/publicar-apk-pruebas.sh` del backend. Usa el mismo
`/var/lib/tuval-runner/deploy.lock` que los otros despliegues y comprueba que
`develop` aún apunte al commit compilado. Lee paquete, versión y build de la APK,
compara el manifiesto y checksum del artefacto, firma y verifica el certificado.

Después llama:

```bash
php artisan app:publicar-apk-pruebas /ruta/app-qa-release.apk /ruta/manifest.json
```

El comando solo admite QA en el host y base indicados; reutiliza `AppService`,
guarda en `storage/app/public/apks`, registra `app_versions` y la auditoría.
El usuario técnico `APP_QA_CI` debe existir, deshabilitado, sin contraseña ni
perfiles de acceso. Solo identifica las publicaciones en la auditoría.

El manifiesto contiene `package`, `versionName`, `buildNumber`, `sha256`, `commit`
y `runUrl`. El comando conserva el mínimo obligatorio de la última versión activa,
rechaza builds anteriores y permite reintentar el mismo build únicamente cuando
contenido, versión, archivo y estado coinciden. No modifica versiones anteriores.
Si falla la transacción, retira el archivo creado sin registro.

El manifiesto también incluye `releaseNotes`: lista de descripciones de commits
desde la última ejecución exitosa del workflow en develop. Se eliminan prefijos
como `feat:` y `fix:`, merges y duplicados; no se muestran hashes ni enlaces de CI
en las notas. Escribir mensajes de commit descriptivos en español para que expliquen
los cambios al usuario. El historial completo se obtiene con `fetch-depth: 0` y
el workflow usa permiso `actions: read` para consultar su última publicación.
En una recompilación del mismo commit se indica que no hay cambios adicionales.

Al terminar se consulta `/api/movil/app/latest` y se descarga la APK vía HTTP local
con el virtual host `tuvalapi.cosenco.ec`, verificando SHA-256. Si esa comprobación
falla, revisar el run: el registro puede haberse creado y el reintento es seguro.

## Operación

Publicar primero estos archivos del backend y provisionar el runner y la firma;
después activar el workflow en appmovil. El token temporal de registro del runner
se obtiene en Settings → Actions → Runners → New self-hosted runner → Linux x64.
Registrar con nombre `srv-web02-appmovil`, etiqueta `tuval-appmovil-pruebas`,
`--unattended --work _work`; instalar el servicio con `svc.sh install administrador`.

Para reintentar, usar Actions → Compilar y publicar APK QA → Re-run failed jobs.
Para volver a la versión anterior, desactivar la nueva desde la gestión web;
esto cambia la versión ofrecida, no baja automáticamente la instalada en Android.
Producción no participa en este flujo.
