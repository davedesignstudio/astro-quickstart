#!/usr/bin/env sh
# End-to-end test: place order → kitchen ticket queued → station ack → reprint
set -eu

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
PIN="${PIN:-1234}"
PASS=0
FAIL=0

assert() {
  MSG="$1"
  shift
  if "$@"; then
    echo "PASS  $MSG"
    PASS=$((PASS + 1))
  else
    echo "FAIL  $MSG"
    FAIL=$((FAIL + 1))
  fi
}

http_code() {
  curl -s -o /dev/null -w "%{http_code}" "$1"
}

echo "==> Restaurant Kitchen Tickets e2e"
echo "    Base URL: $BASE_URL"
echo ""

# --- HTTP smoke ---
assert "homepage returns 200" test "$(http_code "$BASE_URL/")" = "200"
assert "shop returns 200" test "$(http_code "$BASE_URL/shop/")" = "200"
assert "kitchen print station returns 200" test "$(http_code "$BASE_URL/kitchen-print-station/")" = "200"
assert "REST rejects bad PIN" test "$(curl -s -o /dev/null -w "%{http_code}" -H "X-RKT-PIN: wrong" "$BASE_URL/wp-json/rkt/v1/pending-tickets")" = "401"

# --- WP / plugin state via WP-CLI ---
PLUGIN_ACTIVE=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli \
  wp plugin is-active restaurant-kitchen-tickets --path=/var/www/html && echo yes || echo no)
assert "restaurant-kitchen-tickets plugin active" test "$PLUGIN_ACTIVE" = "yes"

WC_ACTIVE=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli \
  wp plugin is-active woocommerce --path=/var/www/html && echo yes || echo no)
assert "woocommerce plugin active" test "$WC_ACTIVE" = "yes"

PRODUCT_COUNT=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli \
  wp post list --post_type=product --post_status=publish --format=count --path=/var/www/html | tr -d '[:space:]')
assert "sample menu has products" test "$PRODUCT_COUNT" -ge 1

# --- Create paid processing order and trigger auto-print path ---
ORDER_JSON=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli sh -c '
set -eu
PID=$(wp post list --post_type=product --post_status=publish --field=ID --posts_per_page=1 --path=/var/www/html | head -n1)
OID=$(wp wc shop_order create \
  --status=processing \
  --set_paid=true \
  --billing='\''{"first_name":"Test","last_name":"Diner","email":"test@example.com","phone":"555-0111","address_1":"1 Test St","city":"Portland","state":"OR","postcode":"97201","country":"US"}'\'' \
  --line_items="[{\"product_id\":$PID,\"quantity\":1}]" \
  --customer_note="E2E test order" \
  --user=1 \
  --porcelain \
  --path=/var/www/html 2>/dev/null)

# Simulate checkout restaurant fields + hook path
wp eval "
\$order = wc_get_order($OID);
\$order->update_meta_data(\"_rkt_order_type\", \"pickup\");
\$order->update_meta_data(\"_rkt_desired_time\", \"ASAP\");
\$order->update_meta_data(\"_rkt_kitchen_notes\", \"Allergy: peanuts\");
\$order->update_meta_data(\"_rkt_print_status\", \"queued\");
\$order->save();
// Clear lock then run the same print service checkout uses
delete_transient(\"rkt_print_lock_$OID\");
\$result = RKT\\Print_Service::print_order($OID, false);
echo wp_json_encode(array(
  \"id\" => $OID,
  \"result\" => \$result,
  \"print_status\" => wc_get_order($OID)->get_meta(\"_rkt_print_status\"),
  \"plain\" => RKT\\Ticket_Generator::plain_text(wc_get_order($OID)),
));
" --path=/var/www/html
')

ORDER_ID=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys,json; print(json.load(sys.stdin)["id"])')
PRINT_STATUS=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys,json; print(json.load(sys.stdin)["print_status"])')
PRINT_OK=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys,json; print("yes" if json.load(sys.stdin)["result"].get("success") else "no")')
HAS_ITEMS=$(printf '%s' "$ORDER_JSON" | python3 -c 'import sys,json; t=json.load(sys.stdin)["plain"]; print("yes" if "ORDER #" in t and "Test Diner" in t else "no")')

assert "order created (id=$ORDER_ID)" test -n "$ORDER_ID"
assert "print service succeeded" test "$PRINT_OK" = "yes"
assert "print status is pending_station" test "$PRINT_STATUS" = "pending_station"
assert "ticket plain text includes customer + order" test "$HAS_ITEMS" = "yes"

# --- REST pending tickets ---
PENDING=$(curl -s -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/pending-tickets")
IN_QUEUE=$(printf '%s' "$PENDING" | python3 -c "import sys,json; ids=[t['id'] for t in json.load(sys.stdin).get('tickets',[])]; print('yes' if $ORDER_ID in ids else 'no')")
assert "order appears in pending-tickets REST queue" test "$IN_QUEUE" = "yes"

TICKET=$(curl -s -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/ticket/$ORDER_ID")
TICKET_HTML_OK=$(printf '%s' "$TICKET" | python3 -c 'import sys,json; h=json.load(sys.stdin).get("html",""); print("yes" if "KITCHEN TICKET" in h and "Test Diner" in h else "no")')
assert "ticket HTML endpoint renders kitchen ticket" test "$TICKET_HTML_OK" = "yes"

# --- Station acknowledge printed ---
ACK=$(curl -s -o /tmp/rkt-ack.json -w "%{http_code}" -X POST -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/tickets/$ORDER_ID/printed")
assert "station mark-printed returns 200" test "$ACK" = "200"

AFTER_STATUS=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli \
  wp eval "echo wc_get_order($ORDER_ID)->get_meta('_rkt_print_status');" --path=/var/www/html | tr -d '[:space:]')
assert "print status becomes printed after ack" test "$AFTER_STATUS" = "printed"

PENDING2=$(curl -s -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/pending-tickets")
GONE=$(printf '%s' "$PENDING2" | python3 -c "import sys,json; ids=[t['id'] for t in json.load(sys.stdin).get('tickets',[])]; print('yes' if $ORDER_ID not in ids else 'no')")
assert "order leaves pending queue after ack" test "$GONE" = "yes"

# --- Reprint path ---
REPRINT=$(curl -s -o /tmp/rkt-reprint.json -w "%{http_code}" -X POST -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/tickets/$ORDER_ID/reprint")
assert "reprint endpoint returns 200" test "$REPRINT" = "200"
REPRINT_STATUS=$(docker compose -f docker-compose.yml -f docker-compose.host.yml exec -T wpcli \
  wp eval "echo wc_get_order($ORDER_ID)->get_meta('_rkt_print_status');" --path=/var/www/html | tr -d '[:space:]')
assert "reprint re-queues as pending_station" test "$REPRINT_STATUS" = "pending_station"

# Cleanup reprint so queue is not polluted for manual demos (ack again)
curl -s -X POST -H "X-RKT-PIN: $PIN" "$BASE_URL/wp-json/rkt/v1/tickets/$ORDER_ID/printed" >/dev/null

echo ""
echo "==> Results: $PASS passed, $FAIL failed (order #$ORDER_ID)"
if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
