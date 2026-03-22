---
phase: quick-7
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Services/PdfService.php
  - src/Ajax/InvoiceAjaxHandler.php
  - templates/admin/invoice-editor.php
  - assets/admin/js/admin.js
  - assets/admin/css/admin.css
autonomous: false
requirements: [QUICK-7]

must_haves:
  truths:
    - "New invoice editor shows live preview panel (not just 'Save first' placeholder)"
    - "Preview renders from form data alone when invoice_id is 0 (unsaved)"
    - "After saving a new invoice, preview continues working with the real invoice_id"
    - "Email/PDF toggle buttons appear in preview panel header"
    - "Clicking Email toggle renders email-mode HTML (inline styles, no mPDF fonts)"
    - "Clicking PDF toggle renders pdf-mode HTML (default, current behavior)"
    - "Existing invoice preview continues to work exactly as before"
  artifacts:
    - path: "src/Services/PdfService.php"
      provides: "renderPreviewHtml supports invoice_id=0 with form-only data; accepts render_mode parameter"
      contains: "renderPreviewHtml"
    - path: "src/Ajax/InvoiceAjaxHandler.php"
      provides: "previewInvoiceHtml allows invoice_id=0; passes render_mode to PdfService"
      contains: "previewInvoiceHtml"
    - path: "templates/admin/invoice-editor.php"
      provides: "Preview panel always rendered (not gated by is_new); email/pdf toggle buttons"
      contains: "invoiceforge-preview-panel"
    - path: "assets/admin/js/admin.js"
      provides: "initInvoicePreview works for new invoices; toggle sends render_mode param"
      contains: "render_mode"
    - path: "assets/admin/css/admin.css"
      provides: "Toggle button styles in preview header"
      contains: "invoiceforge-preview-toggle"
  key_links:
    - from: "assets/admin/js/admin.js"
      to: "src/Ajax/InvoiceAjaxHandler.php"
      via: "AJAX invoiceforge_preview_invoice_html with render_mode param"
      pattern: "render_mode"
    - from: "src/Ajax/InvoiceAjaxHandler.php"
      to: "src/Services/PdfService.php"
      via: "renderPreviewHtml($invoice_id, $form_overrides, $render_mode)"
      pattern: "renderPreviewHtml.*render_mode"
    - from: "templates/admin/invoice-editor.php"
      to: "assets/admin/js/admin.js"
      via: "Preview panel HTML always present, JS initializes for both new and existing"
      pattern: "invoiceforge-preview-panel"
---

<objective>
Fix the invoice preview panel so it works for NEW (unsaved) invoices and add an email/PDF preview mode toggle.

Currently, the preview panel is hidden for new invoices (`$is_new` gate in PHP, `invoice_id <= 0` gate in JS) and `renderPreviewHtml` requires a valid invoice_id to fetch from DB. This plan:
1. Makes preview work for new invoices by building invoice data entirely from form fields when invoice_id is 0
2. Adds email/PDF toggle buttons so users can switch between render modes in the preview

Purpose: Users need to see what their invoice looks like BEFORE saving, especially for new invoices. The email/PDF toggle lets them verify both output formats.
Output: Modified PHP backend (PdfService, AjaxHandler), template, JS, and CSS.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/6-add-invoice-preview-in-editor-and-extend/6-SUMMARY.md

<interfaces>
<!-- Key contracts from Quick-6 that this plan modifies -->

From src/Services/PdfService.php:
```php
public function renderPreviewHtml(int $invoice_id, array $form_overrides = []): string
// Currently: calls getInvoiceData($invoice_id) which returns null for id=0
// getTemplateContext(int $invoice_id, string $render_mode, ?array $invoice_data_override = null): array
```

From src/Ajax/InvoiceAjaxHandler.php:
```php
public function previewInvoiceHtml(): void
// Currently: rejects invoice_id <= 0 with 400 error
// Collects form_overrides from $_POST, delegates to PdfService::renderPreviewHtml()
```

From templates/admin/invoice-editor.php:
```php
// Line 107: class toggles between 'invoiceforge-editor-layout' (new) and 'invoiceforge-editor-with-preview' (existing)
// Line 480: <?php if (!$is_new) : ?> gates the entire preview panel
```

From assets/admin/js/admin.js:
```javascript
// initInvoicePreview(): bails early if !invoiceId or parseInt(invoiceId) <= 0
// loadPreview(): also bails if invoiceId <= 0
// Both send invoice_id in AJAX data
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend - Support preview for new invoices and render_mode parameter</name>
  <files>src/Services/PdfService.php, src/Ajax/InvoiceAjaxHandler.php</files>
  <action>
**PdfService.php - renderPreviewHtml:**
- Change signature to: `renderPreviewHtml(int $invoice_id, array $form_overrides = [], string $render_mode = 'email'): string`
- When `$invoice_id > 0`: keep existing behavior (fetch from DB, merge overrides)
- When `$invoice_id === 0`: skip `getInvoiceData()` call entirely. Instead, build a minimal invoice array from `$form_overrides` alone:
  ```php
  $invoice = [
      'id' => 0,
      'title' => $form_overrides['title'] ?? '',
      'number' => __('(Not yet assigned)', 'invoiceforge'),
      'date' => $form_overrides['invoice_date'] ?? current_time('Y-m-d'),
      'due_date' => $form_overrides['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
      'status' => $form_overrides['status'] ?? 'draft',
      'currency' => $form_overrides['currency'] ?? 'USD',
      'payment_method' => $form_overrides['payment_method'] ?? '',
      'discount_type' => $form_overrides['discount_type'] ?? '',
      'discount_value' => (float)($form_overrides['discount_value'] ?? 0),
      'notes' => $form_overrides['notes'] ?? '',
      'terms' => $form_overrides['terms'] ?? '',
      'total_amount' => 0,
      'subtotal' => 0,
      'tax_total' => 0,
      'line_items' => [],
      'client_name' => '',
      'client_email' => '',
      'client_address' => '',
      'client_city' => '',
      'client_state' => '',
      'client_zip' => '',
      'client_country' => '',
      'client_phone' => '',
      'client_id_no' => '',
      'client_office' => '',
      'client_att_to' => '',
  ];
  ```
- After building the base invoice (either from DB or form-only), the existing client_id resolution and line_items recalculation logic applies unchanged.
- Pass `$render_mode` to `getTemplateContext()` instead of hardcoded `'email'` on line 274. Change: `$this->getTemplateContext($invoice_id, $render_mode, $invoice)`

**InvoiceAjaxHandler.php - previewInvoiceHtml:**
- Change the invoice_id validation: allow `$invoice_id === 0` (remove the `<= 0` rejection). Keep `absint` sanitization.
- Add render_mode parameter: `$render_mode = isset($_POST['render_mode']) && $_POST['render_mode'] === 'pdf' ? 'pdf' : 'email';`
- Pass render_mode to PdfService: `$pdfService->renderPreviewHtml($invoice_id, $form_overrides, $render_mode)`
- For `$invoice_id === 0` case: still require nonce + capability check (unchanged).
  </action>
  <verify>
    <automated>php -l src/Services/PdfService.php && php -l src/Ajax/InvoiceAjaxHandler.php</automated>
  </verify>
  <done>renderPreviewHtml builds invoice data from form fields when invoice_id=0; previewInvoiceHtml accepts invoice_id=0 and passes render_mode to PdfService; both files pass PHP lint</done>
</task>

<task type="auto">
  <name>Task 2: Frontend - Show preview for new invoices with email/PDF toggle</name>
  <files>templates/admin/invoice-editor.php, assets/admin/js/admin.js, assets/admin/css/admin.css</files>
  <action>
**templates/admin/invoice-editor.php:**
- Line 107: Always use `invoiceforge-editor-with-preview` class (remove the `$is_new` ternary). The 3-column grid should apply to both new and existing invoices.
- Line 480: Remove the `<?php if (!$is_new) : ?>` and `<?php endif; ?>` gates around the preview panel. The preview panel should ALWAYS render.
- Add email/PDF toggle buttons inside the `.invoiceforge-card-header` of the preview panel, between the title and status span:
  ```html
  <div class="invoiceforge-preview-toggles">
      <button type="button" class="invoiceforge-preview-toggle active" data-mode="email" title="<?php esc_attr_e('Email Preview', 'invoiceforge'); ?>">
          <?php esc_html_e('Email', 'invoiceforge'); ?>
      </button>
      <button type="button" class="invoiceforge-preview-toggle" data-mode="pdf" title="<?php esc_attr_e('PDF Preview', 'invoiceforge'); ?>">
          <?php esc_html_e('PDF', 'invoiceforge'); ?>
      </button>
  </div>
  ```

**assets/admin/js/admin.js - initInvoicePreview:**
- Remove the early return for `invoiceId <= 0`. The preview should initialize regardless of invoice_id value.
- Store the current render mode: `this._previewRenderMode = 'email';`
- Bind click on `.invoiceforge-preview-toggle` buttons:
  ```javascript
  var self = this;
  $(document).on('click', '.invoiceforge-preview-toggle', function(e) {
      e.preventDefault();
      var $btn = $(this);
      if ($btn.hasClass('active')) return;
      $('.invoiceforge-preview-toggle').removeClass('active');
      $btn.addClass('active');
      self._previewRenderMode = $btn.data('mode');
      self.loadPreview();
  });
  ```

**assets/admin/js/admin.js - loadPreview:**
- Remove the early return for `invoiceId <= 0`. When invoiceId is 0 or empty, still proceed (send `invoice_id: 0`).
- Add `render_mode: this._previewRenderMode || 'email'` to the AJAX data object.
- After a successful save that returns a new invoice_id (in `handleInvoiceSave`), update the hidden `invoice_id` field value. This is likely already handled but verify: `$('[name="invoice_id"]').val()` should reflect the new ID after save.

**assets/admin/js/admin.js - handleInvoiceSave (verify existing behavior):**
- After successful save, the response should contain the new invoice_id. Check that the hidden field is updated. If not, add: `$('[name="invoice_id"]').val(response.invoice_id);` in the success callback before `loadPreview()`.

**assets/admin/css/admin.css:**
- Add toggle button styles:
  ```css
  .invoiceforge-preview-toggles {
      display: flex;
      gap: 0;
      border: 1px solid #c3c4c7;
      border-radius: 3px;
      overflow: hidden;
  }
  .invoiceforge-preview-toggle {
      padding: 2px 10px;
      font-size: 12px;
      border: none;
      background: #f0f0f1;
      color: #50575e;
      cursor: pointer;
      line-height: 1.6;
  }
  .invoiceforge-preview-toggle:not(:last-child) {
      border-right: 1px solid #c3c4c7;
  }
  .invoiceforge-preview-toggle.active {
      background: #2271b1;
      color: #fff;
  }
  .invoiceforge-preview-toggle:hover:not(.active) {
      background: #e0e0e0;
  }
  ```
- In the `.invoiceforge-card-header` of preview panel, ensure flex layout accommodates the toggle group between title and status.
  </action>
  <verify>
    <automated>php -l templates/admin/invoice-editor.php && node -e "try { require('assets/admin/js/admin.js'); } catch(e) { if(e instanceof SyntaxError) { console.error(e.message); process.exit(1); } }" 2>/dev/null; echo "JS check done"</automated>
  </verify>
  <done>Preview panel renders for both new and existing invoices; email/PDF toggle buttons visible in preview header; clicking toggle reloads preview with selected render_mode; new invoice form shows live preview from form data; after save, preview seamlessly transitions to using the real invoice_id</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify preview for new invoices and email/PDF toggle</name>
  <files>n/a</files>
  <action>
    Human verifies the following on the live WordPress site:
    1. Go to InvoiceForge > Add New Invoice in WordPress admin
    2. Verify the preview panel appears on the right (3-column layout, not hidden)
    3. Fill in some fields (client, dates, add a line item) — preview should update live with 500ms debounce
    4. Verify the invoice number shows "(Not yet assigned)" in the preview
    5. Check the Email/PDF toggle buttons in the preview header — "Email" should be active (blue) by default
    6. Click "PDF" toggle — preview should reload with PDF render mode
    7. Click "Email" toggle — back to email mode
    8. Save the invoice — verify preview continues working after save (now with real invoice_id)
    9. Go to an existing invoice — verify preview still works as before (no regression)
    10. Test responsive: at less than 1400px width, preview should collapse below the form
  </action>
  <verify>Human confirmation</verify>
  <done>User types "approved" confirming all 10 verification steps pass</done>
</task>

</tasks>

<verification>
- PHP lint passes on all modified PHP files
- JS has no syntax errors
- New invoice editor shows preview panel with live updates
- Existing invoice editor preview unchanged (no regression)
- Email/PDF toggle switches render mode
- Preview works before and after first save of a new invoice
</verification>

<success_criteria>
- New invoice page shows live preview panel (not placeholder text)
- Preview renders correctly from form-only data (no DB record needed)
- Email/PDF toggle buttons visible and functional
- Toggle state persists across preview refreshes (until page reload)
- Existing invoice preview behavior unchanged
</success_criteria>

<output>
After completion, create `.planning/quick/7-fix-invoice-preview-show-for-new-invoice/7-SUMMARY.md`
</output>
