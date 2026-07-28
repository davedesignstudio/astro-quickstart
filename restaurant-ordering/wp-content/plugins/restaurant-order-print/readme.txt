=== Restaurant Order Print ===
Contributors: restaurant-ordering
Tags: woocommerce, restaurant, printing, kitchen, escpos, ordering
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically print kitchen tickets when WooCommerce restaurant orders are placed.

== Description ==

Restaurant Order Print extends WooCommerce for restaurant online ordering and sends formatted kitchen tickets to network ESC/POS thermal printers as soon as orders are submitted.

**Features:**

* Automatic kitchen ticket printing on order placement
* ESC/POS network printer support (port 9100)
* Order type: pickup, delivery, dine-in
* Table number and pickup time fields
* Configurable print timing (immediate or on payment)
* Test print and reprint from admin
* Block checkout compatible

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory
3. Activate through the 'Plugins' menu in WordPress
4. Configure under WooCommerce → Restaurant Print

== Frequently Asked Questions ==

= What printers are supported? =

Most network thermal printers that accept ESC/POS over TCP port 9100 (Epson, Star, Bixolon, etc.).

= When does the ticket print? =

By default when the order reaches "Processing" status. Set "Placed (immediately at checkout)" for instant printing.

== Changelog ==

= 1.0.0 =
* Initial release
