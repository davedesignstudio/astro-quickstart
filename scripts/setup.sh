#!/usr/bin/env sh
set -eu

SITE_URL="${SITE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-owner@restaurant.local}"
SITE_TITLE="${SITE_TITLE:-Harbor Kitchen}"

echo "==> Waiting for WordPress..."
until wp core is-installed --path=/var/www/html 2>/dev/null || wp core version --path=/var/www/html >/dev/null 2>&1; do
  sleep 2
done

if ! wp core is-installed --path=/var/www/html 2>/dev/null; then
  echo "==> Installing WordPress..."
  wp core install \
    --path=/var/www/html \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Installing WooCommerce..."
# Pin a WC release compatible with WordPress 6.7 in this stack.
# (Latest WooCommerce may require a newer WP than the pinned image.)
if ! wp plugin is-installed woocommerce --path=/var/www/html 2>/dev/null; then
  wp plugin install woocommerce --version=9.8.5 --activate --path=/var/www/html
else
  wp plugin activate woocommerce --path=/var/www/html || true
fi
wp plugin activate restaurant-kitchen-tickets --path=/var/www/html || true

echo "==> Permalinks + WooCommerce pages..."
wp rewrite structure '/%postname%/' --path=/var/www/html
wp rewrite flush --path=/var/www/html
wp wc tool run install_pages --user=1 --path=/var/www/html 2>/dev/null || true

# Enable Cash on Delivery for easy local order testing.
wp eval 'update_option("woocommerce_cod_settings", array("enabled"=>"yes","title"=>"Cash on delivery","description"=>"Pay when you pick up or receive your order.","instructions"=>"","enable_for_methods"=>array(),"enable_for_virtual"=>"yes"));' --path=/var/www/html

echo "==> Configuring WooCommerce basics..."
wp option update woocommerce_store_address "100 Market Street" --path=/var/www/html
wp option update woocommerce_store_city "Portland" --path=/var/www/html
wp option update woocommerce_default_country "US:OR" --path=/var/www/html
wp option update woocommerce_currency "USD" --path=/var/www/html
wp option update woocommerce_enable_guest_checkout "yes" --path=/var/www/html
wp option update woocommerce_ship_to_destination "billing" --path=/var/www/html

# Local pickup + flat rate delivery
wp wc shipping_zone_method create 0 --method_id=local_pickup --enabled=true --user=1 --path=/var/www/html 2>/dev/null || true
wp wc shipping_zone_method create 0 --method_id=flat_rate --enabled=true --settings='{"title":"Delivery","cost":"4.99"}' --user=1 --path=/var/www/html 2>/dev/null || true

echo "==> Creating sample menu products..."
create_product() {
  NAME="$1"
  PRICE="$2"
  CAT="$3"
  DESC="$4"

  CAT_ID=$(wp term list product_cat --field=term_id --name="$CAT" --path=/var/www/html 2>/dev/null | head -n1)
  if [ -z "$CAT_ID" ]; then
    CAT_ID=$(wp term create product_cat "$CAT" --porcelain --path=/var/www/html)
  fi

  EXISTING=$(wp post list --post_type=product --name="$(echo "$NAME" | tr '[:upper:] ' '[:lower:]-')" --field=ID --path=/var/www/html 2>/dev/null | head -n1)
  if [ -n "$EXISTING" ]; then
    echo "  skip: $NAME"
    return
  fi

  PID=$(wp wc product create \
    --name="$NAME" \
    --type=simple \
    --regular_price="$PRICE" \
    --description="$DESC" \
    --short_description="$DESC" \
    --categories="[{\"id\":$CAT_ID}]" \
    --status=publish \
    --user=1 \
    --porcelain \
    --path=/var/www/html)
  echo "  created: $NAME (#$PID)"
}

create_product "Margherita Pizza" "14.00" "Pizzas" "San Marzano tomato, fresh mozzarella, basil."
create_product "Pepperoni Pizza" "16.00" "Pizzas" "Classic pepperoni with mozzarella."
create_product "Caesar Salad" "9.50" "Salads" "Romaine, parmesan, croutons, Caesar dressing."
create_product "Garlic Knots" "6.00" "Sides" "Six knots with herb butter."
create_product "Lemonade" "3.50" "Drinks" "Fresh squeezed lemonade."
create_product "Tiramisu" "7.50" "Desserts" "Espresso-soaked ladyfingers, mascarpone."

echo ""
echo "Setup complete."
echo "  Store:   $SITE_URL"
echo "  Admin:   $SITE_URL/wp-admin  ($ADMIN_USER / $ADMIN_PASS)"
echo "  Kitchen: $SITE_URL/kitchen-print-station/"
echo "  Plugin:  WP Admin → WooCommerce → Kitchen Tickets"
