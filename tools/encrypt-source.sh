#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BOLT_SO="${PROJECT_ROOT}/tools/phpbolt/bolt.so"
PHP_ARGS=()

if ! php -m | grep -qi '^bolt$'; then
    case "$(uname -m)" in
        x86_64|amd64) BOLT_SO="${PROJECT_ROOT}/tools/phpbolt/x86_64/bolt.so" ;;
        aarch64|arm64) BOLT_SO="${PROJECT_ROOT}/tools/phpbolt/aarch64/bolt.so" ;;
    esac

    if [[ ! -f "$BOLT_SO" ]]; then
        "${PROJECT_ROOT}/tools/install-phpbolt.sh"
    fi

    if ! php -m | grep -qi '^bolt$'; then
        PHP_ARGS=(-d "extension=${BOLT_SO}")
    fi
fi

cd "$PROJECT_ROOT"
php "${PHP_ARGS[@]}" artisan encrypt-source "$@"
