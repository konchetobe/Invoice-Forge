<?php
/**
 * Invoice Editor Template
 * Modern custom editor for invoices with AJAX saving
 * Supports both existing client selection and inline new client creation
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

$is_new = empty($invoice);
$page_title = $is_new ? __('New Invoice', 'invoiceforge') : __('Edit Invoice', 'invoiceforge');

// Set defaults for new invoice
if ($is_new) {
    $invoice = [
        'id' => 0,
        'title' => '',
        'number' => '',
        'client_id' => 0,
        'date' => current_time('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 'draft',
        'total_amount' => '',
        'currency' => 'USD',
        'notes' => '',
    ];
}

// Get countries if not passed
if (!isset($countries) || empty($countries)) {
    $clientPostType = new \InvoiceForge\PostTypes\ClientPostType(
        new \InvoiceForge\Security\Nonce(),
        new \InvoiceForge\Security\Sanitizer()
    );
    $countries = $clientPostType->getCountries();
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
        </div>
    </div>

    <!-- Invoice Form -->
    <form id="invoiceforge-invoice-form" class="invoiceforge-form">
        <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice['id']); ?>">
        <input type="hidden" name="invoice_number" value="<?php echo esc_attr($invoice['number']); ?>">
        <input type="hidden" name="client_mode" id="client_mode" value="existing">

        <div class="invoiceforge-editor-layout">
            <!-- Main Content -->
            <div class="invoiceforge-editor-main">
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
                                <label class="invoiceforge-form-label" for="total_amount">
                                    <?php esc_html_e('Total Amount', 'invoiceforge'); ?>
                                </label>
                                <input type="number" id="total_amount" name="total_amount" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($invoice['total_amount']); ?>"
                                       step="0.01" min="0" placeholder="0.00">
                                <p class="invoiceforge-form-help">
                                    <?php esc_html_e('Line items will be available in a future update.', 'invoiceforge'); ?>
                                </p>
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
                        </div>

                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="notes">
                                <?php esc_html_e('Notes', 'invoiceforge'); ?>
                            </label>
                            <textarea id="notes" name="notes" class="invoiceforge-form-textarea" rows="4"
                                      placeholder="<?php esc_attr_e('Add any notes or terms for this invoice...', 'invoiceforge'); ?>"><?php echo esc_textarea($invoice['notes']); ?></textarea>
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
        </div>
    </form>
</div>

<script>
function toggleClientMode(mode) {
    document.getElementById('client_mode').value = mode;
    
    const existingSection = document.getElementById('existing-client-section');
    const newSection = document.getElementById('new-client-section');
    const clientSelect = document.getElementById('client_id');
    
    if (mode === 'new') {
        existingSection.style.display = 'none';
        newSection.style.display = 'block';
        // Clear existing client selection
        if (clientSelect) clientSelect.value = '';
        // Set required on new client fields
        document.getElementById('new_client_first_name').setAttribute('required', '');
        document.getElementById('new_client_last_name').setAttribute('required', '');
        document.getElementById('new_client_email').setAttribute('required', '');
    } else {
        existingSection.style.display = 'block';
        newSection.style.display = 'none';
        // Clear new client fields
        document.querySelectorAll('#new-client-section input, #new-client-section select').forEach(function(el) {
            el.value = '';
        });
        // Remove required from new client fields
        document.getElementById('new_client_first_name').removeAttribute('required');
        document.getElementById('new_client_last_name').removeAttribute('required');
        document.getElementById('new_client_email').removeAttribute('required');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('input[name="client_mode_radio"]:checked');
    if (checkedRadio) {
        toggleClientMode(checkedRadio.value);
    }
});
</script>
