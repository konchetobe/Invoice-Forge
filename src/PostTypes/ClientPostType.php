<?php
/**
 * Client Post Type
 *
 * Registers and manages the Client custom post type.
 * Supports both individuals and companies.
 *
 * @package    InvoiceForge
 * @subpackage PostTypes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\PostTypes;

use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Sanitizer;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client custom post type handler.
 *
 * @since 1.0.0
 */
class ClientPostType
{
    /**
     * Post type name.
     *
     * @since 1.0.0
     * @var string
     */
    public const POST_TYPE = 'if_client';

    /**
     * Meta key prefix.
     *
     * @since 1.0.0
     * @var string
     */
    private const META_PREFIX = '_client_';

    /**
     * Nonce action name.
     *
     * @since 1.0.0
     * @var string
     */
    private const NONCE_ACTION = 'save_client_meta';

    /**
     * Nonce field name.
     *
     * @since 1.0.0
     * @var string
     */
    private const NONCE_FIELD = 'invoiceforge_client_nonce';

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
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Nonce     $nonce     Nonce handler.
     * @param Sanitizer $sanitizer Sanitizer instance.
     */
    public function __construct(Nonce $nonce, Sanitizer $sanitizer)
    {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Register the client post type.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $labels = [
            'name'                  => _x('Clients', 'Post type general name', 'invoiceforge'),
            'singular_name'         => _x('Client', 'Post type singular name', 'invoiceforge'),
            'menu_name'             => _x('Clients', 'Admin Menu text', 'invoiceforge'),
            'name_admin_bar'        => _x('Client', 'Add New on Toolbar', 'invoiceforge'),
            'add_new'               => __('Add New', 'invoiceforge'),
            'add_new_item'          => __('Add New Client', 'invoiceforge'),
            'new_item'              => __('New Client', 'invoiceforge'),
            'edit_item'             => __('Edit Client', 'invoiceforge'),
            'view_item'             => __('View Client', 'invoiceforge'),
            'all_items'             => __('All Clients', 'invoiceforge'),
            'search_items'          => __('Search Clients', 'invoiceforge'),
            'parent_item_colon'     => __('Parent Clients:', 'invoiceforge'),
            'not_found'             => __('No clients found.', 'invoiceforge'),
            'not_found_in_trash'    => __('No clients found in Trash.', 'invoiceforge'),
            'featured_image'        => _x('Client Photo', 'Overrides the "Featured Image" phrase', 'invoiceforge'),
            'set_featured_image'    => _x('Set client photo', 'Overrides the "Set featured image" phrase', 'invoiceforge'),
            'remove_featured_image' => _x('Remove client photo', 'Overrides the "Remove featured image" phrase', 'invoiceforge'),
            'use_featured_image'    => _x('Use as client photo', 'Overrides the "Use as featured image" phrase', 'invoiceforge'),
            'archives'              => _x('Client archives', 'The post type archive label', 'invoiceforge'),
            'insert_into_item'      => _x('Insert into client', 'Overrides the "Insert into post" phrase', 'invoiceforge'),
            'uploaded_to_this_item' => _x('Uploaded to this client', 'Overrides the "Uploaded to this post" phrase', 'invoiceforge'),
            'filter_items_list'     => _x('Filter clients list', 'Screen reader text', 'invoiceforge'),
            'items_list_navigation' => _x('Clients list navigation', 'Screen reader text', 'invoiceforge'),
            'items_list'            => _x('Clients list', 'Screen reader text', 'invoiceforge'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it to our custom menu
            'query_var'          => true,
            'rewrite'            => ['slug' => 'client'],
            'capability_type'    => ['if_client', 'if_clients'],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title', 'author', 'thumbnail'],
            'show_in_rest'       => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for the client post type.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addMetaBoxes(): void
    {
        add_meta_box(
            'invoiceforge_client_contact',
            __('Contact Information', 'invoiceforge'),
            [$this, 'renderContactMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'invoiceforge_client_address',
            __('Address', 'invoiceforge'),
            [$this, 'renderAddressMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'invoiceforge_client_billing',
            __('Billing Information', 'invoiceforge'),
            [$this, 'renderBillingMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render the contact information meta box.
     * Now supports individuals with first name, last name, and optional company.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderContactMetaBox(\WP_Post $post): void
    {
        // Add nonce for security - use consistent field name
        $this->nonce->field(self::NONCE_ACTION, self::NONCE_FIELD);

        // Get current values
        $first_name = get_post_meta($post->ID, self::META_PREFIX . 'first_name', true);
        $last_name = get_post_meta($post->ID, self::META_PREFIX . 'last_name', true);
        $company = get_post_meta($post->ID, self::META_PREFIX . 'company', true);
        $email = get_post_meta($post->ID, self::META_PREFIX . 'email', true);
        $phone = get_post_meta($post->ID, self::META_PREFIX . 'phone', true);
        ?>
        <table class="form-table invoiceforge-meta-table">
            <tr>
                <th scope="row">
                    <label for="client_first_name"><?php esc_html_e('First Name', 'invoiceforge'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="client_first_name" name="client_first_name" 
                           value="<?php echo esc_attr($first_name); ?>" 
                           class="regular-text" required
                           placeholder="<?php esc_attr_e('John', 'invoiceforge'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_last_name"><?php esc_html_e('Last Name', 'invoiceforge'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="client_last_name" name="client_last_name" 
                           value="<?php echo esc_attr($last_name); ?>" 
                           class="regular-text" required
                           placeholder="<?php esc_attr_e('Doe', 'invoiceforge'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_company"><?php esc_html_e('Company', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="text" id="client_company" name="client_company" 
                           value="<?php echo esc_attr($company); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Company or Organization (Optional)', 'invoiceforge'); ?>">
                    <p class="description">
                        <?php esc_html_e('Optional. Leave blank for individual clients.', 'invoiceforge'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_email"><?php esc_html_e('Email Address', 'invoiceforge'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="email" id="client_email" name="client_email" 
                           value="<?php echo esc_attr($email); ?>" 
                           class="regular-text" required
                           placeholder="<?php esc_attr_e('email@example.com', 'invoiceforge'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_phone"><?php esc_html_e('Phone Number', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="tel" id="client_phone" name="client_phone" 
                           value="<?php echo esc_attr($phone); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('+1 (555) 123-4567', 'invoiceforge'); ?>">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the address meta box.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderAddressMetaBox(\WP_Post $post): void
    {
        // Get current values
        $address = get_post_meta($post->ID, self::META_PREFIX . 'address', true);
        $city = get_post_meta($post->ID, self::META_PREFIX . 'city', true);
        $state = get_post_meta($post->ID, self::META_PREFIX . 'state', true);
        $zip = get_post_meta($post->ID, self::META_PREFIX . 'zip', true);
        $country = get_post_meta($post->ID, self::META_PREFIX . 'country', true);
        ?>
        <table class="form-table invoiceforge-meta-table">
            <tr>
                <th scope="row">
                    <label for="client_address"><?php esc_html_e('Street Address', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <textarea id="client_address" name="client_address" rows="3" class="large-text"
                              placeholder="<?php esc_attr_e('123 Main Street, Suite 100', 'invoiceforge'); ?>"><?php 
                        echo esc_textarea($address); 
                    ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_city"><?php esc_html_e('City', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="text" id="client_city" name="client_city" 
                           value="<?php echo esc_attr($city); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_state"><?php esc_html_e('State / Province', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="text" id="client_state" name="client_state" 
                           value="<?php echo esc_attr($state); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_zip"><?php esc_html_e('ZIP / Postal Code', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <input type="text" id="client_zip" name="client_zip" 
                           value="<?php echo esc_attr($zip); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="client_country"><?php esc_html_e('Country', 'invoiceforge'); ?></label>
                </th>
                <td>
                    <select id="client_country" name="client_country" class="regular-text">
                        <option value=""><?php esc_html_e('Select a country...', 'invoiceforge'); ?></option>
                        <?php foreach ($this->getCountries() as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($country, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the billing information meta box.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function renderBillingMetaBox(\WP_Post $post): void
    {
        $tax_id = get_post_meta($post->ID, self::META_PREFIX . 'tax_id', true);
        $id_no  = get_post_meta($post->ID, self::META_PREFIX . 'id_no', true);
        $office = get_post_meta($post->ID, self::META_PREFIX . 'office', true);
        $att_to = get_post_meta($post->ID, self::META_PREFIX . 'att_to', true);
        ?>
        <p>
            <label for="client_tax_id">
                <strong><?php esc_html_e('Tax ID / VAT Number', 'invoiceforge'); ?></strong>
            </label>
        </p>
        <p>
            <input type="text" id="client_tax_id" name="client_tax_id"
                   value="<?php echo esc_attr($tax_id); ?>"
                   class="widefat"
                   placeholder="<?php esc_attr_e('e.g., GB123456789', 'invoiceforge'); ?>">
        </p>
        <p class="description">
            <?php esc_html_e('Required for EU VAT reverse charge and tax compliance.', 'invoiceforge'); ?>
        </p>
        <p>
            <label for="client_id_no">
                <strong><?php esc_html_e('ID No (EIK/BULSTAT/Reg No)', 'invoiceforge'); ?></strong>
            </label>
        </p>
        <p>
            <input type="text" id="client_id_no" name="client_id_no"
                   value="<?php echo esc_attr($id_no); ?>"
                   class="widefat"
                   placeholder="<?php esc_attr_e('e.g., 123456789', 'invoiceforge'); ?>">
        </p>
        <p>
            <label for="client_office">
                <strong><?php esc_html_e('Office / Branch', 'invoiceforge'); ?></strong>
            </label>
        </p>
        <p>
            <input type="text" id="client_office" name="client_office"
                   value="<?php echo esc_attr($office); ?>"
                   class="widefat"
                   placeholder="<?php esc_attr_e('e.g., HQ, Branch 1', 'invoiceforge'); ?>">
        </p>
        <p>
            <label for="client_att_to">
                <strong><?php esc_html_e('Attention To', 'invoiceforge'); ?></strong>
            </label>
        </p>
        <p>
            <input type="text" id="client_att_to" name="client_att_to"
                   value="<?php echo esc_attr($att_to); ?>"
                   class="widefat"
                   placeholder="<?php esc_attr_e('Contact person name', 'invoiceforge'); ?>">
        </p>
        <?php
    }

    /**
     * Save client meta data.
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

        // Define fields to save - updated to include first_name and last_name
        $fields = [
            'first_name' => 'text',
            'last_name'  => 'text',
            'company'    => 'text',
            'email'      => 'email',
            'phone'      => 'phone',
            'address'    => 'textarea',
            'city'       => 'text',
            'state'      => 'text',
            'zip'        => 'text',
            'country'    => 'text',
            'tax_id'     => 'text',
            'id_no'      => 'text',
            'office'     => 'text',
            'att_to'     => 'text',
        ];

        // Save each field
        foreach ($fields as $field => $type) {
            $post_key = 'client_' . $field;

            if (!isset($_POST[$post_key])) {
                continue;
            }

            $value = match ($type) {
                'email'    => $this->sanitizer->email($_POST[$post_key]),
                'phone'    => $this->sanitizer->phone($_POST[$post_key]),
                'textarea' => $this->sanitizer->textarea($_POST[$post_key]),
                default    => $this->sanitizer->text($_POST[$post_key]),
            };

            update_post_meta($post_id, self::META_PREFIX . $field, $value);
        }

        /**
         * Fires after client meta data is saved.
         *
         * @since 1.0.0
         *
         * @param int $post_id The client post ID.
         */
        do_action('invoiceforge_client_saved', $post_id);
    }

    /**
     * Add custom columns to the client list.
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
                $new_columns[$key] = __('Client Name', 'invoiceforge');
                $new_columns['company'] = __('Company', 'invoiceforge');
                $new_columns['email'] = __('Email', 'invoiceforge');
                $new_columns['phone'] = __('Phone', 'invoiceforge');
                $new_columns['location'] = __('Location', 'invoiceforge');
                $new_columns['invoices'] = __('Invoices', 'invoiceforge');
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
            case 'company':
                $company = get_post_meta($post_id, self::META_PREFIX . 'company', true);
                echo esc_html($company ?: '—');
                break;

            case 'email':
                $email = get_post_meta($post_id, self::META_PREFIX . 'email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'phone':
                $phone = get_post_meta($post_id, self::META_PREFIX . 'phone', true);
                if ($phone) {
                    echo '<a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone) ?? '') . '">';
                    echo esc_html($phone);
                    echo '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'location':
                $city = get_post_meta($post_id, self::META_PREFIX . 'city', true);
                $country = get_post_meta($post_id, self::META_PREFIX . 'country', true);
                $parts = array_filter([$city, $country]);
                echo esc_html($parts ? implode(', ', $parts) : '—');
                break;

            case 'invoices':
                $invoice_count = $this->getInvoiceCount($post_id);
                if ($invoice_count > 0) {
                    $url = admin_url('edit.php?post_type=' . InvoicePostType::POST_TYPE . '&client_id=' . $post_id);
                    echo '<a href="' . esc_url($url) . '">' . esc_html((string) $invoice_count) . '</a>';
                } else {
                    echo '0';
                }
                break;
        }
    }

    /**
     * Get the number of invoices for a client.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
     * @return int The invoice count.
     */
    private function getInvoiceCount(int $client_id): int
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
     * Get list of countries.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Array of country code => name.
     */
    public function getCountries(): array
    {
        // Comprehensive list of countries sorted alphabetically by name
        $countries = [
            'AF' => __('Afghanistan', 'invoiceforge'),
            'AL' => __('Albania', 'invoiceforge'),
            'DZ' => __('Algeria', 'invoiceforge'),
            'AD' => __('Andorra', 'invoiceforge'),
            'AO' => __('Angola', 'invoiceforge'),
            'AR' => __('Argentina', 'invoiceforge'),
            'AM' => __('Armenia', 'invoiceforge'),
            'AU' => __('Australia', 'invoiceforge'),
            'AT' => __('Austria', 'invoiceforge'),
            'AZ' => __('Azerbaijan', 'invoiceforge'),
            'BH' => __('Bahrain', 'invoiceforge'),
            'BD' => __('Bangladesh', 'invoiceforge'),
            'BY' => __('Belarus', 'invoiceforge'),
            'BE' => __('Belgium', 'invoiceforge'),
            'BZ' => __('Belize', 'invoiceforge'),
            'BO' => __('Bolivia', 'invoiceforge'),
            'BA' => __('Bosnia and Herzegovina', 'invoiceforge'),
            'BW' => __('Botswana', 'invoiceforge'),
            'BR' => __('Brazil', 'invoiceforge'),
            'BN' => __('Brunei', 'invoiceforge'),
            'BG' => __('Bulgaria', 'invoiceforge'),
            'KH' => __('Cambodia', 'invoiceforge'),
            'CM' => __('Cameroon', 'invoiceforge'),
            'CA' => __('Canada', 'invoiceforge'),
            'CL' => __('Chile', 'invoiceforge'),
            'CN' => __('China', 'invoiceforge'),
            'CO' => __('Colombia', 'invoiceforge'),
            'CR' => __('Costa Rica', 'invoiceforge'),
            'HR' => __('Croatia', 'invoiceforge'),
            'CU' => __('Cuba', 'invoiceforge'),
            'CY' => __('Cyprus', 'invoiceforge'),
            'CZ' => __('Czech Republic', 'invoiceforge'),
            'DK' => __('Denmark', 'invoiceforge'),
            'DO' => __('Dominican Republic', 'invoiceforge'),
            'EC' => __('Ecuador', 'invoiceforge'),
            'EG' => __('Egypt', 'invoiceforge'),
            'SV' => __('El Salvador', 'invoiceforge'),
            'EE' => __('Estonia', 'invoiceforge'),
            'ET' => __('Ethiopia', 'invoiceforge'),
            'FI' => __('Finland', 'invoiceforge'),
            'FR' => __('France', 'invoiceforge'),
            'GE' => __('Georgia', 'invoiceforge'),
            'DE' => __('Germany', 'invoiceforge'),
            'GH' => __('Ghana', 'invoiceforge'),
            'GR' => __('Greece', 'invoiceforge'),
            'GT' => __('Guatemala', 'invoiceforge'),
            'HN' => __('Honduras', 'invoiceforge'),
            'HK' => __('Hong Kong', 'invoiceforge'),
            'HU' => __('Hungary', 'invoiceforge'),
            'IS' => __('Iceland', 'invoiceforge'),
            'IN' => __('India', 'invoiceforge'),
            'ID' => __('Indonesia', 'invoiceforge'),
            'IR' => __('Iran', 'invoiceforge'),
            'IQ' => __('Iraq', 'invoiceforge'),
            'IE' => __('Ireland', 'invoiceforge'),
            'IL' => __('Israel', 'invoiceforge'),
            'IT' => __('Italy', 'invoiceforge'),
            'JM' => __('Jamaica', 'invoiceforge'),
            'JP' => __('Japan', 'invoiceforge'),
            'JO' => __('Jordan', 'invoiceforge'),
            'KZ' => __('Kazakhstan', 'invoiceforge'),
            'KE' => __('Kenya', 'invoiceforge'),
            'KW' => __('Kuwait', 'invoiceforge'),
            'LV' => __('Latvia', 'invoiceforge'),
            'LB' => __('Lebanon', 'invoiceforge'),
            'LY' => __('Libya', 'invoiceforge'),
            'LI' => __('Liechtenstein', 'invoiceforge'),
            'LT' => __('Lithuania', 'invoiceforge'),
            'LU' => __('Luxembourg', 'invoiceforge'),
            'MO' => __('Macau', 'invoiceforge'),
            'MK' => __('North Macedonia', 'invoiceforge'),
            'MY' => __('Malaysia', 'invoiceforge'),
            'MV' => __('Maldives', 'invoiceforge'),
            'MT' => __('Malta', 'invoiceforge'),
            'MU' => __('Mauritius', 'invoiceforge'),
            'MX' => __('Mexico', 'invoiceforge'),
            'MD' => __('Moldova', 'invoiceforge'),
            'MC' => __('Monaco', 'invoiceforge'),
            'MN' => __('Mongolia', 'invoiceforge'),
            'ME' => __('Montenegro', 'invoiceforge'),
            'MA' => __('Morocco', 'invoiceforge'),
            'MM' => __('Myanmar', 'invoiceforge'),
            'NP' => __('Nepal', 'invoiceforge'),
            'NL' => __('Netherlands', 'invoiceforge'),
            'NZ' => __('New Zealand', 'invoiceforge'),
            'NI' => __('Nicaragua', 'invoiceforge'),
            'NG' => __('Nigeria', 'invoiceforge'),
            'NO' => __('Norway', 'invoiceforge'),
            'OM' => __('Oman', 'invoiceforge'),
            'PK' => __('Pakistan', 'invoiceforge'),
            'PA' => __('Panama', 'invoiceforge'),
            'PY' => __('Paraguay', 'invoiceforge'),
            'PE' => __('Peru', 'invoiceforge'),
            'PH' => __('Philippines', 'invoiceforge'),
            'PL' => __('Poland', 'invoiceforge'),
            'PT' => __('Portugal', 'invoiceforge'),
            'PR' => __('Puerto Rico', 'invoiceforge'),
            'QA' => __('Qatar', 'invoiceforge'),
            'RO' => __('Romania', 'invoiceforge'),
            'RU' => __('Russia', 'invoiceforge'),
            'SA' => __('Saudi Arabia', 'invoiceforge'),
            'RS' => __('Serbia', 'invoiceforge'),
            'SG' => __('Singapore', 'invoiceforge'),
            'SK' => __('Slovakia', 'invoiceforge'),
            'SI' => __('Slovenia', 'invoiceforge'),
            'ZA' => __('South Africa', 'invoiceforge'),
            'KR' => __('South Korea', 'invoiceforge'),
            'ES' => __('Spain', 'invoiceforge'),
            'LK' => __('Sri Lanka', 'invoiceforge'),
            'SE' => __('Sweden', 'invoiceforge'),
            'CH' => __('Switzerland', 'invoiceforge'),
            'TW' => __('Taiwan', 'invoiceforge'),
            'TZ' => __('Tanzania', 'invoiceforge'),
            'TH' => __('Thailand', 'invoiceforge'),
            'TN' => __('Tunisia', 'invoiceforge'),
            'TR' => __('Turkey', 'invoiceforge'),
            'UA' => __('Ukraine', 'invoiceforge'),
            'AE' => __('United Arab Emirates', 'invoiceforge'),
            'GB' => __('United Kingdom', 'invoiceforge'),
            'US' => __('United States', 'invoiceforge'),
            'UY' => __('Uruguay', 'invoiceforge'),
            'UZ' => __('Uzbekistan', 'invoiceforge'),
            'VE' => __('Venezuela', 'invoiceforge'),
            'VN' => __('Vietnam', 'invoiceforge'),
            'YE' => __('Yemen', 'invoiceforge'),
            'ZW' => __('Zimbabwe', 'invoiceforge'),
        ];

        // Sort by country name
        asort($countries);

        /**
         * Filter the list of available countries.
         *
         * @since 1.0.0
         *
         * @param array<string, string> $countries Array of country code => name.
         */
        return apply_filters('invoiceforge_countries', $countries);
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

    /**
     * Get client display name.
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
     * @return string The display name.
     */
    public static function getDisplayName(int $client_id): string
    {
        $first_name = get_post_meta($client_id, '_client_first_name', true);
        $last_name = get_post_meta($client_id, '_client_last_name', true);
        $company = get_post_meta($client_id, '_client_company', true);

        $name = trim($first_name . ' ' . $last_name);
        
        if (!empty($company)) {
            return $name ? "$name ($company)" : $company;
        }

        return $name ?: __('Unknown Client', 'invoiceforge');
    }
}
