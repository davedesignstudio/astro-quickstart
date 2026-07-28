#!/bin/sh
set -eu

WP_PATH="/var/www/html"
MARKER="${WP_PATH}/.ros-setup-complete"

if [ -f "$MARKER" ]; then
  echo "Restaurant setup already completed."
  exit 0
fi

echo "Waiting for WordPress files..."
for i in $(seq 1 60); do
  if [ -f "${WP_PATH}/wp-config.php" ]; then
    break
  fi
  sleep 2
done

if [ ! -f "${WP_PATH}/wp-config.php" ]; then
  echo "WordPress not ready after waiting."
  exit 1
fi

cd "$WP_PATH"

# Ensure wp-config is writable for wp-cli
chmod u+w wp-config.php 2>/dev/null || true

echo "Installing WordPress..."
wp core install \
  --url="http://localhost:${WP_PORT:-8080}" \
  --title="${WP_SITE_TITLE:-Restaurant Online Ordering}" \
  --admin_user="${WP_ADMIN_USER:-admin}" \
  --admin_password="${WP_ADMIN_PASSWORD:-changeme}" \
  --admin_email="${WP_ADMIN_EMAIL:-admin@restaurant.local}" \
  --skip-email \
  --allow-root 2>/dev/null || echo "WordPress may already be installed."

echo "Installing WooCommerce..."
wp plugin install woocommerce --activate --allow-root

echo "Activating Restaurant Order System..."
wp plugin activate restaurant-order-system --allow-root

echo "Configuring WooCommerce for restaurant ordering..."
wp option update woocommerce_currency "USD" --allow-root
wp option update woocommerce_currency_pos "left" --allow-root
wp option update woocommerce_enable_guest_checkout "yes" --allow-root
wp option update woocommerce_enable_signup_and_login_from_checkout "no" --allow-root

echo "Creating sample menu products..."
wp eval-file /scripts/seed-menu.php --allow-root

echo "Setting permalinks..."
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

touch "$MARKER"
echo "Restaurant ordering system setup complete."
