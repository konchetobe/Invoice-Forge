<?php
/**
 * Client AJAX Handler
 *
 * Handles all AJAX requests for client operations.
 * FIXED: Added proper capability fallback and error logging.
 *
 * @package    InvoiceForge
 * @subpackage Ajax
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Ajax;

use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Sanitizer;
use InvoiceForge\Security\Validator;
use InvoiceForge\PostTypes\ClientPostType;
use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client AJAX handler class.
 *
 * @since 1.0.0
 */
class ClientAjaxHandler
{
    /**
     * Nonce handler.
     *
     * @since 1.0.0
     * @var Nonce
     */
    private Nonce $nonce;

    /**
     * Sanitizer instance.
     *
     * @since 1.0.0
     * @var Sanitizer
     */
    private Sanitizer $sanitizer;

    /**
     * Validator instance.
     *
     * @since 1.0.0
     * @var Validator
     */
    private Validator $validator;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger|null
     */
    private ?Logger $logger = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Nonce     $nonce     Nonce handler.
     * @param Sanitizer $sanitizer Sanitizer instance.
     * @param Validator $validator Validator instance.
     */
    public function __construct(
        Nonce $nonce,
        Sanitizer $sanitizer,
        Validator $validator
    ) {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
        
        // Initialize logger
        try {
            $this->logger = new Logger();
        } catch (\Exception $e) {
            // Logger initialization failed, continue without logging
        }
    }

    /**
     * Register AJAX hooks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        add_action('wp_ajax_invoiceforge_save_client', [$this, 'saveClient']);
        add_action('wp_ajax_invoiceforge_delete_client', [$this, 'deleteClient']);
        add_action('wp_ajax_invoiceforge_get_client', [$this, 'getClient']);
        add_action('wp_ajax_invoiceforge_get_clients', [$this, 'getClients']);
    }

    /**
     * Log a message if logger is available.
     *
     * @since 1.0.0
     *
     * @param string               $level   Log level.
     * @param string               $message Message to log.
     * @param array<string, mixed> $context Context data.
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, '[ClientAjax] ' . $message, $context);
        }
    }

    /**
     * Check if user has permission to edit clients.
     *
     * @since 1.0.0
     *
     * @return bool True if user has permission.
     */
    private function canEditClients(): bool
    {
        // Allow custom capability OR standard edit_posts capability
        return current_user_can('edit_if_clients') || current_user_can('edit_posts');
    }

    /**
     * Check if user has permission to delete clients.
     *
     * @since 1.0.0
     *
     * @return bool True if user has permission.
     */
    private function canDeleteClients(): bool
    {
        // Allow custom capability OR standard delete_posts capability
        return current_user_can('delete_if_clients') || current_user_can('delete_posts');
    }

    /**
     * Save client via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveClient(): void
    {
        $this->log('debug', 'saveClient called', ['POST' => $_POST]);

        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                $this->log('warning', 'Nonce verification failed');
                wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions with fallback
            if (!$this->canEditClients()) {
                $this->log('warning', 'Permission denied for user', ['user_id' => get_current_user_id()]);
                wp_send_json_error(['message' => __('You do not have permission to edit clients.', 'invoiceforge')], 403);
                return;
            }

            // Get and validate data
            $client_id = isset($_POST['client_id']) ? $this->sanitizer->absint($_POST['client_id']) : 0;
            $first_name = isset($_POST['first_name']) ? $this->sanitizer->text($_POST['first_name']) : '';
            $last_name = isset($_POST['last_name']) ? $this->sanitizer->text($_POST['last_name']) : '';
            $company = isset($_POST['company']) ? $this->sanitizer->text($_POST['company']) : '';
            $email = isset($_POST['email']) ? $this->sanitizer->email($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? $this->sanitizer->phone($_POST['phone']) : '';
            $address = isset($_POST['address']) ? $this->sanitizer->textarea($_POST['address']) : '';
            $city = isset($_POST['city']) ? $this->sanitizer->text($_POST['city']) : '';
            $state = isset($_POST['state']) ? $this->sanitizer->text($_POST['state']) : '';
            $zip = isset($_POST['zip']) ? $this->sanitizer->text($_POST['zip']) : '';
            $country = isset($_POST['country']) ? $this->sanitizer->text($_POST['country']) : '';
            $tax_id = isset($_POST['tax_id']) ? $this->sanitizer->text($_POST['tax_id']) : '';
            $id_no  = isset($_POST['id_no']) ? $this->sanitizer->text($_POST['id_no']) : '';
            $office = isset($_POST['office']) ? $this->sanitizer->text($_POST['office']) : '';
            $att_to = isset($_POST['att_to']) ? $this->sanitizer->text($_POST['att_to']) : '';

            $this->log('debug', 'Parsed client data', [
                'client_id' => $client_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
            ]);

            // Validate email format only if provided
            if (!empty($email) && !$this->validator->isValidEmail($email)) {
                wp_send_json_error(['message' => __('Please enter a valid email address.', 'invoiceforge')], 400);
                return;
            }

            // Build title from name (no parenthesised company suffix)
            if (!empty($company)) {
                $title = $company;
            } else {
                $title = trim($first_name . ' ' . $last_name);
            }
            if (empty($title)) {
                $title = $email ?: __('Unnamed Client', 'invoiceforge');
            }

            // Create or update post
            $post_data = [
                'post_title'  => $title,
                'post_type'   => ClientPostType::POST_TYPE,
                'post_status' => 'publish',
            ];

            if ($client_id > 0) {
                $post_data['ID'] = $client_id;
                $this->log('debug', 'Updating client', ['client_id' => $client_id]);
                $result = wp_update_post($post_data, true);
            } else {
                $this->log('debug', 'Creating new client');
                $result = wp_insert_post($post_data, true);
            }

            if (is_wp_error($result)) {
                $this->log('error', 'Failed to save client post', ['error' => $result->get_error_message()]);
                wp_send_json_error(['message' => $result->get_error_message()], 500);
                return;
            }

            $post_id = (int) $result;
            $this->log('debug', 'Client post saved', ['post_id' => $post_id]);

            // Save meta data
            update_post_meta($post_id, '_client_first_name', $first_name);
            update_post_meta($post_id, '_client_last_name', $last_name);
            update_post_meta($post_id, '_client_company', $company);
            update_post_meta($post_id, '_client_email', $email);
            update_post_meta($post_id, '_client_phone', $phone);
            update_post_meta($post_id, '_client_address', $address);
            update_post_meta($post_id, '_client_city', $city);
            update_post_meta($post_id, '_client_state', $state);
            update_post_meta($post_id, '_client_zip', $zip);
            update_post_meta($post_id, '_client_country', $country);
            update_post_meta($post_id, '_client_tax_id', $tax_id);
            update_post_meta($post_id, '_client_id_no', $id_no);
            update_post_meta($post_id, '_client_office', $office);
            update_post_meta($post_id, '_client_att_to', $att_to);

            $this->log('info', 'Client saved successfully', ['post_id' => $post_id]);

            /**
             * Fires after client is saved via AJAX.
             *
             * @since 1.0.0
             *
             * @param int $post_id The client post ID.
             */
            do_action('invoiceforge_client_saved', $post_id);

            wp_send_json_success([
                'message'   => $client_id > 0 ? __('Client updated successfully.', 'invoiceforge') : __('Client created successfully.', 'invoiceforge'),
                'client_id' => $post_id,
                'client'    => $this->getClientData($post_id),
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in saveClient', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred. Please try again.', 'invoiceforge')], 500);
        }
    }

    /**
     * Create a client from inline invoice form data.
     * Used when creating invoices with new clients.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $data Client data.
     * @return int|false Client post ID or false on failure.
     */
    public function createClientFromInvoice(array $data): int|false
    {
        $this->log('debug', 'createClientFromInvoice called', $data);

        $first_name = $this->sanitizer->text($data['first_name'] ?? '');
        $last_name = $this->sanitizer->text($data['last_name'] ?? '');
        $email = $this->sanitizer->email($data['email'] ?? '');
        $company = $this->sanitizer->text($data['company'] ?? '');
        $phone = $this->sanitizer->phone($data['phone'] ?? '');
        $address = $this->sanitizer->textarea($data['address'] ?? '');
        $city = $this->sanitizer->text($data['city'] ?? '');
        $state = $this->sanitizer->text($data['state'] ?? '');
        $zip = $this->sanitizer->text($data['zip'] ?? '');
        $country = $this->sanitizer->text($data['country'] ?? '');
        $tax_id  = $this->sanitizer->text($data['tax_id'] ?? '');
        $id_no   = $this->sanitizer->text($data['id_no'] ?? '');
        $office  = $this->sanitizer->text($data['office'] ?? '');
        $att_to  = $this->sanitizer->text($data['att_to'] ?? '');

        // Validate email format only if provided
        if (!empty($email) && !$this->validator->isValidEmail($email)) {
            $this->log('warning', 'Invalid email for inline client creation');
            return false;
        }

        // Build title (no parenthesised company suffix)
        if (!empty($company)) {
            $title = $company;
        } else {
            $title = trim($first_name . ' ' . $last_name);
        }
        if (empty($title)) {
            $title = $email ?: __('Unnamed Client', 'invoiceforge');
        }

        // Create post
        $post_data = [
            'post_title'  => $title,
            'post_type'   => ClientPostType::POST_TYPE,
            'post_status' => 'publish',
        ];

        $result = wp_insert_post($post_data, true);

        if (is_wp_error($result)) {
            $this->log('error', 'Failed to create inline client', ['error' => $result->get_error_message()]);
            return false;
        }

        $post_id = (int) $result;

        // Save meta data
        update_post_meta($post_id, '_client_first_name', $first_name);
        update_post_meta($post_id, '_client_last_name', $last_name);
        update_post_meta($post_id, '_client_company', $company);
        update_post_meta($post_id, '_client_email', $email);
        update_post_meta($post_id, '_client_phone', $phone);
        update_post_meta($post_id, '_client_address', $address);
        update_post_meta($post_id, '_client_city', $city);
        update_post_meta($post_id, '_client_state', $state);
        update_post_meta($post_id, '_client_zip', $zip);
        update_post_meta($post_id, '_client_country', $country);
        update_post_meta($post_id, '_client_tax_id', $tax_id);
        update_post_meta($post_id, '_client_id_no', $id_no);
        update_post_meta($post_id, '_client_office', $office);
        update_post_meta($post_id, '_client_att_to', $att_to);

        $this->log('info', 'Inline client created successfully', ['post_id' => $post_id]);

        do_action('invoiceforge_client_saved', $post_id);

        return $post_id;
    }

    /**
     * Delete client via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function deleteClient(): void
    {
        $this->log('debug', 'deleteClient called');

        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                $this->log('warning', 'Nonce verification failed for delete');
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canDeleteClients()) {
                $this->log('warning', 'Permission denied for delete');
                wp_send_json_error(['message' => __('You do not have permission to delete clients.', 'invoiceforge')], 403);
                return;
            }

            $client_id = isset($_POST['client_id']) ? $this->sanitizer->absint($_POST['client_id']) : 0;

            if ($client_id <= 0) {
                wp_send_json_error(['message' => __('Invalid client ID.', 'invoiceforge')], 400);
                return;
            }

            // Verify it's a client
            if (get_post_type($client_id) !== ClientPostType::POST_TYPE) {
                wp_send_json_error(['message' => __('Invalid client.', 'invoiceforge')], 400);
                return;
            }

            // Check if client has invoices
            $invoice_count = $this->getClientInvoiceCount($client_id);
            if ($invoice_count > 0) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Cannot delete client with %d invoice(s). Please delete or reassign the invoices first.', 'invoiceforge'),
                        $invoice_count
                    ),
                ], 400);
                return;
            }

            $result = wp_trash_post($client_id);

            if (!$result) {
                $this->log('error', 'Failed to delete client', ['client_id' => $client_id]);
                wp_send_json_error(['message' => __('Failed to delete client.', 'invoiceforge')], 500);
                return;
            }

            $this->log('info', 'Client deleted successfully', ['client_id' => $client_id]);

            wp_send_json_success([
                'message' => __('Client deleted successfully.', 'invoiceforge'),
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in deleteClient', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get single client via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function getClient(): void
    {
        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canEditClients()) {
                wp_send_json_error(['message' => __('You do not have permission to view clients.', 'invoiceforge')], 403);
                return;
            }

            $client_id = isset($_GET['client_id']) ? $this->sanitizer->absint($_GET['client_id']) : 0;

            if ($client_id <= 0) {
                wp_send_json_error(['message' => __('Invalid client ID.', 'invoiceforge')], 400);
                return;
            }

            $client = $this->getClientData($client_id);

            if (!$client) {
                wp_send_json_error(['message' => __('Client not found.', 'invoiceforge')], 404);
                return;
            }

            wp_send_json_success(['client' => $client]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in getClient', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get clients list via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function getClients(): void
    {
        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canEditClients()) {
                wp_send_json_error(['message' => __('You do not have permission to view clients.', 'invoiceforge')], 403);
                return;
            }

            $page = isset($_GET['page']) ? max(1, $this->sanitizer->absint($_GET['page'])) : 1;
            $per_page = isset($_GET['per_page']) ? min(100, max(1, $this->sanitizer->absint($_GET['per_page']))) : 20;
            $search = isset($_GET['search']) ? $this->sanitizer->text($_GET['search']) : '';

            $args = [
                'post_type'      => ClientPostType::POST_TYPE,
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ];

            if (!empty($search)) {
                $args['s'] = $search;
            }

            $query = new \WP_Query($args);

            $clients = [];
            foreach ($query->posts as $post) {
                $clients[] = $this->getClientData($post->ID);
            }

            wp_send_json_success([
                'clients'     => $clients,
                'total'       => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'page'        => $page,
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in getClients', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get client data array.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
     * @return array<string, mixed>|null Client data or null if not found.
     */
    public function getClientData(int $client_id): ?array
    {
        $post = get_post($client_id);
        if (!$post || $post->post_type !== ClientPostType::POST_TYPE) {
            return null;
        }

        return [
            'id'            => $client_id,
            'title'         => $post->post_title,
            'first_name'    => get_post_meta($client_id, '_client_first_name', true),
            'last_name'     => get_post_meta($client_id, '_client_last_name', true),
            'company'       => get_post_meta($client_id, '_client_company', true),
            'email'         => get_post_meta($client_id, '_client_email', true),
            'phone'         => get_post_meta($client_id, '_client_phone', true),
            'address'       => get_post_meta($client_id, '_client_address', true),
            'city'          => get_post_meta($client_id, '_client_city', true),
            'state'         => get_post_meta($client_id, '_client_state', true),
            'zip'           => get_post_meta($client_id, '_client_zip', true),
            'country'       => get_post_meta($client_id, '_client_country', true),
            'tax_id'        => get_post_meta($client_id, '_client_tax_id', true),
            'id_no'         => get_post_meta($client_id, '_client_id_no', true),
            'office'        => get_post_meta($client_id, '_client_office', true),
            'att_to'        => get_post_meta($client_id, '_client_att_to', true),
            'invoice_count' => $this->getClientInvoiceCount($client_id),
            'created_at'    => $post->post_date,
            'updated_at'    => $post->post_modified,
        ];
    }

    /**
     * Get invoice count for a client.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
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
}
