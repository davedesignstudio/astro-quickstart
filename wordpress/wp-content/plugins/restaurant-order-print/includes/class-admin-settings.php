<?php

if (!defined('ABSPATH')) {
    exit;
}

class ROP_Admin_Settings {
    private static ?ROP_Admin_Settings $instance = null;

    public static function instance(): ROP_Admin_Settings {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        add_submenu_page(
            'woocommerce',
            'Kitchen Print Settings',
            'Kitchen Print',
            'manage_woocommerce',
            'restaurant-order-print',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting('rop_settings', 'rop_restaurant_name');
        register_setting('rop_settings', 'rop_auto_print');
        register_setting('rop_settings', 'rop_print_method');
        register_setting('rop_settings', 'rop_printnode_api_key');
        register_setting('rop_settings', 'rop_printnode_printer_id');
        register_setting('rop_settings', 'rop_daemon_api_key');
        register_setting('rop_settings', 'rop_print_on_statuses', [
            'sanitize_callback' => [$this, 'sanitize_statuses'],
        ]);
    }

    public function sanitize_statuses($input): string {
        if (isset($_POST['rop_print_on_statuses_arr']) && is_array($_POST['rop_print_on_statuses_arr'])) {
            $statuses = array_map('sanitize_text_field', $_POST['rop_print_on_statuses_arr']);
            return wp_json_encode($statuses);
        }
        return is_string($input) ? $input : '["processing","pending"]';
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'woocommerce_page_restaurant-order-print') {
            return;
        }
        wp_enqueue_style('rop-admin', ROP_PLUGIN_URL . 'assets/admin.css', [], ROP_VERSION);
    }

    public function render_page(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'rop_print_queue';
        $recent = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT 20",
            ARRAY_A
        ) ?: [];

        $statuses = json_decode(get_option('rop_print_on_statuses', '["processing","pending"]'), true) ?: [];
        ?>
        <div class="wrap rop-admin">
            <h1>Kitchen Print Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields('rop_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th>Restaurant Name</th>
                        <td>
                            <input type="text" name="rop_restaurant_name" class="regular-text"
                                   value="<?php echo esc_attr(get_option('rop_restaurant_name', '')); ?>">
                            <p class="description">Shown at the top of kitchen tickets.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto-Print Orders</th>
                        <td>
                            <label>
                                <input type="checkbox" name="rop_auto_print" value="yes"
                                    <?php checked(get_option('rop_auto_print', 'yes'), 'yes'); ?>>
                                Automatically print tickets when orders are placed
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Print On Statuses</th>
                        <td>
                            <?php
                            $all_statuses = wc_get_order_statuses();
                            foreach ($all_statuses as $key => $label):
                                $status_key = str_replace('wc-', '', $key);
                            ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="rop_print_on_statuses_arr[]"
                                           value="<?php echo esc_attr($status_key); ?>"
                                        <?php checked(in_array($status_key, $statuses, true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Print when order reaches any of these statuses.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Print Method</th>
                        <td>
                            <select name="rop_print_method" id="rop_print_method">
                                <option value="daemon" <?php selected(get_option('rop_print_method'), 'daemon'); ?>>
                                    Local Print Daemon (recommended)
                                </option>
                                <option value="printnode" <?php selected(get_option('rop_print_method'), 'printnode'); ?>>
                                    PrintNode Cloud Printing
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr class="rop-printnode-fields">
                        <th>PrintNode API Key</th>
                        <td>
                            <input type="password" name="rop_printnode_api_key" class="regular-text"
                                   value="<?php echo esc_attr(get_option('rop_printnode_api_key', '')); ?>">
                        </td>
                    </tr>
                    <tr class="rop-printnode-fields">
                        <th>PrintNode Printer ID</th>
                        <td>
                            <input type="text" name="rop_printnode_printer_id" class="regular-text"
                                   value="<?php echo esc_attr(get_option('rop_printnode_printer_id', '')); ?>">
                        </td>
                    </tr>
                    <tr class="rop-daemon-fields">
                        <th>Daemon API Key</th>
                        <td>
                            <input type="text" name="rop_daemon_api_key" class="regular-text"
                                   value="<?php echo esc_attr(get_option('rop_daemon_api_key', wp_generate_password(32, false))); ?>">
                            <p class="description">Shared secret between WordPress and the local print daemon.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>
            <h2>Print Daemon Setup</h2>
            <p>Run the print daemon on a computer connected to your kitchen printer:</p>
            <pre class="rop-code">cd print-daemon
npm install
ROP_API_URL=http://localhost:8080 \
ROP_API_KEY=<?php echo esc_html(get_option('rop_daemon_api_key', 'your-api-key')); ?> \
ROP_PRINTER=network://192.168.1.100 \
npm start</pre>

            <h2>Recent Print Jobs</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Created</th>
                        <th>Printed</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="7">No print jobs yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent as $job): ?>
                            <tr>
                                <td><?php echo esc_html($job['id']); ?></td>
                                <td><a href="<?php echo esc_url(admin_url('post.php?post=' . $job['order_id'] . '&action=edit')); ?>">#<?php echo esc_html($job['order_id']); ?></a></td>
                                <td><span class="rop-status rop-status-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($job['status']); ?></span></td>
                                <td><?php echo esc_html($job['print_method']); ?></td>
                                <td><?php echo esc_html($job['created_at']); ?></td>
                                <td><?php echo esc_html($job['printed_at'] ?: '—'); ?></td>
                                <td><?php echo esc_html($job['error_message'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.getElementById('rop_print_method').addEventListener('change', function() {
            const method = this.value;
            document.querySelectorAll('.rop-printnode-fields').forEach(el => {
                el.style.display = method === 'printnode' ? '' : 'none';
            });
            document.querySelectorAll('.rop-daemon-fields').forEach(el => {
                el.style.display = method === 'daemon' ? '' : 'none';
            });
        });
        document.getElementById('rop_print_method').dispatchEvent(new Event('change'));
        </script>
        <?php
    }
}
