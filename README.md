# Restaurant Online Ordering System

A complete WordPress + WooCommerce restaurant ordering system with **automatic kitchen ticket printing** the moment an order is placed.

## Features

- **Online ordering** — WooCommerce-powered menu, cart, and checkout
- **Auto-print tickets** — Kitchen tickets print automatically on new orders
- **ESC/POS support** — Works with thermal receipt printers (Epson, Star, etc.)
- **Two print methods:**
  - **Local Print Daemon** — Polls WordPress and prints to a network/USB printer (recommended)
  - **PrintNode** — Cloud printing for remote/multi-location setups
- **Mobile-friendly theme** — Clean restaurant theme optimized for phone ordering
- **Docker setup** — One-command local development environment

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- Node.js 18+ (for the print daemon)

### 1. Start WordPress

```bash
docker compose up -d
```

Wait ~60 seconds for WordPress, WooCommerce, and sample menu items to install.

| Resource | URL |
|----------|-----|
| Storefront | http://localhost:8080 |
| Menu/Shop | http://localhost:8080/shop |
| Admin | http://localhost:8080/wp-admin |
| Login | `admin` / `admin123` |

### 2. Start the Print Daemon

On a computer connected to your kitchen printer:

```bash
cd print-daemon
npm install
cp .env.example .env
```

Edit `.env` with your API key (found in **WooCommerce → Kitchen Print** in wp-admin):

```bash
ROP_API_URL=http://localhost:8080
ROP_API_KEY=your-daemon-api-key
ROP_PRINTER=network://192.168.1.100:9100
```

Start the daemon:

```bash
npm start
```

### 3. Place a Test Order

1. Go to http://localhost:8080/shop
2. Add items to cart and checkout
3. The kitchen ticket prints automatically within seconds

For testing without a physical printer, use file output:

```bash
ROP_PRINTER=file:///tmp/kitchen-tickets.txt npm start
```

Then watch tickets appear:

```bash
tail -f /tmp/kitchen-tickets.txt
```

## Architecture

```
Customer places order
        │
        ▼
┌─────────────────┐
│   WooCommerce   │  checkout completes
│   WordPress     │
└────────┬────────┘
         │ woocommerce_checkout_order_processed
         ▼
┌─────────────────┐
│ Restaurant Order│  formats ESC/POS ticket
│ Print Plugin    │  queues print job in DB
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
 PrintNode   Print Daemon (polls REST API every 3s)
 (cloud)         │
                  ▼
            Kitchen Printer
            (ESC/POS thermal)
```

## Print Methods

### Local Print Daemon (Recommended)

Best for a single restaurant with a printer on the local network.

1. Install the daemon on any computer on the same network as the printer
2. Set **Print Method** to "Local Print Daemon" in wp-admin
3. Copy the **Daemon API Key** into your `.env` file
4. Point `ROP_PRINTER` at your printer:
   - Network: `network://192.168.1.100:9100`
   - File (testing): `file:///tmp/kitchen-tickets.txt`

The daemon polls `GET /wp-json/restaurant-print/v1/queue` and prints pending jobs immediately.

### PrintNode (Cloud)

Best for cloud-hosted WordPress or multiple locations.

1. Create an account at [printnode.com](https://www.printnode.com)
2. Install the PrintNode client on the restaurant computer
3. In wp-admin (**WooCommerce → Kitchen Print**):
   - Set **Print Method** to "PrintNode Cloud Printing"
   - Enter your API key and printer ID

Tickets print instantly via PrintNode's API — no daemon needed.

## Configuration

All settings are in **WooCommerce → Kitchen Print**:

| Setting | Description |
|---------|-------------|
| Restaurant Name | Shown at top of kitchen tickets |
| Auto-Print Orders | Enable/disable automatic printing |
| Print On Statuses | Which order statuses trigger printing |
| Print Method | Daemon or PrintNode |
| Daemon API Key | Shared secret for the print daemon |

## Kitchen Ticket Format

Each ticket includes:

- Restaurant name and order number
- Date/time and order type (Pickup/Delivery)
- Customer name, phone, and address
- All items with quantities, modifiers, and prices
- Order notes and special instructions
- Subtotal, shipping, tax, and total
- Payment method

## REST API

The plugin exposes these endpoints (authenticated with `X-ROP-API-Key` header):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/restaurant-print/v1/queue` | Get pending print jobs |
| POST | `/wp-json/restaurant-print/v1/queue/{id}/complete` | Mark job as printed |
| POST | `/wp-json/restaurant-print/v1/queue/{id}/fail` | Mark job as failed |
| GET | `/wp-json/restaurant-print/v1/test` | Health check |

## Project Structure

```
├── docker-compose.yml          # WordPress + MySQL stack
├── scripts/wp-init.sh          # Auto-installs WP, WooCommerce, sample menu
├── wordpress/
│   └── wp-content/
│       ├── plugins/restaurant-order-print/   # Auto-print plugin
│       └── themes/restaurant-ordering/       # Restaurant theme
└── print-daemon/               # Local print client (Node.js)
```

## Production Deployment

For a live restaurant:

1. Deploy WordPress to a hosting provider (WP Engine, DigitalOcean, etc.)
2. Install WooCommerce and activate the Restaurant Order Print plugin
3. Configure payment gateway (Stripe, Square, etc.)
4. Set up delivery/pickup shipping methods
5. Run the print daemon on a dedicated mini-PC or Raspberry Pi at the restaurant
6. Connect an ESC/POS thermal printer via Ethernet (port 9100)

### Recommended Printers

- Epson TM-T88VI (Ethernet)
- Star TSP143IIIU (USB, use with Raspberry Pi)
- Any ESC/POS-compatible thermal printer with network port

## Troubleshooting

**Tickets not printing?**
- Check **WooCommerce → Kitchen Print** for failed jobs and error messages
- Verify the print daemon is running: `npm start` in `print-daemon/`
- Test API connection: `npm run test-print`
- Confirm API key matches between `.env` and wp-admin

**Printer not reachable?**
- Ping the printer IP from the daemon machine
- Verify port 9100 is open (default for ESC/POS network printers)
- Try file output mode first to confirm the queue is working

**Orders not triggering prints?**
- Ensure "Auto-Print Orders" is enabled
- Check that the order status matches "Print On Statuses" settings
- Cash-on-delivery orders start as "Pending" — this is included by default

## License

MIT
