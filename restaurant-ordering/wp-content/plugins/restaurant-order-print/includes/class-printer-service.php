<?php

namespace RestaurantOrderPrint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends kitchen tickets to network ESC/POS thermal printers.
 */
class Printer_Service {

    private Ticket_Formatter $formatter;

    public function __construct() {
        $this->formatter = new Ticket_Formatter();
    }

    /**
     * Print a kitchen ticket for the given order.
     *
     * @return bool True on success, false on failure.
     */
    public function print_ticket(int $order_id): bool {
        if (!get_option('rop_printer_enabled', '1')) {
            $this->log($order_id, 'Printer disabled in settings — ticket saved to log only.');
            $this->log_ticket_text($order_id);
            return true;
        }

        $host = get_option('rop_printer_host', '127.0.0.1');
        $port = (int) get_option('rop_printer_port', 9100);
        $copies = max(1, (int) get_option('rop_ticket_copies', 1));

        if (!class_exists('Mike42\Escpos\PrintConnectors\NetworkPrintConnector')) {
            $this->log($order_id, 'ESC/POS library missing — run composer install. Ticket text logged.');
            $this->log_ticket_text($order_id);
            return false;
        }

        $success = false;

        for ($copy = 1; $copy <= $copies; $copy++) {
            try {
                $connector = new \Mike42\Escpos\PrintConnectors\NetworkPrintConnector($host, $port, 5);
                $this->formatter->print_escpos($order_id, $connector);
                $success = true;
                $this->log($order_id, sprintf('Ticket printed successfully (copy %d/%d) to %s:%d', $copy, $copies, $host, $port));
            } catch (\Exception $e) {
                $this->log($order_id, sprintf('Print failed (copy %d): %s', $copy, $e->getMessage()));
            }
        }

        if (!$success) {
            $this->log_ticket_text($order_id);
        }

        return $success;
    }

    /**
     * Test printer connection from admin settings.
     */
    public function print_test_ticket(): bool {
        $host = get_option('rop_printer_host', '127.0.0.1');
        $port = (int) get_option('rop_printer_port', 9100);

        if (!class_exists('Mike42\Escpos\PrintConnectors\NetworkPrintConnector')) {
            return false;
        }

        try {
            $connector = new \Mike42\Escpos\PrintConnectors\NetworkPrintConnector($host, $port, 5);
            $printer   = new \Mike42\Escpos\Printer($connector);
            $restaurant = get_option('rop_restaurant_name', get_bloginfo('name'));

            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($restaurant . "\n");
            $printer->text("TEST TICKET\n");
            $printer->setEmphasis(false);
            $printer->feed();
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_LEFT);
            $printer->text("Printer connection OK\n");
            $printer->text(date_i18n('M j, Y g:i A') . "\n");
            $printer->feed(2);
            $printer->cut();
            $printer->close();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function log_ticket_text(int $order_id): void {
        $text = $this->formatter->format_text($order_id);
        $this->log($order_id, "Ticket content:\n" . $text);
    }

    private function log(int $order_id, string $message): void {
        if (!get_option('rop_log_print_jobs', '1')) {
            return;
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('[Print] ' . $message, false, true);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Restaurant Order Print] Order #' . $order_id . ': ' . $message);
        }
    }
}
