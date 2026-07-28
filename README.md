# Restaurant Online Ordering + Automatic Kitchen Tickets

WordPress + WooCommerce restaurant ordering stack that **prints a kitchen ticket as soon as an order is placed**.

## What’s included

- `docker-compose.yml` — WordPress, MySQL, and WP-CLI
- `wp-content/plugins/restaurant-kitchen-tickets/` — WooCommerce plugin that:
  - Adds pickup / delivery / dine-in fields + kitchen notes at checkout
  - Generates thermal-friendly kitchen tickets (80mm / 58mm)
  - **Auto-queues a ticket the moment checkout completes**
  - Provides a always-on **Kitchen Print Station** browser page that polls for new orders and prints them immediately
  - Optional **PrintNode** cloud printing for silent thermal printers
  - Admin reprint / preview on each order

## Quick start (Docker)

```bash
cp .env.example .env
docker compose up -d
docker compose exec wpcli sh /scripts/setup.sh
```

Then open:

| URL | Purpose |
| --- | --- |
| http://localhost:8080 | Online storefront |
| http://localhost:8080/wp-admin | Admin (`admin` / `admin123` by default) |
| http://localhost:8080/kitchen-print-station/ | Kitchen ticket printer (leave this open) |

Default station PIN: `1234` (change under **WooCommerce → Kitchen Tickets**).

## How automatic printing works

```text
Customer checks out
        │
        ▼
WooCommerce creates order
        │
        ▼
Plugin hooks (checkout / payment / processing)
        │
        ├──► Queue ticket for Kitchen Print Station  ──► browser silent print
        └──► (optional) Send job to PrintNode         ──► thermal printer
```

1. Keep `/kitchen-print-station/` open on a tablet/PC connected to the kitchen printer.
2. Enter the station PIN once.
3. When an online order lands, the station beeps and prints within a few seconds.

### PrintNode (optional, fully silent)

1. Create an account at [printnode.com](https://www.printnode.com/).
2. Install the PrintNode client on the kitchen computer and share the receipt printer.
3. Paste API key + printer ID into **WooCommerce → Kitchen Tickets**.
4. Set **Print method** to `PrintNode` or `Both`.

## Plugin settings

**WooCommerce → Kitchen Tickets**

- Auto-print on/off
- Print method: station / PrintNode / both
- Ticket header, footer, paper width, copies
- Whether prices / phone appear on kitchen tickets
- Station PIN + poll interval
- PrintNode credentials

On any order, use **Order actions → Print kitchen ticket** to reprint.

## Deploying to an existing WordPress site

1. Copy `wp-content/plugins/restaurant-kitchen-tickets` into your site’s `wp-content/plugins/`.
2. Install & activate **WooCommerce**.
3. Activate **Restaurant Kitchen Tickets**.
4. Visit **Settings → Permalinks → Save** (registers `/kitchen-print-station/`).
5. Configure **WooCommerce → Kitchen Tickets**.
6. Open the print station on the kitchen device.

## Manual setup without the helper script

```bash
docker compose up -d
docker compose exec wpcli wp core install \
  --url=http://localhost:8080 \
  --title='Harbor Kitchen' \
  --admin_user=admin \
  --admin_password=admin123 \
  --admin_email=owner@restaurant.local \
  --skip-email
docker compose exec wpcli wp plugin install woocommerce --activate
docker compose exec wpcli wp plugin activate restaurant-kitchen-tickets
```

## Development notes

- HPOS (High-Performance Order Storage) compatible
- Works with classic checkout and Checkout Block additional fields
- REST namespace: `rkt/v1`
  - `GET /pending-tickets` (PIN required)
  - `POST /tickets/{id}/printed`
  - `POST /tickets/{id}/reprint`
  - `GET /ticket/{id}`

## Security tips for production

- Change the station PIN immediately
- Prefer HTTPS
- Restrict `/kitchen-print-station/` to staff network if possible
- Store PrintNode keys only in WP admin settings (not in git)
