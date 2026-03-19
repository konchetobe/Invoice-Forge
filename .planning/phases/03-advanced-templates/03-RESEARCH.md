# Phase 3: Advanced Templates - Research

**Researched:** 2026-03-19
**Domain:** WordPress plugin — PDF template system, admin section editor, email HTML body, client/seller profile fields
**Confidence:** HIGH (stack is locked; research verifies concrete technical approaches against existing codebase)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Rendering engine**
- Keep HTML/CSS → mPDF pipeline (no engine change)
- `PdfService.php` extended, not replaced
- New template file replaces `templates/pdf/invoice-default.php`

**Template count and structure**
- Single configurable template (not a library of templates)
- Sections can be shown/hidden and reordered via admin editor
- All configuration stored in `invoiceforge_settings` (existing WordPress options key)

**Logo**
- Position: Top left of the SELLER section (above/beside company name in the header)
- Upload mechanism: Dedicated file input in Settings page (NOT WordPress media library)
- Stored in `wp-content/uploads/invoiceforge/`
- Falls back gracefully if no logo set

**Visual style**
- Formal document base (black/white, bold section headers, simple table borders)
- Configurable accent color (admin sets hex value in Settings → General)
- Accent color applied to: section header backgrounds, totals block, column headers
- Default accent: professional dark navy

**Section visibility**
- Always visible (EU VAT Directive mandatory): Invoice number/date, Buyer/Seller details, line items, VAT amount, total
- Conditionally shown by default: VAT note, Bank/IBAN section (when payment method = Bank transfer)
- Admin-toggleable (optional): Signature block, whole document discount row, additional notes

**Section ordering**
- Admin can reorder sections via drag-and-drop visual editor in Settings
- Order persisted in `invoiceforge_settings`

**Discount display in line items table**
- Discount columns (% and value) shown only when at least one line item has non-zero discount
- Whole document discount row hidden when value = 0

**Notes placement**
- Additional notes appear below the full footer (full page width)

**Signature block**
- Fields are configurable — admin can add/remove fields
- Default fields: Date, Place (left column); Compiler, Personal code, Attended to (right column)

**New buyer/seller profile fields**
- ID No — label configurable (e.g., EIK, BULSTAT, Reg No, VAT No 2)
- Office — office/branch identifier
- Att To — attention to (contact name)

**Payment methods**
- Configurable list defined in Settings (admin adds/removes options)
- Selectable per invoice (dropdown)
- Bank/IBAN footer section auto-shows when "Bank transfer" is selected

**Email vs PDF rendering**
- Shared base template logic, mode flag (pdf|email)
- PDF mode: Full template with all configured sections
- Email mode: Simplified summary body + "Pay Invoice" button (placeholder) + PDF attached
- Email HTML body avoids mPDF — rendered as standard HTML for email clients

**Language / i18n**
- All template labels use `__()` translation functions
- Bulgarian translations already in place (`invoiceforge-bg_BG.po`)

### Claude's Discretion
- Exact HTML/CSS layout details within the reference PDF structure
- Drag-and-drop JS library choice (sortable.js or similar)
- mPDF configuration tweaks for table rendering
- Responsive behavior of the admin section editor
- How section config is serialized in `invoiceforge_settings`

### Deferred Ideas (OUT OF SCOPE)
- Multiple named templates (template library / template picker)
- Visual template customizer beyond section order (font picker, margin control)
- Per-invoice template override
</user_constraints>

---

## Summary

Phase 3 extends the existing InvoiceForge plugin to replace the minimal placeholder PDF template with a single, richly configurable invoice template modelled on the Bulgarian business invoice reference format. The work falls into four areas: (1) replacing `templates/pdf/invoice-default.php` with a new PHP template that handles PDF and email render modes, (2) extending `PdfService.php` with a mode flag and template context injection, (3) adding Settings page tabs/fields for logo upload, accent color, ID label, payment methods, section toggles, section order, and signature fields, and (4) adding new meta fields to both the Client post type and the Settings company profile.

The stack is entirely decided: PHP 8.1+, WordPress options API, mPDF for PDF, `wp_mail` for email, vanilla JS with SortableJS for drag-and-drop. All new config keys live under the existing `invoiceforge_settings` option. No new third-party PHP packages are required beyond mPDF (already installed). SortableJS can be loaded via a bundled file or enqueued CDN-free from a downloaded copy in `assets/admin/js/`.

The most technically subtle aspects are: (a) correct use of HTML tables — not CSS grid/flexbox — for mPDF multi-column layouts; (b) embedding the logo via the `$mpdf->imageVars` mechanism or an absolute server path (not a URL) when in PDF mode; (c) implementing the independent file upload (not WP media library) with `wp_handle_upload` plus the `upload_dir` filter to redirect to `wp-content/uploads/invoiceforge/`; and (d) structuring the section config serialization so both visibility booleans and ordering are stored cleanly and queried cheaply.

**Primary recommendation:** Build section config as a single `invoiceforge_settings['template']` sub-array containing `section_order` (ordered array of slugs), `section_visibility` (slug → bool), `accent_color`, `logo_path`, `id_no_label`, `payment_methods`, and `signature_fields`. This isolates template config from other settings groups and makes the SettingsPage tab-field merge logic straightforward.

---

## Standard Stack

### Core
| Library / API | Version | Purpose | Why Standard |
|---|---|---|---|
| mPDF | ^8.2 (already installed) | HTML→PDF rendering | Locked decision; existing `PdfService` already wraps it |
| WordPress Options API | WP 6.0+ | Persist all template config | Existing plugin pattern — `invoiceforge_settings` |
| `wp_mail` | WP core | Send HTML email body + PDF attachment | Existing `EmailService` uses it |
| WordPress Settings API | WP 6.0+ | Register settings sections and fields | All existing Settings tabs use it |
| `wp_handle_upload` | WP core | Handle logo file upload to custom directory | Standard WP file upload without media library |
| SortableJS | ^1.15 | Drag-and-drop section reorder in admin | Lightweight, zero-dependency, touch-friendly; established community use in WP plugins |

### Supporting
| Library | Purpose | When to Use |
|---|---|---|
| `ob_start` / `ob_get_clean` | Capture PHP template output as string | Already used in `PdfService::generate()` for PDF mode; reuse for email mode |
| PHP `file_put_contents` | Write logo to `invoiceforge/` uploads dir | Simpler than `wp_filesystem` for plugin-private files |
| `wp_localize_script` | Pass settings nonce and AJAX URL to admin JS | Already used in plugin for other AJAX calls |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| SortableJS | HTML5 native drag events | Native drag events are verbose and have poor touch support; SortableJS is ~50 KB minified and well-tested |
| SortableJS | jQuery UI Sortable (already in WP admin) | jQuery UI Sortable is available in WP admin without extra loading. Valid fallback if bundling SortableJS adds complexity; touch support is weaker |
| Server-path logo in mPDF | Base64 data URI in `<img src="data:...">` | mPDF 8.x supports data URIs but requires enabling the `data` stream wrapper whitelist. Server path is simpler and more reliable |

**Installation:**
SortableJS — download and place in `assets/admin/js/sortable.min.js`, then enqueue with `wp_enqueue_script`. No Composer requirement.

---

## Architecture Patterns

### Recommended Project Structure (additions only)

```
src/
├── Services/
│   └── PdfService.php          # extend: add renderMode flag and getTemplateContext()
├── Admin/
│   └── Pages/
│       └── SettingsPage.php    # extend: add 'template' tab, new field groups
├── PostTypes/
│   ├── ClientPostType.php      # extend: add id_no, office, att_to meta fields
│   └── InvoicePostType.php     # extend: add payment_method meta field
templates/
└── pdf/
    └── invoice-default.php     # REPLACE with new configurable template
assets/
└── admin/
    ├── js/
    │   ├── admin.js             # extend: add section editor drag-drop init
    │   └── sortable.min.js      # new: bundled SortableJS
    └── css/
        └── admin.css            # extend: section editor UI styles
```

### Pattern 1: PDF/Email Dual Render Mode

`PdfService::generate()` gains a `string $mode = 'pdf'` parameter. It passes `$mode` into the template `include` scope along with the invoice data array. The template branches on `$mode`:

```php
// In PdfService::generate() — extend signature
public function generate(
    int $invoice_id,
    string $output_mode = 'S',
    string $file_path = '',
    string $render_mode = 'pdf'  // NEW: 'pdf' | 'email'
): ?string

// Build template context
$context = $this->getTemplateContext($invoice_id, $render_mode);

ob_start();
// Pass $context variables into template scope
extract($context, EXTR_SKIP);
include $template_file;
$html = ob_get_clean();
```

In `EmailService::sendInvoice()`, call `$this->pdfService->renderEmailBody($invoice_id)` which internally calls `generate()` with `render_mode='email'` and `output_mode='S'`, then returns the HTML string for use in `wp_mail` with `Content-Type: text/html`.

### Pattern 2: Settings Sub-Array for Template Config

All template configuration lives under `invoiceforge_settings['template']` as a nested array. This mirrors how WooCommerce integration settings are already stored as a peer key group:

```php
// invoiceforge_settings structure (excerpt)
[
    'company_name'   => '...',
    // ... existing keys ...
    'template' => [
        'logo_path'          => '/var/www/.../wp-content/uploads/invoiceforge/logo.png',
        'accent_color'       => '#1a2b4a',
        'id_no_label'        => 'EIK',
        'payment_methods'    => ['Bank transfer', 'Cash', 'Credit card'],
        'section_order'      => ['header', 'line_items', 'totals', 'bank', 'notes', 'signature'],
        'section_visibility' => [
            'signature'      => true,
            'notes'          => true,
            'discount_row'   => true,
        ],
        'signature_fields'   => [
            ['label' => 'Date',          'col' => 'left'],
            ['label' => 'Place',         'col' => 'left'],
            ['label' => 'Compiler',      'col' => 'right'],
            ['label' => 'Personal code', 'col' => 'right'],
            ['label' => 'Attended to',   'col' => 'right'],
        ],
    ],
]
```

`SettingsPage::TAB_FIELDS` gains a `'template'` key listing the flattened field names so the existing `determineActiveTabFromInput` / merge logic handles it correctly. Because `template` is a nested array, add a custom sanitize branch in `sanitizeField()`.

### Pattern 3: mPDF Table-Based Three-Column Header

mPDF does not support CSS grid or flexbox. Multi-column layouts MUST use `<table>`. The three-column header (BUYER | Invoice No+Date | SELLER) uses a single-row, three-cell table with explicit percentage widths. All widths must sum to exactly 100% after accounting for any border/padding.

```php
// Source: https://mpdf.github.io/tables/table-layout.html
// Use fixed widths; mPDF auto-layout can collapse columns unpredictably
<table width="100%" style="border-collapse:collapse; margin-bottom:8px;">
  <tr>
    <td width="38%" style="vertical-align:top; padding:4px;">
      <!-- BUYER block -->
    </td>
    <td width="24%" style="vertical-align:top; text-align:center; padding:4px;
                           background-color:<?php echo esc_html($accent_color); ?>; color:#fff;">
      <!-- Invoice No + Date -->
    </td>
    <td width="38%" style="vertical-align:top; text-align:right; padding:4px;">
      <!-- SELLER block + logo -->
    </td>
  </tr>
</table>
```

Block elements (`<div>`, `<p>`) inside `<td>` lose their block behavior in mPDF — use `<br>` for line breaks within cells instead.

### Pattern 4: Logo Embed in PDF

Use an absolute server path — not a URL — when referencing the logo in the mPDF template. mPDF resolves `<img src="/absolute/path/to/logo.png">` reliably; relative URLs cause 404s when mPDF fetches them internally.

```php
// In template — PDF mode only
if ($render_mode === 'pdf' && !empty($logo_path) && file_exists($logo_path)) {
    echo '<img src="' . esc_attr($logo_path) . '" alt="" style="max-height:60px; max-width:200px;">';
}
// Email mode: use wp_get_attachment_url() or an absolute public URL
```

Store the logo absolute path in `invoiceforge_settings['template']['logo_path']` at upload time.

### Pattern 5: Independent Logo Upload (No Media Library)

Use the `upload_dir` filter to redirect uploads to `wp-content/uploads/invoiceforge/` temporarily, call `wp_handle_upload`, then restore the filter. Store the resulting `['file']` path in settings.

```php
// In SettingsPage or a dedicated LogoUploader helper
add_filter('upload_dir', function(array $dirs): array {
    $custom = WP_CONTENT_DIR . '/uploads/invoiceforge';
    wp_mkdir_p($custom);
    $dirs['path']    = $custom;
    $dirs['url']     = WP_CONTENT_URL . '/uploads/invoiceforge';
    $dirs['subdir']  = '';
    return $dirs;
});

$overrides = ['test_form' => false, 'mimes' => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif']];
$uploaded  = wp_handle_upload($_FILES['template_logo'], $overrides);

remove_filter('upload_dir', /* same closure reference */);

if (isset($uploaded['file'])) {
    // save $uploaded['file'] as absolute path
    // save $uploaded['url']  as public URL (for email mode)
}
```

Security: validate `$_FILES` nonce before upload; validate MIME type via `wp_check_filetype_and_ext`.

### Pattern 6: SortableJS Section Editor

Enqueue SortableJS in Settings page only. The section editor renders an `<ul>` of draggable `<li>` items, each with a hidden `<input name="template[section_order][]">`. On drop, the hidden input values are updated via JS. On form save, PHP reads the ordered array.

```php
// PHP: enqueue on settings page only
wp_enqueue_script(
    'invoiceforge-sortable',
    INVOICEFORGE_PLUGIN_URL . 'assets/admin/js/sortable.min.js',
    [],
    '1.15.6',
    true
);
```

```javascript
// JS: initialize after DOM ready
const el = document.getElementById('if-section-order-list');
if (el) {
    Sortable.create(el, {
        animation: 150,
        handle: '.if-drag-handle',
        onEnd: function() {
            // Re-index hidden inputs to reflect new order
            document.querySelectorAll('#if-section-order-list .if-section-slug').forEach((input, i) => {
                input.name = 'invoiceforge_settings[template][section_order][' + i + ']';
            });
        }
    });
}
```

### Anti-Patterns to Avoid

- **CSS flexbox/grid in mPDF templates:** mPDF does not support them. Use `<table>` for all multi-column layouts.
- **Using `<p>` or `<div>` inside `<td>` for line breaks:** Block elements lose their display in mPDF table cells. Use `<br>` instead.
- **Storing logo as media library attachment ID:** The decision is an independent file upload. Storing an attachment ID would couple the feature to the WP media library (which is explicitly excluded).
- **Fetching logo by URL in mPDF:** mPDF HTTP-fetches images given a URL; this is slow, fragile (CORS, SSL), and breaks on local dev. Use the server filesystem path.
- **Overwriting the entire `invoiceforge_settings` array on template tab save:** The existing `sanitizeSettings` merge pattern must be extended for the `template` sub-key. A naive `update_option` with only the submitted tab fields will erase other tabs.
- **Using jQuery UI Sortable instead of SortableJS for new code:** jQuery UI Sortable is in WP core but the project has chosen SortableJS (Claude's discretion). If this becomes a blocker (e.g., asset loading order), jQuery UI Sortable is an acceptable fallback since it is already enqueued on admin pages.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag-and-drop list reordering | Custom mouse-event drag code | SortableJS | Handles touch, keyboard, nested lists, animations, and cross-browser edge cases |
| File type validation on upload | Manual MIME sniffing | `wp_check_filetype_and_ext()` | Handles magic bytes, not just extension; required for WP security hardening |
| HTML email body inline CSS | Custom CSS inliner | Keep inline styles in the template directly | Email clients strip `<style>` blocks; write inline styles in the template, not a post-processor |
| PDF color injection | String replace on HTML | PHP template variable `<?php echo esc_html($accent_color); ?>` | String replace is fragile; template variables are already the pattern in use |

**Key insight:** The PDF template is a PHP file `include`d by `PdfService`. It already has access to all PHP variables in scope via `extract()`. Do not build a Twig/Blade rendering layer — the existing ob_start/include pattern is the project standard.

---

## Common Pitfalls

### Pitfall 1: mPDF Table Column Width Collapse

**What goes wrong:** A three-column table header looks correct in a browser but in the PDF one or more columns collapse to near-zero width or text overflows.
**Why it happens:** mPDF's auto-layout algorithm can override declared widths when it thinks the content doesn't fit. The algorithm also applies font-size reduction on overflow.
**How to avoid:** Set explicit pixel or percentage widths on ALL columns (not just some). Use `style="table-layout:fixed;"` on the `<table>` element to force mPDF into fixed layout mode, bypassing its auto-resize heuristic.
**Warning signs:** First test render shows squashed columns or unexpectedly small text.

### Pitfall 2: Logo Path vs URL Confusion

**What goes wrong:** Logo renders in the admin preview (uses URL) but not in the PDF (mPDF needs path), or the PDF works locally but fails on production behind a reverse proxy.
**Why it happens:** Two different values are needed: the absolute server filesystem path for mPDF, and the public URL for the HTML email body and admin preview.
**How to avoid:** Store BOTH at upload time:
- `invoiceforge_settings['template']['logo_path']` — absolute filesystem path (`/var/www/…/uploads/invoiceforge/logo.png`)
- `invoiceforge_settings['template']['logo_url']` — public URL (`https://…/wp-content/uploads/invoiceforge/logo.png`)

### Pitfall 3: Settings Tab Merge Clobbering Template Sub-Array

**What goes wrong:** Saving the "Template" settings tab erases data from the "General" or "Email" tab.
**Why it happens:** `sanitizeSettings()` uses `determineActiveTabFromInput()` to identify which tab is being saved and only updates those fields. The `template` sub-key must be registered in `TAB_FIELDS['template']` so the merge logic knows to process it only when the template tab is submitted.
**How to avoid:** Add `'template'` to `TAB_FIELDS` with a sentinel field (e.g., `'_template_tab_marker'` — a hidden input in the template tab form). The sanitize callback detects this field, processes the entire `$input['template']` sub-array, and merges it back.

### Pitfall 4: Section Ordering Saved as Non-Sequential Array

**What goes wrong:** After drag-and-drop and form save, `section_order` is stored as `['0' => 'header', '2' => 'line_items', ...]` with gaps because array indices weren't reset.
**Why it happens:** HTML input arrays with numeric brackets can submit with non-contiguous indexes if JavaScript does not renumber them correctly.
**How to avoid:** In the JS `onEnd` handler, always renumber input `name` attributes from 0 upward after every drop. In the PHP sanitizer, call `array_values()` on the received array to reset keys.

### Pitfall 5: Email HTML Body Blocked by Email Client

**What goes wrong:** The HTML email body renders correctly in browser preview but arrives stripped or broken in Gmail/Outlook.
**Why it happens:** Email clients strip `<style>` blocks, `class` attributes (for external CSS), and many HTML5 elements.
**How to avoid:** Write all email body styles as inline `style=""` attributes. Use only `<table>`, `<tr>`, `<td>`, `<p>`, `<strong>`, `<a>` — avoid `<div>`, `<section>`, `<header>`. Test with the Litmus checklist or equivalent.

### Pitfall 6: Payment Method Conditional Bank Section

**What goes wrong:** Bank/IBAN section always shows or never shows, regardless of selected payment method.
**Why it happens:** The section visibility condition is evaluated at PHP render time using the invoice's `_invoice_payment_method` meta value. If this meta key is not saved when the invoice is created/edited, the condition falls back incorrectly.
**How to avoid:** Add `_invoice_payment_method` to `InvoicePostType`'s save fields list and to `PdfService::getInvoiceData()`. In the template: `if ($invoice['payment_method'] === 'Bank transfer') { // render bank section }`. The comparison string must exactly match the stored value — use constants or the configured list.

---

## Code Examples

Verified patterns from existing codebase and official sources:

### Existing PdfService Template Include Pattern (to extend)

```php
// Source: src/Services/PdfService.php (existing, lines 99-102)
ob_start();
include $template_file;
$html = ob_get_clean();
```

Extend by passing context variables:

```php
// Extended version
$template_vars = $this->getTemplateContext($invoice_id, $render_mode);
ob_start();
extract($template_vars, EXTR_SKIP);
include $template_file;
$html = ob_get_clean();
```

### Existing Settings Merge Pattern (to extend for template tab)

```php
// Source: src/Admin/Pages/SettingsPage.php (existing, lines 821-828)
$current   = get_option(self::OPTION_NAME, []);
$sanitized = array_merge($this->getDefaults(), $current);
$active_tab = $this->determineActiveTabFromInput($input);
$tab_fields = self::TAB_FIELDS[$active_tab] ?? [];
```

Add to `TAB_FIELDS`:
```php
'template' => [
    '_template_tab_marker',   // hidden sentinel input
    // All template sub-keys handled as a block in sanitizeField()
],
```

### WordPress File Upload to Custom Directory

```php
// Source: https://developer.wordpress.org/reference/functions/wp_handle_upload/
// Pattern: use upload_dir filter + wp_handle_upload
$upload_dir_filter = function(array $dirs): array {
    $target = WP_CONTENT_DIR . '/uploads/invoiceforge';
    wp_mkdir_p($target);
    $dirs['path']   = $target;
    $dirs['url']    = content_url('uploads/invoiceforge');
    $dirs['subdir'] = '';
    return $dirs;
};
add_filter('upload_dir', $upload_dir_filter);

$result = wp_handle_upload(
    $_FILES['template_logo'],
    ['test_form' => false, 'mimes' => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']]
);

remove_filter('upload_dir', $upload_dir_filter);

if (!isset($result['error'])) {
    $logo_path = $result['file'];  // absolute filesystem path → for mPDF
    $logo_url  = $result['url'];   // public URL → for email + admin preview
}
```

### SortableJS Initialization (admin.js pattern — extend existing module)

```javascript
// Extend InvoiceForgeAdmin.init() in assets/admin/js/admin.js
initSectionEditor: function() {
    const list = document.getElementById('if-section-order-list');
    if (!list || typeof Sortable === 'undefined') return;

    Sortable.create(list, {
        animation: 150,
        handle: '.if-drag-handle',
        onEnd: function() {
            list.querySelectorAll('.if-section-item').forEach(function(item, idx) {
                const input = item.querySelector('input[type="hidden"]');
                if (input) {
                    input.name = 'invoiceforge_settings[template][section_order][' + idx + ']';
                }
            });
        }
    });
},
```

### mPDF Section with Accent Color Variable

```php
// In templates/pdf/invoice-default.php (new template)
// $accent_color is extracted from template context
$safe_color = preg_match('/^#[0-9a-fA-F]{3,6}$/', $accent_color ?? '') ? $accent_color : '#1a2b4a';
?>
<table width="100%" style="border-collapse:collapse; table-layout:fixed;">
  <tr>
    <td width="38%" style="vertical-align:top; padding:5px; border:1px solid #ccc;">
      <!-- BUYER -->
    </td>
    <td width="24%" style="vertical-align:top; text-align:center; padding:5px;
                            background-color:<?php echo $safe_color; ?>; color:#fff;
                            border:1px solid <?php echo $safe_color; ?>;">
      <strong><?php echo esc_html($invoice['number']); ?></strong><br>
      <?php echo esc_html($invoice['date']); ?>
    </td>
    <td width="38%" style="vertical-align:top; text-align:right; padding:5px; border:1px solid #ccc;">
      <!-- SELLER + logo -->
    </td>
  </tr>
</table>
```

### Conditional Discount Columns in Line Items Table

```php
// Determine whether any line item has a discount before rendering the table
$has_discount = array_filter($invoice['line_items'], fn($item) => !empty($item['discount_pct']) && (float)$item['discount_pct'] > 0);

// In table header
if ($has_discount) {
    echo '<th>Disc %</th><th>Disc</th>';
}

// In each row
foreach ($invoice['line_items'] as $item) {
    echo '<td>' . esc_html($item['description']) . '</td>';
    // ... qty, unit price ...
    if ($has_discount) {
        echo '<td>' . esc_html($item['discount_pct'] ?? '0') . '%</td>';
        echo '<td>' . esc_html(number_format((float)($item['discount_amount'] ?? 0), 2)) . '</td>';
    }
    echo '<td>' . esc_html(number_format((float)($item['total'] ?? 0), 2)) . '</td>';
}
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|---|---|---|
| Hardcoded fallback HTML in `getFallbackHtml()` | Dedicated configurable PHP template with mode flag | Full document structure; no more emergency fallback for production |
| `company_logo` stored as WP media attachment ID (existing settings) | Phase 3 logo stored as filesystem path (separate from attachment ID) | Resolves mPDF path vs URL mismatch; independent of WP media library |
| Email body as plain text in `EmailService::sendInvoice()` | HTML body built from template with `Content-Type: text/html` | Enables styled email with line items summary and CTA button |
| No payment method field on invoice | `_invoice_payment_method` meta + configurable list in settings | Drives Bank/IBAN section auto-visibility |

**Deprecated/outdated in this phase:**
- `templates/pdf/invoice-default.php` (current placeholder): replaced entirely; do not extend it, write a new file.
- Plain-text email body in `EmailService::sendInvoice()`: replaced by HTML body from template render.

---

## Open Questions

1. **Line item discount fields in existing data model**
   - What we know: `InvoicePostType` saves `_invoice_discount_type` and `_invoice_discount_value` (document-level). The `line_items` table columns are managed by `LineItemRepository` / `LineItem` model.
   - What's unclear: Whether `LineItem` model already has per-item `discount_pct` and `discount_amount` columns in the DB table, or whether Phase 3 needs to add them.
   - Recommendation: Read `src/Models/LineItem.php` and `src/Repositories/LineItemRepository.php` before writing the line-items section plan. If columns don't exist, a DB migration is required in Wave 0.

2. **Invoice editor UI for payment method field**
   - What we know: There is no `_invoice_payment_method` field in the current invoice editor (`templates/admin/invoice-editor.php`).
   - What's unclear: Whether Phase 3 should add the dropdown to the invoice editor UI (affecting the invoice creation UX), or only read from a default/fallback.
   - Recommendation: Phase 3 should add the `payment_method` dropdown to the invoice editor — the Bank/IBAN visibility is only meaningful if the field can be set per invoice.

3. **Existing `company_logo` setting (attachment ID) vs new `logo_path` (file path)**
   - What we know: `SettingsPage` already has a `company_logo` field (attachment ID, via WP media library). The Phase 3 decision requires a separate dedicated file input NOT using WP media library.
   - What's unclear: Whether to reuse `company_logo` and change its storage format (breaking change), or add a new `template[logo_path]` key.
   - Recommendation: Add new `template[logo_path]` and `template[logo_url]` keys. Leave `company_logo` (attachment ID) intact for backward compat. The template uses `template[logo_path]` for PDF rendering.

---

## Validation Architecture

> `workflow.nyquist_validation` not present in `.planning/config.json` — treating as enabled.

### Test Framework

| Property | Value |
|---|---|
| Framework | None detected — InvoiceForge has no test suite currently (0% coverage per STATE.md) |
| Config file | None — Wave 0 must create |
| Quick run command | `./vendor/bin/phpunit --filter Phase3 --no-coverage` (after setup) |
| Full suite command | `./vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map

| Behavior | Test Type | Notes |
|---|---|---|
| Template context array contains required keys (number, date, client, line_items, etc.) | Unit | Test `PdfService::getTemplateContext()` return shape |
| Email render mode returns HTML string, not mPDF binary | Unit | Assert `render_mode='email'` returns string with `<table>` markup |
| Logo path stored correctly after upload | Integration | Mock `$_FILES` + `wp_handle_upload`; assert settings key set |
| Accent color hex validated before storage | Unit | Test `sanitizeField('accent_color', ...)` rejects invalid strings |
| Section order array values reset to 0-indexed after save | Unit | Test sanitize callback calls `array_values()` |
| Payment method saved to invoice meta | Integration | Save invoice with payment_method, assert `get_post_meta` returns it |
| Discount columns conditionally rendered | Unit | Template output with/without discount items; assert column presence |
| Bank section shown only for Bank transfer | Unit | Template rendered with payment_method='Bank transfer' vs 'Cash'; assert section HTML |

### Wave 0 Gaps

- [ ] `tests/Unit/Services/PdfServiceTest.php` — covers template context and render modes
- [ ] `tests/Unit/Admin/SettingsPageTest.php` — covers sanitize logic for template tab
- [ ] `tests/bootstrap.php` — WordPress test bootstrap (WP_Mock or Brain\Monkey)
- [ ] Framework install: `composer require --dev phpunit/phpunit brain/monkey wp-coding-standards/wpcs`
- [ ] `phpunit.xml` — test suite configuration

---

## Sources

### Primary (HIGH confidence)
- Existing codebase: `src/Services/PdfService.php`, `src/Services/EmailService.php`, `src/Admin/Pages/SettingsPage.php`, `src/PostTypes/ClientPostType.php`, `assets/admin/js/admin.js` — direct code read
- `.planning/phases/03-advanced-templates/03-CONTEXT.md` — authoritative user decisions
- [mPDF Tables documentation](https://mpdf.github.io/tables/tables.html) — table layout rules
- [mPDF Table Layout](https://mpdf.github.io/tables/table-layout.html) — auto-layout vs fixed
- [mPDF Images](https://mpdf.github.io/what-else-can-i-do/images.html) — imageVars and path handling
- [WordPress `wp_handle_upload()`](https://developer.wordpress.org/reference/functions/wp_handle_upload/) — file upload API
- [WordPress `wp_upload_dir()`](https://developer.wordpress.org/reference/functions/wp_upload_dir/) — upload directory control

### Secondary (MEDIUM confidence)
- [SortableJS GitHub](https://github.com/SortableJS/Sortable) — API reference and CDN options
- [SortableJS npm](https://www.npmjs.com/package/sortablejs) — version and install info
- [EU VAT Directive mandatory invoice fields (Taxually)](https://www.taxually.com/blog/eu-vat-invoice-requirements-for-businesses) — confirmed against European Commission source
- [mPDF imageVars Discussion](https://github.com/mpdf/mpdf/discussions/1743) — base64 vs path approach

### Tertiary (LOW confidence — flag for validation)
- WordPress support thread on custom upload directory keeping files out of media library — describes `upload_dir` filter approach; pattern is well-known but test against actual WP version in use

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — stack is locked by user decisions; codebase confirms versions in use
- Architecture patterns: HIGH — derived directly from existing codebase patterns + official mPDF docs
- Pitfalls: HIGH (mPDF layout), MEDIUM (email client compat) — mPDF limits confirmed via official docs; email client behavior is well-documented community knowledge
- Validation: MEDIUM — no test infrastructure currently exists; Wave 0 setup is speculative until framework choice confirmed

**Research date:** 2026-03-19
**Valid until:** 2026-06-19 (stable stack; mPDF 8.x, SortableJS 1.x, WordPress 6.x are all stable with long support windows)
