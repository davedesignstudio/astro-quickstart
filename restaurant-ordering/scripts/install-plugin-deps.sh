#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/../wp-content/plugins/restaurant-order-print"

echo "Installing Restaurant Order Print plugin dependencies..."

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer not found. Install from https://getcomposer.org or run inside Docker:"
  echo "  docker compose run --rm wpcli sh -c 'cd wp-content/plugins/restaurant-order-print && composer install'"
  exit 1
fi

cd "$PLUGIN_DIR"
composer install --no-dev --optimize-autoloader

echo "Done. ESC/POS library installed in vendor/"
