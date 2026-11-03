#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="brandpulse"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
PACKAGE_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
cd "${ROOT_DIR}"

for command in composer php rsync python3; do
  if ! command -v "${command}" >/dev/null 2>&1; then
    echo "Missing required command: ${command}" >&2
    exit 1
  fi
done

PLUGIN_VERSION="$(sed -n "s/^const PLUGIN_BRANDPULSE_VERSION = '\([^']*\)';/\1/p" setup.php)"
TAG_NAME="${1:-${PLUGIN_VERSION}}"
VERSION="${TAG_NAME#v}"
if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ || "${VERSION}" != "${PLUGIN_VERSION}" ]]; then
  echo "Build version ${TAG_NAME} does not match plugin version ${PLUGIN_VERSION}" >&2
  exit 1
fi
ARCHIVE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

composer validate --no-check-publish
php scripts/compile-translations.php
php -r '
$xml = simplexml_load_file("brandpulse.xml");
if ($xml === false || (string) $xml->versions->version[0]->num !== $argv[1]) {
    fwrite(STDERR, "Plugin XML version does not match setup.php\n");
    exit(1);
}
' "${VERSION}"

rm -rf "${DIST_DIR}"
mkdir -p "${PACKAGE_DIR}"

# An explicit manifest prevents local state and newly added private files from
# silently becoming part of a public release.
rsync -a --relative \
  setup.php hook.php logo.png brandpulse.xml composer.json composer.lock \
  README.md CHANGELOG.md LICENSE SECURITY.md THIRD_PARTY_NOTICES.md \
  docs/ARCHITECTURE_SECURITY.md src front ajax public templates locales \
  "${PACKAGE_DIR}/" \
  --exclude 'AGENTS.md' --exclude '.env*' --exclude '*:Zone.Identifier' \
  --exclude '.agents/' --exclude '.aws/' --exclude '.codex/' \
  --exclude '*.log' --exclude '*.tmp' --exclude '*.bak' \
  --exclude '*.pem' --exclude '*.key' --exclude '*.p12' --exclude '*.pfx' \
  --exclude '*.sql' --exclude '*.sql.gz'

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"

ARCHIVE="${ARCHIVE}" DIST_DIR="${DIST_DIR}" PLUGIN_SLUG="${PLUGIN_SLUG}" python3 - <<'PYZIP'
import os
import pathlib
import time
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
dist_dir = pathlib.Path(os.environ['DIST_DIR'])
slug = os.environ['PLUGIN_SLUG']
plugin_dir = dist_dir / slug
required = ['setup.php', 'hook.php', 'logo.png', 'public/logo.png', 'public/logo.svg',
            'vendor/autoload.php', 'src/Config.php',
            'src/AssetManagementLink.php', 'src/TicketView.php',
            'templates/ticket_view.html.twig', 'src/ProfileAccess.php',
            'templates/pulse_composition.html.twig', 'templates/pulse_profiles.html.twig',
            'templates/pulse_profile_permissions.html.twig',
            'public/js/brandpulse.js',
            'public/icons/pulse/index.json', 'locales/fr_FR.mo', 'locales/en_GB.mo']
for name in required:
    if not (plugin_dir / name).is_file():
        raise SystemExit(f'Missing release file: {name}')
forbidden = {'.git', '.github', '.local', '.agents', '.aws', '.codex', 'tests',
             '.phpunit.cache', 'dist', 'node_modules', 'AGENTS.md'}
# Fix ZIP metadata so identical inputs produce identical archives. Release
# systems may provide SOURCE_DATE_EPOCH; otherwise use ZIP's earliest date.
timestamp = time.gmtime(max(315532800, int(os.environ.get('SOURCE_DATE_EPOCH', '315532800'))))[:6]
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as zf:
    for path in sorted(plugin_dir.rglob('*')):
        relative = path.relative_to(plugin_dir)
        if (forbidden.intersection(relative.parts) or path.name.startswith('.env')
                or path.name.endswith(('.pem', '.key', '.p12', '.pfx', '.sql', '.sql.gz', '.log', '.tmp', '.bak', ':Zone.Identifier'))):
            raise SystemExit(f'Forbidden release file: {relative}')
        if path.is_symlink():
            raise SystemExit(f'Symlink in release: {relative}')
        if path.is_file():
            entry = zipfile.ZipInfo(str(path.relative_to(dist_dir)), timestamp)
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            zf.writestr(entry, path.read_bytes())
with zipfile.ZipFile(archive) as zf:
    if zf.testzip() is not None:
        raise SystemExit('Corrupt release archive')
    for name in required:
        if f'{slug}/{name}' not in zf.namelist():
            raise SystemExit(f'Missing archive file: {name}')
PYZIP

echo "${ARCHIVE}"
