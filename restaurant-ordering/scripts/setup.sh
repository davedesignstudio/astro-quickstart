#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

if [ ! -f .env ]; then
	cp .env.example .env
	echo "Created .env from .env.example"
fi

# shellcheck disable=SC1091
source .env

echo "Starting Docker containers..."
docker compose up -d

echo "Waiting for WordPress to be ready..."
for i in $(seq 1 60); do
	if docker compose exec -T wordpress curl -sf http://localhost/wp-admin/install.php > /dev/null 2>&1; then
		break
	fi
	sleep 3
done

WP_CLI="docker compose exec -T -u www-data wpcli wp"

echo "Installing WordPress..."
$WP_CLI core is-installed 2>/dev/null || $WP_CLI core install \
	--url="http://localhost:${WORDPRESS_PORT:-8080}" \
	--title="${WP_SITE_TITLE:-Restaurant Online Ordering}" \
	--admin_user="${WP_ADMIN_USER:-admin}" \
	--admin_password="${WP_ADMIN_PASSWORD:-admin123}" \
	--admin_email="${WP_ADMIN_EMAIL:-admin@restaurant.local}" \
	--skip-email

echo "Installing WooCommerce..."
$WP_CLI plugin is-installed woocommerce 2>/dev/null || $WP_CLI plugin install woocommerce --activate

echo "Activating restaurant plugins and theme..."
$WP_CLI plugin activate restaurant-auto-print restaurant-menu-setup
$WP_CLI theme activate restaurant-ordering

echo "Running restaurant setup..."
$WP_CLI eval 'if ( class_exists("Restaurant_Menu_Setup") ) { Restaurant_Menu_Setup::run_full_setup(); echo "Restaurant setup complete.\n"; }'

echo "Configuring kitchen printer secret..."
$WP_CLI option update rap_kitchen_api_secret "${KITCHEN_API_SECRET:-change-me-kitchen-secret}"
$WP_CLI option update rap_auto_print_enabled yes
$WP_CLI option update rap_poll_interval 3

echo "Flushing rewrite rules for kitchen display..."
$WP_CLI rewrite structure '/%postname%/' --hard
$WP_CLI rewrite flush --hard

echo ""
echo "=========================================="
echo " Restaurant Ordering System is ready!"
echo "=========================================="
echo ""
echo " Storefront:      http://localhost:${WORDPRESS_PORT:-8080}"
echo " Admin:           http://localhost:${WORDPRESS_PORT:-8080}/wp-admin"
echo " Username:        ${WP_ADMIN_USER:-admin}"
echo " Password:        ${WP_ADMIN_PASSWORD:-admin123}"
echo ""
echo " Kitchen Display: http://localhost:${WORDPRESS_PORT:-8080}/kitchen-display/"
echo " (Open on kitchen computer with printer connected)"
echo ""
echo " WooCommerce > Kitchen Printer for settings"
echo "=========================================="
