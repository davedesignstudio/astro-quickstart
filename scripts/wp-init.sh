#!/bin/sh
set -e

echo "Waiting for WordPress to be ready..."
sleep 15

until wp core is-installed --path=/var/www/html --allow-root 2>/dev/null; do
  echo "Installing WordPress..."
  wp core install \
    --path=/var/www/html \
    --url="http://localhost:8080" \
    --title="Restaurant Online Ordering" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@restaurant.local" \
    --skip-email \
    --allow-root 2>/dev/null || sleep 5
done

echo "WordPress installed. Configuring WooCommerce and restaurant plugin..."

wp plugin install woocommerce --activate --path=/var/www/html --allow-root
wp theme activate restaurant-ordering --path=/var/www/html --allow-root 2>/dev/null || true
wp plugin activate restaurant-order-print --path=/var/www/html --allow-root

wp option update woocommerce_store_address "123 Main Street" --path=/var/www/html --allow-root
wp option update woocommerce_store_city "Your City" --path=/var/www/html --allow-root
wp option update woocommerce_default_country "US:CA" --path=/var/www/html --allow-root
wp option update woocommerce_currency "USD" --path=/var/www/html --allow-root
wp option update woocommerce_calc_taxes "no" --path=/var/www/html --allow-root
wp option update woocommerce_enable_guest_checkout "yes" --path=/var/www/html --allow-root
wp option update woocommerce_enable_checkout_login_reminder "no" --path=/var/www/html --allow-root

wp wc tool run install_pages --user=admin --path=/var/www/html --allow-root 2>/dev/null || true

wp option update rop_restaurant_name "Demo Restaurant" --path=/var/www/html --allow-root
wp option update rop_auto_print "yes" --path=/var/www/html --allow-root
wp option update rop_print_method "daemon" --path=/var/www/html --allow-root
wp option update rop_print_on_statuses '["processing","pending"]' --path=/var/www/html --allow-root

# Create sample menu items
create_product() {
  name="$1"
  price="$2"
  desc="$3"
  existing=$(wp post list --post_type=product --name="$(echo "$name" | tr ' ' '-' | tr '[:upper:]' '[:lower:]')" --field=ID --path=/var/www/html --allow-root 2>/dev/null)
  if [ -z "$existing" ]; then
    wp wc product create \
      --name="$name" \
      --type=simple \
      --regular_price="$price" \
      --description="$desc" \
      --status=publish \
      --user=admin \
      --path=/var/www/html \
      --allow-root
  fi
}

create_product "Classic Burger" "12.99" "Angus beef patty, lettuce, tomato, pickles, house sauce"
create_product "Margherita Pizza" "14.99" "Fresh mozzarella, basil, tomato sauce"
create_product "Caesar Salad" "9.99" "Romaine, parmesan, croutons, caesar dressing"
create_product "Fish Tacos" "13.99" "Grilled fish, cabbage slaw, lime crema (3 pcs)"
create_product "Chocolate Brownie" "5.99" "Warm brownie with vanilla ice cream"
create_product "Iced Tea" "2.99" "Fresh brewed, sweet or unsweet"
create_product "Lemonade" "3.49" "House-made lemonade"

echo ""
echo "============================================"
echo " Restaurant ordering system is ready!"
echo "============================================"
echo " Site:    http://localhost:8080"
echo " Admin:   http://localhost:8080/wp-admin"
echo " Login:   admin / admin123"
echo " Shop:    http://localhost:8080/shop"
echo "============================================"
