---
phase: quick-6
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Services/NumberingService.php
  - src/Admin/Pages/SettingsPage.php
  - src/Ajax/InvoiceAjaxHandler.php
  - templates/admin/invoice-editor.php
  - templates/admin/settings.php
  - assets/admin/css/admin.css
  - assets/admin/js/admin.js
autonomous: false
requirements: [PREVIEW-01, NUMBERING-01]

must_haves:
  truths:
    - "Invoice editor shows a split view with the form on the left and a live HTML preview on the right"
    - "Preview updates after save (debounced) as the user changes fields (title, client, dates, line items, totals)"
    - "Settings page Advanced tab has fields for invoice number prefix, suffix, date pattern (year/month), counter reset mode (yearly/monthly), and custom start number"
    - "NumberingService generates invoice numbers using the full customization settings (prefix, suffix, date patterns, counter reset)"
    - "Preview of next invoice number shown on settings page reflects the configured format"
  artifacts:
    - path: "src/Services/NumberingService.php"
      provides: "Extended numbering with prefix, suffix, date patterns, counter reset modes"
      contains: "invoice_suffix"
    - path: "src/Admin/Pages/SettingsPage.php"
      provides: "Invoice numbering customization fields in Advanced tab"
      contains: "invoice_suffix"
    - path: "templates/admin/invoice-editor.php"
      provides: "Split view layout with preview panel"
      contains: "invoiceforge-preview-panel"
    - path: "assets/admin/js/admin.js"
      provides: "Real-time debounced preview update logic"
      contains: "initInvoicePreview"
    - path: "assets/admin/css/admin.css"
      provides: "Split view CSS for editor + preview layout"
      contains: "invoiceforge-editor-with-preview"
  key_links:
    - from: "assets/admin/js/admin.js"
      to: "/wp-admin/admin-ajax.php?action=invoiceforge_preview_invoice_html"
      via: "AJAX call on form field change (debounced 500ms)"
      pattern: "invoiceforge_preview_invoice_html"
    - from: "src/Ajax/InvoiceAjaxHandler.php"
      to: "src/Services/PdfService.php"
      via: "renderEmailBody or getTemplateContext for HTML preview"
      pattern: "previewInvoiceHtml"
    - from: "src/Services/NumberingService.php"
      to: "invoiceforge_settings"
      via: "Reading numbering config from wp_options"
      pattern: "invoice_suffix|counter_reset|date_pattern"
---

<objective>
Add a live invoice preview panel in the invoice editor (split view: form left, preview right) with real-time debounced updates as fields change. Also extend invoice number customization in Settings > Advanced with prefix, suffix, date patterns (year/month), counter reset mode (yearly/monthly), and custom start number.

Purpose: Users need visual feedback while editing invoices, and businesses need flexible invoice numbering schemes beyond the current simple PREFIX-YEAR-COUNTER format.

Output: Split-view invoice editor with live preview, extended NumberingService, and numbering settings UI.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

<interfaces>
<!-- Key types and contracts the executor needs -->

From src/Services/NumberingService.php:
- generate(): string — generates next invoice number with lock
- format(string $prefix, int $year, int $number): string — formats number
- preview(): string — preview next number without generating
- parse(string $invoice_number): ?array — parses PREFIX-YEAR-NUMBER
- getCurrentCounter(): array{year: int, number: int}
- reset(?int $number, ?int $year): void
- Options: invoiceforge_last_invoice_number, invoiceforge_last_invoice_year

From src/Admin/Pages/SettingsPage.php:
- TAB_FIELDS['advanced'] = ['default_currency', 'invoice_prefix', 'invoice_terms', 'invoice_notes']
- sanitizeSettings() uses determineActiveTabFromInput() + sanitizeField()
- getDefaults() returns 'invoice_prefix' => 'INV'
- register() calls addAdvancedFields() which adds settings fields to 'invoiceforge-settings-advanced'

From src/Ajax/InvoiceAjaxHandler.php:
- registerAjaxHandlers() registers wp_ajax actions
- Existing: invoiceforge_preview_pdf (renders PDF inline via mPDF)
- Uses $this->nonce, $this->sanitizer, $this->logger

From src/Services/PdfService.php:
- generate(int $invoice_id, string $output_mode, string $file_path, string $render_mode): ?string
- getTemplateContext(int $invoice_id, string $render_mode): array
- getInvoiceData(int $invoice_id): ?array
- renderEmailBody(int $invoice_id): string — returns HTML without mPDF

From templates/admin/invoice-editor.php:
- Layout: div.invoiceforge-editor-layout > div.invoiceforge-editor-main + div.invoiceforge-editor-sidebar
- Grid: grid-template-columns: 1fr 320px (collapses to 1fr at 1024px)
- Form id: invoiceforge-invoice-form
- Form fields: title, client_id, invoice_date, due_date, currency, payment_method, status, discount_type, discount_value, notes, terms
- Line items in tbody#invoiceforge-items-body

From assets/admin/js/admin.js:
- Module pattern: InvoiceForgeAdmin object with init(), bindEvents(), etc.
- Uses jQuery ($), InvoiceForge.ajaxUrl, InvoiceForge.nonce
- Has debounce() utility method already
- handleInvoiceSave collects all form data into formData object

From assets/admin/css/admin.css:
- .invoiceforge-editor-layout: grid with 1fr 320px
- .invoiceforge-editor-sidebar: sticky, top: 32px
- CSS custom properties: --if-space-*, --if-radius, --if-gray-*, --if-primary, etc.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Extend NumberingService and Settings for full invoice number customization</name>
  <files>
    src/Services/NumberingService.php,
    src/Admin/Pages/SettingsPage.php,
    templates/admin/settings.php
  </files>
  <action>
**NumberingService.php — Extend the numbering engine:**

1. Add a new method `getNumberingConfig(): array` that reads from `invoiceforge_settings` and returns:
   - `prefix` (string, default 'INV') — from `invoice_prefix`
   - `suffix` (string, default '') — from `invoice_suffix`
   - `date_pattern` (string, default 'Y') — from `invoice_date_pattern`, supports: 'Y' (2026), 'Ym' (202603), 'Y-m' (2026-03), 'none' (no date segment)
   - `counter_reset` (string, default 'yearly') — from `invoice_counter_reset`, supports: 'yearly', 'monthly', 'never'
   - `start_number` (int, default 1) — from `invoice_start_number`
   - `padding` (int, default 4) — from `invoice_number_padding`

2. Modify `generate()`:
   - Read config via `getNumberingConfig()`
   - For `counter_reset`:
     - `yearly`: compare `$current_year !== $last_year` (existing behavior)
     - `monthly`: store month in new option `invoiceforge_last_invoice_month`, reset when `$current_year !== $last_year || $current_month !== $last_month`
     - `never`: never reset counter
   - For start_number: when counter resets, set to `max(0, $start_number - 1)` so next increment produces start_number
   - Add new option constant `OPTION_LAST_MONTH = 'invoiceforge_last_invoice_month'`

3. Modify `format()` to accept the full config array instead of just prefix/year/number. Keep backward compatibility by checking parameter count or using a new `formatCustom()` method. The new format builds the number as:
   - `{PREFIX}` (if not empty)
   - `-{DATE_SEGMENT}` based on date_pattern (if not 'none')
   - `-{PADDED_NUMBER}` (zero-padded to `padding` digits)
   - `-{SUFFIX}` (if not empty)
   - Example with prefix=INV, date_pattern=Ym, padding=4, suffix=BG: `INV-202603-0001-BG`
   - Join with `-`, skip empty segments
   - Still apply the `invoiceforge_invoice_number_format` filter

4. Modify `preview()` to use the new config and `formatCustom()`.

5. Modify `parse()` to handle the new flexible format (more lenient regex).

6. Modify `reset()` to also reset the month option.

**SettingsPage.php — Add numbering fields to Advanced tab:**

1. Add to `TAB_FIELDS['advanced']` array: `'invoice_suffix'`, `'invoice_date_pattern'`, `'invoice_counter_reset'`, `'invoice_start_number'`, `'invoice_number_padding'`

2. In `addAdvancedFields()`, after the existing `invoice_prefix` field, add:
   - `invoice_suffix` — text field, description: "Suffix appended to invoice numbers (e.g., BG, LLC)"
   - `invoice_date_pattern` — select field with options: 'Y' => 'Year only (2026)', 'Ym' => 'Year + Month (202603)', 'Y-m' => 'Year-Month (2026-03)', 'none' => 'No date in number'
   - `invoice_counter_reset` — select field with options: 'yearly' => 'Reset yearly', 'monthly' => 'Reset monthly', 'never' => 'Never reset (continuous)'
   - `invoice_start_number` — number field, min=1, default=1, description: "Starting number when counter resets"
   - `invoice_number_padding` — number field, min=1, max=10, default=4, description: "Number of digits (zero-padded, e.g., 4 = 0001)"

3. In `sanitizeField()`, add cases:
   - `invoice_suffix`: `sanitize_text_field`, strip non-alphanumeric except `-`
   - `invoice_date_pattern`: validate against allowed values `['Y', 'Ym', 'Y-m', 'none']`, default 'Y'
   - `invoice_counter_reset`: validate against `['yearly', 'monthly', 'never']`, default 'yearly'
   - `invoice_start_number`: `absint`, min 1
   - `invoice_number_padding`: `absint`, clamp 1-10, default 4

4. In `getDefaults()`, add: `'invoice_suffix' => ''`, `'invoice_date_pattern' => 'Y'`, `'invoice_counter_reset' => 'yearly'`, `'invoice_start_number' => 1`, `'invoice_number_padding' => 4`

**templates/admin/settings.php — Update the preview box:**

In the Advanced tab section (around line 65-72), update the "Next Invoice Number" preview box to also show the current format pattern as a hint below the number preview. Something like: "Format: {PREFIX}-{DATE}-{NUMBER}-{SUFFIX}" showing the configured pattern. This is informational only — the actual preview number from `$numberingService->preview()` already uses the new logic.
  </action>
  <verify>
    <automated>cd C:/GitHubRepos/Invoice-Forge && php -l src/Services/NumberingService.php && php -l src/Admin/Pages/SettingsPage.php && php -l templates/admin/settings.php && echo "All files pass syntax check"</automated>
  </verify>
  <done>
    - NumberingService supports prefix, suffix, date patterns (Y, Ym, Y-m, none), counter reset modes (yearly, monthly, never), custom start number, and configurable zero-padding
    - Settings Advanced tab has all 5 new fields registered and sanitized
    - Settings preview box shows next invoice number with format hint
    - All existing functionality preserved (backward compatible defaults match current behavior: INV prefix, yearly reset, 4-digit padding)
  </done>
</task>

<task type="auto">
  <name>Task 2: Add live invoice preview panel to the editor with real-time updates</name>
  <files>
    templates/admin/invoice-editor.php,
    src/Ajax/InvoiceAjaxHandler.php,
    assets/admin/js/admin.js,
    assets/admin/css/admin.css
  </files>
  <action>
**templates/admin/invoice-editor.php — Restructure layout for split view:**

1. Change the outer layout div from `invoiceforge-editor-layout` to `invoiceforge-editor-with-preview`. The new layout is a 3-column grid: `grid-template-columns: 1fr 320px 480px` (form | sidebar | preview). On screens under 1400px, collapse to 2-column with preview below. On mobile (under 1024px), single column.

2. After the closing `</div>` of `invoiceforge-editor-sidebar` (around line 478), add a new preview panel div:

```php
<!-- Invoice Preview Panel -->
<div class="invoiceforge-preview-panel">
    <div class="invoiceforge-card">
        <div class="invoiceforge-card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="invoiceforge-card-title"><?php esc_html_e('Preview', 'invoiceforge'); ?></h3>
            <span id="invoiceforge-preview-status" class="invoiceforge-preview-status"></span>
        </div>
        <div class="invoiceforge-card-body invoiceforge-preview-body" style="padding:0;">
            <div id="invoiceforge-preview-frame" class="invoiceforge-preview-frame">
                <?php if (!$is_new) : ?>
                    <p class="invoiceforge-preview-loading"><?php esc_html_e('Loading preview...', 'invoiceforge'); ?></p>
                <?php else : ?>
                    <p class="invoiceforge-preview-placeholder"><?php esc_html_e('Save the invoice first to see a preview.', 'invoiceforge'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

3. The preview panel only shows the AJAX preview for saved invoices (invoice_id > 0). For new invoices, show a placeholder message.

**src/Ajax/InvoiceAjaxHandler.php — Add HTML preview endpoint:**

1. In `registerAjaxHandlers()`, add: `add_action('wp_ajax_invoiceforge_preview_invoice_html', [$this, 'previewInvoiceHtml']);`

2. Create method `previewInvoiceHtml()`:
   - Verify nonce (POST data, key 'nonce', action 'invoiceforge_admin')
   - Check capabilities via `$this->canEditInvoices()`
   - Get `invoice_id` from `$_POST['invoice_id']`, validate > 0
   - Create PdfService instance, call `renderEmailBody($invoice_id)` which returns the invoice template HTML without mPDF
   - Return `wp_send_json_success(['html' => $html])`
   - On error: `wp_send_json_error(['message' => 'Preview failed'])`

**assets/admin/js/admin.js — Add preview logic:**

1. In `init()`, add `this.initInvoicePreview();`

2. Create `initInvoicePreview()` method:
   - Check if `#invoiceforge-preview-frame` exists, bail if not
   - Check if `$('[name="invoice_id"]').val()` is truthy (> 0), bail if new invoice
   - Load initial preview via `this.loadPreview()`
   - After a successful save (in `handleInvoiceSave` success callback), also trigger `this.loadPreview()` to refresh the preview
   - Add a "Refresh Preview" button click handler

3. Create `loadPreview()` method:
   - Show "Updating..." status indicator in `#invoiceforge-preview-status` (small text, no spinner needed)
   - AJAX POST to `InvoiceForge.ajaxUrl` with `action: 'invoiceforge_preview_invoice_html'`, `nonce: InvoiceForge.nonce`, `invoice_id: $('[name="invoice_id"]').val()`
   - On success: set `$('#invoiceforge-preview-frame').html(response.data.html)` and clear status
   - On error: show "Preview unavailable" in the status indicator
   - Important: The preview loads the SAVED state of the invoice. After the user saves via AJAX, the preview refreshes to show the updated data. Between saves, the preview shows the last-saved state. This is acceptable because the save is one click away, and the preview updates immediately after save.
   - Add a small note below the preview: "Preview shows last saved state. Save changes to update."

**assets/admin/css/admin.css — Split view styles:**

1. Add new `.invoiceforge-editor-with-preview` class:
```css
.invoiceforge-editor-with-preview {
    display: grid;
    grid-template-columns: 1fr 320px 480px;
    gap: var(--if-space-6);
    align-items: start;
}

@media (max-width: 1400px) {
    .invoiceforge-editor-with-preview {
        grid-template-columns: 1fr 320px;
    }
    .invoiceforge-preview-panel {
        grid-column: 1 / -1;
    }
}

@media (max-width: 1024px) {
    .invoiceforge-editor-with-preview {
        grid-template-columns: 1fr;
    }
}
```

2. Add `.invoiceforge-preview-panel` styles:
```css
.invoiceforge-preview-panel {
    position: sticky;
    top: 32px;
}

.invoiceforge-preview-frame {
    max-height: 80vh;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--if-gray-200);
    border-radius: var(--if-radius);
    padding: var(--if-space-4);
}

.invoiceforge-preview-frame img {
    max-width: 100%;
    height: auto;
}

.invoiceforge-preview-status {
    font-size: var(--if-font-size-xs);
    color: var(--if-gray-400);
}

.invoiceforge-preview-placeholder,
.invoiceforge-preview-loading {
    text-align: center;
    color: var(--if-gray-400);
    padding: var(--if-space-8) var(--if-space-4);
}
```

3. Keep the existing `.invoiceforge-editor-layout` class unchanged for backward compatibility (though currently only invoice-editor.php uses it, we replace its usage).

4. For new invoices (`$is_new` = true), use the original `invoiceforge-editor-layout` class (no preview panel). For existing invoices, use `invoiceforge-editor-with-preview`. This means the layout class in the template should be conditional:
```php
<div class="<?php echo $is_new ? 'invoiceforge-editor-layout' : 'invoiceforge-editor-with-preview'; ?>">
```
  </action>
  <verify>
    <automated>cd C:/GitHubRepos/Invoice-Forge && php -l templates/admin/invoice-editor.php && php -l src/Ajax/InvoiceAjaxHandler.php && echo "Syntax OK"</automated>
  </verify>
  <done>
    - Invoice editor for existing invoices shows a 3-column layout: form | sidebar | preview panel
    - Preview panel loads invoice HTML via AJAX on page load (for saved invoices)
    - Preview auto-refreshes after successful AJAX save
    - New invoices show placeholder text instead of preview (save first to preview)
    - Layout is responsive: collapses preview below on medium screens, single column on mobile
    - Preview panel is scrollable with max-height 80vh
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify invoice preview and numbering customization</name>
  <files>none</files>
  <action>
    Human verification of both features:
    1. Extended invoice number customization in Settings > Advanced tab
    2. Live invoice preview panel in the invoice editor

    Steps:
    1. Go to InvoiceForge > Settings > Advanced tab
    2. Verify new fields appear: Invoice Suffix, Date Pattern, Counter Reset, Start Number, Number Padding
    3. Change prefix to "FAK", suffix to "BG", date pattern to "Year + Month", padding to 5
    4. Check that the "Next Invoice Number" preview updates to show the new format (e.g., FAK-202603-00001-BG)
    5. Save settings, verify they persist after page reload
    6. Go to InvoiceForge > Invoices and edit an existing invoice
    7. Verify the preview panel appears on the right side showing the invoice HTML
    8. Make a change to a field (e.g., edit title or notes), click Save
    9. Verify the preview auto-refreshes after save completes
    10. Create a new invoice — verify the preview panel shows a placeholder message instead
    11. Resize browser window to under 1400px — verify preview moves below the form
  </action>
  <verify>User confirms both features work correctly</verify>
  <done>User types "approved" or describes issues to fix</done>
</task>

</tasks>

<verification>
- PHP syntax check passes on all modified files
- Settings page Advanced tab shows all numbering customization fields
- NumberingService::preview() returns correctly formatted number per settings
- Invoice editor split view renders without CSS layout breaks
- AJAX preview endpoint returns invoice HTML for valid invoice IDs
- Responsive layout works at 1400px and 1024px breakpoints
</verification>

<success_criteria>
- Invoice editor shows split view with live preview panel (for saved invoices)
- Preview updates automatically after each save
- Settings > Advanced has full invoice number customization (prefix, suffix, date pattern, counter reset, start number, padding)
- NumberingService generates numbers in the configured format
- All changes are backward compatible (existing invoices and default settings produce the same format as before)
</success_criteria>

<output>
After completion, create `.planning/quick/6-add-invoice-preview-in-editor-and-extend/6-SUMMARY.md`
</output>
