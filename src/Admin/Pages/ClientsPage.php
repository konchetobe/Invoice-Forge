<?php
/**
 * Clients Page
 *
 * Custom admin page for managing clients with modern UI.
 *
 * @package    InvoiceForge
 * @subpackage Admin/Pages
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Admin\Pages;

use InvoiceForge\PostTypes\ClientPostType;
use InvoiceForge\PostTypes\InvoicePostType;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clients page handler.
 *
 * @since 1.0.0
 */
class ClientsPage
{
    /**
     * Render the clients page.
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
        $client_id = isset($_GET['client_id']) ? absint($_GET['client_id']) : 0;

        if ($action === 'edit' || $action === 'new') {
            $this->renderEditor($client_id);
        } else {
            $this->renderList();
        }
    }

    /**
     * Render the client list view.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function renderList(): void
    {
        $clients = $this->getClients();
        $countries = (new ClientPostType(
            new \InvoiceForge\Security\Nonce(),
            new \InvoiceForge\Security\Sanitizer()
        ))->getCountries();

        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/client-list.php';
    }

    /**
     * Render the client editor view.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client ID (0 for new).
     * @return void
     */
    private function renderEditor(int $client_id): void
    {
        $client = $client_id > 0 ? $this->getClientData($client_id) : null;
        $countries = (new ClientPostType(
            new \InvoiceForge\Security\Nonce(),
            new \InvoiceForge\Security\Sanitizer()
        ))->getCountries();
        $stats = $client_id > 0 ? $this->getClientStats($client_id) : null;

        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/client-editor.php';
    }

    /**
     * Get client data by ID.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
     * @return array<string, mixed>|null Client data or null.
     */
    public function getClientData(int $client_id): ?array
    {
        $post = get_post($client_id);
        if (!$post || $post->post_type !== ClientPostType::POST_TYPE) {
            return null;
        }

        return [
            'id'         => $client_id,
            'title'      => $post->post_title,
            'first_name' => get_post_meta($client_id, '_client_first_name', true),
            'last_name'  => get_post_meta($client_id, '_client_last_name', true),
            'company'    => get_post_meta($client_id, '_client_company', true),
            'email'      => get_post_meta($client_id, '_client_email', true),
            'phone'      => get_post_meta($client_id, '_client_phone', true),
            'address'    => get_post_meta($client_id, '_client_address', true),
            'city'       => get_post_meta($client_id, '_client_city', true),
            'state'      => get_post_meta($client_id, '_client_state', true),
            'zip'        => get_post_meta($client_id, '_client_zip', true),
            'country'    => get_post_meta($client_id, '_client_country', true),
            'tax_id'     => get_post_meta($client_id, '_client_tax_id', true),
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ];
    }

    /**
     * Get all clients.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Query arguments.
     * @return array<array<string, mixed>> Array of client data.
     */
    public function getClients(array $args = []): array
    {
        $defaults = [
            'post_type'      => ClientPostType::POST_TYPE,
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $query_args = array_merge($defaults, $args);
        $query = new \WP_Query($query_args);

        $clients = [];
        foreach ($query->posts as $post) {
            $client = $this->getClientData($post->ID);
            if ($client) {
                $client['invoice_count'] = $this->getClientInvoiceCount($post->ID);
                $clients[] = $client;
            }
        }

        return $clients;
    }

    /**
     * Search clients.
     *
     * @since 1.0.0
     *
     * @param string $search Search term.
     * @return array<array<string, mixed>> Array of client data.
     */
    public function searchClients(string $search): array
    {
        return $this->getClients(['s' => $search]);
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
     * Get client by email.
     *
     * @since 1.0.0
     *
     * @param string $email Email address.
     * @return array<string, mixed>|null Client data or null.
     */
    public function getClientByEmail(string $email): ?array
    {
        global $wpdb;

        $client_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_client_email'
                AND meta_value = %s
                LIMIT 1",
                $email
            )
        );

        return $client_id ? $this->getClientData((int) $client_id) : null;
    }

    /**
     * Get client statistics.
     *
     * @since 1.0.0
     *
     * @param int $client_id Client ID.
     * @return array<string, mixed> Statistics.
     */
    public function getClientStats(int $client_id): array
    {
        global $wpdb;

        $stats = [
            'total_invoices' => 0,
            'total_revenue'  => 0.0,
            'outstanding'    => 0.0,
            'paid_invoices'  => 0,
        ];

        // Get invoice IDs for this client
        $invoice_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_invoice_client_id'
                AND meta_value = %d",
                $client_id
            )
        );

        if (empty($invoice_ids)) {
            return $stats;
        }

        $stats['total_invoices'] = count($invoice_ids);

        foreach ($invoice_ids as $invoice_id) {
            $amount = (float) get_post_meta($invoice_id, '_invoice_total_amount', true);
            $status = get_post_meta($invoice_id, '_invoice_status', true);

            $stats['total_revenue'] += $amount;

            if ($status === 'paid') {
                $stats['paid_invoices']++;
            } elseif (!in_array($status, ['cancelled', 'draft'], true)) {
                $stats['outstanding'] += $amount;
            }
        }

        return $stats;
    }

    /**
     * Get invoice count for client.
     *
     * @since 1.0.0
     *
     * @param int $client_id Client ID.
     * @return int Invoice count.
     */
    private function getClientInvoiceCount(int $client_id): int
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_invoice_client_id'
                AND pm.meta_value = %d
                AND p.post_status != 'trash'",
                $client_id
            )
        );

        return (int) $count;
    }

    /**
     * Format address.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $client Client data.
     * @return string Formatted address.
     */
    public function formatAddress(array $client): string
    {
        $parts = array_filter([
            $client['address'] ?? '',
            $client['city'] ?? '',
            $client['state'] ?? '',
            $client['zip'] ?? '',
            $client['country'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
