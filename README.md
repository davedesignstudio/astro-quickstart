# Restaurant Online Ordering (WordPress + WooCommerce)

WordPress + WooCommerce restaurant ordering system that **automatically prints kitchen tickets as soon as an order is placed**.

## What’s included

| Piece | Purpose |
| --- | --- |
| `wordpress/plugins/restaurant-kitchen-tickets` | Auto-print plugin: ticket generation, print queue, PrintNode + kitchen browser display |
| `wordpress/themes/bistro-order` | Restaurant storefront theme for browsing/ordering the menu |
| `wordpress/docker-compose.yml` | Local WordPress + MySQL + WP-CLI stack |
| `wordpress/scripts/setup.sh` | One-command install: WooCommerce, theme, plugin, sample menu |

## How auto-printing works

When a customer completes checkout:

1. WooCommerce creates the order.
2. The **Restaurant Kitchen Tickets** plugin immediately builds a kitchen ticket.
3. Tickets are sent through one or both channels:
   - **Kitchen Display (browser)** — open `/kitchen-display/` on a kitchen tablet/POS PC connected to a receipt printer. The page polls for new orders and auto-prints them.
   - **PrintNode (optional)** — silent cloud print to a thermal printer via [PrintNode](https://www.printnode.com/) (install their desktop client in the kitchen).

Tickets include order number, fulfillment type (pickup/delivery), ready-by time, line items + modifiers, kitchen notes, and customer contact info.

## Quick start

### Requirements

- Docker + Docker Compose
- Ports `8080` free (configurable)

### Setup

```bash
cd wordpress
cp .env.example .env
chmod +x scripts/setup.sh
./scripts/setup.sh
```

Then open:

- **Storefront:** http://localhost:8080
- **Admin:** http://localhost:8080/wp-admin (`admin` / `admin123`)
- **Kitchen display:** http://localhost:8080/kitchen-display/ (log in as admin)

### Kitchen printer setup

**Option A — Browser print (works immediately)**

1. On the kitchen computer, log into WordPress.
2. Open **Kitchen Display**.
3. Connect that computer to your receipt/kitchen printer.
4. Leave the tab open during service. New orders print automatically (browser print dialog / saved print defaults).

**Option B — Silent PrintNode printing**

1. Create a PrintNode account and install the PrintNode client on the kitchen PC.
2. In WP Admin go to **WooCommerce → Kitchen Tickets**.
3. Paste your API key, select the printer, set print method to PrintNode (or both).
4. Place a test order — the ticket prints without a browser dialog.

## Manual reprint

On any order in WP Admin: **Order actions → Print kitchen ticket now**.

## Sample menu

`setup.sh` seeds categories (Starters, Mains, Sides, Drinks) and demo dishes so you can place a test order immediately.

## Configuration

Plugin settings live under **WooCommerce → Kitchen Tickets**:

- Auto-print on/off
- Print method (browser / PrintNode / both)
- Order statuses that trigger printing
- Pickup / delivery / dine-in toggles
- Default prep time
- Ticket copies, phone/prices on tickets, alert sound

## Project layout

```
wordpress/
  docker-compose.yml
  .env.example
  config/uploads.ini
  plugins/restaurant-kitchen-tickets/
  themes/bistro-order/
  scripts/setup.sh
  scripts/seed-menu.php
```

## Useful commands

```bash
cd wordpress
docker compose logs -f wordpress
docker compose exec wpcli wp plugin list
docker compose exec wpcli wp wc product list --user=1
docker compose down
```

## Notes

- Guest checkout is enabled for fast restaurant ordering.
- Cash on delivery / pay-at-pickup is enabled by default for local demos.
- For production, put WordPress behind HTTPS, configure real payments (Stripe/Square), and lock down the kitchen display to staff accounts.
