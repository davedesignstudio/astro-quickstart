#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required. Install Docker Desktop / Engine, then re-run this script."
  exit 1
fi

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — edit passwords before production use."
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

WP_URL="${WP_URL:-http://localhost:8080}"
WP_TITLE="${WP_TITLE:-Harbor Kitchen}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-changeme}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-owner@example.com}"

wp() {
  docker compose run --rm wpcli "$@"
}

echo "Starting WordPress stack..."
docker compose up -d db wordpress

echo "Waiting for database + WordPress files..."
for i in $(seq 1 90); do
  if docker compose exec -T wordpress test -f /var/www/html/wp-settings.php 2>/dev/null; then
    if wp db check >/dev/null 2>&1; then
      break
    fi
  fi
  sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
  echo "Installing WordPress..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "Installing WooCommerce + Storefront..."
wp plugin install woocommerce --activate
wp theme install storefront --activate
wp plugin activate restaurant-order-tickets

echo "Configuring store for restaurant ordering..."
wp option update woocommerce_store_address "100 Harbor Way"
wp option update woocommerce_store_city "Portland"
wp option update woocommerce_default_country "US:OR"
wp option update woocommerce_store_postcode "97201"
wp option update woocommerce_currency "USD"
wp option update woocommerce_calc_taxes "no"
wp option update woocommerce_ship_to_countries "disabled"
wp option update woocommerce_enable_guest_checkout "yes"
wp option update woocommerce_cart_redirect_after_add "no"
wp wc tool run install_pages --user=1 || true
wp wc payment_gateway update cod --enabled=true --user=1 || true

wp rewrite structure '/%postname%/'
wp rewrite flush

if [[ -n "${ROT_PRINTNODE_API_KEY:-}" || -n "${ROT_PRINTNODE_PRINTER_ID:-}" ]]; then
  wp eval '
    $s = get_option("rot_settings", array());
    if (!is_array($s)) { $s = array(); }
    $key = getenv("ROT_PRINTNODE_API_KEY");
    $pid = getenv("ROT_PRINTNODE_PRINTER_ID");
    if ($key) { $s["printnode_api_key"] = $key; }
    if ($pid) { $s["printnode_printer_id"] = $pid; }
    $s["auto_print_enabled"] = "1";
    update_option("rot_settings", $s);
    echo "PrintNode settings saved\n";
  '
fi

bash "$ROOT_DIR/scripts/bootstrap-menu.sh"

cat <<EOF

Harbor Kitchen is ready.

  Store:           $WP_URL
  Admin:           $WP_URL/wp-admin
  Kitchen display: $WP_URL/kitchen-display/
  Login:           $WP_ADMIN_USER / $WP_ADMIN_PASSWORD

Next:
  1. Open WooCommerce → Kitchen Tickets
  2. Add your PrintNode API key + kitchen printer
  3. Place a test order — the ticket prints automatically

EOF
