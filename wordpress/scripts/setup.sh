#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example"
fi

# shellcheck disable=SC1091
source .env

WP_PORT="${WP_PORT:-8080}"
SITE_URL="${SITE_URL:-http://localhost:${WP_PORT}}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
TITLE="${SITE_TITLE:-Harbor & Hearth}"

echo "==> Starting WordPress stack..."
docker compose up -d db wordpress
docker compose up -d wpcli

echo "==> Waiting for WordPress files..."
for i in $(seq 1 60); do
  if docker compose exec -T wordpress test -f /var/www/html/wp-settings.php; then
    break
  fi
  sleep 2
done

echo "==> Waiting for database..."
DB_OK=0
for i in $(seq 1 60); do
  if docker compose exec -T wpcli wp db check --quiet 2>/dev/null; then
    DB_OK=1
    break
  fi
  sleep 2
done
if [[ "$DB_OK" -ne 1 ]]; then
  echo "Database not reachable from the wpcli container."
  echo "If Docker bridge networking is blocked in your environment, try:"
  echo "  docker compose -f docker-compose.yml -f docker-compose.host.yml up -d"
  echo "Or install WordPress locally and symlink the plugin/theme from this repo."
  exit 1
fi

if ! docker compose exec -T wpcli wp core is-installed 2>/dev/null; then
  echo "==> Installing WordPress..."
  docker compose exec -T wpcli wp core install \
    --url="$SITE_URL" \
    --title="$TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Installing WooCommerce..."
docker compose exec -T wpcli wp plugin install woocommerce --activate

echo "==> Activating restaurant plugin + theme..."
docker compose exec -T wpcli wp plugin activate restaurant-kitchen-tickets
docker compose exec -T wpcli wp theme activate bistro-order

echo "==> Creating pages and WooCommerce defaults..."
docker compose exec -T wpcli wp wc tool run install_pages --user=1 || true
docker compose exec -T wpcli wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json || true
docker compose exec -T wpcli wp option update woocommerce_show_marketplace_suggestions no || true
docker compose exec -T wpcli wp option update woocommerce_allow_tracking no || true
docker compose exec -T wpcli wp option update woocommerce_currency USD
docker compose exec -T wpcli wp option update woocommerce_default_country US:CA
docker compose exec -T wpcli wp option update woocommerce_calc_taxes no
docker compose exec -T wpcli wp option update woocommerce_enable_guest_checkout yes
docker compose exec -T wpcli wp option update woocommerce_enable_checkout_login_reminder yes

# Cash on delivery / pay at pickup for restaurants.
docker compose exec -T wpcli wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Pay at pickup / delivery","description":"Pay when you receive your order.","instructions":"Please have payment ready.","enable_for_methods":"","enable_for_virtual":"yes"}' --format=json

# Shipping: local pickup + flat rate delivery.
docker compose exec -T wpcli wp wc shipping_zone create --name="Local" --user=1 >/tmp/rkt_zone.json 2>/dev/null || true
ZONE_ID=$(docker compose exec -T wpcli wp wc shipping_zone list --user=1 --format=csv 2>/dev/null | awk -F, 'NR==2{print $1}' || true)
if [[ -n "${ZONE_ID:-}" && "$ZONE_ID" != "id" ]]; then
  docker compose exec -T wpcli wp wc shipping_zone_method create "$ZONE_ID" --method_id=local_pickup --user=1 || true
  docker compose exec -T wpcli wp wc shipping_zone_method create "$ZONE_ID" --method_id=flat_rate --settings='{"title":"Delivery","cost":"4.99"}' --user=1 || true
fi

echo "==> Flushing permalinks + creating kitchen display route..."
docker compose exec -T wpcli wp rewrite structure '/%postname%/' --hard
docker compose exec -T wpcli wp rewrite flush --hard

echo "==> Seeding sample menu..."
docker compose exec -T wpcli wp eval-file /scripts/seed-menu.php

echo "==> Configuring kitchen ticket defaults..."
docker compose exec -T wpcli wp option update rkt_settings "$(cat <<JSON
{
  "restaurant_name": "$TITLE",
  "auto_print_enabled": "1",
  "print_on_statuses": ["processing", "on-hold", "pending"],
  "print_method": "browser",
  "printnode_api_key": "${PRINTNODE_API_KEY:-}",
  "printnode_printer_id": "${PRINTNODE_PRINTER_ID:-}",
  "ticket_copies": 1,
  "show_prices": "0",
  "show_customer_phone": "1",
  "kitchen_sound": "1",
  "poll_interval_seconds": 4,
  "pickup_label": "Pickup",
  "delivery_label": "Delivery",
  "dine_in_label": "Dine-in",
  "enable_pickup": "1",
  "enable_delivery": "1",
  "enable_dine_in": "0",
  "default_prep_minutes": 25
}
JSON
)" --format=json

# Ensure print queue table exists.
docker compose exec -T wpcli wp eval 'RKT_Print_Queue::create_table(); echo "queue ready\n";'

echo
echo "============================================"
echo " Restaurant ordering is ready"
echo " Storefront:     $SITE_URL"
echo " Admin:          $SITE_URL/wp-admin"
echo " Kitchen display:$SITE_URL/kitchen-display/"
echo " Login:          $ADMIN_USER / $ADMIN_PASS"
echo "============================================"
echo "Keep the kitchen display page open on the printer computer."
echo "Optional: add PrintNode API credentials in WooCommerce → Kitchen Tickets."
