#!/usr/bin/env bash
set -euo pipefail
umask 0002

target=/var/www/webtuval
secrets=/var/lib/tuval-runner/appmovil-signing
artifact=$(realpath "${1:?Falta directorio del artefacto}")
[[ $(hostname -s) == srv-web02 ]]
[[ ${GITHUB_REPOSITORY:-} == TuvalSystems/appmovil ]]
[[ ${GITHUB_REF:-} == refs/heads/develop ]]
[[ ${GITHUB_SHA:-} =~ ^[0-9a-f]{40}$ ]]
[[ ${GITHUB_RUN_ID:-} =~ ^[0-9]+$ ]]
[[ -s $artifact/app-qa-release.apk && -s $artifact/manifest.json ]]

# El backend y los demás despliegues usan este mismo bloqueo.
exec 9>/var/lib/tuval-runner/deploy.lock
flock -w 900 9
latest=$(curl --fail --silent --show-error --retry 3 \
    --header "Authorization: Bearer ${GH_TOKEN:?Falta GH_TOKEN}" \
    --header 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/TuvalSystems/appmovil/git/ref/heads/develop" \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["object"]["sha"])')
[[ $latest == "$GITHUB_SHA" ]] || { echo 'develop avanzó; no se publica un commit anterior.' >&2; exit 1; }

work=$(mktemp -d /var/lib/tuval-runner/apk-publicacion.XXXXXX)
trap 'rm -rf -- "$work"' EXIT
aapt dump badging "$artifact/app-qa-release.apk" > "$work/badging.txt"
python3 - "$artifact" "$work/badging.txt" <<'PY'
import hashlib, json, os, pathlib, re, sys
artifact = pathlib.Path(sys.argv[1])
metadata = json.loads((artifact / 'manifest.json').read_text())
badging = pathlib.Path(sys.argv[2]).read_text()
match = re.search(r"^package: name='([^']+)' versionCode='(\d+)' versionName='([^']+)'", badging, re.M)
if not match:
    raise SystemExit('No se puede leer el paquete Android.')
package, build, version = match.groups()
expected = {'package': package, 'buildNumber': int(build), 'versionName': version,
            'commit': os.environ['GITHUB_SHA'],
            'runUrl': 'https://github.com/TuvalSystems/appmovil/actions/runs/' + os.environ['GITHUB_RUN_ID'],
            'sha256': hashlib.sha256((artifact / 'app-qa-release.apk').read_bytes()).hexdigest()}
if 'releaseNotes' in metadata:
    notes = metadata['releaseNotes']
    if not isinstance(notes, str) or not notes.strip() or len(notes) > 20000:
        raise SystemExit('Notas de versión inválidas.')
    expected['releaseNotes'] = notes
if metadata != expected or package != 'com.tuvalsa.tuvalapp.qa' or not version.endswith('-qa'):
    raise SystemExit('La APK y el manifiesto de QA no coinciden.')
PY

# La clave de firma nunca se sube a GitHub ni al repositorio.
apksigner sign --ks "$secrets/release.jks" \
    --ks-key-alias "$(cat "$secrets/key-alias")" \
    --ks-pass "file:$secrets/store-password" --key-pass "file:$secrets/key-password" \
    --out "$work/app-qa-release.apk" "$artifact/app-qa-release.apk"
apksigner verify --print-certs "$work/app-qa-release.apk" > "$work/certificate.txt"
actual=$(sed -n 's/^Signer #1 certificate SHA-256 digest: //p' "$work/certificate.txt")
[[ $actual == "$(cat "$secrets/certificate-sha256")" ]] || { echo 'Firma inesperada.' >&2; exit 1; }
python3 - "$artifact/manifest.json" "$work" <<'PY'
import hashlib, json, pathlib, sys
metadata = json.loads(pathlib.Path(sys.argv[1]).read_text())
work = pathlib.Path(sys.argv[2])
metadata['sha256'] = hashlib.sha256((work / 'app-qa-release.apk').read_bytes()).hexdigest()
(work / 'manifest.json').write_text(json.dumps(metadata))
PY
cd "$target"
php artisan app:publicar-apk-pruebas "$work/app-qa-release.apk" "$work/manifest.json" --no-interaction

curl --fail --silent --show-error --max-time 30 \
    --header 'Host: tuvalapi.cosenco.ec' \
    http://127.0.0.1/api/movil/app/latest > "$work/latest.json"
python3 - "$work" <<'PY'
import json, pathlib, sys, urllib.request, hashlib
work = pathlib.Path(sys.argv[1])
expected = json.loads((work / 'manifest.json').read_text())
response = json.loads((work / 'latest.json').read_text())['data']
assert response['latest_build'] == expected['buildNumber'], 'latest no devuelve el nuevo build'
assert response['checksum_sha256'] == expected['sha256'], 'Checksum publicado incorrecto'
url = response['apk_url']
from urllib.parse import urlsplit
parsed = urlsplit(url)
assert parsed.hostname == 'tuvalapi.cosenco.ec' and parsed.path.startswith('/storage/apks/'), 'URL inesperada'
request = urllib.request.Request('http://127.0.0.1' + parsed.path, headers={'Host': 'tuvalapi.cosenco.ec'})
with urllib.request.urlopen(request, timeout=60) as apk:
    digest = hashlib.sha256()
    for chunk in iter(lambda: apk.read(1024 * 1024), b''):
        digest.update(chunk)
    assert digest.hexdigest() == expected['sha256'], 'Descarga incompleta'
print('APK QA publicada y descarga verificada:', response['latest_build'])
PY
