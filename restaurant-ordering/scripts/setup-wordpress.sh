#!/bin/sh
set -e

echo "Installing plugin dependencies..."
if [ -f /var/www/html/wp-content/plugins/restaurant-order-print/composer.json ]; then
  cd /var/www/html/wp-content/plugins/restaurant-order-print
  if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader 2>/dev/null || true
  fi
fi

echo "Waiting for WordPress to be ready..."
until wp core is-installed --path=/var/www/html --allow-root 2>/dev/null; do
  sleep 3
done

echo "Installing WooCommerce..."
wp plugin install woocommerce --activate --path=/var/www/html --allow-root 2>/dev/null || true

echo "Activating Restaurant Order Print plugin..."
wp plugin activate restaurant-order-print --path=/var/www/html --allow-root 2>/dev/null || true

echo "Configuring WooCommerce for restaurant ordering..."
wp option update woocommerce_enable_guest_checkout yes --path=/var/www/html --allow-root
wp option update woocommerce_enable_checkout_login_reminder no --path=/var/www/html --allow-root
wp option update woocommerce_enable_signup_and_login_from_checkout no --path=/var/www/html --allow-root

# Create sample menu categories and products if none exist
PRODUCT_COUNT=$(wp post list --post_type=product --format=count --path=/var/www/html --allow-root 2>/dev/null || echo "0")
if [ "$PRODUCT_COUNT" = "0" ]; then
  echo "Creating sample restaurant menu..."

  wp term create product_cat "Appetizers" --slug=appetizers --path=/var/www/html --allow-root
  wp term create product_cat "Main Dishes" --slug=main-dishes --path=/var/www/html --allow-root
  wp term create product_cat "Sides" --slug=sides --path=/var/www/html --allow-root
  wp term create product_cat "Drinks" --slug=drinks --path=/var/www/html --allow-root

  wp wc product create \
    --name="Garlic Bread" \
    --type=simple \
    --regular_price=5.99 \
    --categories='[{"id":'"$(wp term list product_cat --slug=appetizers --field=term_id --path=/var/www/html --allow-root)"'}]' \
    --user=1 --path=/var/www/html --allow-root

  wp wc product create \
    --name="Margherita Pizza" \
    --type=simple \
    --regular_price=14.99 \
    --categories='[{"id":'"$(wp term list product_cat --slug=main-dishes --field=term_id --path=/var/www/html --allow-root)"'}]' \
    --user=1 --path=/var/www/html --allow-root

  wp wc product create \
    --name="Grilled Chicken" \
    --type=simple \
    --regular_price=16.99 \
    --categories='[{"id":'"$(wp term list product_cat --slug=main-dishes --field=term_id --path=/var/www/html --allow-root)"'}]' \
    --user=1 --path=/var/www/html --allow-root

  wp wc product create \
    --name="French Fries" \
    --type=simple \
    --regular_price=4.99 \
    --categories='[{"id":'"$(wp term list product_cat --slug=sides --field=term_id --path=/var/www/html --allow-root)"'}]' \
    --user=1 --path=/var/www/html --allow-root

  wp wc product create \
    --name="Soft Drink" \
    --type=simple \
    --regular_price=2.99 \
    --categories='[{"id":'"$(wp term list product_cat --slug=drinks --field=term_id --path=/var/www/html --allow-root)"'}]' \
    --user=1 --path=/var/www/html --allow-root
fi

echo "WordPress restaurant ordering setup complete."
