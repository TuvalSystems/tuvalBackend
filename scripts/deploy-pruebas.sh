#!/usr/bin/env bash
set -euo pipefail
umask 0002

target=/var/www/webtuval
source_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
sha=${GITHUB_SHA:?Falta GITHUB_SHA}

[[ $(hostname -s) == srv-web02 ]] || { echo 'Destino incorrecto.' >&2; exit 1; }
[[ ${GITHUB_REPOSITORY:-} == TuvalSystems/tuvalBackend ]]
[[ ${GITHUB_REF:-} == refs/heads/develop ]]
[[ $sha =~ ^[0-9a-f]{40}$ ]]
[[ $(git -C "$source_dir" rev-parse HEAD) == "$sha" ]]
[[ $source_dir != "$target" && -f $target/artisan && -s $target/.env ]]
command -v composer >/dev/null
command -v php >/dev/null

# Los dos repositorios comparten el bloqueo en el servidor.
exec 9>/var/lib/tuval-runner/deploy.lock
flock -w 900 9

live_git() { git -c safe.directory="$target" -C "$target" "$@"; }
[[ -z $(live_git ls-files -- .env) ]] || { echo '.env no debe estar versionado.' >&2; exit 1; }

# Usar el checkout validado por Actions evita guardar credenciales de GitHub.
live_git fetch --no-tags "$source_dir" "$sha"
[[ $(live_git rev-parse --verify 'FETCH_HEAD^{commit}') == "$sha" ]] || { echo 'No se importó el commit solicitado.' >&2; exit 1; }
if live_git cat-file -e FETCH_HEAD:.env 2>/dev/null; then
    echo 'El commit de destino no debe incluir .env.' >&2
    exit 1
fi
previous=$(live_git rev-parse HEAD)
echo "Backend: $previous -> $sha"
cd "$target"

# Si falla un paso posterior, mantener 503 para no servir una instalación parcial.
trap 'echo "Despliegue incompleto. Revisar logs; Laravel puede seguir en mantenimiento. Commit anterior: $previous" >&2' ERR
php artisan down --retry=60
# develop es la fuente de verdad: descartar cambios locales del código como en Jenkins.
# No ejecutar git clean: conservar .env, uploads y demás archivos no versionados.
live_git reset --hard FETCH_HEAD
live_git checkout -B develop
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan config:cache
chmod 640 bootstrap/cache/config.php
php artisan view:cache
php artisan route:list --json >/dev/null
php artisan queue:restart
php artisan up

status=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
    --max-time 20 --header 'Host: tuvalapi.cosenco.ec' http://127.0.0.1/api/login)
[[ $status == 405 ]] || { echo "Verificación HTTP fallida: $status (esperado 405)." >&2; exit 1; }
echo "Backend desplegado: $sha"
