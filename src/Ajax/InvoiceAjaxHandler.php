<?php
/**
 * Invoice AJAX Handler
 *
 * Handles all AJAX requests for invoice operations.
 * FIXED: Added support for inline client creation and proper capability checks.
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
use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;
use InvoiceForge\Services\NumberingService;
use InvoiceForge\Services\TaxService;
use InvoiceForge\Models\LineItem;
use InvoiceForge\Repositories\LineItemRepository;
use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice AJAX handler class.
 *
 * @since 1.0.0
 */
class InvoiceAjaxHandler
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
     * Numbering service.
     *
     * @since 1.0.0
     * @var NumberingService
     */
    private NumberingService $numberingService;

    /**
     * Line item repository.
     *
     * @since 1.1.0
     * @var LineItemRepository
     */
    private LineItemRepository $lineItemRepo;

    /**
     * Tax calculation service.
     *
     * @since 1.1.0
     * @var TaxService
     */
    private TaxService $taxService;

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
     * @param Nonce              $nonce            Nonce handler.
     * @param Sanitizer          $sanitizer        Sanitizer instance.
     * @param Validator          $validator        Validator instance.
     * @param NumberingService   $numberingService Numbering service.
     * @param LineItemRepository $lineItemRepo     Line item repository.
     * @param TaxService         $taxService       Tax calculation service.
     */
    public function __construct(
        Nonce $nonce,
        Sanitizer $sanitizer,
        Validator $validator,
        NumberingService $numberingService,
        LineItemRepository $lineItemRepo,
        TaxService $taxService
    ) {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
        $this->numberingService = $numberingService;
        $this->lineItemRepo = $lineItemRepo;
        $this->taxService = $taxService;
        
        // Initialize logger
        try {
            $this->logger = new Logger();
        } catch (\Exception $e) {
            // Logger initialization failed
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
        add_action('wp_ajax_invoiceforge_save_invoice', [$this, 'saveInvoice']);
        add_action('wp_ajax_invoiceforge_delete_invoice', [$this, 'deleteInvoice']);
        add_action('wp_ajax_invoiceforge_get_invoice', [$this, 'getInvoice']);
        add_action('wp_ajax_invoiceforge_get_invoices', [$this, 'getInvoices']);
        add_action('wp_ajax_invoiceforge_get_tax_rates', [$this, 'getTaxRates']);
        add_action('wp_ajax_invoiceforge_save_tax_rate', [$this, 'saveTaxRate']);
        add_action('wp_ajax_invoiceforge_delete_tax_rate', [$this, 'deleteTaxRate']);

        // Phase 1C: PDF & Email
        add_action('wp_ajax_invoiceforge_download_pdf', [$this, 'downloadPdf']);
        add_action('wp_ajax_invoiceforge_preview_pdf', [$this, 'previewPdf']);
        add_action('wp_ajax_invoiceforge_send_invoice_email', [$this, 'sendInvoiceEmail']);
        add_action('wp_ajax_invoiceforge_send_reminder', [$this, 'sendReminder']);

        // Phase 2: WooCommerce Integration
        add_action('wp_ajax_invoiceforge_generate_from_order', [$this, 'generateFromOrder']);
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
            $this->logger->log($level, '[InvoiceAjax] ' . $message, $context);
        }
    }

    /**
     * Check if user has permission to edit invoices.
     *
     * @since 1.0.0
     *
     * @return bool True if user has permission.
     */
    private function canEditInvoices(): bool
    {
        return current_user_can('edit_if_invoices') || current_user_can('edit_posts');
    }

    /**
     * Check if user has permission to delete invoices.
     *
     * @since 1.0.0
     *
     * @return bool True if user has permission.
     */
    private function canDeleteInvoices(): bool
    {
        return current_user_can('delete_if_invoices') || current_user_can('delete_posts');
    }

    /**
     * Save invoice via AJAX.
     * Supports both existing client selection and inline new client creation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveInvoice(): void
    {
        $this->log('debug', 'saveInvoice called', ['POST' => $_POST]);

        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                $this->log('warning', 'Nonce verification failed');
                wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canEditInvoices()) {
                $this->log('warning', 'Permission denied', ['user_id' => get_current_user_id()]);
                wp_send_json_error(['message' => __('You do not have permission to edit invoices.', 'invoiceforge')], 403);
                return;
            }

            // Get and validate data
            $invoice_id = isset($_POST['invoice_id']) ? $this->sanitizer->absint($_POST['invoice_id']) : 0;
            $title = isset($_POST['title']) ? $this->sanitizer->text($_POST['title']) : '';
            $client_id = isset($_POST['client_id']) ? $this->sanitizer->absint($_POST['client_id']) : 0;
            $invoice_date = isset($_POST['invoice_date']) ? $this->sanitizer->date($_POST['invoice_date']) : '';
            $due_date = isset($_POST['due_date']) ? $this->sanitizer->date($_POST['due_date']) : '';
            $status = isset($_POST['status']) ? $this->sanitizer->option($_POST['status'], array_keys(InvoicePostType::STATUSES), 'draft') : 'draft';
            $total_amount = isset($_POST['total_amount']) ? $this->sanitizer->money($_POST['total_amount']) : 0;
            $currency = isset($_POST['currency']) ? $this->sanitizer->text($_POST['currency']) : 'USD';
            $notes = isset($_POST['notes']) ? $this->sanitizer->textarea($_POST['notes']) : '';

            // Check for inline client creation (client_mode = 'new')
            $client_mode = isset($_POST['client_mode']) ? $this->sanitizer->text($_POST['client_mode']) : 'existing';
            
            if ($client_mode === 'new' && $client_id === 0) {
                // Create new client from inline data
                $this->log('debug', 'Creating inline client');
                
                $client_data = [
                    'first_name' => isset($_POST['new_client_first_name']) ? $_POST['new_client_first_name'] : '',
                    'last_name'  => isset($_POST['new_client_last_name']) ? $_POST['new_client_last_name'] : '',
                    'email'      => isset($_POST['new_client_email']) ? $_POST['new_client_email'] : '',
                    'company'    => isset($_POST['new_client_company']) ? $_POST['new_client_company'] : '',
                    'phone'      => isset($_POST['new_client_phone']) ? $_POST['new_client_phone'] : '',
                    'address'    => isset($_POST['new_client_address']) ? $_POST['new_client_address'] : '',
                    'city'       => isset($_POST['new_client_city']) ? $_POST['new_client_city'] : '',
                    'state'      => isset($_POST['new_client_state']) ? $_POST['new_client_state'] : '',
                    'zip'        => isset($_POST['new_client_zip']) ? $_POST['new_client_zip'] : '',
                    'country'    => isset($_POST['new_client_country']) ? $_POST['new_client_country'] : '',
                ];

                // Validate inline client data
                $client_first_name = $this->sanitizer->text($client_data['first_name']);
                $client_last_name = $this->sanitizer->text($client_data['last_name']);
                $client_email = $this->sanitizer->email($client_data['email']);

                if (empty($client_first_name) || empty($client_last_name) || empty($client_email)) {
                    $this->log('debug', 'Missing required inline client fields');
                    wp_send_json_error(['message' => __('For new clients, first name, last name and email are required.', 'invoiceforge')], 400);
                    return;
                }

                // Create the client using ClientAjaxHandler
                $clientHandler = new ClientAjaxHandler($this->nonce, $this->sanitizer, $this->validator);
                $new_client_id = $clientHandler->createClientFromInvoice($client_data);

                if ($new_client_id === false) {
                    $this->log('error', 'Failed to create inline client');
                    wp_send_json_error(['message' => __('Failed to create new client. Please check the client details.', 'invoiceforge')], 400);
                    return;
                }

                $client_id = $new_client_id;
                $this->log('info', 'Inline client created', ['client_id' => $client_id]);
            }

            // Validate required fields
            $errors = [];
            if (empty($title)) {
                $errors[] = __('Invoice title is required.', 'invoiceforge');
            }
            if (empty($client_id)) {
                $errors[] = __('Client is required. Select an existing client or add new client details.', 'invoiceforge');
            }

            if (!empty($errors)) {
                $this->log('debug', 'Validation errors', ['errors' => $errors]);
                wp_send_json_error(['message' => implode(' ', $errors)], 400);
                return;
            }

            // Create or update post
            $post_data = [
                'post_title'  => $title,
                'post_type'   => InvoicePostType::POST_TYPE,
                'post_status' => 'publish',
            ];

            if ($invoice_id > 0) {
                $post_data['ID'] = $invoice_id;
                $this->log('debug', 'Updating invoice', ['invoice_id' => $invoice_id]);
                $result = wp_update_post($post_data, true);
            } else {
                $this->log('debug', 'Creating new invoice');
                $result = wp_insert_post($post_data, true);
            }

            if (is_wp_error($result)) {
                $this->log('error', 'Failed to save invoice', ['error' => $result->get_error_message()]);
                wp_send_json_error(['message' => $result->get_error_message()], 500);
                return;
            }

            $post_id = (int) $result;

            // Generate invoice number if new
            $existing_number = get_post_meta($post_id, '_invoice_number', true);
            if (empty($existing_number)) {
                $invoice_number = $this->numberingService->generate();
                update_post_meta($post_id, '_invoice_number', $invoice_number);
            }

            // Save meta data
            update_post_meta($post_id, '_invoice_client_id', $client_id);
            update_post_meta($post_id, '_invoice_date', $invoice_date);
            update_post_meta($post_id, '_invoice_due_date', $due_date);
            update_post_meta($post_id, '_invoice_status', $status);
            update_post_meta($post_id, '_invoice_currency', $currency);
            update_post_meta($post_id, '_invoice_notes', $notes);

            // Save terms and conditions
            $terms = isset($_POST['terms']) ? $this->sanitizer->textarea($_POST['terms']) : '';
            update_post_meta($post_id, '_invoice_terms', $terms);

            // Save internal notes
            $internal_notes = isset($_POST['internal_notes']) ? $this->sanitizer->textarea($_POST['internal_notes']) : '';
            update_post_meta($post_id, '_invoice_internal_notes', $internal_notes);

            // Save discount
            $discount_type  = isset($_POST['discount_type']) ? $this->sanitizer->option($_POST['discount_type'], ['', 'percentage', 'fixed'], '') : '';
            $discount_value = isset($_POST['discount_value']) ? $this->sanitizer->money($_POST['discount_value']) : 0;
            update_post_meta($post_id, '_invoice_discount_type', $discount_type);
            update_post_meta($post_id, '_invoice_discount_value', $discount_value);

            // --- Line Items ---
            $this->lineItemRepo->deleteByInvoice($post_id);

            $rawItems = isset($_POST['line_items']) && is_array($_POST['line_items']) ? $_POST['line_items'] : [];
            $lineItems = [];

            foreach ($rawItems as $order => $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }

                $item = LineItem::fromArray($rawItem);
                $item->id = 0; // Always insert fresh
                $item->invoice_id = $post_id;
                $item->item_order = (int) $order;
                $lineItems[] = $item;
            }

            // Calculate totals via TaxService
            $calculated = $this->taxService->calculateInvoice($lineItems);

            foreach ($calculated['items'] as $item) {
                $this->lineItemRepo->save($item);
            }

            // Apply discount
            $invoiceSubtotal = $calculated['subtotal'];
            $invoiceTax = $calculated['tax'];
            $invoiceTotal = $calculated['total'];

            if ($discount_type === 'percentage' && $discount_value > 0) {
                $discountAmount = round($invoiceSubtotal * ($discount_value / 100), 2);
                $invoiceTotal = round($invoiceTotal - $discountAmount, 2);
            } elseif ($discount_type === 'fixed' && $discount_value > 0) {
                $invoiceTotal = round($invoiceTotal - $discount_value, 2);
            }

            // Store computed totals
            update_post_meta($post_id, '_invoice_subtotal', $invoiceSubtotal);
            update_post_meta($post_id, '_invoice_tax', $invoiceTax);
            update_post_meta($post_id, '_invoice_total_amount', max(0, $invoiceTotal));

            $this->log('info', 'Invoice saved successfully', ['post_id' => $post_id]);

            /**
             * Fires after invoice is saved via AJAX.
             *
             * @since 1.0.0
             *
             * @param int $post_id The invoice post ID.
             */
            do_action('invoiceforge_invoice_saved', $post_id);

            wp_send_json_success([
                'message'    => $invoice_id > 0 ? __('Invoice updated successfully.', 'invoiceforge') : __('Invoice created successfully.', 'invoiceforge'),
                'invoice_id' => $post_id,
                'invoice'    => $this->getInvoiceData($post_id),
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in saveInvoice', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred. Please try again.', 'invoiceforge')], 500);
        }
    }

    /**
     * Delete invoice via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function deleteInvoice(): void
    {
        $this->log('debug', 'deleteInvoice called');

        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canDeleteInvoices()) {
                wp_send_json_error(['message' => __('You do not have permission to delete invoices.', 'invoiceforge')], 403);
                return;
            }

            $invoice_id = isset($_POST['invoice_id']) ? $this->sanitizer->absint($_POST['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_send_json_error(['message' => __('Invalid invoice ID.', 'invoiceforge')], 400);
                return;
            }

            // Verify it's an invoice
            if (get_post_type($invoice_id) !== InvoicePostType::POST_TYPE) {
                wp_send_json_error(['message' => __('Invalid invoice.', 'invoiceforge')], 400);
                return;
            }

            $result = wp_trash_post($invoice_id);

            if (!$result) {
                $this->log('error', 'Failed to delete invoice', ['invoice_id' => $invoice_id]);
                wp_send_json_error(['message' => __('Failed to delete invoice.', 'invoiceforge')], 500);
                return;
            }

            $this->log('info', 'Invoice deleted successfully', ['invoice_id' => $invoice_id]);

            wp_send_json_success([
                'message' => __('Invoice deleted successfully.', 'invoiceforge'),
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in deleteInvoice', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get single invoice via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function getInvoice(): void
    {
        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('You do not have permission to view invoices.', 'invoiceforge')], 403);
                return;
            }

            $invoice_id = isset($_GET['invoice_id']) ? $this->sanitizer->absint($_GET['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_send_json_error(['message' => __('Invalid invoice ID.', 'invoiceforge')], 400);
                return;
            }

            $invoice = $this->getInvoiceData($invoice_id);

            if (!$invoice) {
                wp_send_json_error(['message' => __('Invoice not found.', 'invoiceforge')], 404);
                return;
            }

            wp_send_json_success(['invoice' => $invoice]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in getInvoice', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get invoices list via AJAX.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function getInvoices(): void
    {
        try {
            // Verify nonce
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            // Check permissions
            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('You do not have permission to view invoices.', 'invoiceforge')], 403);
                return;
            }

            $page = isset($_GET['page']) ? max(1, $this->sanitizer->absint($_GET['page'])) : 1;
            $per_page = isset($_GET['per_page']) ? min(100, max(1, $this->sanitizer->absint($_GET['per_page']))) : 20;
            $status = isset($_GET['status']) ? $this->sanitizer->text($_GET['status']) : '';
            $search = isset($_GET['search']) ? $this->sanitizer->text($_GET['search']) : '';

            $args = [
                'post_type'      => InvoicePostType::POST_TYPE,
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];

            if (!empty($search)) {
                $args['s'] = $search;
            }

            if (!empty($status) && array_key_exists($status, InvoicePostType::STATUSES)) {
                $args['meta_query'] = [
                    [
                        'key'   => '_invoice_status',
                        'value' => $status,
                    ],
                ];
            }

            $query = new \WP_Query($args);

            $invoices = [];
            foreach ($query->posts as $post) {
                $invoices[] = $this->getInvoiceData($post->ID);
            }

            wp_send_json_success([
                'invoices'    => $invoices,
                'total'       => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'page'        => $page,
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in getInvoices', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get invoice data array.
     *
     * @since 1.0.0
     *
     * @param int $invoice_id The invoice post ID.
     * @return array<string, mixed>|null Invoice data or null if not found.
     */
    private function getInvoiceData(int $invoice_id): ?array
    {
        $post = get_post($invoice_id);
        if (!$post || $post->post_type !== InvoicePostType::POST_TYPE) {
            return null;
        }

        $client_id = (int) get_post_meta($invoice_id, '_invoice_client_id', true);
        $client = $client_id ? get_post($client_id) : null;

        // Load line items
        $lineItems = $this->lineItemRepo->findByInvoice($invoice_id);
        $lineItemsArray = array_map(fn(LineItem $item) => $item->toArray(), $lineItems);

        return [
            'id'              => $invoice_id,
            'title'           => $post->post_title,
            'number'          => get_post_meta($invoice_id, '_invoice_number', true),
            'client_id'       => $client_id,
            'client_name'     => $client ? $client->post_title : '',
            'date'            => get_post_meta($invoice_id, '_invoice_date', true),
            'due_date'        => get_post_meta($invoice_id, '_invoice_due_date', true),
            'status'          => get_post_meta($invoice_id, '_invoice_status', true) ?: 'draft',
            'subtotal'        => (float) get_post_meta($invoice_id, '_invoice_subtotal', true),
            'tax'             => (float) get_post_meta($invoice_id, '_invoice_tax', true),
            'total_amount'    => (float) get_post_meta($invoice_id, '_invoice_total_amount', true),
            'currency'        => get_post_meta($invoice_id, '_invoice_currency', true) ?: 'USD',
            'notes'           => get_post_meta($invoice_id, '_invoice_notes', true),
            'terms'           => get_post_meta($invoice_id, '_invoice_terms', true),
            'internal_notes'  => get_post_meta($invoice_id, '_invoice_internal_notes', true),
            'discount_type'   => get_post_meta($invoice_id, '_invoice_discount_type', true),
            'discount_value'  => (float) get_post_meta($invoice_id, '_invoice_discount_value', true),
            'line_items'      => $lineItemsArray,
            'created_at'      => $post->post_date,
            'updated_at'      => $post->post_modified,
        ];
    }

    /**
     * Download PDF via AJAX.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function downloadPdf(): void
    {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'invoiceforge_ajax')) {
                wp_die(__('Security check failed.', 'invoiceforge'));
            }

            if (!$this->canEditInvoices()) {
                wp_die(__('Unauthorized.', 'invoiceforge'));
            }

            $invoice_id = isset($_GET['invoice_id']) ? $this->sanitizer->absint($_GET['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_die(__('Invalid invoice ID.', 'invoiceforge'));
            }

            $pdfService = new \InvoiceForge\Services\PdfService($this->logger ?? new \InvoiceForge\Utilities\Logger());
            $pdfService->generate($invoice_id, 'D'); // Will output directly and structure exit
            exit;
            
        } catch (\Exception $e) {
            $this->log('error', 'Exception in downloadPdf', ['exception' => $e->getMessage()]);
            wp_die(__('An error occurred while generating the PDF.', 'invoiceforge'));
        }
    }

    /**
     * Preview PDF via AJAX in browser inline.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function previewPdf(): void
    {
        try {
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'invoiceforge_ajax')) {
                wp_die(__('Security check failed.', 'invoiceforge'));
            }

            if (!$this->canEditInvoices()) {
                wp_die(__('Unauthorized.', 'invoiceforge'));
            }

            $invoice_id = isset($_GET['invoice_id']) ? $this->sanitizer->absint($_GET['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_die(__('Invalid invoice ID.', 'invoiceforge'));
            }

            $pdfService = new \InvoiceForge\Services\PdfService($this->logger ?? new \InvoiceForge\Utilities\Logger());
            $pdfService->generate($invoice_id, 'I'); // Inline preview
            exit;

        } catch (\Exception $e) {
            $this->log('error', 'Exception in previewPdf', ['exception' => $e->getMessage()]);
            wp_die(__('An error occurred while previewing the PDF.', 'invoiceforge'));
        }
    }

    /**
     * Send Invoice Email via AJAX.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function sendInvoiceEmail(): void
    {
        try {
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'invoiceforge')], 403);
                return;
            }

            $invoice_id = isset($_POST['invoice_id']) ? $this->sanitizer->absint($_POST['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_send_json_error(['message' => __('Invalid invoice ID.', 'invoiceforge')], 400);
                return;
            }

            $logger = $this->logger ?? new \InvoiceForge\Utilities\Logger();
            $pdfService = new \InvoiceForge\Services\PdfService($logger);
            $emailService = new \InvoiceForge\Services\EmailService($logger, $pdfService);

            $result = $emailService->sendInvoice($invoice_id);

            if ($result) {
                wp_send_json_success(['message' => __('Email sent successfully.', 'invoiceforge')]);
            } else {
                wp_send_json_error(['message' => __('Failed to send email. Check error logs for more details.', 'invoiceforge')], 500);
            }

        } catch (\Exception $e) {
            $this->log('error', 'Exception in sendInvoiceEmail', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Send Reminder Email via AJAX.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function sendReminder(): void
    {
        try {
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'invoiceforge')], 403);
                return;
            }

            $invoice_id = isset($_POST['invoice_id']) ? $this->sanitizer->absint($_POST['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                wp_send_json_error(['message' => __('Invalid invoice ID.', 'invoiceforge')], 400);
                return;
            }

            $logger = $this->logger ?? new \InvoiceForge\Utilities\Logger();
            $pdfService = new \InvoiceForge\Services\PdfService($logger);
            $emailService = new \InvoiceForge\Services\EmailService($logger, $pdfService);

            $result = $emailService->sendReminder($invoice_id);

            if ($result) {
                wp_send_json_success(['message' => __('Reminder sent successfully.', 'invoiceforge')]);
            } else {
                wp_send_json_error(['message' => __('Failed to send reminder. Check error logs for more details.', 'invoiceforge')], 500);
            }

        } catch (\Exception $e) {
            $this->log('error', 'Exception in sendReminder', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Get available tax rates via AJAX.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function getTaxRates(): void
    {
        try {
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'invoiceforge')], 403);
                return;
            }

            $taxRateRepo = $this->taxService->getTaxRateRepository();
            $rates = $taxRateRepo->findAllActive();

            $ratesArray = array_map(fn($rate) => $rate->toArray(), $rates);

            wp_send_json_success(['tax_rates' => $ratesArray]);

        } catch (\Exception $e) {
            $this->log('error', 'Exception in getTaxRates', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500);
        }
    }

    /**
     * Manually generate an invoice from a WooCommerce order.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function generateFromOrder(): void
    {
        try {
            if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
                wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
                return;
            }

            if (!$this->canEditInvoices()) {
                wp_send_json_error(['message' => __('Unauthorized.', 'invoiceforge')], 403);
                return;
            }

            $order_id = isset($_POST['order_id']) ? $this->sanitizer->absint($_POST['order_id']) : 0;

            if ($order_id <= 0) {
                wp_send_json_error(['message' => __('Invalid order ID.', 'invoiceforge')], 400);
                return;
            }

            if (!\InvoiceForge\Integrations\WooCommerce\WooCommerceIntegration::isAvailable()) {
                wp_send_json_error(['message' => __('WooCommerce is not active.', 'invoiceforge')], 400);
                return;
            }

            $logger     = $this->logger ?? new \InvoiceForge\Utilities\Logger();
            $integration = new \InvoiceForge\Integrations\WooCommerce\WooCommerceIntegration(
                $logger,
                $this->lineItemRepo,
                $this->numberingService
            );

            $invoice_id = $integration->generateFromOrder($order_id);

            if ($invoice_id) {
                wp_send_json_success([
                    'message'    => __('Invoice generated successfully.', 'invoiceforge'),
                    'invoice_id' => $invoice_id,
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to generate invoice. Check logs for details.', 'invoiceforge')], 500);
            }

        } catch (\Exception $e) {
            $this->log('error', 'generateFromOrder failed', ['exception' => $e->getMessage()]);
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }
}
