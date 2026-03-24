---
phase: quick-8
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Ajax/InvoiceAjaxHandler.php
  - assets/admin/js/admin.js
  - templates/admin/settings.php
autonomous: true
requirements: [QUICK-8]
must_haves:
  truths:
    - "Clicking Reset Invoice Counter in Settings > Advanced resets the counter to start_number via AJAX"
    - "User can manually set the invoice counter to any value via a number input in Settings > Advanced"
    - "Both actions show success/error toast feedback"
    - "Both actions are nonce-protected and require manage_options capability"
  artifacts:
    - path: "src/Ajax/InvoiceAjaxHandler.php"
      provides: "AJAX endpoints for reset_invoice_counter and set_invoice_counter"
    - path: "assets/admin/js/admin.js"
      provides: "JS handlers for reset button click and counter adjustment"
    - path: "templates/admin/settings.php"
      provides: "Manual counter adjustment UI in Danger Zone section"
  key_links:
    - from: "assets/admin/js/admin.js"
      to: "src/Ajax/InvoiceAjaxHandler.php"
      via: "jQuery AJAX to wp_ajax_invoiceforge_reset_invoice_counter and wp_ajax_invoiceforge_set_invoice_counter"
      pattern: "invoiceforge_reset_invoice_counter|invoiceforge_set_invoice_counter"
    - from: "src/Ajax/InvoiceAjaxHandler.php"
      to: "src/Services/NumberingService.php"
      via: "$this->numberingService->reset()"
      pattern: "numberingService->reset"
---

<objective>
Fix the broken "Reset Invoice Counter" button in Settings > Advanced (currently shows confirm dialog but does nothing) and add a manual counter adjustment input so users can set the counter to any specific value.

Purpose: The reset button is non-functional because no AJAX handler or JS callback exists beyond the confirm() dialog. Users also need to manually adjust the counter (e.g., after data migration or correcting numbering gaps).

Output: Working reset button with AJAX backend, plus a new "Set Counter" input field with its own AJAX endpoint.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

<interfaces>
<!-- Existing code contracts the executor needs -->

From src/Ajax/InvoiceAjaxHandler.php (AJAX pattern):
```php
// Nonce check pattern used by all handlers:
if (!$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)) {
    wp_send_json_error(['message' => __('Security check failed.', 'invoiceforge')], 403);
    return;
}

// Already has $this->numberingService (NumberingService instance) injected via constructor
// Already registers hooks in registerHooks() method via:
add_action('wp_ajax_invoiceforge_{action}', [$this, 'methodName']);
```

From src/Services/NumberingService.php:
```php
public function reset(?int $number = null, ?int $year = null): void;
// Resets counter. $number defaults to 0, $year defaults to current year.
// Also resets month to current month.

public function getCurrentCounter(): array;
// Returns ['year' => int, 'number' => int]

public function getNumberingConfig(): array;
// Returns ['prefix', 'suffix', 'date_pattern', 'counter_reset', 'start_number', 'padding']

public function preview(): string;
// Returns formatted next invoice number without consuming it
```

From assets/admin/js/admin.js (JS patterns):
```javascript
// Localized data available: InvoiceForge.ajaxUrl, InvoiceForge.nonce
// Toast: InvoiceForgeAdmin.showToast('success'|'error'|'warning', message);
// Confirm: handleConfirm binds to [data-confirm] elements
// Init binds in InvoiceForgeAdmin.init() method's bindEvents() call
```

From templates/admin/settings.php (Danger Zone at line 392-407):
```php
// Existing reset button: id="reset-invoice-counter", has data-confirm attribute
// Danger Zone section only renders when $active_tab === 'advanced'
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add AJAX endpoints for reset and set counter</name>
  <files>src/Ajax/InvoiceAjaxHandler.php</files>
  <action>
In InvoiceAjaxHandler.php, add two new AJAX endpoints:

1. In the `registerHooks()` method, add after the existing `add_action` calls:
   ```php
   add_action('wp_ajax_invoiceforge_reset_invoice_counter', [$this, 'resetInvoiceCounter']);
   add_action('wp_ajax_invoiceforge_set_invoice_counter', [$this, 'setInvoiceCounter']);
   ```

2. Add `resetInvoiceCounter()` method:
   - Nonce check: `$this->nonce->checkAjaxReferer('invoiceforge_admin', 'nonce', false)` -- return 403 on failure
   - Capability check: `current_user_can('manage_options')` -- return 403 on failure
   - Get config: `$config = $this->numberingService->getNumberingConfig()`
   - Call: `$this->numberingService->reset($config['start_number'] - 1)` (so next generate() produces start_number)
   - Get new preview: `$preview = $this->numberingService->preview()`
   - Get new counter: `$counter = $this->numberingService->getCurrentCounter()`
   - Return success JSON: `['message' => __('Invoice counter has been reset.', 'invoiceforge'), 'preview' => $preview, 'counter' => $counter['number']]`

3. Add `setInvoiceCounter()` method:
   - Same nonce + capability checks
   - Read POST param: `$value = isset($_POST['counter_value']) ? absint($_POST['counter_value']) : null`
   - If null or 0: return error JSON `['message' => __('Please enter a valid counter value.', 'invoiceforge')]`
   - Call: `$this->numberingService->reset($value - 1)` (so next generate() produces $value)
   - Note: subtract 1 because generate() increments before use
   - Get new preview: `$preview = $this->numberingService->preview()`
   - Return success JSON: `['message' => sprintf(__('Invoice counter set to %d. Next invoice: %s', 'invoiceforge'), $value, $preview), 'preview' => $preview, 'counter' => $value]`
  </action>
  <verify>
    <automated>grep -n "resetInvoiceCounter\|setInvoiceCounter\|reset_invoice_counter\|set_invoice_counter" src/Ajax/InvoiceAjaxHandler.php</automated>
  </verify>
  <done>Both AJAX endpoints registered and implemented with nonce + capability checks, calling NumberingService::reset() with correct offset</done>
</task>

<task type="auto">
  <name>Task 2: Add JS handlers and counter adjustment UI</name>
  <files>assets/admin/js/admin.js, templates/admin/settings.php</files>
  <action>
**In templates/admin/settings.php** -- Replace the existing Danger Zone section (lines 392-407) with an expanded version:

After the existing reset button, add a "Set Invoice Counter" section within the same Danger Zone div:
```php
<!-- Keep existing reset button as-is -->

<div style="margin-top: var(--if-space-4); padding-top: var(--if-space-4); border-top: 1px solid var(--if-gray-200);">
    <label for="manual-counter-value" style="display: block; margin-bottom: var(--if-space-2); font-weight: 600;">
        <?php esc_html_e('Set Invoice Counter', 'invoiceforge'); ?>
    </label>
    <p style="color: var(--if-gray-600); margin-bottom: var(--if-space-2); font-size: 13px;">
        <?php esc_html_e('Manually set the next invoice number counter. The next invoice created will use this number.', 'invoiceforge'); ?>
    </p>
    <div style="display: flex; align-items: center; gap: var(--if-space-2);">
        <input type="number" id="manual-counter-value" min="1" step="1" placeholder="<?php esc_attr_e('e.g., 100', 'invoiceforge'); ?>" style="width: 150px;" class="regular-text" />
        <button type="button" class="invoiceforge-btn invoiceforge-btn-danger" id="set-invoice-counter">
            <span class="dashicons dashicons-edit"></span>
            <?php esc_html_e('Set Counter', 'invoiceforge'); ?>
        </button>
    </div>
    <p id="counter-current-info" style="color: var(--if-gray-500); margin-top: var(--if-space-2); font-size: 12px;">
        <?php
        /* Show current counter value for reference */
        $numbering = new \InvoiceForge\Services\NumberingService(new \InvoiceForge\Utilities\Logger());
        $current = $numbering->getCurrentCounter();
        $preview = $numbering->preview();
        printf(
            /* translators: 1: current counter number, 2: next invoice number preview */
            esc_html__('Current counter: %1$d | Next invoice number: %2$s', 'invoiceforge'),
            $current['number'],
            esc_html($preview)
        );
        ?>
    </p>
</div>
```

Wait -- do NOT instantiate NumberingService directly in the template. Instead, use the DI container. Check how the template receives data. Looking at the settings.php template, it gets rendered by SettingsPage which has access to the container. The simplest approach: pass the counter info via a localized script variable or just read the wp_options directly in the template:

```php
<?php
$current_counter = (int) get_option('invoiceforge_last_invoice_number', 0);
printf(
    esc_html__('Current counter: %d', 'invoiceforge'),
    $current_counter
);
?>
```

This avoids DI issues. The preview will be shown dynamically via JS after set/reset actions.

**In assets/admin/js/admin.js** -- Add two new handler methods to the InvoiceForgeAdmin object:

1. In the `bindEvents` method, add:
   ```javascript
   // Reset invoice counter
   $(document).on('click', '#reset-invoice-counter', this.handleResetCounter.bind(this));
   // Set invoice counter
   $(document).on('click', '#set-invoice-counter', this.handleSetCounter.bind(this));
   ```

2. Add `handleResetCounter` method (add before the `showToast` method around line 618):
   ```javascript
   handleResetCounter: function(e) {
       e.preventDefault();
       var message = $(e.currentTarget).data('confirm');
       if (!confirm(message)) return;

       var $btn = $(e.currentTarget);
       $btn.prop('disabled', true);

       $.ajax({
           url: InvoiceForge.ajaxUrl,
           type: 'POST',
           data: {
               action: 'invoiceforge_reset_invoice_counter',
               nonce: InvoiceForge.nonce
           },
           success: function(response) {
               if (response.success) {
                   InvoiceForgeAdmin.showToast('success', response.data.message);
                   if (response.data.counter !== undefined) {
                       $('#counter-current-info').text('Current counter: ' + response.data.counter + ' | Next invoice: ' + response.data.preview);
                   }
               } else {
                   InvoiceForgeAdmin.showToast('error', response.data.message || 'Failed to reset counter.');
               }
           },
           error: function() {
               InvoiceForgeAdmin.showToast('error', 'Network error. Please try again.');
           },
           complete: function() {
               $btn.prop('disabled', false);
           }
       });
   },
   ```

3. Add `handleSetCounter` method:
   ```javascript
   handleSetCounter: function(e) {
       e.preventDefault();
       var value = parseInt($('#manual-counter-value').val(), 10);
       if (!value || value < 1) {
           InvoiceForgeAdmin.showToast('error', 'Please enter a valid counter value (minimum 1).');
           return;
       }
       if (!confirm('Set the invoice counter to ' + value + '? The next invoice will use this number.')) return;

       var $btn = $(e.currentTarget);
       $btn.prop('disabled', true);

       $.ajax({
           url: InvoiceForge.ajaxUrl,
           type: 'POST',
           data: {
               action: 'invoiceforge_set_invoice_counter',
               nonce: InvoiceForge.nonce,
               counter_value: value
           },
           success: function(response) {
               if (response.success) {
                   InvoiceForgeAdmin.showToast('success', response.data.message);
                   $('#manual-counter-value').val('');
                   if (response.data.counter !== undefined) {
                       $('#counter-current-info').text('Current counter: ' + (response.data.counter - 1) + ' | Next invoice: ' + response.data.preview);
                   }
               } else {
                   InvoiceForgeAdmin.showToast('error', response.data.message || 'Failed to set counter.');
               }
           },
           error: function() {
               InvoiceForgeAdmin.showToast('error', 'Network error. Please try again.');
           },
           complete: function() {
               $btn.prop('disabled', false);
           }
       });
   },
   ```

IMPORTANT: Since `handleResetCounter` now handles the reset button click with its own confirm() call, the generic `handleConfirm` bound to `[data-confirm]` will also fire. To prevent double-confirm, remove the `data-confirm` attribute from the reset button in settings.php, and put the confirm message directly in the JS handler instead. Alternatively, add `e.stopImmediatePropagation()` in handleResetCounter before the confirm. The cleanest approach: keep `data-confirm` on the button for the message text, but in `handleResetCounter` call `e.stopImmediatePropagation()` at the top so the generic handler does not also fire.

Actually, the simplest fix: the generic `handleConfirm` only calls `e.preventDefault()` on cancel -- it does NOT do anything on confirm (just lets the event through). Since the reset button is type="button" (not a link/submit), `preventDefault` is a no-op anyway. The issue is that `handleConfirm` runs on ALL `[data-confirm]` clicks, but since it binds to `$(document)` and our handler also binds to `$(document)`, order matters. Safest approach: in `bindEvents`, bind the reset handler BEFORE the generic confirm handler, OR just remove `data-confirm` from the reset button in settings.php since `handleResetCounter` now has its own confirm().

Decision: Remove `data-confirm` attribute from the reset button in settings.php. The JS handler `handleResetCounter` will hardcode the confirm message. This avoids any event ordering issues.
  </action>
  <verify>
    <automated>grep -n "handleResetCounter\|handleSetCounter\|set-invoice-counter\|manual-counter-value" assets/admin/js/admin.js templates/admin/settings.php</automated>
  </verify>
  <done>Reset button triggers AJAX call with confirm dialog and shows toast on success/error. Set Counter input and button allow manual counter adjustment. Current counter value displayed for reference. Both update the displayed counter info on success.</done>
</task>

</tasks>

<verification>
1. Navigate to Settings > Advanced in WordPress admin
2. Verify "Reset Invoice Counter" button triggers confirm dialog, then AJAX call, then shows success toast
3. Verify "Set Invoice Counter" input accepts a number, confirm dialog appears, AJAX sets the counter, success toast shown
4. Verify counter info text updates after both operations
5. Verify creating a new invoice uses the expected next number after reset/set
</verification>

<success_criteria>
- Reset Invoice Counter button performs actual AJAX reset (not just confirm dialog)
- Manual counter adjustment input and button exist in Danger Zone
- Both operations protected by nonce and manage_options capability check
- Success/error feedback via toast notifications
- Current counter value displayed and updated dynamically after operations
</success_criteria>

<output>
After completion, create `.planning/quick/8-fix-reset-invoice-counter-bug-and-add-ma/8-SUMMARY.md`
</output>
