#!/usr/bin/env bash
# Bootstrap WordPress, WooCommerce, theme, and sample restaurant menu.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ -f .env ]; then
  # shellcheck disable=SC1091
  set -a && source .env && set +a
fi

WP_URL="${WP_URL:-http://localhost:8080}"
WP_TITLE="${WP_TITLE:-Harbor Kitchen}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin123}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

echo "==> Waiting for WordPress..."
for i in $(seq 1 60); do
  if docker compose exec -T wordpress curl -sf "$WP_URL" >/dev/null 2>&1 \
    || docker compose exec -T wordpress curl -sf http://localhost >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

wp() {
  docker compose exec -T wpcli wp "$@" --allow-root
}

echo "==> Installing WordPress core (if needed)..."
if ! wp core is-installed 2>/dev/null; then
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Installing WooCommerce..."
wp plugin install woocommerce --activate || true
wp plugin activate restaurant-kitchen-print || true

echo "==> Activating restaurant theme..."
wp theme activate restaurant-order || true

echo "==> WooCommerce basics..."
wp option update woocommerce_store_address "100 Market Street" || true
wp option update woocommerce_store_city "Portland" || true
wp option update woocommerce_default_country "US:OR" || true
wp option update woocommerce_currency "USD" || true
wp option update woocommerce_calc_taxes "no" || true
wp option update woocommerce_enable_guest_checkout "yes" || true
wp option update woocommerce_enable_checkout_login_reminder "no" || true
wp option update woocommerce_ship_to_destination "billing" || true

# COD for easy local testing of kitchen print
wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Pay at pickup","description":"Pay when you pick up your order.","instructions":"Pay when you collect your order.","enable_for_methods":"","enable_for_virtual":"yes"}' --format=json || true

echo "==> Creating pages..."
SHOP_ID=$(wp post list --post_type=page --name=menu --field=ID 2>/dev/null || true)
if [ -z "$SHOP_ID" ]; then
  SHOP_ID=$(wp post create --post_type=page --post_title='Menu' --post_name=menu --post_status=publish --porcelain)
fi
CART_ID=$(wp post list --post_type=page --name=cart --field=ID 2>/dev/null || true)
if [ -z "$CART_ID" ]; then
  CART_ID=$(wp post create --post_type=page --post_title='Cart' --post_name=cart --post_status=publish --post_content='[woocommerce_cart]' --porcelain)
fi
CHECKOUT_ID=$(wp post list --post_type=page --name=checkout --field=ID 2>/dev/null || true)
if [ -z "$CHECKOUT_ID" ]; then
  CHECKOUT_ID=$(wp post create --post_type=page --post_title='Checkout' --post_name=checkout --post_status=publish --post_content='[woocommerce_checkout]' --porcelain)
fi
ACCOUNT_ID=$(wp post list --post_type=page --name=my-account --field=ID 2>/dev/null || true)
if [ -z "$ACCOUNT_ID" ]; then
  ACCOUNT_ID=$(wp post create --post_type=page --post_title='My Account' --post_name=my-account --post_status=publish --post_content='[woocommerce_my_account]' --porcelain)
fi
KITCHEN_ID=$(wp post list --post_type=page --name=kitchen-station --field=ID 2>/dev/null || true)
if [ -z "$KITCHEN_ID" ]; then
  KITCHEN_ID=$(wp post create --post_type=page --post_title='Kitchen Station' --post_name=kitchen-station --post_status=publish --post_content='[rkp_kitchen_station]' --porcelain)
fi

wp option update woocommerce_shop_page_id "$SHOP_ID" || true
wp option update woocommerce_cart_page_id "$CART_ID" || true
wp option update woocommerce_checkout_page_id "$CHECKOUT_ID" || true
wp option update woocommerce_myaccount_page_id "$ACCOUNT_ID" || true
wp option update show_on_front page || true
wp option update page_on_front "$SHOP_ID" || true

echo "==> Seeding sample menu..."
wp eval-file /scripts/seed-menu.php || true

echo "==> Flushing rewrite rules..."
wp rewrite structure '/%postname%/' --hard || true
wp rewrite flush --hard || true

echo ""
echo "============================================"
echo " Harbor Kitchen is ready"
echo "--------------------------------------------"
echo " Storefront:      $WP_URL"
echo " Admin:           $WP_URL/wp-admin"
echo "   user:          $WP_ADMIN_USER"
echo "   password:      $WP_ADMIN_PASSWORD"
echo " Kitchen Station: $WP_URL/kitchen-station/"
echo " phpMyAdmin:      http://localhost:${PMA_PORT:-8081}"
echo " MailHog:         http://localhost:${MAILHOG_PORT:-8025}"
echo "============================================"
echo ""
echo "Leave Kitchen Station open on a kitchen tablet/"
echo "computer connected to your thermal printer."
echo "New orders print automatically."
