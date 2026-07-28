#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "Seeding sample restaurant menu..."
docker compose run --rm wpcli eval-file /scripts/seed-menu.php
