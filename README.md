# Restaurant Online Ordering (WordPress + WooCommerce)

Online ordering for restaurants with **automatic kitchen ticket printing** as soon as an order is placed.

Built on WordPress + WooCommerce, plus a custom plugin (`restaurant-order-tickets`) that:

- Adds restaurant checkout fields (pickup / delivery / dine-in, requested time, kitchen notes)
- Builds a kitchen ticket the moment checkout completes
- Sends the ticket to a thermal/receipt printer via [PrintNode](https://www.printnode.com/) (no clicks)
- Falls back to a live **Kitchen Display** that chimes and browser-prints new tickets

## Quick start

### Requirements

- Docker + Docker Compose
- (Optional) Free [PrintNode](https://app.printnode.com/) account for silent auto-print

### Setup

```bash
cp .env.example .env
# optional: set ROT_PRINTNODE_API_KEY and ROT_PRINTNODE_PRINTER_ID
bash scripts/setup.sh
```

Then open:

| URL | Purpose |
| --- | --- |
| http://localhost:8080 | Customer storefront / menu |
| http://localhost:8080/wp-admin | WordPress admin |
| http://localhost:8080/kitchen-display/ | Kitchen screen (shop manager login) |

Default admin login comes from `.env` (`admin` / `changeme` unless you change it).

## How auto-print works

```text
Customer checkout
       │
       ▼
WooCommerce creates order
       │
       ▼
Restaurant Order Tickets plugin
  • builds ticket (items, type, time, notes)
  • marks order for kitchen queue
       │
       ├─► PrintNode API ──► kitchen printer (silent)
       │
       └─► Kitchen Display polls REST API
             • chime on new order
             • optional browser print backup
```

### PrintNode (recommended for silent printing)

1. Create a PrintNode account and install the desktop client on the kitchen computer.
2. Connect your receipt/thermal printer to that computer.
3. In WordPress go to **WooCommerce → Kitchen Tickets**.
4. Paste your API key, choose the printer, enable auto-print, save.
5. Place a test order — the ticket should print immediately.

Without PrintNode, keep the kitchen display open with **Auto-print new tickets** checked. New orders will still appear and print from the browser.

## Plugin features

- Immediate print hook on `woocommerce_checkout_order_processed` and Store API checkouts
- Re-print when order status moves to Processing / On hold (configurable)
- Thermal-friendly plain-text ticket + HTML ticket
- Admin **Reprint kitchen ticket** button on each order
- Shortcode `[restaurant_kitchen_display]` if you prefer a WP page over `/kitchen-display/`
- Sample menu seeder (`scripts/bootstrap-menu.sh`)

## Project layout

```text
docker-compose.yml          WordPress + MySQL + WP-CLI
mu-plugins/                 Light restaurant store defaults
scripts/setup.sh            One-command install
scripts/bootstrap-menu.sh   Sample menu items
wp-content/plugins/restaurant-order-tickets/
  restaurant-order-tickets.php
  includes/                 Settings, checkout, PrintNode, hooks, KDS, REST
  templates/                Ticket + kitchen + admin UI
  assets/                   CSS / JS / chime
```

## Manual commands

```bash
docker compose up -d
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli eval-file /scripts/seed-menu.php
docker compose down
```

## Production notes

- Change all `.env` passwords before going live
- Put WordPress behind HTTPS
- Use a real payment gateway (Stripe, Square, etc.); Cash on Delivery is enabled for local testing
- Keep the PrintNode client running on the kitchen PC
- Restrict kitchen display access to shop managers only (already enforced)

## Existing WordPress site?

Copy `wp-content/plugins/restaurant-order-tickets` into your site’s plugins folder, activate it, install WooCommerce, then configure **WooCommerce → Kitchen Tickets**.
