#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="brandpulse"
REPO_NAME="glpi-brandpulse"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-${GITHUB_REF_NAME:-dev}}"
VERSION="${TAG_NAME#v}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/build"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ARCHIVE="${DIST_DIR}/${REPO_NAME}-${VERSION}.zip"

cd "${ROOT_DIR}"

PLUGIN_VERSION="$(sed -n "s/^const PLUGIN_BRANDPULSE_VERSION = '\([^']*\)';/\1/p" setup.php)"
if [[ -z "${PLUGIN_VERSION}" ]]; then
  echo "Unable to read PLUGIN_BRANDPULSE_VERSION from setup.php" >&2
  exit 1
fi

if [[ "${TAG_NAME}" == v* && "${VERSION}" != "${PLUGIN_VERSION}" ]]; then
  echo "Tag ${TAG_NAME} does not match plugin version ${PLUGIN_VERSION}" >&2
  exit 1
fi

for command in composer php rsync python3; do
  if ! command -v "${command}" >/dev/null 2>&1; then
    echo "Missing required command: ${command}" >&2
    exit 1
  fi
done

rm -rf "${DIST_DIR}"
mkdir -p "${PACKAGE_DIR}"

rsync -a ./ "${PACKAGE_DIR}/" \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude 'dist/' \
  --exclude 'vendor/' \
  --exclude '.gitignore' \
  --exclude 'composer.lock'

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"

if command -v zip >/dev/null 2>&1; then
  (
    cd "${BUILD_DIR}"
    zip -qr "${ARCHIVE}" "${PLUGIN_SLUG}"
  )
else
  ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_SLUG="${PLUGIN_SLUG}" python3 - <<'PYZIP'
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_slug = os.environ['PLUGIN_SLUG']
plugin_dir = build_dir / plugin_slug

with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as zf:
    for path in plugin_dir.rglob('*'):
        if path.is_file():
            zf.write(path, path.relative_to(build_dir))
PYZIP
fi

echo "${ARCHIVE}"
