<?php
/**
 * Client Editor Template
 * Modern custom editor for clients with AJAX saving
 * Supports individuals (person name primary, company optional)
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/** @var array|null $client */
/** @var array $countries */
/** @var array|null $stats */

$is_new = empty($client);
$page_title = $is_new ? __('New Client', 'invoiceforge') : __('Edit Client', 'invoiceforge');

// Set defaults for new client
if ($is_new) {
    $client = [
        'id'         => 0,
        'first_name' => '',
        'last_name'  => '',
        'company'    => '',
        'email'      => '',
        'phone'      => '',
        'address'    => '',
        'city'       => '',
        'state'      => '',
        'zip'        => '',
        'country'    => '',
        'tax_id'     => '',
        'id_no'      => '',
        'office'     => '',
        'att_to'     => '',
    ];
}

$clientsPage = new \InvoiceForge\Admin\Pages\ClientsPage();
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php echo esc_html($page_title); ?>
            <?php if (!$is_new) : ?>
                <span class="invoiceforge-client-name-preview">
                    <?php echo esc_html(trim($client['first_name'] . ' ' . $client['last_name']) ?: $client['title'] ?? ''); ?>
                </span>
            <?php endif; ?>
        </h1>
        <div class="invoiceforge-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients')); ?>" class="invoiceforge-btn invoiceforge-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Back to List', 'invoiceforge'); ?>
            </a>
        </div>
    </div>

    <!-- Client Form -->
    <form id="invoiceforge-client-form" class="invoiceforge-form">
        <input type="hidden" name="client_id" value="<?php echo esc_attr($client['id']); ?>">

        <div class="invoiceforge-editor-layout">
            <!-- Main Content -->
            <div class="invoiceforge-editor-main">
                <!-- Personal Information -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Personal Information', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-grid">
                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="client_first_name">
                                    <?php esc_html_e('First Name', 'invoiceforge'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="client_first_name" name="first_name" class="invoiceforge-form-input" required
                                       value="<?php echo esc_attr($client['first_name']); ?>"
                                       placeholder="<?php esc_attr_e('John', 'invoiceforge'); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="client_last_name">
                                    <?php esc_html_e('Last Name', 'invoiceforge'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="client_last_name" name="last_name" class="invoiceforge-form-input" required
                                       value="<?php echo esc_attr($client['last_name']); ?>"
                                       placeholder="<?php esc_attr_e('Doe', 'invoiceforge'); ?>">
                            </div>

                            <div class="invoiceforge-form-group full-width">
                                <label class="invoiceforge-form-label" for="company">
                                    <?php esc_html_e('Company', 'invoiceforge'); ?>
                                </label>
                                <input type="text" id="company" name="company" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($client['company']); ?>"
                                       placeholder="<?php esc_attr_e('Company or Organization (Optional)', 'invoiceforge'); ?>">
                                <p class="invoiceforge-form-help">
                                    <?php esc_html_e('Optional. Leave blank for individual clients.', 'invoiceforge'); ?>
                                </p>
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="email">
                                    <?php esc_html_e('Email', 'invoiceforge'); ?> <span class="required">*</span>
                                </label>
                                <input type="email" id="email" name="email" class="invoiceforge-form-input" required
                                       value="<?php echo esc_attr($client['email']); ?>"
                                       placeholder="<?php esc_attr_e('email@example.com', 'invoiceforge'); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="phone">
                                    <?php esc_html_e('Phone', 'invoiceforge'); ?>
                                </label>
                                <input type="tel" id="phone" name="phone" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($client['phone']); ?>"
                                       placeholder="<?php esc_attr_e('+1 (555) 123-4567', 'invoiceforge'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Address', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="address">
                                <?php esc_html_e('Street Address', 'invoiceforge'); ?>
                            </label>
                            <textarea id="address" name="address" class="invoiceforge-form-textarea" rows="2"
                                      placeholder="<?php esc_attr_e('123 Main Street, Suite 100', 'invoiceforge'); ?>"><?php echo esc_textarea($client['address']); ?></textarea>
                        </div>

                        <div class="invoiceforge-form-grid">
                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="city">
                                    <?php esc_html_e('City', 'invoiceforge'); ?>
                                </label>
                                <input type="text" id="city" name="city" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($client['city']); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="state">
                                    <?php esc_html_e('State / Province', 'invoiceforge'); ?>
                                </label>
                                <input type="text" id="state" name="state" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($client['state']); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="zip">
                                    <?php esc_html_e('ZIP / Postal Code', 'invoiceforge'); ?>
                                </label>
                                <input type="text" id="zip" name="zip" class="invoiceforge-form-input"
                                       value="<?php echo esc_attr($client['zip']); ?>">
                            </div>

                            <div class="invoiceforge-form-group">
                                <label class="invoiceforge-form-label" for="country">
                                    <?php esc_html_e('Country', 'invoiceforge'); ?>
                                </label>
                                <select id="country" name="country" class="invoiceforge-form-select">
                                    <option value=""><?php esc_html_e('Select a country...', 'invoiceforge'); ?></option>
                                    <?php foreach ($countries as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($client['country'], $code); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="invoiceforge-editor-sidebar">
                <!-- Actions Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-body">
                        <button type="submit" class="invoiceforge-btn invoiceforge-btn-primary invoiceforge-btn-lg" style="width: 100%; margin-bottom: var(--if-space-3);">
                            <span class="dashicons dashicons-saved"></span>
                            <?php $is_new ? esc_html_e('Create Client', 'invoiceforge') : esc_html_e('Save Changes', 'invoiceforge'); ?>
                        </button>

                        <?php if (!$is_new) : ?>
                            <button type="button" class="invoiceforge-btn invoiceforge-btn-secondary invoiceforge-btn-lg invoiceforge-delete-client" data-id="<?php echo esc_attr($client['id']); ?>" style="width: 100%;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Delete Client', 'invoiceforge'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Billing Info Card -->
                <div class="invoiceforge-card">
                    <div class="invoiceforge-card-header">
                        <h3 class="invoiceforge-card-title"><?php esc_html_e('Billing Information', 'invoiceforge'); ?></h3>
                    </div>
                    <div class="invoiceforge-card-body">
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="tax_id">
                                <?php esc_html_e('Tax ID / VAT Number', 'invoiceforge'); ?>
                            </label>
                            <input type="text" id="tax_id" name="tax_id" class="invoiceforge-form-input"
                                   value="<?php echo esc_attr($client['tax_id']); ?>"
                                   placeholder="<?php esc_attr_e('e.g., GB123456789', 'invoiceforge'); ?>">
                            <p class="invoiceforge-form-help">
                                <?php esc_html_e('For EU VAT reverse charge and tax compliance.', 'invoiceforge'); ?>
                            </p>
                        </div>
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="id_no">
                                <?php esc_html_e('ID No (EIK/BULSTAT/Reg No)', 'invoiceforge'); ?>
                            </label>
                            <input type="text" id="id_no" name="id_no" class="invoiceforge-form-input"
                                   value="<?php echo esc_attr($client['id_no'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('e.g., 123456789', 'invoiceforge'); ?>">
                        </div>
                        <div class="invoiceforge-form-group">
                            <label class="invoiceforge-form-label" for="office">
                                <?php esc_html_e('Office / Branch', 'invoiceforge'); ?>
                            </label>
                            <input type="text" id="office" name="office" class="invoiceforge-form-input"
                                   value="<?php echo esc_attr($client['office'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('e.g., HQ, Branch 1', 'invoiceforge'); ?>">
                        </div>
                        <div class="invoiceforge-form-group" style="margin-bottom: 0;">
                            <label class="invoiceforge-form-label" for="att_to">
                                <?php esc_html_e('Attention To', 'invoiceforge'); ?>
                            </label>
                            <input type="text" id="att_to" name="att_to" class="invoiceforge-form-input"
                                   value="<?php echo esc_attr($client['att_to'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('Contact person name', 'invoiceforge'); ?>">
                        </div>
                    </div>
                </div>

                <?php if (!$is_new && $stats) : ?>
                    <!-- Client Stats Card -->
                    <div class="invoiceforge-card">
                        <div class="invoiceforge-card-header">
                            <h3 class="invoiceforge-card-title"><?php esc_html_e('Statistics', 'invoiceforge'); ?></h3>
                        </div>
                        <div class="invoiceforge-card-body">
                            <div style="display: grid; gap: var(--if-space-4);">
                                <div>
                                    <div style="font-size: var(--if-font-size-xs); color: var(--if-gray-500); margin-bottom: var(--if-space-1);">
                                        <?php esc_html_e('Total Invoices', 'invoiceforge'); ?>
                                    </div>
                                    <div style="font-size: var(--if-font-size-lg); font-weight: 600;">
                                        <?php echo esc_html($stats['total_invoices']); ?>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size: var(--if-font-size-xs); color: var(--if-gray-500); margin-bottom: var(--if-space-1);">
                                        <?php esc_html_e('Total Revenue', 'invoiceforge'); ?>
                                    </div>
                                    <div style="font-size: var(--if-font-size-lg); font-weight: 600; color: var(--if-success);">
                                        <?php echo esc_html('$' . number_format($stats['total_revenue'], 2)); ?>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size: var(--if-font-size-xs); color: var(--if-gray-500); margin-bottom: var(--if-space-1);">
                                        <?php esc_html_e('Outstanding', 'invoiceforge'); ?>
                                    </div>
                                    <div style="font-size: var(--if-font-size-lg); font-weight: 600; color: <?php echo $stats['outstanding'] > 0 ? 'var(--if-warning)' : 'var(--if-gray-600)'; ?>;">
                                        <?php echo esc_html('$' . number_format($stats['outstanding'], 2)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
