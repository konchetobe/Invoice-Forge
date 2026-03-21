<?php
/**
 * Invoice Editor Template
 * Modern custom editor for invoices with AJAX saving.
 * Supports line items, tax calculations, and inline client creation.
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/** @var array|null $invoice */
/** @var array $clients */
/** @var array $statuses */
/** @var array $currencies */
/** @var array $countries */
/** @var array $tax_rates */
/** @var array $line_items */
/** @var array $payment_methods */

$is_new = empty($invoice);
$page_title = $is_new ? __('New Invoice', 'invoiceforge') : __('Edit Invoice', 'invoiceforge');

// Set defaults for new invoice
if ($is_new) {
    $invoice = [
        'id'             => 0,
        'title'          => '',
        'number'         => '',
        'client_id'      => 0,
        'date'           => current_time('Y-m-d'),
        'due_date'       => date('Y-m-d', strtotime('+30 days')),
        'status'         => 'draft',
        'total_amount'   => '',
        'currency'       => 'USD',
        'notes'          => '',
        'terms'          => '',
        'internal_notes' => '',
        'discount_type'  => '',
        'discount_value' => 0,
        'payment_method' => '',
    ];
}

// Load payment methods from settings (fallback to defaults)
if (!isset($payment_methods) || empty($payment_methods)) {
    $if_settings = get_option('invoiceforge_settings', []);
    $payment_methods = $if_settings['template']['payment_methods'] ?? ['Bank transfer', 'Cash'];
    if (!is_array($payment_methods) || empty($payment_methods)) {
        $payment_methods = ['Bank transfer', 'Cash'];
    }
}

// Get countries if not passed
if (!isset($countries) || empty($countries)) {
    $clientPostType = new \InvoiceForge\PostTypes\ClientPostType(
        new \InvoiceForge\Security\Nonce(),
        new \InvoiceForge\Security\Sanitizer()
    );
    $countries = $clientPostType->getCountries();
}

if (!isset($tax_rates)) {
    $tax_rates = [];
}
if (!isset($line_items)) {
    $line_items = [];
}
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php echo esc_html($page_title); ?>
            <?php if (!$is_new && !empty($invoice['number'])) : ?>
                <span><?php echo esc_html($invoice['number']); ?></span>
            <?php endif; ?>
        </h1>
        <div class="invoiceforge-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices')); ?>" class="invoiceforge-btn invoiceforge-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Back to List', 'invoiceforge'); ?>
            </a>
            <?php if (!$is_new) : ?>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=invoiceforge_download_pdf&invoice_id=' . $invoice['id'] . '&nonce=' . wp_create_nonce('invoiceforge_ajax'))); ?>" class="invoiceforge-btn invoiceforge-btn-secondary" target="_blank">
                    <span class="dashicons dashicons-pdf"></span>
                    <?php esc_html_e('Download PDF', 'invoiceforge'); ?>
                </a>
                <button type="button" class="invoiceforge-btn invoiceforge-btn-primary invoiceforge-send-email" data-id="<?php echo esc_attr($invoice['id']); ?>" title="<?php esc_attr_e('Send to Client', 'invoiceforge'); ?>">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('Send Email', 'invoiceforge'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Form -->
    <form id="invoiceforge-invoice-form" class="invoiceforge-form">
        <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice['id']); ?>">
        <input type="hidden" name="invoice_number" value="<?php echo esc_attr($invoice['number']); ?>">
        <input type="hidden" name="client_mode" id="client_mode" value="existing">

        <div class="<?php echo $is_new ? 'invoiceforge-editor-layout' : 'invoiceforge-editor-with-preview'; ?>">
            <!-- Main Content -->
            <div class="invoiceforge-editor-main">
                <!-- Invoice Details Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Invoice Details', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="title">
                                <?php esc_html_e('Invoice Title', 'invoiceforge'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" id="title" name="title" class="invoiceforge-form-input" required
                                   value="<?php echo esc_attr($invoice['title']); ?>"
                                   placeholder="<?php esc_attr_e('e.g., Website Development - March 2025', 'invoiceforge'); ?>">
                        </div>

                        <!-- Client Selection Section -->
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label">
                                <?php esc_html_e('Client', 'invoiceforge'); ?> <span class="required">*</span>
                            </label>
                            
                            <!-- Client Mode Toggle -->
                            <div class="invoiceforge-client-toggle" style="margin-bottom: var(--if-space-3);">
                                <label class="invoiceforge-radio-label" style="display: inline-flex; align-items: center; gap: 0.5rem; margin-right: 1.5rem; cursor: pointer;">
                                    <input type="radio" name="client_mode_radio" value="existing" <?php checked(!$is_new || !empty($clients)); ?> onchange="toggleClientMode('existing')">
                                    <span><?php esc_html_e('Select Existing Client', 'invoiceforge'); ?></span>
                                </label>
                                <label class="invoiceforge-radio-label" style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="client_mode_radio" value="new" <?php checked($is_new && empty($clients)); ?> onchange="toggleClientMode('new')">
                                    <span><?php esc_html_e('Add New Client', 'invoiceforge'); ?></span>
                                </label>
                            </div>

                            <!-- Existing Client Dropdown -->
                            <div id="existing-client-section" <?php echo ($is_new && empty($clients)) ? 'style="display:none;"' : ''; ?>>
                                <select id="client_id" name="client_id" class="invoiceforge-form-select">
                                    <option value=""><?php esc_html_e('Select a client...', 'invoiceforge'); ?></option>
                                    <?php foreach ($clients as $client_id => $client_name) : ?>
                                        <option value="<?php echo esc_attr($client_id); ?>" <?php selected($invoice['client_id'], $client_id); ?>>
                                            <?php echo esc_html($client_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($clients)) : ?>
                                    <p class="invoiceforge-form-help">
                                        <?php esc_html_e('No clients found. Switch to "Add New Client" to create one inline.', 'invoiceforge'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- New Client Inline Form -->
                            <div id="new-client-section" class="invoiceforge-inline-client" <?php echo !($is_new && empty($clients)) ? 'style="display:none;"' : ''; ?>>
                                <div class="invoiceforge-card" style="background: var(--if-gray-50); margin-top: var(--if-space-2);">
                                    <div class="invoiceforge-card-header" style="padding: var(--if-space-3);">
                                        <h4 class="invoiceforge-card-title" style="margin: 0; font-size: var(--if-font-size-sm);">
                                            <?php esc_html_e('New Client Details', 'invoiceforge'); ?>
                                        </h4>
                                    </div>
                                    <div class="invoiceforge-card-body" style="padding: var(--if-space-3);">
                                        <div class="invoiceforge-form-grid">
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_first_name">
                                                    <?php esc_html_e('First Name', 'invoiceforge'); ?> <span class="required">*</span>
                                                </label>
                                                <input type="text" id="new_client_first_name" name="new_client_first_name" 
                                                       class="invoiceforge-form-input" placeholder="<?php esc_attr_e('John', 'invoiceforge'); ?>">
                                            </div>
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_last_name">
                                                    <?php esc_html_e('Last Name', 'invoiceforge'); ?> <span class="required">*</span>
                                                </label>
                                                <input type="text" id="new_client_last_name" name="new_client_last_name" 
                                                       class="invoiceforge-form-input" placeholder="<?php esc_attr_e('Doe', 'invoiceforge'); ?>">
                                            </div>
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_email">
                                                    <?php esc_html_e('Email', 'invoiceforge'); ?> <span class="required">*</span>
                                                </label>
                                                <input type="email" id="new_client_email" name="new_client_email" 
                                                       class="invoiceforge-form-input" placeholder="<?php esc_attr_e('email@example.com', 'invoiceforge'); ?>">
                                            </div>
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_company">
                                                    <?php esc_html_e('Company', 'invoiceforge'); ?>
                                                </label>
                                                <input type="text" id="new_client_company" name="new_client_company" 
                                                       class="invoiceforge-form-input" placeholder="<?php esc_attr_e('Company (Optional)', 'invoiceforge'); ?>">
                                            </div>
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_phone">
                                                    <?php esc_html_e('Phone', 'invoiceforge'); ?>
                                                </label>
                                                <input type="tel" id="new_client_phone" name="new_client_phone" 
                                                       class="invoiceforge-form-input" placeholder="<?php esc_attr_e('+1 (555) 123-4567', 'invoiceforge'); ?>">
                                            </div>
                                            <div class="invoiceforge-form-group">
                                                <label class="invoiceforge-form-label" for="new_client_country">
                                                    <?php esc_html_e('Country', 'invoiceforge'); ?>
                                                </label>
                                                <select id="new_client_country" name="new_client_country" class="invoiceforge-form-select">
                                                    <option value=""><?php esc_html_e('Select...', 'invoiceforge'); ?></option>
                                                    <?php foreach ($countries as $code => $name) : ?>
                                                        <option value="<?php echo esc_attr($code); ?>">
                                                            <?php echo esc_html($name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Collapsible Address Fields -->
                                        <details style="margin-top: var(--if-space-3);">
                                            <summary style="cursor: pointer; color: var(--if-primary); font-size: var(--if-font-size-sm);">
                                                <?php esc_html_e('+ Add Address (Optional)', 'invoiceforge'); ?>
                                            </summary>
                                            <div class="invoiceforge-form-grid" style="margin-top: var(--if-space-3);">
                                                <div class="invoiceforge-form-group full-width">
                                                    <label class="invoiceforge-form-label" for="new_client_address">
                                                        <?php esc_html_e('Street Address', 'invoiceforge'); ?>
                                                    </label>
                                                    <input type="text" id="new_client_address" name="new_client_address" 
                                                           class="invoiceforge-form-input" placeholder="<?php esc_attr_e('123 Main Street', 'invoiceforge'); ?>">
                                                </div>
                                                <div class="invoiceforge-form-group">
                                                    <label class="invoiceforge-form-label" for="new_client_city">
                                                        <?php esc_html_e('City', 'invoiceforge'); ?>
                                                    </label>
                                                    <input type="text" id="new_client_city" name="new_client_city" class="invoiceforge-form-input">
                                                </div>
                                                <div class="invoiceforge-form-group">
                                                    <label class="invoiceforge-form-label" for="new_client_state">
                                                        <?php esc_html_e('State / Province', 'invoiceforge'); ?>
                                                    </label>
                                                    <input type="text" id="new_client_state" name="new_client_state" class="invoiceforge-form-input">
                                                </div>
                                                <div class="invoiceforge-form-group">
                                                    <label class="invoiceforge-form-label" for="new_client_zip">
                                                        <?php esc_html_e('ZIP / Postal Code', 'invoiceforge'); ?>
                                                    </label>
                                                    <input type="text" id="new_client_zip" name="new_client_zip" class="invoiceforge-form-input">
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="invoiceforge-form-grid">
                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="invoice_date">
                                    <?php esc_html_e('Invoice Date', 'invoiceforge'); ?>
                                </label>
                                <input type="date" id="invoice_date" name="invoice_date" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($invoice['date']); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="due_date">
                                    <?php esc_html_e('Due Date', 'invoiceforge'); ?>
                                </label>
                                <input type="date" id="due_date" name="due_date" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($invoice['due_date']); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="currency">
                                    <?php esc_html_e('Currency', 'invoiceforge'); ?>
                                </label>
                                <select id="currency" name="currency" class="invoiceforge-form-select">
                                    <?php foreach ($currencies as $code => $label) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($invoice['currency'], $code); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="payment_method">
                                    <?php esc_html_e('Payment Method', 'invoiceforge'); ?>
                                </label>
                                <select id="payment_method" name="payment_method" class="invoiceforge-form-select">
                                    <option value=""><?php esc_html_e('Select...', 'invoiceforge'); ?></option>
                                    <?php foreach ($payment_methods as $method) : ?>
                                        <option value="<?php echo esc_attr($method); ?>" <?php selected($invoice['payment_method'] ?? '', $method); ?>>
                                            <?php echo esc_html($method); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header" style="display: flex; align-items: center; justify-content: space-between;">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Line Items', 'invoiceforge'); ?></h3>
                        <button type="button" id="invoiceforge-add-item" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-primary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add Item', 'invoiceforge'); ?>
                        </button>
                    </div>
                    <div class="invoiceforge-card-body" style="padding: 0;">
                        <table class="invoiceforge-line-items-table" id="invoiceforge-line-items">
                            <thead>
                                <tr>
                                    <th class="col-description"><?php esc_html_e('Description', 'invoiceforge'); ?></th>
                                    <th class="col-qty"><?php esc_html_e('Qty', 'invoiceforge'); ?></th>
                                    <th class="col-price"><?php esc_html_e('Unit Price', 'invoiceforge'); ?></th>
                                    <th class="col-tax"><?php esc_html_e('Tax', 'invoiceforge'); ?></th>
                                    <th class="col-total"><?php esc_html_e('Total', 'invoiceforge'); ?></th>
                                    <th class="col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="invoiceforge-items-body">
                                <!-- Rows injected by JS -->
                            </tbody>
                        </table>
                        <div id="invoiceforge-no-items" class="invoiceforge-empty-items" style="display:none;">
                            <p><?php esc_html_e('No line items yet. Click "Add Item" to get started.', 'invoiceforge'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Totals & Discount Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Totals', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-totals-grid">
                            <div class="invoiceforge-totals-discount">
                                <div class="invoiceforge-form-grid" style="grid-template-columns: 1fr 1fr; max-width: 400px;">
                                    <div class="invoiceforge-form-group">
                                        <label class="invoiceforge-form-label" for="discount_type">
                                            <?php esc_html_e('Discount', 'invoiceforge'); ?>
                                        </label>
                                        <select id="discount_type" name="discount_type" class="invoiceforge-form-select" onchange="recalculateTotals()">
                                            <option value="" <?php selected($invoice['discount_type'] ?? '', ''); ?>><?php esc_html_e('No Discount', 'invoiceforge'); ?></option>
                                            <option value="percentage" <?php selected($invoice['discount_type'] ?? '', 'percentage'); ?>><?php esc_html_e('Percentage (%)', 'invoiceforge'); ?></option>
                                            <option value="fixed" <?php selected($invoice['discount_type'] ?? '', 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'invoiceforge'); ?></option>
                                        </select>
                                    </div>
                                    <div class="invoiceforge-form-group">
                                        <label class="invoiceforge-form-label" for="discount_value">
                                            <?php esc_html_e('Value', 'invoiceforge'); ?>
                                        </label>
                                        <input type="number" id="discount_value" name="discount_value" class="invoiceforge-form-input"
                                               value="<?php echo esc_attr($invoice['discount_value'] ?? 0); ?>"
                                               step="0.01" min="0" placeholder="0.00" oninput="recalculateTotals()">
                                    </div>
                                </div>
                            </div>
                            <div class="invoiceforge-totals-summary">
                                <table class="invoiceforge-summary-table">
                                    <tr>
                                        <td><?php esc_html_e('Subtotal', 'invoiceforge'); ?></td>
                                        <td id="invoiceforge-subtotal" class="invoiceforge-amount">0.00</td>
                                    </tr>
                                    <tr>
                                        <td><?php esc_html_e('Tax', 'invoiceforge'); ?></td>
                                        <td id="invoiceforge-tax" class="invoiceforge-amount">0.00</td>
                                    </tr>
                                    <tr id="invoiceforge-discount-row" style="display:none;">
                                        <td><?php esc_html_e('Discount', 'invoiceforge'); ?></td>
                                        <td id="invoiceforge-discount" class="invoiceforge-amount invoiceforge-discount-amount">-0.00</td>
                                    </tr>
                                    <tr class="invoiceforge-total-row">
                                        <td><strong><?php esc_html_e('Total', 'invoiceforge'); ?></strong></td>
                                        <td id="invoiceforge-total" class="invoiceforge-amount"><strong>0.00</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes & Terms Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Notes & Terms', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-grid">
                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="notes">
                                    <?php esc_html_e('Customer Notes', 'invoiceforge'); ?>
                                </label>
                                <textarea id="notes" name="notes" class="invoiceforge-form-textarea" rows="3"
                                          placeholder="<?php esc_attr_e('Notes visible to the customer on the invoice...', 'invoiceforge'); ?>"><?php echo esc_textarea($invoice['notes'] ?? ''); ?></textarea>
                            </div>
                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="terms">
                                    <?php esc_html_e('Terms & Conditions', 'invoiceforge'); ?>
                                </label>
                                <textarea id="terms" name="terms" class="invoiceforge-form-textarea" rows="3"
                                          placeholder="<?php esc_attr_e('Payment terms, late fees, etc.', 'invoiceforge'); ?>"><?php echo esc_textarea($invoice['terms'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="invoiceforge-form-group" style="margin-top: var(--if-space-3);">
                            <label class="invoiceforge-form-label" for="internal_notes">
                                <?php esc_html_e('Internal Notes', 'invoiceforge'); ?>
                                <span style="font-weight: normal; color: var(--if-gray-400); font-size: var(--if-font-size-xs);"><?php esc_html_e('(not visible on invoice)', 'invoiceforge'); ?></span>
                            </label>
                            <textarea id="internal_notes" name="internal_notes" class="invoiceforge-form-textarea" rows="2"
                                      placeholder="<?php esc_attr_e('Private notes for your reference...', 'invoiceforge'); ?>"><?php echo esc_textarea($invoice['internal_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="invoiceforge-editor-sidebar">
                <!-- Status Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Status', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-group" style="margin-bottom: 0;">
                            <select id="status" name="status" class="invoiceforge-form-select">
                                <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($invoice['status'], $status_key); ?>>
                                        <?php echo esc_html($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-body">
                        <button type="submit" class="invoiceforge-btn invoiceforge-btn-primary invoiceforge-btn-lg" style="width: 100%; margin-bottom: var(--if-space-3);">
                            <span class="dashicons dashicons-saved"></span>
                            <?php $is_new ? esc_html_e('Create Invoice', 'invoiceforge') : esc_html_e('Save Changes', 'invoiceforge'); ?>
                        </button>

                        <?php if (!$is_new) : ?>
                            <button type="button" class="invoiceforge-btn invoiceforge-btn-secondary invoiceforge-btn-lg invoiceforge-delete-invoice" data-id="<?php echo esc_attr($invoice['id']); ?>" style="width: 100%;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Delete Invoice', 'invoiceforge'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_new && !empty($invoice['number'])) : ?>
                    <!-- Invoice Info Card -->
                    <div class="invoiceforge-card">
                        <div class="invoiceforge-card-header">
                            <h3 class="invoiceforge-card-title"><?php esc_html_e('Invoice Info', 'invoiceforge'); ?></h3>
                        </div>
                        <div class="invoiceforge-card-body">
                            <p style="margin: 0 0 var(--if-space-2);">
                                <strong><?php esc_html_e('Invoice #:', 'invoiceforge'); ?></strong><br>
                                <?php echo esc_html($invoice['number']); ?>
                            </p>
                            <p style="margin: 0; font-size: var(--if-font-size-xs); color: var(--if-gray-500);">
                                <?php esc_html_e('Created:', 'invoiceforge'); ?>
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($invoice['created_at']))); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_new) : ?>
            <!-- Invoice Preview Panel -->
            <div class="invoiceforge-preview-panel">
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Preview', 'invoiceforge'); ?></h3>
                        <span id="invoiceforge-preview-status" class="invoiceforge-preview-status"></span>
                    </div>
                    <div class="invoiceforge-card-body invoiceforge-preview-body" style="padding:0;">
                        <div id="invoiceforge-preview-frame" class="invoiceforge-preview-frame">
                            <p class="invoiceforge-preview-loading"><?php esc_html_e('Loading preview...', 'invoiceforge'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </form>
</div>

<script>
/**
 * InvoiceForge Line Items Manager
 * Handles dynamic line item rows, tax calculations, and totals.
 */
(function() {
    'use strict';

    // Tax rates loaded from PHP
    var taxRates = <?php echo wp_json_encode($tax_rates); ?>;
    var existingItems = <?php echo wp_json_encode($line_items); ?>;
    var itemIndex = 0;

    /**
     * Build tax rate <select> options HTML.
     */
    function buildTaxOptions(selectedId) {
        var html = '<option value=""><?php echo esc_js(__('No Tax', 'invoiceforge')); ?></option>';
        for (var i = 0; i < taxRates.length; i++) {
            var r = taxRates[i];
            var sel = (selectedId && parseInt(selectedId) === parseInt(r.id)) ? ' selected' : '';
            html += '<option value="' + r.id + '"' + sel + '>' + r.name + ' (' + parseFloat(r.rate).toFixed(2) + '%)</option>';
        }
        return html;
    }

    /**
     * Add a line item row to the table.
     */
    window.addLineItemRow = function(data) {
        data = data || {};
        var tbody = document.getElementById('invoiceforge-items-body');
        var idx = itemIndex++;

        var tr = document.createElement('tr');
        tr.className = 'invoiceforge-line-item-row';
        tr.dataset.index = idx;

        tr.innerHTML = '' +
            '<td class="col-description">' +
                '<input type="text" name="line_items[' + idx + '][description]" class="invoiceforge-form-input" ' +
                'value="' + (data.description || '').replace(/"/g, '&quot;') + '" ' +
                'placeholder="<?php echo esc_js(__('Item description', 'invoiceforge')); ?>">' +
            '</td>' +
            '<td class="col-qty">' +
                '<input type="number" name="line_items[' + idx + '][quantity]" class="invoiceforge-form-input invoiceforge-item-qty" ' +
                'value="' + (data.quantity || 1) + '" step="0.01" min="0.01" oninput="recalculateTotals()">' +
            '</td>' +
            '<td class="col-price">' +
                '<input type="number" name="line_items[' + idx + '][unit_price]" class="invoiceforge-form-input invoiceforge-item-price" ' +
                'value="' + (data.unit_price || '') + '" step="0.01" min="0" placeholder="0.00" oninput="recalculateTotals()">' +
            '</td>' +
            '<td class="col-tax">' +
                '<select name="line_items[' + idx + '][tax_rate_id]" class="invoiceforge-form-select invoiceforge-item-tax" onchange="recalculateTotals()">' +
                    buildTaxOptions(data.tax_rate_id) +
                '</select>' +
            '</td>' +
            '<td class="col-total">' +
                '<span class="invoiceforge-item-total">0.00</span>' +
            '</td>' +
            '<td class="col-actions">' +
                '<button type="button" class="invoiceforge-btn-icon invoiceforge-remove-item" onclick="removeLineItem(this)" title="<?php echo esc_js(__('Remove', 'invoiceforge')); ?>">' +
                    '<span class="dashicons dashicons-no-alt"></span>' +
                '</button>' +
            '</td>';

        tbody.appendChild(tr);
        toggleEmptyState();
        recalculateTotals();
    };

    /**
     * Remove a line item row.
     */
    window.removeLineItem = function(btn) {
        var row = btn.closest('tr');
        if (row) {
            row.remove();
            toggleEmptyState();
            recalculateTotals();
        }
    };

    /**
     * Toggle empty state message.
     */
    function toggleEmptyState() {
        var tbody = document.getElementById('invoiceforge-items-body');
        var emptyMsg = document.getElementById('invoiceforge-no-items');
        var table = document.getElementById('invoiceforge-line-items');
        if (tbody.children.length === 0) {
            emptyMsg.style.display = 'block';
            table.style.display = 'none';
        } else {
            emptyMsg.style.display = 'none';
            table.style.display = 'table';
        }
    }

    /**
     * Get a tax rate percentage by ID.
     */
    function getTaxRateById(id) {
        if (!id) return 0;
        for (var i = 0; i < taxRates.length; i++) {
            if (parseInt(taxRates[i].id) === parseInt(id)) {
                return parseFloat(taxRates[i].rate);
            }
        }
        return 0;
    }

    /**
     * Recalculate all line item totals and the invoice summary.
     */
    window.recalculateTotals = function() {
        var rows = document.querySelectorAll('#invoiceforge-items-body .invoiceforge-line-item-row');
        var subtotal = 0;
        var totalTax = 0;

        rows.forEach(function(row) {
            var qty = parseFloat(row.querySelector('.invoiceforge-item-qty').value) || 0;
            var price = parseFloat(row.querySelector('.invoiceforge-item-price').value) || 0;
            var taxSelect = row.querySelector('.invoiceforge-item-tax');
            var taxRateId = taxSelect ? taxSelect.value : '';
            var taxPct = getTaxRateById(taxRateId);

            var lineSubtotal = qty * price;
            var lineTax = lineSubtotal * (taxPct / 100);
            var lineTotal = lineSubtotal + lineTax;

            subtotal += lineSubtotal;
            totalTax += lineTax;

            row.querySelector('.invoiceforge-item-total').textContent = lineTotal.toFixed(2);
        });

        // Discount
        var discountType = document.getElementById('discount_type').value;
        var discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
        var discountAmount = 0;
        var discountRow = document.getElementById('invoiceforge-discount-row');

        if (discountType === 'percentage' && discountValue > 0) {
            discountAmount = subtotal * (discountValue / 100);
            discountRow.style.display = '';
        } else if (discountType === 'fixed' && discountValue > 0) {
            discountAmount = discountValue;
            discountRow.style.display = '';
        } else {
            discountRow.style.display = 'none';
        }

        var total = subtotal + totalTax - discountAmount;
        if (total < 0) total = 0;

        document.getElementById('invoiceforge-subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('invoiceforge-tax').textContent = totalTax.toFixed(2);
        document.getElementById('invoiceforge-discount').textContent = '-' + discountAmount.toFixed(2);
        document.getElementById('invoiceforge-total').innerHTML = '<strong>' + total.toFixed(2) + '</strong>';
    };

    // --- Initialisation ---
    document.addEventListener('DOMContentLoaded', function() {
        // Add item button
        document.getElementById('invoiceforge-add-item').addEventListener('click', function() {
            addLineItemRow();
        });

        // Load existing items
        if (existingItems && existingItems.length > 0) {
            for (var i = 0; i < existingItems.length; i++) {
                addLineItemRow(existingItems[i]);
            }
        } else {
            // Start with one empty row for new invoices
            addLineItemRow();
        }

        toggleEmptyState();
        recalculateTotals();

        // Client mode toggle
        var checkedRadio = document.querySelector('input[name="client_mode_radio"]:checked');
        if (checkedRadio) {
            toggleClientMode(checkedRadio.value);
        }
    });
})();

function toggleClientMode(mode) {
    document.getElementById('client_mode').value = mode;
    
    var existingSection = document.getElementById('existing-client-section');
    var newSection = document.getElementById('new-client-section');
    var clientSelect = document.getElementById('client_id');
    
    if (mode === 'new') {
        existingSection.style.display = 'none';
        newSection.style.display = 'block';
        if (clientSelect) clientSelect.value = '';
        document.getElementById('new_client_first_name').setAttribute('required', '');
        document.getElementById('new_client_last_name').setAttribute('required', '');
        document.getElementById('new_client_email').setAttribute('required', '');
    } else {
        existingSection.style.display = 'block';
        newSection.style.display = 'none';
        document.querySelectorAll('#new-client-section input, #new-client-section select').forEach(function(el) {
            el.value = '';
        });
        document.getElementById('new_client_first_name').removeAttribute('required');
        document.getElementById('new_client_last_name').removeAttribute('required');
        document.getElementById('new_client_email').removeAttribute('required');
    }
}
</script>
