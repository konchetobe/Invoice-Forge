<?php
/**
 * Invoices Page
 *
 * Custom admin page for managing invoices with modern UI.
 *
 * @package    InvoiceForge
 * @subpackage Admin/Pages
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Admin\Pages;

use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;
use InvoiceForge\Repositories\LineItemRepository;
use InvoiceForge\Repositories\TaxRateRepository;
use InvoiceForge\Models\LineItem;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoices page handler.
 *
 * @since 1.0.0
 */
class InvoicesPage
{
    /**
     * Render the invoices page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function render(): void
    {
        // Determine view (list or editor)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $invoice_id = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : 0;

        if ($action === 'edit' || $action === 'new') {
            $this->renderEditor($invoice_id);
        } else {
            $this->renderList();
        }
    }

    /**
     * Render the invoice list view.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function renderList(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $source = isset($_GET['source']) ? sanitize_key($_GET['source']) : 'all';

        $query_args = [];
        if ($source === 'custom') {
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_invoice_source',
                    'value'   => 'manual',
                ],
                [
                    'key'     => '_invoice_source',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        } elseif ($source === 'woocommerce') {
            $query_args['meta_query'] = [
                [
                    'key'   => '_invoice_source',
                    'value' => 'woocommerce',
                ],
            ];
        }

        $invoices      = $this->getInvoices($query_args);
        $statuses      = InvoicePostType::STATUSES;
        $status_counts = $this->getStatusCounts();
        $source_counts = $this->getSourceCounts();
        $active_source = $source;

        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/invoice-list.php';
    }

    /**
     * Render the invoice editor view.
     *
     * @since 1.0.0
     *
     * @param int $invoice_id The invoice ID (0 for new).
     * @return void
     */
    private function renderEditor(int $invoice_id): void
    {
        $invoice = $invoice_id > 0 ? $this->getInvoiceData($invoice_id) : null;
        $clients = $this->getClientOptions();
        $statuses = InvoicePostType::STATUSES;
        $currencies = $this->getCurrencies();
        $countries = $this->getCountries();

        // Load tax rates for the editor dropdown
        $taxRateRepo = new TaxRateRepository();
        $tax_rates = array_map(fn($r) => $r->toArray(), $taxRateRepo->findAllActive());

        // Load line items for existing invoice
        $line_items = [];
        if ($invoice_id > 0) {
            $lineItemRepo = new LineItemRepository();
            $line_items = array_map(fn(LineItem $item) => $item->toArray(), $lineItemRepo->findByInvoice($invoice_id));
        }

        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/invoice-editor.php';
    }

    /**
     * Get countries list for the invoice editor.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Country code => name pairs.
     */
    private function getCountries(): array
    {
        $clientPostType = new ClientPostType(
            new \InvoiceForge\Security\Nonce(),
            new \InvoiceForge\Security\Sanitizer()
        );
        return $clientPostType->getCountries();
    }

    /**
     * Get invoice data by ID.
     *
     * @since 1.0.0
     *
     * @param int $invoice_id The invoice post ID.
     * @return array<string, mixed>|null Invoice data or null.
     */
    public function getInvoiceData(int $invoice_id): ?array
    {
        $post = get_post($invoice_id);
        if (!$post || $post->post_type !== InvoicePostType::POST_TYPE) {
            return null;
        }

        $client_id = (int) get_post_meta($invoice_id, '_invoice_client_id', true);
        $client = $client_id ? get_post($client_id) : null;

        return [
            'id'             => $invoice_id,
            'title'          => $post->post_title,
            'number'         => get_post_meta($invoice_id, '_invoice_number', true),
            'client_id'      => $client_id,
            'client_name'    => $client ? $client->post_title : '',
            'date'           => get_post_meta($invoice_id, '_invoice_date', true),
            'due_date'       => get_post_meta($invoice_id, '_invoice_due_date', true),
            'status'         => get_post_meta($invoice_id, '_invoice_status', true) ?: 'draft',
            'subtotal'       => (float) get_post_meta($invoice_id, '_invoice_subtotal', true),
            'tax'            => (float) get_post_meta($invoice_id, '_invoice_tax', true),
            'total_amount'   => (float) get_post_meta($invoice_id, '_invoice_total_amount', true),
            'currency'       => get_post_meta($invoice_id, '_invoice_currency', true) ?: 'USD',
            'notes'          => get_post_meta($invoice_id, '_invoice_notes', true),
            'terms'          => get_post_meta($invoice_id, '_invoice_terms', true),
            'internal_notes' => get_post_meta($invoice_id, '_invoice_internal_notes', true),
            'discount_type'  => get_post_meta($invoice_id, '_invoice_discount_type', true),
            'discount_value' => (float) get_post_meta($invoice_id, '_invoice_discount_value', true),
            'created_at'     => $post->post_date,
            'updated_at'     => $post->post_modified,
        ];
    }

    /**
     * Get all invoices.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array<array<string, mixed>> Array of invoice data.
     */
    public function getInvoices(array $args = []): array
    {
        $defaults = [
            'post_type'      => InvoicePostType::POST_TYPE,
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query_args = array_merge($defaults, $args);
        $query = new \WP_Query($query_args);

        $invoices = [];
        foreach ($query->posts as $post) {
            $invoices[] = $this->getInvoiceData($post->ID);
        }

        return array_filter($invoices);
    }

    /**
     * Get invoices by status.
     *
     * @since 1.0.0
     *
     * @param string $status The status to filter by.
     * @return array<array<string, mixed>> Array of invoice data.
     */
    public function getInvoicesByStatus(string $status): array
    {
        return $this->getInvoices([
            'meta_query' => [
                [
                    'key'   => '_invoice_status',
                    'value' => $status,
                ],
            ],
        ]);
    }

    /**
     * Get invoices by client.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client ID.
     * @return array<array<string, mixed>> Array of invoice data.
     */
    public function getInvoicesByClient(int $client_id): array
    {
        return $this->getInvoices([
            'meta_query' => [
                [
                    'key'   => '_invoice_client_id',
                    'value' => $client_id,
                ],
            ],
        ]);
    }

    /**
     * Get overdue invoices.
     *
     * @since 1.0.0
     *
     * @return array<array<string, mixed>> Array of invoice data.
     */
    public function getOverdueInvoices(): array
    {
        return $this->getInvoices([
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'     => '_invoice_due_date',
                    'value'   => current_time('Y-m-d'),
                    'compare' => '<',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => '_invoice_status',
                    'value'   => ['paid', 'cancelled'],
                    'compare' => 'NOT IN',
                ],
            ],
        ]);
    }

    /**
     * Get status counts.
     *
     * @since 1.0.0
     *
     * @return array<string, int> Status => count.
     */
    public function getStatusCounts(): array
    {
        global $wpdb;

        $counts = [];
        foreach (array_keys(InvoicePostType::STATUSES) as $status) {
            $counts[$status] = 0;
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as status, COUNT(*) as count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value",
                '_invoice_status',
                InvoicePostType::POST_TYPE
            )
        );

        foreach ($results as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->count;
            }
        }

        return $counts;
    }

    /**
     * Get client options for dropdown.
     *
     * @since 1.0.0
     *
     * @return array<int, string> Client ID => name.
     */
    public function getClientOptions(): array
    {
        $clients = get_posts([
            'post_type'      => ClientPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $options = [];
        foreach ($clients as $client) {
            $options[$client->ID] = $client->post_title;
        }

        return $options;
    }

    /**
     * Get available currencies.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Currency code => label.
     */
    public function getCurrencies(): array
    {
        return [
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'CAD' => 'CAD - Canadian Dollar',
            'AUD' => 'AUD - Australian Dollar',
        ];
    }

    /**
     * Format currency amount.
     *
     * @since 1.0.0
     *
     * @param float  $amount   Amount.
     * @param string $currency Currency code.
     * @return string Formatted amount.
     */
    public function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . number_format($amount, 2);
    }

    /**
     * Get status label.
     *
     * @since 1.0.0
     *
     * @param string $status Status key.
     * @return string Status label.
     */
    public function getStatusLabel(string $status): string
    {
        $statuses = [
            'draft'     => __('Draft', 'invoiceforge'),
            'sent'      => __('Sent', 'invoiceforge'),
            'paid'      => __('Paid', 'invoiceforge'),
            'overdue'   => __('Overdue', 'invoiceforge'),
            'cancelled' => __('Cancelled', 'invoiceforge'),
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Get invoice counts grouped by source (manual vs woocommerce).
     *
     * @since 1.1.0
     *
     * @return array<string, int> Source => count.
     */
    public function getSourceCounts(): array
    {
        global $wpdb;

        $counts = ['all' => 0, 'custom' => 0, 'woocommerce' => 0];

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm.meta_value as source, COUNT(*) as count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_invoice_source'
                AND p.post_type = %s
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value",
                InvoicePostType::POST_TYPE
            )
        );

        $woo_count    = 0;
        $custom_count = 0;
        $total        = 0;

        foreach ($results as $row) {
            if ($row->source === 'woocommerce') {
                $woo_count = (int) $row->count;
            } else {
                $custom_count += (int) $row->count;
            }
            $total += (int) $row->count;
        }

        // Count posts without a source meta (old manual invoices)
        $no_source = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm
                    WHERE pm.post_id = p.ID AND pm.meta_key = '_invoice_source'
                )",
                InvoicePostType::POST_TYPE
            )
        );

        $custom_count += $no_source;
        $total        += $no_source;

        $counts['all']         = $total;
        $counts['custom']      = $custom_count;
        $counts['woocommerce'] = $woo_count;

        return $counts;
    }
}
