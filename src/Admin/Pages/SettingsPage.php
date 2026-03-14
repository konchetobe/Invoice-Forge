<?php
/**
 * Settings Page
 *
 * Handles the plugin settings page with tabs.
 * FIXED: Settings now properly merge across tabs instead of overwriting.
 *
 * @package    InvoiceForge
 * @subpackage Admin/Pages
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Admin\Pages;

use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Sanitizer;
use InvoiceForge\Security\Validator;
use InvoiceForge\Security\Capabilities;
use InvoiceForge\Security\Encryption;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page handler.
 *
 * @since 1.0.0
 */
class SettingsPage
{
    /**
     * Option name for storing settings.
     *
     * @since 1.0.0
     * @var string
     */
    public const OPTION_NAME = 'invoiceforge_settings';

    /**
     * Settings group name.
     *
     * @since 1.0.0
     * @var string
     */
    public const SETTINGS_GROUP = 'invoiceforge_settings_group';

    /**
     * Tab field definitions.
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    private const TAB_FIELDS = [
        'general' => [
            'company_name',
            'company_email',
            'company_phone',
            'company_address',
            'company_logo',
            'language',
        ],
        'email' => [
            'email_from_name',
            'email_from_address',
            'smtp_enabled',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
        ],
        'advanced' => [
            'default_currency',
            'invoice_prefix',
            'invoice_terms',
            'invoice_notes',
        ],
        'integrations' => [
            'woo_enabled',
            'woo_trigger_statuses',
            'woo_invoice_number_format',
            'woo_invoice_prefix',
            'woo_auto_email',
        ],
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
     * Validator instance.
     *
     * @since 1.0.0
     * @var Validator
     */
    private Validator $validator;

    /**
     * Capabilities handler.
     *
     * @since 1.0.0
     * @var Capabilities
     */
    private Capabilities $capabilities;

    /**
     * Encryption handler.
     *
     * @since 1.0.0
     * @var Encryption
     */
    private Encryption $encryption;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Nonce        $nonce        Nonce handler.
     * @param Sanitizer    $sanitizer    Sanitizer instance.
     * @param Validator    $validator    Validator instance.
     * @param Capabilities $capabilities Capabilities handler.
     * @param Encryption   $encryption   Encryption handler.
     */
    public function __construct(
        Nonce $nonce,
        Sanitizer $sanitizer,
        Validator $validator,
        Capabilities $capabilities,
        Encryption $encryption
    ) {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
        $this->capabilities = $capabilities;
        $this->encryption = $encryption;
    }

    /**
     * Register settings with WordPress.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default'           => $this->getDefaults(),
            ]
        );

        // General Settings Section
        add_settings_section(
            'invoiceforge_general',
            __('Company Information', 'invoiceforge'),
            [$this, 'renderGeneralSection'],
            'invoiceforge-settings-general'
        );

        $this->addGeneralFields();

        // Email Settings Section
        add_settings_section(
            'invoiceforge_email',
            __('Email Settings', 'invoiceforge'),
            [$this, 'renderEmailSection'],
            'invoiceforge-settings-email'
        );

        $this->addEmailFields();

        // Advanced Settings Section
        add_settings_section(
            'invoiceforge_advanced',
            __('Advanced Settings', 'invoiceforge'),
            [$this, 'renderAdvancedSection'],
            'invoiceforge-settings-advanced'
        );

        $this->addAdvancedFields();
    }

    /**
     * Add general settings fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function addGeneralFields(): void
    {
        add_settings_field(
            'company_name',
            __('Company Name', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'company_name',
                'description' => __('Your company or business name.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'company_email',
            __('Company Email', 'invoiceforge'),
            [$this, 'renderEmailField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'company_email',
                'description' => __('Main contact email for your business.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'company_phone',
            __('Company Phone', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'company_phone',
                'description' => __('Contact phone number.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'company_address',
            __('Company Address', 'invoiceforge'),
            [$this, 'renderTextareaField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'company_address',
                'description' => __('Your business address (appears on invoices).', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'company_logo',
            __('Company Logo', 'invoiceforge'),
            [$this, 'renderImageField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'company_logo',
                'description' => __('Logo to display on invoices (recommended: 300x100px).', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'language',
            __('Language', 'invoiceforge'),
            [$this, 'renderLanguageField'],
            'invoiceforge-settings-general',
            'invoiceforge_general',
            [
                'id'          => 'language',
                'description' => __('Select the language for the InvoiceForge admin interface.', 'invoiceforge'),
            ]
        );
    }

    /**
     * Render the language selection field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderLanguageField(array $args): void
    {
        $options = get_option(self::OPTION_NAME, []);
        $value = $options[$args['id']] ?? '';

        $languages = $this->getAvailableLanguages();
        ?>
        <select
            id="<?php echo esc_attr($args['id']); ?>"
            name="<?php echo esc_attr(self::OPTION_NAME . '[' . $args['id'] . ']'); ?>"
            class="regular-text"
        >
            <?php foreach ($languages as $code => $name) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Get available languages.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Language code => name pairs.
     */
    public function getAvailableLanguages(): array
    {
        return [
            ''      => __('Default (Site Language)', 'invoiceforge'),
            'en_US' => __('English (US)', 'invoiceforge'),
            'en_GB' => __('English (UK)', 'invoiceforge'),
            'bg_BG' => __('Bulgarian (Български)', 'invoiceforge'),
            'de_DE' => __('German (Deutsch)', 'invoiceforge'),
            'fr_FR' => __('French (Français)', 'invoiceforge'),
            'es_ES' => __('Spanish (Español)', 'invoiceforge'),
            'it_IT' => __('Italian (Italiano)', 'invoiceforge'),
            'nl_NL' => __('Dutch (Nederlands)', 'invoiceforge'),
            'pl_PL' => __('Polish (Polski)', 'invoiceforge'),
            'pt_PT' => __('Portuguese (Português)', 'invoiceforge'),
            'ro_RO' => __('Romanian (Română)', 'invoiceforge'),
            'ru_RU' => __('Russian (Русский)', 'invoiceforge'),
        ];
    }

    /**
     * Add email settings fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function addEmailFields(): void
    {
        add_settings_field(
            'email_from_name',
            __('From Name', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'email_from_name',
                'description' => __('Name shown as the email sender.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'email_from_address',
            __('From Email', 'invoiceforge'),
            [$this, 'renderEmailField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'email_from_address',
                'description' => __('Email address used as the sender.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'smtp_enabled',
            __('Enable SMTP', 'invoiceforge'),
            [$this, 'renderCheckboxField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_enabled',
                'description' => __('Use custom SMTP server for sending emails.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'smtp_host',
            __('SMTP Host', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_host',
                'description' => __('SMTP server hostname (e.g., smtp.gmail.com).', 'invoiceforge'),
                'class'       => 'smtp-field',
            ]
        );

        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'invoiceforge'),
            [$this, 'renderNumberField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_port',
                'description' => __('SMTP server port (common: 587 for TLS, 465 for SSL).', 'invoiceforge'),
                'class'       => 'smtp-field',
                'min'         => 1,
                'max'         => 65535,
            ]
        );

        add_settings_field(
            'smtp_username',
            __('SMTP Username', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_username',
                'description' => __('Username for SMTP authentication.', 'invoiceforge'),
                'class'       => 'smtp-field',
            ]
        );

        add_settings_field(
            'smtp_password',
            __('SMTP Password', 'invoiceforge'),
            [$this, 'renderPasswordField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_password',
                'description' => __('Password for SMTP authentication (stored encrypted).', 'invoiceforge'),
                'class'       => 'smtp-field',
            ]
        );

        add_settings_field(
            'smtp_encryption',
            __('SMTP Encryption', 'invoiceforge'),
            [$this, 'renderSelectField'],
            'invoiceforge-settings-email',
            'invoiceforge_email',
            [
                'id'          => 'smtp_encryption',
                'description' => __('Encryption method for SMTP connection.', 'invoiceforge'),
                'class'       => 'smtp-field',
                'options'     => [
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                    ''    => __('None', 'invoiceforge'),
                ],
            ]
        );
    }

    /**
     * Add advanced settings fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function addAdvancedFields(): void
    {
        add_settings_field(
            'default_currency',
            __('Default Currency', 'invoiceforge'),
            [$this, 'renderSelectField'],
            'invoiceforge-settings-advanced',
            'invoiceforge_advanced',
            [
                'id'          => 'default_currency',
                'description' => __('Default currency for new invoices.', 'invoiceforge'),
                'options'     => [
                    'USD' => 'USD - US Dollar',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - British Pound',
                    'CAD' => 'CAD - Canadian Dollar',
                    'AUD' => 'AUD - Australian Dollar',
                ],
            ]
        );

        add_settings_field(
            'invoice_prefix',
            __('Invoice Prefix', 'invoiceforge'),
            [$this, 'renderTextField'],
            'invoiceforge-settings-advanced',
            'invoiceforge_advanced',
            [
                'id'          => 'invoice_prefix',
                'description' => __('Prefix for invoice numbers (e.g., INV, INVOICE).', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'invoice_terms',
            __('Default Terms', 'invoiceforge'),
            [$this, 'renderTextareaField'],
            'invoiceforge-settings-advanced',
            'invoiceforge_advanced',
            [
                'id'          => 'invoice_terms',
                'description' => __('Default terms and conditions for invoices.', 'invoiceforge'),
            ]
        );

        add_settings_field(
            'invoice_notes',
            __('Default Notes', 'invoiceforge'),
            [$this, 'renderTextareaField'],
            'invoiceforge-settings-advanced',
            'invoiceforge_advanced',
            [
                'id'          => 'invoice_notes',
                'description' => __('Default notes to appear on invoices.', 'invoiceforge'),
            ]
        );
    }

    /**
     * Render the general section description.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderGeneralSection(): void
    {
        echo '<p>' . esc_html__('Configure your company information that will appear on invoices.', 'invoiceforge') . '</p>';
    }

    /**
     * Render the email section description.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderEmailSection(): void
    {
        echo '<p>' . esc_html__('Configure how emails are sent from InvoiceForge.', 'invoiceforge') . '</p>';
    }

    /**
     * Render the advanced section description.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderAdvancedSection(): void
    {
        echo '<p>' . esc_html__('Advanced configuration options.', 'invoiceforge') . '</p>';
    }

    /**
     * Render a text input field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderTextField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        $class = $args['class'] ?? '';
        ?>
        <input type="text" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text <?php echo esc_attr($class); ?>">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render an email input field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderEmailField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        ?>
        <input type="email" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderNumberField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        $class = $args['class'] ?? '';
        ?>
        <input type="number" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
               value="<?php echo esc_attr((string) $value); ?>" 
               class="small-text <?php echo esc_attr($class); ?>"
               <?php echo $min !== '' ? 'min="' . esc_attr((string) $min) . '"' : ''; ?>
               <?php echo $max !== '' ? 'max="' . esc_attr((string) $max) . '"' : ''; ?>>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a password input field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderPasswordField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        $class = $args['class'] ?? '';

        // Decrypt the password for display (masked)
        if (!empty($value)) {
            $decrypted = $this->encryption->safeDecrypt($value);
            $masked = str_repeat('•', min(strlen($decrypted), 20));
        } else {
            $masked = '';
        }
        ?>
        <input type="password" 
               id="<?php echo esc_attr($id); ?>" 
               name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
               value="" 
               class="regular-text <?php echo esc_attr($class); ?>"
               placeholder="<?php echo esc_attr($masked ?: __('Enter password', 'invoiceforge')); ?>">
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <p class="description">
            <?php 
            if (!empty($value)) {
                esc_html_e('Leave blank to keep current password.', 'invoiceforge');
            }
            ?>
        </p>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderTextareaField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        ?>
        <textarea id="<?php echo esc_attr($id); ?>" 
                  name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
                  rows="4" 
                  class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a checkbox field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderCheckboxField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = !empty($settings[$id]);
        ?>
        <label for="<?php echo esc_attr($id); ?>">
            <input type="checkbox" 
                   id="<?php echo esc_attr($id); ?>" 
                   name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
                   value="1" 
                   <?php checked($value); ?>>
            <?php if (!empty($args['description'])) : ?>
                <?php echo esc_html($args['description']); ?>
            <?php endif; ?>
        </label>
        <?php
    }

    /**
     * Render a select field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderSelectField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? '';
        $options = $args['options'] ?? [];
        $class = $args['class'] ?? '';
        ?>
        <select id="<?php echo esc_attr($id); ?>" 
                name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>"
                class="<?php echo esc_attr($class); ?>">
            <?php foreach ($options as $option_value => $option_label) : ?>
                <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                    <?php echo esc_html($option_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render an image upload field.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $args Field arguments.
     * @return void
     */
    public function renderImageField(array $args): void
    {
        $settings = $this->getSettings();
        $id = $args['id'];
        $value = $settings[$id] ?? 0;
        $image_url = $value ? wp_get_attachment_image_url((int) $value, 'medium') : '';
        ?>
        <div class="invoiceforge-image-upload">
            <input type="hidden" 
                   id="<?php echo esc_attr($id); ?>" 
                   name="<?php echo esc_attr(self::OPTION_NAME . '[' . $id . ']'); ?>" 
                   value="<?php echo esc_attr((string) $value); ?>">
            <div class="invoiceforge-image-preview" id="<?php echo esc_attr($id); ?>_preview">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                <?php endif; ?>
            </div>
            <button type="button" class="button invoiceforge-upload-image" data-target="<?php echo esc_attr($id); ?>">
                <?php esc_html_e('Select Image', 'invoiceforge'); ?>
            </button>
            <button type="button" class="button invoiceforge-remove-image" data-target="<?php echo esc_attr($id); ?>" 
                    style="<?php echo $value ? '' : 'display:none;'; ?>">
                <?php esc_html_e('Remove Image', 'invoiceforge'); ?>
            </button>
        </div>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize settings before saving.
     * FIXED: Now properly merges new settings with existing ones to preserve other tabs.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $input The input settings.
     * @return array<string, mixed> The sanitized settings.
     */
    public function sanitizeSettings(array $input): array
    {
        // Get current saved settings (not defaults) - this preserves other tabs
        $current = get_option(self::OPTION_NAME, []);
        if (!is_array($current)) {
            $current = [];
        }

        // Start with current settings to preserve other tabs
        $sanitized = array_merge($this->getDefaults(), $current);

        // Determine which tab we're saving based on submitted fields
        $active_tab = $this->determineActiveTabFromInput($input);

        // Only process fields from the active tab
        $tab_fields = self::TAB_FIELDS[$active_tab] ?? [];

        foreach ($tab_fields as $field) {
            // Handle checkbox fields specially - if not in input, they're unchecked
            if ($field === 'smtp_enabled') {
                $sanitized[$field] = !empty($input[$field]);
                continue;
            }

            // Skip if field not in input (shouldn't happen for non-checkbox fields)
            if (!array_key_exists($field, $input)) {
                continue;
            }

            // Sanitize based on field type
            $sanitized[$field] = $this->sanitizeField($field, $input[$field], $current[$field] ?? null);
        }

        /**
         * Fires after settings are sanitized.
         *
         * @since 1.0.0
         *
         * @param array<string, mixed> $sanitized The sanitized settings.
         * @param array<string, mixed> $input     The original input.
         */
        do_action('invoiceforge_settings_saved', $sanitized, $input);

        return $sanitized;
    }

    /**
     * Determine which tab is being saved based on submitted fields.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $input The input settings.
     * @return string The active tab slug.
     */
    private function determineActiveTabFromInput(array $input): string
    {
        foreach (self::TAB_FIELDS as $tab => $fields) {
            // Check if any field from this tab is in the input
            foreach ($fields as $field) {
                if (array_key_exists($field, $input)) {
                    return $tab;
                }
            }
        }

        return 'general';
    }

    /**
     * Sanitize a single field based on its name.
     *
     * @since 1.0.0
     *
     * @param string $field         The field name.
     * @param mixed  $value         The value to sanitize.
     * @param mixed  $current_value The current stored value.
     * @return mixed The sanitized value.
     */
    private function sanitizeField(string $field, mixed $value, mixed $current_value): mixed
    {
        switch ($field) {
            // Text fields
            case 'company_name':
            case 'company_phone':
            case 'email_from_name':
            case 'smtp_host':
            case 'smtp_username':
            case 'invoice_prefix':
                return $this->sanitizer->text((string) $value);

            // Email fields
            case 'company_email':
            case 'email_from_address':
                return $this->sanitizer->email((string) $value);

            // Textarea fields
            case 'company_address':
            case 'invoice_terms':
            case 'invoice_notes':
                return $this->sanitizer->textarea((string) $value);

            // Number fields
            case 'company_logo':
                return $this->sanitizer->absint($value);

            case 'smtp_port':
                return $this->sanitizer->absint($value) ?: 587;

            // Boolean fields
            case 'smtp_enabled':
                return $this->sanitizer->bool($value);

            // Select fields
            case 'default_currency':
                return $this->sanitizer->option(
                    (string) $value,
                    ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                    'USD'
                );

            case 'smtp_encryption':
                return $this->sanitizer->option(
                    (string) $value,
                    ['tls', 'ssl', ''],
                    'tls'
                );

            // Password field (encrypt if changed, keep existing if empty)
            case 'smtp_password':
                if (!empty($value)) {
                    return $this->encryption->safeEncrypt((string) $value);
                }
                return $current_value ?? '';

            default:
                return $this->sanitizer->text((string) $value);
        }
    }

    /**
     * Get current settings.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The current settings.
     */
    public function getSettings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge($this->getDefaults(), $settings);
    }

    /**
     * Get default settings.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> Default settings.
     */
    public function getDefaults(): array
    {
        return [
            'company_name'       => '',
            'company_email'      => get_option('admin_email', ''),
            'company_phone'      => '',
            'company_address'    => '',
            'company_logo'       => 0,
            'email_from_name'    => get_option('blogname', 'InvoiceForge'),
            'email_from_address' => get_option('admin_email', ''),
            'smtp_enabled'       => false,
            'smtp_host'          => '',
            'smtp_port'          => 587,
            'smtp_username'      => '',
            'smtp_password'      => '',
            'smtp_encryption'    => 'tls',
            'default_currency'          => 'USD',
            'invoice_prefix'            => 'INV',
            'invoice_terms'             => '',
            'invoice_notes'             => '',
            // WooCommerce Integration
            'woo_enabled'               => false,
            'woo_trigger_statuses'      => ['wc-completed'],
            'woo_invoice_number_format' => 'invoiceforge',
            'woo_invoice_prefix'        => 'ORD',
            'woo_auto_email'            => true,
        ];
    }

    /**
     * Get available tabs.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Array of tab slug => label.
     */
    public function getTabs(): array
    {
        $tabs = [
            'general'      => __('General', 'invoiceforge'),
            'email'        => __('Email', 'invoiceforge'),
            'advanced'     => __('Advanced', 'invoiceforge'),
            'integrations' => __('Integrations', 'invoiceforge'),
        ];

        return apply_filters('invoiceforge_settings_tabs', $tabs);
    }

    /**
     * Get the current active tab.
     *
     * @since 1.0.0
     *
     * @return string The active tab slug.
     */
    public function getActiveTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = array_keys($this->getTabs());

        return in_array($tab, $tabs, true) ? $tab : 'general';
    }
}
