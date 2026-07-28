# Harbor Kitchen — Restaurant Online Ordering

WordPress + WooCommerce restaurant ordering system that **automatically prints kitchen tickets** as soon as an order is placed.

## What you get

- Online menu & checkout (pickup / delivery / dine-in)
- Kitchen notes + table / pickup time fields
- Automatic kitchen ticket queue on every new order
- **Kitchen Station** browser page that polls for new tickets and silent-prints to a thermal printer
- Optional **PrintNode** cloud printing
- Sample “Harbor Kitchen” menu seeder
- Docker Compose stack (WordPress, MySQL, WP-CLI, MailHog, phpMyAdmin)

## Quick start

```bash
cp .env.example .env
docker compose up -d
chmod +x scripts/setup.sh
./scripts/setup.sh
```

Then open:

| URL | Purpose |
|-----|---------|
| http://localhost:8080 | Storefront / menu |
| http://localhost:8080/wp-admin | Admin (`admin` / `admin123`) |
| http://localhost:8080/kitchen-station/ | Auto-print station |
| http://localhost:8081 | phpMyAdmin |
| http://localhost:8025 | MailHog |

## Auto-print setup (kitchen)

1. Connect an **80mm thermal printer** to a kitchen tablet/PC and set it as the system default printer (or choose it in the browser print dialog once and enable “silent”/auto for the site).
2. In **WooCommerce → Settings → Kitchen Print**, copy the **station token**.
3. Open **Kitchen Station** on the kitchen device, paste the token, click **Connect**.
4. Leave that tab open. When a customer checks out, the ticket prints within a few seconds.

### Optional: PrintNode

Set **Print method** to `PrintNode` or `Both`, then add your PrintNode API key and printer ID in Kitchen Print settings. Tickets are pushed to PrintNode immediately on order.

## Project layout

```
docker-compose.yml
scripts/
  setup.sh              # Install WP + WooCommerce + seed menu
  seed-menu.php
  mu-plugins/           # Local mail → MailHog
wp-content/
  plugins/restaurant-kitchen-print/   # Auto-print + restaurant fields
  themes/restaurant-order/            # Storefront theme
```

## Order → print flow

1. Customer places order in WooCommerce.
2. Plugin hooks `woocommerce_checkout_order_processed` / payment complete / processing.
3. Ticket payload is stored in `wp_rkp_print_queue` with status `pending`.
4. Kitchen Station polls `GET /wp-json/rkp/v1/pending`.
5. Station prints HTML ticket (thermal 58/80mm) via hidden iframe + `window.print()`.
6. Station calls `POST /wp-json/rkp/v1/printed/{job_id}` to mark complete.

Admins can **Preview** or **Re-queue** tickets from the order screen metabox.

## Production notes

- Point a real domain at your WordPress host and install WooCommerce + this plugin/theme.
- Use a payment gateway (Stripe, Square, etc.); Cash on Delivery is enabled for local demos.
- Keep Kitchen Station on a dedicated kitchen device, or use PrintNode for headless printing.
- Protect `/kitchen-station/` with the station token (and optionally HTTP auth / VPN).

## License

MIT — built for Harbor Kitchen / restaurant online ordering demos.
