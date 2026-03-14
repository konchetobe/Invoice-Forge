<?php
/**
 * WooCommerce Integration
 *
 * Listens to WooCommerce order status transitions and automatically
 * generates InvoiceForge invoices from orders.
 *
 * @package    InvoiceForge
 * @subpackage Integrations/WooCommerce
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Integrations\WooCommerce;

use InvoiceForge\Admin\Pages\SettingsPage;
use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\Repositories\LineItemRepository;
use InvoiceForge\Services\NumberingService;
use InvoiceForge\Services\PdfService;
use InvoiceForge\Services\EmailService;
use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrates InvoiceForge with WooCommerce.
 *
 * @since 1.1.0
 */
class WooCommerceIntegration
{
    /**
     * Logger instance.
     *
     * @since 1.1.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Line item repository.
     *
     * @since 1.1.0
     * @var LineItemRepository
     */
    private LineItemRepository $lineItemRepo;

    /**
     * Numbering service.
     *
     * @since 1.1.0
     * @var NumberingService
     */
    private NumberingService $numberingService;

    /**
     * Constructor.
     *
     * @since 1.1.0
     *
     * @param Logger             $logger           Logger instance.
     * @param LineItemRepository $lineItemRepo     Line item repository.
     * @param NumberingService   $numberingService Numbering service.
     */
    public function __construct(
        Logger $logger,
        LineItemRepository $lineItemRepo,
        NumberingService $numberingService
    ) {
        $this->logger           = $logger;
        $this->lineItemRepo     = $lineItemRepo;
        $this->numberingService = $numberingService;
    }

    /**
     * Check whether WooCommerce is active.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Register integration hooks if enabled in settings.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function register(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $settings = get_option(SettingsPage::OPTION_NAME, []);

        if (empty($settings['woo_enabled'])) {
            return;
        }

        // Determine which order statuses should trigger invoice generation
        $trigger_statuses = $settings['woo_trigger_statuses'] ?? ['wc-completed'];
        if (!is_array($trigger_statuses)) {
            $trigger_statuses = ['wc-completed'];
        }

        foreach ($trigger_statuses as $status) {
            // woocommerce_order_status_{status} fires when an order moves to that status
            $clean = str_replace('wc-', '', sanitize_key($status));
            add_action(
                'woocommerce_order_status_' . $clean,
                [$this, 'handleOrderStatusChange'],
                10,
                2
            );
        }

        // Add meta box to WooCommerce order edit screen
        add_action('add_meta_boxes', [$this, 'addOrderMetaBox']);
    }

    /**
     * Handle order status change: generate invoice if not already generated.
     *
     * @since 1.1.0
     *
     * @param int       $order_id   WooCommerce order ID.
     * @param \WC_Order $order      WooCommerce order object.
     * @return void
     */
    public function handleOrderStatusChange(int $order_id, $order): void
    {
        // Prevent duplicate generation
        if (get_post_meta($order_id, '_invoiceforge_invoice_id', true)) {
            return;
        }

        $this->generateFromOrder($order_id);
    }

    /**
     * Generate an InvoiceForge invoice from a WooCommerce order.
     *
     * @since 1.1.0
     *
     * @param int $order_id WooCommerce order ID.
     * @return int|null Created invoice post ID, or null on failure.
     */
    public function generateFromOrder(int $order_id): ?int
    {
        if (!self::isAvailable()) {
            return null;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->logger->error('WooCommerce order not found', ['order_id' => $order_id]);
            return null;
        }

        $settings = get_option(SettingsPage::OPTION_NAME, []);

        // --- Determine invoice number ---
        $number_format = $settings['woo_invoice_number_format'] ?? 'invoiceforge';

        if ($number_format === 'woocommerce') {
            $invoice_number = $settings['woo_invoice_prefix'] ?? 'ORD';
            $invoice_number .= '-' . $order->get_order_number();
        } else {
            // Use InvoiceForge sequential numbering
            $invoice_number = $this->numberingService->generate();
        }

        // --- Find or create InvoiceForge client from order billing data ---
        $client_id = $this->syncClient($order);

        // --- Create the invoice post ---
        $invoice_id = wp_insert_post([
            'post_type'   => InvoicePostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $invoice_number,
        ]);

        if (is_wp_error($invoice_id) || !$invoice_id) {
            $this->logger->error('Failed to create invoice post from WC order', ['order_id' => $order_id]);
            return null;
        }

        // --- Save core invoice meta ---
        $date     = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : current_time('Y-m-d');
        $currency = $order->get_currency();

        update_post_meta($invoice_id, '_invoice_number', $invoice_number);
        update_post_meta($invoice_id, '_invoice_status', 'sent');
        update_post_meta($invoice_id, '_invoice_date', $date);
        update_post_meta($invoice_id, '_invoice_currency', $currency);
        update_post_meta($invoice_id, '_invoice_source', 'woocommerce');
        update_post_meta($invoice_id, '_invoice_woo_order_id', $order_id);
        update_post_meta($invoice_id, '_invoice_client_id', $client_id);

        // --- Map line items ---
        $subtotal   = 0.0;
        $tax_total  = 0.0;
        $sort_order = 1;

        /** @var \WC_Order_Item_Product $item */
        foreach ($order->get_items() as $item) {
            $quantity   = (float) $item->get_quantity();
            $unit_price = $quantity > 0 ? (float) $item->get_subtotal() / $quantity : 0.0;
            $item_tax   = (float) $item->get_subtotal_tax();
            $line_total = (float) $item->get_subtotal() + $item_tax;

            $this->lineItemRepo->save([
                'invoice_id'  => $invoice_id,
                'description' => $item->get_name(),
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'tax_rate_id' => 0,
                'tax_amount'  => $item_tax,
                'total'       => $line_total,
                'sort_order'  => $sort_order++,
            ]);

            $subtotal  += (float) $item->get_subtotal();
            $tax_total += $item_tax;
        }

        // --- Map shipping as a line item ---
        if ((float) $order->get_shipping_total() > 0) {
            $shipping_total = (float) $order->get_shipping_total();
            $shipping_tax   = (float) $order->get_shipping_tax();

            $this->lineItemRepo->save([
                'invoice_id'  => $invoice_id,
                'description' => __('Shipping', 'invoiceforge'),
                'quantity'    => 1.0,
                'unit_price'  => $shipping_total,
                'tax_rate_id' => 0,
                'tax_amount'  => $shipping_tax,
                'total'       => $shipping_total + $shipping_tax,
                'sort_order'  => $sort_order++,
            ]);

            $subtotal  += $shipping_total;
            $tax_total += $shipping_tax;
        }

        // --- Totals ---
        $grand_total = (float) $order->get_total();
        update_post_meta($invoice_id, '_invoice_subtotal', $subtotal);
        update_post_meta($invoice_id, '_invoice_tax_total', $tax_total);
        update_post_meta($invoice_id, '_invoice_total_amount', $grand_total);

        // --- Link back to order ---
        update_post_meta($order_id, '_invoiceforge_invoice_id', $invoice_id);
        $order->add_order_note(sprintf(
            /* translators: %s: invoice number */
            __('InvoiceForge invoice %s generated.', 'invoiceforge'),
            $invoice_number
        ));

        $this->logger->info('Invoice generated from WC order', [
            'order_id'   => $order_id,
            'invoice_id' => $invoice_id,
        ]);

        // --- Auto-send PDF email if enabled ---
        if (!empty($settings['woo_auto_email'])) {
            try {
                $pdfService   = new PdfService($this->logger);
                $emailService = new EmailService($this->logger, $pdfService);
                $emailService->sendInvoice($invoice_id);
            } catch (\Exception $e) {
                $this->logger->warning('Auto-email failed for WC invoice', ['error' => $e->getMessage()]);
            }
        }

        /**
         * Fires after an invoice is generated from a WooCommerce order.
         *
         * @since 1.1.0
         *
         * @param int $invoice_id The generated invoice post ID.
         * @param int $order_id   The WooCommerce order ID.
         */
        do_action('invoiceforge_invoice_generated_from_order', $invoice_id, $order_id);

        return $invoice_id;
    }

    /**
     * Add a meta box to the WooCommerce order page.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function addOrderMetaBox(): void
    {
        $screens = ['shop_order', 'woocommerce_page_wc-orders'];

        foreach ($screens as $screen) {
            add_meta_box(
                'invoiceforge_order_invoice',
                __('InvoiceForge Invoice', 'invoiceforge'),
                [$this, 'renderOrderMetaBox'],
                $screen,
                'side',
                'high'
            );
        }
    }

    /**
     * Render the meta box content on the WooCommerce order page.
     *
     * @since 1.1.0
     *
     * @param \WP_Post $post Current post (order).
     * @return void
     */
    public function renderOrderMetaBox(\WP_Post $post): void
    {
        $order_id   = $post->ID;
        $invoice_id = (int) get_post_meta($order_id, '_invoiceforge_invoice_id', true);
        $nonce      = wp_create_nonce('invoiceforge_admin');

        echo '<div class="invoiceforge-order-box">';

        if ($invoice_id) {
            $invoice_number = get_post_meta($invoice_id, '_invoice_number', true);
            $edit_link      = admin_url('admin.php?page=invoiceforge-invoices&action=edit&id=' . $invoice_id);
            $pdf_link       = admin_url('admin-ajax.php?action=invoiceforge_download_pdf&invoice_id=' . $invoice_id . '&nonce=' . $nonce);

            echo '<p><strong>' . esc_html__('Invoice:', 'invoiceforge') . '</strong> ';
            echo '<a href="' . esc_url($edit_link) . '">' . esc_html($invoice_number) . '</a></p>';
            echo '<a href="' . esc_url($pdf_link) . '" class="button button-small" target="_blank">'
                . esc_html__('Download PDF', 'invoiceforge') . '</a>';
        } else {
            echo '<p>' . esc_html__('No invoice generated yet.', 'invoiceforge') . '</p>';
            echo '<button type="button" class="button button-primary invoiceforge-generate-invoice" '
                . 'data-order-id="' . esc_attr((string) $order_id) . '" '
                . 'data-nonce="' . esc_attr($nonce) . '">'
                . esc_html__('Generate Invoice', 'invoiceforge')
                . '</button>';
            echo '<span class="invoiceforge-spinner" style="display:none;"> '
                . esc_html__('Generating...', 'invoiceforge') . '</span>';

            // Inline script for the button
            ?>
            <script>
            (function($) {
                $(document).on('click', '.invoiceforge-generate-invoice', function() {
                    var $btn = $(this);
                    var $spinner = $btn.siblings('.invoiceforge-spinner');

                    $btn.prop('disabled', true);
                    $spinner.show();

                    $.post(ajaxurl, {
                        action: 'invoiceforge_generate_from_order',
                        order_id: $btn.data('order-id'),
                        nonce: $btn.data('nonce')
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php echo esc_js(__('Error generating invoice.', 'invoiceforge')); ?>');
                            $btn.prop('disabled', false);
                            $spinner.hide();
                        }
                    });
                });
            })(jQuery);
            </script>
            <?php
        }

        echo '</div>';
    }

    /**
     * Sync WooCommerce billing data to an InvoiceForge client post.
     * Finds an existing client by email or creates a new one.
     *
     * @since 1.1.0
     *
     * @param \WC_Order $order WooCommerce order.
     * @return int Client post ID (may be 0 if creation fails).
     */
    private function syncClient($order): int
    {
        $billing_email = $order->get_billing_email();

        if (empty($billing_email)) {
            return 0;
        }

        // Search for existing client by email meta
        $existing = get_posts([
            'post_type'      => 'if_client',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => '_client_email',
            'meta_value'     => sanitize_email($billing_email),
        ]);

        if (!empty($existing)) {
            return (int) $existing[0]->ID;
        }

        // Create new client
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $full_name  = trim($first_name . ' ' . $last_name) ?: $billing_email;

        $client_id = wp_insert_post([
            'post_type'   => 'if_client',
            'post_status' => 'publish',
            'post_title'  => $full_name,
        ]);

        if (is_wp_error($client_id) || !$client_id) {
            return 0;
        }

        update_post_meta($client_id, '_client_first_name', sanitize_text_field($first_name));
        update_post_meta($client_id, '_client_last_name', sanitize_text_field($last_name));
        update_post_meta($client_id, '_client_email', sanitize_email($billing_email));
        update_post_meta($client_id, '_client_company', sanitize_text_field($order->get_billing_company()));
        update_post_meta($client_id, '_client_phone', sanitize_text_field($order->get_billing_phone()));
        update_post_meta($client_id, '_client_address', sanitize_textarea_field(implode("\n", array_filter([
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_postcode(),
            $order->get_billing_country(),
        ]))));
        update_post_meta($client_id, '_client_source', 'woocommerce');

        return (int) $client_id;
    }
}
