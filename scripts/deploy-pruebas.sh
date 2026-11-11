#!/usr/bin/env bash
set -euo pipefail
umask 0022

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
live_git diff --quiet
live_git diff --cached --quiet
[[ -z $(live_git ls-files -- .env) ]] || { echo '.env no debe estar versionado.' >&2; exit 1; }

# Usar el checkout validado por Actions evita guardar credenciales de GitHub.
live_git fetch --no-tags "$source_dir" "$sha"
previous=$(live_git rev-parse HEAD)
echo "Backend: $previous -> $sha"
cd "$target"

# Si falla un paso posterior, mantener 503 para no servir una instalación parcial.
trap 'echo "Despliegue incompleto. Revisar logs; Laravel puede seguir en mantenimiento. Commit anterior: $previous" >&2' ERR
php artisan down --retry=60
live_git checkout -B develop FETCH_HEAD
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan route:list --json >/dev/null
php artisan queue:restart
php artisan up

status=$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
    --max-time 20 --header 'Host: tuvalapi.cosenco.ec' http://127.0.0.1/api/login)
[[ $status == 405 ]] || { echo "Verificación HTTP fallida: $status (esperado 405)." >&2; exit 1; }
echo "Backend desplegado: $sha"
