#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKIP_ENCRYPT=0
OUTPUT_DIR=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-encrypt)
            SKIP_ENCRYPT=1
            shift
            ;;
        *)
            OUTPUT_DIR="$1"
            shift
            ;;
    esac
done

OUTPUT_DIR="${OUTPUT_DIR:-${PROJECT_ROOT}/dist/powerps-core}"
PHP_VERSION="$(tr -d '[:space:]' < "${PROJECT_ROOT}/.powerps-php-version")"
BOLT_VERSION="$(tr -d '[:space:]' < "${PROJECT_ROOT}/.powerps-bolt-version")"
ARCH="$(uname -m)"

if [[ "${SKIP_ENCRYPT}" -eq 0 ]]; then
    echo "==> Encrypting app and routes (database stays plain)"
    "${PROJECT_ROOT}/tools/encrypt-source.sh" --source=app,routes --force
else
    echo "==> Skipping encryption (using existing encrypted/ directory)"
fi

echo "==> Preparing release directory: ${OUTPUT_DIR}"
rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}"

RSYNC_EXCLUDES=(
    --exclude '.git'
    --exclude '.env'
    --exclude '.env.*'
    --exclude 'node_modules'
    --exclude 'vendor'
    --exclude 'encrypted'
    --exclude 'dist'
    --exclude 'tests'
    --exclude 'install.sh'
    --exclude '.powerps-php-version'
    --exclude '.powerps-bolt-version'
    --exclude 'config/source-encrypter.php'
    --exclude '.phpunit.cache'
    --exclude '.phpunit.result.cache'
    --exclude 'storage/logs/*'
    --exclude 'storage/framework/cache/*'
    --exclude 'storage/framework/sessions/*'
    --exclude 'storage/framework/views/*'
)

rsync -a "${RSYNC_EXCLUDES[@]}" "${PROJECT_ROOT}/" "${OUTPUT_DIR}/"

echo "==> Overlaying encrypted app and routes (database from source)"
rsync -a "${PROJECT_ROOT}/encrypted/app/" "${OUTPUT_DIR}/app/"
rsync -a "${PROJECT_ROOT}/encrypted/routes/" "${OUTPUT_DIR}/routes/"

pick_bolt() {
    local arch="$1"
    case "${arch}" in
        x86_64|amd64)
            echo "${PROJECT_ROOT}/tools/phpbolt/x86_64/bolt.so"
            ;;
        aarch64|arm64)
            echo "${PROJECT_ROOT}/tools/phpbolt/aarch64/bolt.so"
            ;;
        *)
            echo "Unsupported architecture: ${arch}" >&2
            exit 1
            ;;
    esac
}

BOLT_SRC="$(pick_bolt "${ARCH}")"
cp "${BOLT_SRC}" "${OUTPUT_DIR}/bolt.so"
cp "${PROJECT_ROOT}/tools/phpbolt/x86_64/bolt.so" "${OUTPUT_DIR}/bolt-x86_64.so"
cp "${PROJECT_ROOT}/tools/phpbolt/aarch64/bolt.so" "${OUTPUT_DIR}/bolt-aarch64.so"
cp "${PROJECT_ROOT}/php.ini" "${OUTPUT_DIR}/php.ini"
cp "${PROJECT_ROOT}/chabok-php.ini" "${OUTPUT_DIR}/chabok-php.ini"
cp "${PROJECT_ROOT}/.powerps-php-version" "${OUTPUT_DIR}/.powerps-php-version"
cp "${PROJECT_ROOT}/.powerps-bolt-version" "${OUTPUT_DIR}/.powerps-bolt-version"
cp "${PROJECT_ROOT}/tools/powerps-core-README.md" "${OUTPUT_DIR}/README.md"

mkdir -p "${OUTPUT_DIR}/storage/logs" "${OUTPUT_DIR}/storage/framework/cache" \
    "${OUTPUT_DIR}/storage/framework/sessions" "${OUTPUT_DIR}/storage/framework/views"
touch "${OUTPUT_DIR}/storage/logs/.gitkeep"

cat > "${OUTPUT_DIR}/RELEASE.txt" <<EOF
PowerPs Core release
PHP: ${PHP_VERSION}
phpBolt: ${BOLT_VERSION}
Built on: $(date -u '+%Y-%m-%d %H:%M:%S UTC')
Host arch: ${ARCH}
EOF

echo "==> Release ready at ${OUTPUT_DIR}"
echo "    PHP ${PHP_VERSION} | phpBolt ${BOLT_VERSION} | bolt.so arch: ${ARCH}"
echo ""
echo "Next steps:"
echo "  cd ${OUTPUT_DIR}"
echo "  git init && git remote add origin git@github.com:rezahajrahimi/powerps-core.git"
echo "  git add -A && git commit -m 'release: PHP ${PHP_VERSION} / phpBolt ${BOLT_VERSION}'"
echo "  git push origin main"
