# Restaurant Online Ordering (WordPress + WooCommerce)

A complete restaurant online ordering stack with **automatic kitchen ticket printing** as soon as customers place orders.

## What's included

| Component | Purpose |
|-----------|---------|
| **Docker Compose stack** | WordPress 6.7, MySQL 8, WooCommerce |
| **Restaurant Order Print plugin** | Auto-prints ESC/POS kitchen tickets on new orders |
| **Checkout extensions** | Order type (pickup / delivery / dine-in), table number, pickup time |
| **Admin settings** | Printer IP, print timing, ticket copies, test print |

## How auto-print works

1. Customer completes checkout on your WooCommerce store.
2. The plugin hooks `woocommerce_checkout_order_processed` (and the Store API for block checkout).
3. A formatted kitchen ticket is sent to your **network thermal printer** via ESC/POS over TCP (default port **9100**).
4. Print status is logged on the order; you can **reprint** from the order actions menu.

```
Customer checkout → WooCommerce order created → Plugin formats ticket → ESC/POS → Kitchen printer
```

### Print timing

In **WooCommerce → Restaurant Print**, set **Print When Order Is**:

- **Placed (immediately at checkout)** — prints as soon as the order is submitted (recommended for restaurants).
- **Processing** — prints after payment is confirmed.
- **Pending / On hold** — prints when the order reaches that status.

## Quick start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose

### 1. Start the stack

```bash
cd restaurant-ordering
docker compose up -d
```

First run installs plugin dependencies (Composer), starts WordPress, and runs the setup script (WooCommerce + sample menu).

### 2. Complete WordPress setup

Open http://localhost:8080 and finish the WordPress installer:

- Site title: your restaurant name
- Admin username / password: choose your own

The setup container will then install WooCommerce, activate the plugin, and add sample menu items.

To re-run setup manually:

```bash
docker compose run --rm wpcli sh /setup-wordpress.sh
```

### 3. Configure the kitchen printer

1. Go to **WooCommerce → Restaurant Print** in wp-admin.
2. Set **Printer IP / Host** to your thermal printer's network address (e.g. `192.168.1.100`).
3. Set **Print When Order Is** to **Placed (immediately at checkout)**.
4. Click **Save Changes**, then **Print Test Ticket**.

#### Printer requirements

- Network-connected ESC/POS thermal printer (Epson, Star, Bixolon, etc.)
- Raw TCP printing on port **9100** (standard for most kitchen printers)
- Printer must be reachable from the WordPress server (same LAN or routed network)

For Docker on Mac/Windows, use `host.docker.internal` as the printer host if the printer is on your local machine network via the host.

### 4. Add your menu

1. **Products → Add New** — create dishes with prices and photos.
2. Organize with **Product categories** (Appetizers, Entrees, etc.).
3. Optional: use WooCommerce **Product Add-Ons** for modifiers (extra cheese, spice level).

### 5. Test an order

1. Visit the shop page and add items to cart.
2. Checkout as a guest (guest checkout is enabled by default).
3. Select order type (Pickup / Delivery) and submit.
4. Kitchen ticket should print immediately.

## Plugin installation (existing WordPress)

If you already run WordPress elsewhere:

1. Copy `wp-content/plugins/restaurant-order-print` into your site's `wp-content/plugins/`.
2. Install dependencies:

   ```bash
   cd wp-content/plugins/restaurant-order-print
   composer install --no-dev --optimize-autoloader
   ```

3. Activate **Restaurant Order Print** in wp-admin.
4. Ensure **WooCommerce** is installed and active.
5. Configure under **WooCommerce → Restaurant Print**.

## Kitchen ticket contents

Each ticket includes:

- Restaurant name and "KITCHEN TICKET" header
- Order number and timestamp
- Order type (Pickup / Delivery / Dine In)
- Table number (if dine-in)
- Requested pickup time
- Customer name and phone
- Line items with quantity, modifiers, and notes
- Customer order notes
- Order total

## Admin features

- **Test print** — verify printer connectivity
- **Reprint** — WooCommerce order actions → "Reprint kitchen ticket"
- **Print logs** — order notes show print success/failure
- **Multiple copies** — print 1–5 copies per order

## Project structure

```
restaurant-ordering/
├── docker-compose.yml
├── scripts/
│   ├── setup-wordpress.sh      # WooCommerce + sample menu setup
│   └── install-plugin-deps.sh  # Local Composer install
└── wp-content/plugins/restaurant-order-print/
    ├── restaurant-order-print.php
    ├── includes/
    │   ├── class-order-handler.php
    │   ├── class-printer-service.php
    │   ├── class-ticket-formatter.php
    │   ├── class-restaurant-checkout.php
    │   └── class-admin-settings.php
    └── assets/
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No print, order shows "Print attempted" | Check printer IP/port; ensure server can reach printer on port 9100 |
| Plugin says ESC/POS library missing | Run `composer install` in the plugin directory |
| Double prints | Plugin tracks `_rop_ticket_printed` meta; clear only if reprinting |
| Docker can't reach printer | Use printer's LAN IP; on Docker Desktop try `host.docker.internal` |
| WooCommerce not found | Run `docker compose run --rm wpcli sh /setup-wordpress.sh` |

## Production deployment

For a live restaurant:

1. Host WordPress on a server with stable network access to the kitchen printer.
2. Use a proper domain with SSL (Let's Encrypt).
3. Configure WooCommerce payment gateways (Stripe, Square, etc.).
4. Set printer IP to the kitchen printer's static LAN address.
5. Use **Placed** print timing for fastest kitchen notification.
6. Consider a backup printer or print-to-PDF logging if the network printer is down.

## License

GPL-2.0-or-later (compatible with WordPress and WooCommerce).
