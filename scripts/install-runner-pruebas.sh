#!/usr/bin/env bash
set -euo pipefail
umask 0077

# Ejecutar como root en srv-web02. El segundo argumento es un archivo privado
# con el token temporal de registro del repositorio correspondiente.
[[ $EUID == 0 && $(hostname -s) == srv-web02 ]] || { echo 'Usar sudo en srv-web02.' >&2; exit 1; }
[[ $(uname -m) == x86_64 ]] || { echo 'Se requiere Linux x64.' >&2; exit 1; }
component=${1:?Uso: install-runner-pruebas.sh backend|frontend /ruta/token}
token_file=${2:?Falta el archivo privado del token de registro}
[[ -s $token_file ]] || { echo 'No existe el archivo del token.' >&2; exit 1; }
[[ $(stat -c '%a' "$token_file") == 600 ]] || { echo 'El archivo del token debe tener permisos 600.' >&2; exit 1; }
case "$component" in
    backend) repo=tuvalBackend; target=/var/www/webtuval ;;
    frontend) repo=TuvalFront; target=/var/www/TuvalFront ;;
    *) echo 'Componente desconocido.' >&2; exit 1 ;;
esac
command -v setfacl >/dev/null
[[ -d $target/.git ]]
runner_user=tuval-runner
runner_dir=/var/lib/tuval-runner/$component
version=2.338.2
checksum=baebf204307bac6193480852732f0aba2ae97b6c5597c244f4d5558390147c40

if ! id "$runner_user" >/dev/null 2>&1; then
    useradd --create-home --home-dir /var/lib/tuval-runner --shell /bin/bash "$runner_user"
fi
usermod -a -G www-data "$runner_user"
install -d -o "$runner_user" -g "$runner_user" -m 0700 "$runner_dir"
[[ ! -e $runner_dir/.runner ]] || { echo 'Este runner ya está registrado.' >&2; exit 1; }

archive=$(mktemp /tmp/tuval-runner.XXXXXX.tar.gz)
trap 'rm -f -- "$archive"' EXIT
curl --fail --silent --show-error --location --retry 3 \
    "https://github.com/actions/runner/releases/download/v$version/actions-runner-linux-x64-$version.tar.gz" \
    --output "$archive"
echo "$checksum  $archive" | sha256sum --check --status
tar -xzf "$archive" -C "$runner_dir" --no-same-owner
chown -R "$runner_user:$runner_user" "$runner_dir"

# Acceso al proyecto; el usuario del runner no recibe sudo ni privilegios root.
# ACL conserva los propietarios y los permisos de www-data existentes.
getfacl -R -p "$target" > "/root/tuval-$component-acl-before-$(date +%Y%m%d%H%M%S).txt"
setfacl -R -m "u:$runner_user:rwX" "$target"
find "$target" -type d -exec setfacl -m "d:u:$runner_user:rwx" {} +
# Git 2.37 del servidor requiere safe.directory en la configuración global.
if ! sudo -H -u "$runner_user" git config --global --get-all safe.directory | grep -Fxq "$target"; then
    sudo -H -u "$runner_user" git config --global --add safe.directory "$target"
fi

token=$(tr -d '\r\n' < "$token_file")
cd "$runner_dir"
sudo -u "$runner_user" env ACTIONS_RUNNER_INPUT_TOKEN="$token" \
    ./config.sh --unattended --url "https://github.com/TuvalSystems/$repo" \
    --name "srv-web02-tuval-$component" --labels "tuval-$component-pruebas" --work _work
unset token
./svc.sh install "$runner_user"
chmod 700 "$runner_dir"
find "$runner_dir" -maxdepth 1 -type f -name '.credentials*' -exec chmod 600 {} +
./svc.sh start
./svc.sh status
