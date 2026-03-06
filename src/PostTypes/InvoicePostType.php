<?php
/**
 * Invoice Post Type
 *
 * Registers and manages the Invoice custom post type.
 *
 * @package    InvoiceForge
 * @subpackage PostTypes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\PostTypes;

use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Sanitizer;
use InvoiceForge\Services\NumberingService;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice custom post type handler.
 *
 * @since 1.0.0
 */
class InvoicePostType
{
    /**
     * Post type name.
     *
     * @since 1.0.0
     * @var string
     */
    public const POST_TYPE = 'if_invoice';

    /**
     * Meta key prefix.
     *
     * @since 1.0.0
     * @var string
     */
    private const META_PREFIX = '_invoice_';

    /**
     * Nonce action name.
     *
     * @since 1.0.0
     * @var string
     */
    private const NONCE_ACTION = 'save_invoice_meta';

    /**
     * Nonce field name.
     *
     * @since 1.0.0
     * @var string
     */
    private const NONCE_FIELD = 'invoiceforge_invoice_nonce';

    /**
     * Invoice statuses.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public const STATUSES = [
        'draft'     => 'Draft',
        'sent'      => 'Sent',
        'paid'      => 'Paid',
        'overdue'   => 'Overdue',
        'cancelled' => 'Cancelled',
    ];

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
     * Numbering service.
     *
     * @since 1.0.0
     * @var NumberingService
     */
    private NumberingService $numberingService;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Nonce            $nonce            Nonce handler.
     * @param Sanitizer        $sanitizer        Sanitizer instance.
     * @param NumberingService $numberingService Numbering service.
     */
    public function __construct(Nonce $nonce, Sanitizer $sanitizer, NumberingService $numberingService)
    {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
        $this->numberingService = $numberingService;
    }

    /**
     * Register the invoice post type.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $labels = [
            'name'                  => _x('Invoices', 'Post type general name', 'invoiceforge'),
            'singular_name'         => _x('Invoice', 'Post type singular name', 'invoiceforge'),
            'menu_name'             => _x('Invoices', 'Admin Menu text', 'invoiceforge'),
            'name_admin_bar'        => _x('Invoice', 'Add New on Toolbar', 'invoiceforge'),
            'add_new'               => __('Add New', 'invoiceforge'),
            'add_new_item'          => __('Add New Invoice', 'invoiceforge'),
            'new_item'              => __('New Invoice', 'invoiceforge'),
            'edit_item'             => __('Edit Invoice', 'invoiceforge'),
            'view_item'             => __('View Invoice', 'invoiceforge'),
            'all_items'             => __('All Invoices', 'invoiceforge'),
            'search_items'          => __('Search Invoices', 'invoiceforge'),
            'parent_item_colon'     => __('Parent Invoices:', 'invoiceforge'),
            'not_found'             => __('No invoices found.', 'invoiceforge'),
            'not_found_in_trash'    => __('No invoices found in Trash.', 'invoiceforge'),
            'featured_image'        => _x('Invoice Logo', 'Overrides the "Featured Image" phrase', 'invoiceforge'),
            'set_featured_image'    => _x('Set invoice logo', 'Overrides the "Set featured image" phrase', 'invoiceforge'),
            'remove_featured_image' => _x('Remove invoice logo', 'Overrides the "Remove featured image" phrase', 'invoiceforge'),
            'use_featured_image'    => _x('Use as invoice logo', 'Overrides the "Use as featured image" phrase', 'invoiceforge'),
            'archives'              => _x('Invoice archives', 'The post type archive label', 'invoiceforge'),
            'insert_into_item'      => _x('Insert into invoice', 'Overrides the "Insert into post" phrase', 'invoiceforge'),
            'uploaded_to_this_item' => _x('Uploaded to this invoice', 'Overrides the "Uploaded to this post" phrase', 'invoiceforge'),
            'filter_items_list'     => _x('Filter invoices list', 'Screen reader text', 'invoiceforge'),
            'items_list_navigation' => _x('Invoices list navigation', 'Screen reader text', 'invoiceforge'),
            'items_list'            => _x('Invoices list', 'Screen reader text', 'invoiceforge'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it to our custom menu
            'query_var'          => true,
            'rewrite'            => ['slug' => 'invoice'],
            'capability_type'    => ['if_invoice', 'if_invoices'],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-media-document',
            'supports'           => ['title', 'author'],
            'show_in_rest'       => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for the invoice post type.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addMetaBoxes(): void
    {
        add_meta_box(
            'invoiceforge_invoice_details',
            __('Invoice Details', 'invoiceforge'),
            [$this, 'renderDetailsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'invoiceforge_invoice_notes',
            __('Notes', 'invoiceforge'),
            [$this, 'renderNotesMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'invoiceforge_invoice_status',
            __('Invoice Status', 'invoiceforge'),
            [$this, 'renderStatusMetaBox'],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render the invoice details meta box.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderDetailsMetaBox(\WP_Post $post): void
    {
        // Add nonce for security - use consistent field name
        $this->nonce->field(self::NONCE_ACTION, self::NONCE_FIELD);

        // Get current values
        $invoice_number = get_post_meta($post->ID, self::META_PREFIX . 'number', true);
        $client_id = get_post_meta($post->ID, self::META_PREFIX . 'client_id', true);
        $invoice_date = get_post_meta($post->ID, self::META_PREFIX . 'date', true);
        $due_date = get_post_meta($post->ID, self::META_PREFIX . 'due_date', true);
        $total_amount = get_post_meta($post->ID, self::META_PREFIX . 'total_amount', true);
        $currency = get_post_meta($post->ID, self::META_PREFIX . 'currency', true) ?: 'USD';

        // Set default dates for new invoices
        if (empty($invoice_date)) {
            $invoice_date = current_time('Y-m-d');
        }
        if (empty($due_date)) {
            $due_date = gmdate('Y-m-d', strtotime('+30 days'));
        }

        // Get all clients
        $clients = get_posts([
            'post_type'      => ClientPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        ?>
        <table class="form-table invoiceforge-meta-table">
            <tr>
                <th scope="row">
                    <label for="invoice_number"><?php esc_html_e('Invoice Number', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="text" id="invoice_number" name="invoice_number" 
                           value="<?php echo esc_attr($invoice_number); ?>" 
                           class="regular-text" readonly 
                           placeholder="<?php esc_attr_e('Auto-generated on save', 'invoiceforge'); ?>">
                    <p class="description">
                        <?php esc_html_e('Invoice number is automatically generated.', 'invoiceforge'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_id"><?php esc_html_e('Client', 'invoiceforge'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select id="client_id" name="client_id" class="regular-text" required>
                        <option value=""><?php esc_html_e('Select a client...', 'invoiceforge'); ?></option>
                        <?php foreach ($clients as $client) : ?>
                            <option value="<?php echo esc_attr((string) $client->ID); ?>" 
                                    <?php selected($client_id, $client->ID); ?>>
                                <?php echo esc_html($client->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($clients)) : ?>
                        <p class="description">
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . ClientPostType::POST_TYPE)); ?>">
                                <?php esc_html_e('Add a client first', 'invoiceforge'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="invoice_date"><?php esc_html_e('Invoice Date', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="date" id="invoice_date" name="invoice_date" 
                           value="<?php echo esc_attr($invoice_date); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="due_date"><?php esc_html_e('Due Date', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="date" id="due_date" name="due_date" 
                           value="<?php echo esc_attr($due_date); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="total_amount"><?php esc_html_e('Total Amount', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="number" id="total_amount" name="total_amount" 
                           value="<?php echo esc_attr($total_amount); ?>" 
                           class="regular-text" step="0.01" min="0">
                    <p class="description">
                        <?php esc_html_e('For Phase 1A, enter the total manually. Line items will be added in Phase 1B.', 'invoiceforge'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="currency"><?php esc_html_e('Currency', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <select id="currency" name="currency" class="regular-text">
                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - US Dollar</option>
                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro</option>
                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound</option>
                        <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar</option>
                        <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar</option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Multi-currency support will be expanded in Phase 4.', 'invoiceforge'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the invoice notes meta box.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderNotesMetaBox(\WP_Post $post): void
    {
        $notes = get_post_meta($post->ID, self::META_PREFIX . 'notes', true);
        ?>
        <label for="invoice_notes" class="screen-reader-text">
            <?php esc_html_e('Invoice Notes', 'invoiceforge'); ?>
        </label>
        <textarea id="invoice_notes" name="invoice_notes" rows="5" class="large-text"
                  placeholder="<?php esc_attr_e('Add any notes or terms for this invoice...', 'invoiceforge'); ?>"><?php 
            echo esc_textarea($notes); 
        ?></textarea>
        <p class="description">
            <?php esc_html_e('These notes will appear on the invoice.', 'invoiceforge'); ?>
        </p>
        <?php
    }

    /**
     * Render the invoice status meta box.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderStatusMetaBox(\WP_Post $post): void
    {
        $status = get_post_meta($post->ID, self::META_PREFIX . 'status', true) ?: 'draft';
        $statuses = $this->getStatuses();
        ?>
        <div class="invoiceforge-status-box">
            <label for="invoice_status" class="screen-reader-text">
                <?php esc_html_e('Invoice Status', 'invoiceforge'); ?>
            </label>
            <select id="invoice_status" name="invoice_status" class="widefat">
                <?php foreach ($statuses as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invoiceforge-status-indicator status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html($statuses[$status] ?? $status); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save invoice meta data.
     *
     * @since 1.0.0
     *
     * @param int $post_id The post ID.
     * @return void
     */
    public function saveMetaData(int $post_id): void
    {
        // Verify nonce - FIXED: use consistent field name
        if (!$this->nonce->verifyRequest(self::NONCE_ACTION, self::NONCE_FIELD)) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Generate invoice number if not set
        $existing_number = get_post_meta($post_id, self::META_PREFIX . 'number', true);
        if (empty($existing_number)) {
            $invoice_number = $this->numberingService->generate();
            update_post_meta($post_id, self::META_PREFIX . 'number', $invoice_number);
        }

        // Save client ID
        if (isset($_POST['client_id'])) {
            $client_id = $this->sanitizer->absint($_POST['client_id']);
            update_post_meta($post_id, self::META_PREFIX . 'client_id', $client_id);
        }

        // Save invoice date
        if (isset($_POST['invoice_date'])) {
            $invoice_date = $this->sanitizer->date($_POST['invoice_date']);
            update_post_meta($post_id, self::META_PREFIX . 'date', $invoice_date);
        }

        // Save due date
        if (isset($_POST['due_date'])) {
            $due_date = $this->sanitizer->date($_POST['due_date']);
            update_post_meta($post_id, self::META_PREFIX . 'due_date', $due_date);
        }

        // Save status
        if (isset($_POST['invoice_status'])) {
            $status = $this->sanitizer->option(
                $_POST['invoice_status'],
                array_keys(self::STATUSES),
                'draft'
            );
            update_post_meta($post_id, self::META_PREFIX . 'status', $status);
        }

        // Save total amount
        if (isset($_POST['total_amount'])) {
            $total_amount = $this->sanitizer->money($_POST['total_amount']);
            update_post_meta($post_id, self::META_PREFIX . 'total_amount', $total_amount);
        }

        // Save currency
        if (isset($_POST['currency'])) {
            $currency = $this->sanitizer->text($_POST['currency']);
            update_post_meta($post_id, self::META_PREFIX . 'currency', $currency);
        }

        // Save notes
        if (isset($_POST['invoice_notes'])) {
            $notes = $this->sanitizer->textarea($_POST['invoice_notes']);
            update_post_meta($post_id, self::META_PREFIX . 'notes', $notes);
        }

        /**
         * Fires after invoice meta data is saved.
         *
         * @since 1.0.0
         *
         * @param int $post_id The invoice post ID.
         */
        do_action('invoiceforge_invoice_saved', $post_id);
    }

    /**
     * Add custom columns to the invoice list.
     *
     * @since 1.0.0
     *
     * @param array<string, string> $columns The existing columns.
     * @return array<string, string> The modified columns.
     */
    public function addAdminColumns(array $columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['invoice_number'] = __('Invoice #', 'invoiceforge');
                $new_columns['client'] = __('Client', 'invoiceforge');
                $new_columns['invoice_date'] = __('Date', 'invoiceforge');
                $new_columns['due_date'] = __('Due Date', 'invoiceforge');
                $new_columns['total_amount'] = __('Amount', 'invoiceforge');
                $new_columns['invoice_status'] = __('Status', 'invoiceforge');
            } elseif ($key !== 'date') {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @since 1.0.0
     *
     * @param string $column  The column name.
     * @param int    $post_id The post ID.
     * @return void
     */
    public function renderAdminColumn(string $column, int $post_id): void
    {
        switch ($column) {
            case 'invoice_number':
                $number = get_post_meta($post_id, self::META_PREFIX . 'number', true);
                echo esc_html($number ?: '—');
                break;

            case 'client':
                $client_id = get_post_meta($post_id, self::META_PREFIX . 'client_id', true);
                if ($client_id) {
                    $client = get_post($client_id);
                    if ($client) {
                        echo '<a href="' . esc_url(get_edit_post_link($client_id) ?? '') . '">';
                        echo esc_html($client->post_title);
                        echo '</a>';
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'invoice_date':
                $date = get_post_meta($post_id, self::META_PREFIX . 'date', true);
                echo esc_html($date ? date_i18n(get_option('date_format'), strtotime($date)) : '—');
                break;

            case 'due_date':
                $date = get_post_meta($post_id, self::META_PREFIX . 'due_date', true);
                if ($date) {
                    $is_overdue = strtotime($date) < current_time('timestamp');
                    $status = get_post_meta($post_id, self::META_PREFIX . 'status', true);
                    $class = ($is_overdue && !in_array($status, ['paid', 'cancelled'], true)) ? 'invoiceforge-overdue' : '';
                    echo '<span class="' . esc_attr($class) . '">';
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($date)));
                    echo '</span>';
                } else {
                    echo '—';
                }
                break;

            case 'total_amount':
                $amount = get_post_meta($post_id, self::META_PREFIX . 'total_amount', true);
                $currency = get_post_meta($post_id, self::META_PREFIX . 'currency', true) ?: 'USD';
                if ($amount !== '') {
                    echo esc_html($this->formatCurrency((float) $amount, $currency));
                } else {
                    echo '—';
                }
                break;

            case 'invoice_status':
                $status = get_post_meta($post_id, self::META_PREFIX . 'status', true) ?: 'draft';
                $statuses = $this->getStatuses();
                $label = $statuses[$status] ?? $status;
                echo '<span class="invoiceforge-status invoiceforge-status-' . esc_attr($status) . '">';
                echo esc_html($label);
                echo '</span>';
                break;
        }
    }

    /**
     * Add sortable columns.
     *
     * @since 1.0.0
     *
     * @param array<string, string> $columns The sortable columns.
     * @return array<string, string> The modified columns.
     */
    public function addSortableColumns(array $columns): array
    {
        $columns['invoice_number'] = 'invoice_number';
        $columns['invoice_date'] = 'invoice_date';
        $columns['due_date'] = 'due_date';
        $columns['total_amount'] = 'total_amount';
        $columns['invoice_status'] = 'invoice_status';
        return $columns;
    }

    /**
     * Get invoice statuses.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Array of status key => label.
     */
    public function getStatuses(): array
    {
        $statuses = [
            'draft'     => __('Draft', 'invoiceforge'),
            'sent'      => __('Sent', 'invoiceforge'),
            'paid'      => __('Paid', 'invoiceforge'),
            'overdue'   => __('Overdue', 'invoiceforge'),
            'cancelled' => __('Cancelled', 'invoiceforge'),
        ];

        /**
         * Filter available invoice statuses.
         *
         * @since 1.0.0
         *
         * @param array<string, string> $statuses Array of status key => label.
         */
        return apply_filters('invoiceforge_invoice_statuses', $statuses);
    }

    /**
     * Format a currency amount.
     *
     * @since 1.0.0
     *
     * @param float  $amount   The amount to format.
     * @param string $currency The currency code.
     * @return string The formatted amount.
     */
    public function formatCurrency(float $amount, string $currency): string
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
     * Get the meta prefix.
     *
     * @since 1.0.0
     *
     * @return string The meta prefix.
     */
    public function getMetaPrefix(): string
    {
        return self::META_PREFIX;
    }
}
