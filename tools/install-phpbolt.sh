#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BOLT_SRC="${PROJECT_ROOT}/tools/phpbolt/bolt.so"
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
EXT_DIR="$(php -r 'echo ini_get("extension_dir");')"
ARCH="$(uname -m)"
DOWNLOAD_ROOT="${PHPBOLT_DOWNLOAD:-$HOME/Downloads/phpBolt-extension-1.0.7/phpBolt-extension-1.0.7}"

if [[ ! -f "$BOLT_SRC" ]]; then
    case "$ARCH" in
        x86_64|amd64)
            BOLT_DOWNLOAD="${PROJECT_ROOT}/tools/phpbolt/x86_64/bolt.so"
            [[ -f "$BOLT_DOWNLOAD" ]] || BOLT_DOWNLOAD="${DOWNLOAD_ROOT}/linux 64/linux-php${PHP_VERSION}/x86_64-linux/bolt.so"
            ;;
        aarch64|arm64)
            BOLT_DOWNLOAD="${PROJECT_ROOT}/tools/phpbolt/aarch64/bolt.so"
            [[ -f "$BOLT_DOWNLOAD" ]] || BOLT_DOWNLOAD="${DOWNLOAD_ROOT}/linux 64/linux-php${PHP_VERSION}/aarch64-linux/bolt.so"
            ;;
        *)
            echo "Unsupported architecture: $ARCH" >&2
            exit 1
            ;;
    esac

    if [[ ! -f "$BOLT_DOWNLOAD" ]]; then
        echo "bolt.so not found for PHP ${PHP_VERSION} (${ARCH})." >&2
        echo "Download phpBolt from https://phpbolt.com/download-phpbolt/ and set PHPBOLT_DOWNLOAD." >&2
        exit 1
    fi

    mkdir -p "${PROJECT_ROOT}/tools/phpbolt"
    cp "$BOLT_DOWNLOAD" "$BOLT_SRC"
    case "$ARCH" in
        x86_64|amd64) cp "$BOLT_DOWNLOAD" "${PROJECT_ROOT}/tools/phpbolt/x86_64/bolt.so" ;;
        aarch64|arm64) cp "$BOLT_DOWNLOAD" "${PROJECT_ROOT}/tools/phpbolt/aarch64/bolt.so" ;;
    esac
fi

if php -m | grep -qi '^bolt$'; then
    echo "phpBolt is already loaded."
    exit 0
fi

if [[ -w "$EXT_DIR" ]]; then
    cp "$BOLT_SRC" "${EXT_DIR}/bolt.so"
    INI_DIR="$(php --ini | awk -F': ' '/Scan for additional .ini files in/{print $2}')"
    echo "extension=bolt.so" > "${INI_DIR}/99-bolt.ini"
    echo "Installed bolt.so to ${EXT_DIR}"
elif command -v sudo >/dev/null 2>&1; then
    sudo cp "$BOLT_SRC" "${EXT_DIR}/bolt.so"
    echo "extension=bolt.so" | sudo tee "/etc/php/${PHP_VERSION}/mods-available/bolt.ini" >/dev/null
    sudo phpenmod -v "${PHP_VERSION}" bolt 2>/dev/null || true
    echo "Installed bolt.so system-wide with sudo."
else
    echo "Cannot write to ${EXT_DIR}. Run with sudo or load bolt manually:" >&2
    echo "  php -d extension=${BOLT_SRC} artisan encrypt-source" >&2
    exit 1
fi

php -m | grep -qi '^bolt$' && echo "phpBolt enabled successfully."
