---
phase: quick-4
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/admin/js/admin.js
  - src/Ajax/ClientAjaxHandler.php
  - src/Admin/Pages/ClientsPage.php
  - src/PostTypes/ClientPostType.php
  - src/Services/PdfService.php
  - templates/pdf/invoice-default.php
autonomous: true
requirements: [QUICK-4]

must_haves:
  truths:
    - "All billing fields (id_no, office, att_to) are saved when a client is created or updated via AJAX"
    - "Client name in post_title and invoice display does NOT include company in parentheses"
    - "Invoice PDF/email shows full client address including city, postal code, state, and country"
    - "No client fields are mandatory -- saving succeeds even with only partial data"
    - "Billing fields round-trip correctly: save -> reload editor -> values are populated"
  artifacts:
    - path: "assets/admin/js/admin.js"
      provides: "AJAX form data includes id_no, office, att_to fields"
      contains: "id_no.*att_to.*office"
    - path: "src/Ajax/ClientAjaxHandler.php"
      provides: "Backend saves and returns all billing meta fields"
      contains: "_client_id_no|_client_office|_client_att_to"
    - path: "src/Admin/Pages/ClientsPage.php"
      provides: "Editor data includes id_no, office, att_to for pre-population"
      contains: "_client_id_no|_client_office|_client_att_to"
    - path: "src/Services/PdfService.php"
      provides: "Invoice data includes full client address components"
      contains: "client_city|client_zip|client_state|client_country"
    - path: "templates/pdf/invoice-default.php"
      provides: "Template renders full address with city, zip, country"
      contains: "client_city|client_zip|client_country"
  key_links:
    - from: "assets/admin/js/admin.js"
      to: "src/Ajax/ClientAjaxHandler.php"
      via: "AJAX POST formData with id_no, office, att_to fields"
      pattern: "id_no.*office.*att_to"
    - from: "src/Ajax/ClientAjaxHandler.php"
      to: "src/Services/PdfService.php"
      via: "post_meta _client_id_no, _client_office, _client_att_to read by getInvoiceData"
      pattern: "_client_id_no|_client_att_to"
    - from: "src/Admin/Pages/ClientsPage.php"
      to: "templates/admin/client-editor.php"
      via: "getClientData returns id_no/office/att_to for editor template pre-population"
      pattern: "_client_id_no|_client_office|_client_att_to"
    - from: "src/Services/PdfService.php"
      to: "templates/pdf/invoice-default.php"
      via: "template context variables client_city, client_zip etc."
      pattern: "client_city|client_country"
---

<objective>
Fix client billing details not saving and not displaying correctly in invoices.

Purpose: Currently only tax_id is saved from the billing section; id_no, office, and att_to fields are lost on save. Additionally, invoices only show the street address (missing city/zip/country), client names incorrectly include "(Company)" in parentheses, and all core fields are mandatory when they should not be.

Output: Fully functional client billing save/load cycle with correct invoice rendering of all address and billing fields.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

Key source files:
@assets/admin/js/admin.js (client form AJAX - lines 316-331)
@src/Ajax/ClientAjaxHandler.php (saveClient, createClientFromInvoice, getClientData)
@src/Admin/Pages/ClientsPage.php (getClientData for editor template)
@src/PostTypes/ClientPostType.php (getDisplayName, saveMetaData)
@src/Services/PdfService.php (getInvoiceData, getTemplateContext)
@templates/pdf/invoice-default.php (header section rendering)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix billing field save/load in JS and AJAX handler + remove mandatory constraints + fix client name format</name>
  <files>assets/admin/js/admin.js, src/Ajax/ClientAjaxHandler.php, src/Admin/Pages/ClientsPage.php, src/PostTypes/ClientPostType.php</files>
  <action>
1. **assets/admin/js/admin.js** (~line 330): Add the three missing billing fields to the formData object in the client form AJAX submit handler:
   ```
   id_no: $form.find('[name="id_no"]').val(),
   office: $form.find('[name="office"]').val(),
   att_to: $form.find('[name="att_to"]').val()
   ```
   Add these after the existing `tax_id` line. Keep the trailing comma consistent.

2. **src/Ajax/ClientAjaxHandler.php** - `saveClient()` method:
   - Add parsing for the three new fields after the `$tax_id` line (~line 190):
     ```php
     $id_no = isset($_POST['id_no']) ? $this->sanitizer->text($_POST['id_no']) : '';
     $office = isset($_POST['office']) ? $this->sanitizer->text($_POST['office']) : '';
     $att_to = isset($_POST['att_to']) ? $this->sanitizer->text($_POST['att_to']) : '';
     ```
   - Remove the validation block (~lines 199-217) that enforces first_name, last_name, and email as required. Replace with NO mandatory validation -- all fields are optional. Remove the `$errors` array, the empty checks for first_name/last_name/email, and the early return on errors.
   - Fix client title construction (~line 220-223): Remove the parenthesized company from the title. For companies, use just the company name as title. For individuals, use "FirstName LastName". Logic:
     ```php
     if (!empty($company)) {
         $title = $company;
     } else {
         $title = trim($first_name . ' ' . $last_name);
     }
     if (empty($title)) {
         $title = $email ?: __('Unnamed Client', 'invoiceforge');
     }
     ```
   - Add `update_post_meta` calls for the three new fields after the existing `_client_tax_id` line (~line 261):
     ```php
     update_post_meta($post_id, '_client_id_no', $id_no);
     update_post_meta($post_id, '_client_office', $office);
     update_post_meta($post_id, '_client_att_to', $att_to);
     ```

3. **src/Ajax/ClientAjaxHandler.php** - `createClientFromInvoice()` method (~line 295):
   - Remove the required field check (~line 311): `if (empty($first_name) || empty($last_name) || empty($email))` -- make it non-blocking. Still validate email format IF provided but don't require it.
   - Fix title construction same as above (remove parenthesized company).
   - Add parsing of id_no, office, att_to, AND tax_id from `$data` array (after the existing `$country` line ~308):
     ```php
     $tax_id = $this->sanitizer->text($data['tax_id'] ?? '');
     $id_no = $this->sanitizer->text($data['id_no'] ?? '');
     $office = $this->sanitizer->text($data['office'] ?? '');
     $att_to = $this->sanitizer->text($data['att_to'] ?? '');
     ```
   - Add update_post_meta for all four billing fields after the existing `_client_country` line (~353):
     ```php
     update_post_meta($post_id, '_client_tax_id', $tax_id);
     update_post_meta($post_id, '_client_id_no', $id_no);
     update_post_meta($post_id, '_client_office', $office);
     update_post_meta($post_id, '_client_att_to', $att_to);
     ```
   NOTE: tax_id was previously missing from createClientFromInvoice entirely -- this is a silent data-loss fix.

4. **src/Ajax/ClientAjaxHandler.php** - `getClientData()` method (~line 544):
   - Add the three billing fields to the returned array (after `tax_id` on ~line 564):
     ```php
     'id_no'  => get_post_meta($client_id, '_client_id_no', true),
     'office' => get_post_meta($client_id, '_client_office', true),
     'att_to' => get_post_meta($client_id, '_client_att_to', true),
     ```

5. **src/Admin/Pages/ClientsPage.php** - `getClientData()` method (~line 99):
   - This method populates `$client` for the client-editor.php template. Add the three billing fields to the returned array (after `tax_id` on ~line 119):
     ```php
     'id_no'  => get_post_meta($client_id, '_client_id_no', true),
     'office' => get_post_meta($client_id, '_client_office', true),
     'att_to' => get_post_meta($client_id, '_client_att_to', true),
     ```
   Without this fix, the editor form fields for id_no/office/att_to always render blank on page load even if the data exists in post_meta.

6. **src/PostTypes/ClientPostType.php** - `getDisplayName()` static method (~line 773):
   - Change to use company name as title for companies (no parentheses):
     ```php
     if (!empty($company)) {
         return $company;
     }
     return $name ?: __('Unknown Client', 'invoiceforge');
     ```
  </action>
  <verify>
    <automated>grep -n "id_no\|office\|att_to" assets/admin/js/admin.js src/Ajax/ClientAjaxHandler.php src/Admin/Pages/ClientsPage.php | grep -c "id_no\|att_to\|office" | xargs -I{} test {} -ge 18 && echo "PASS: billing fields present in JS, handler, and ClientsPage" || echo "FAIL"</automated>
    Verify: grep for `_client_id_no` in ClientAjaxHandler.php returns matches in saveClient, createClientFromInvoice, AND getClientData. Grep for `_client_id_no` in ClientsPage.php returns a match in getClientData. Grep for `_client_tax_id` in createClientFromInvoice block returns a match. Grep for `required` or `is required` in saveClient validation section returns 0 matches. Grep for `"($company)"` returns 0 matches in ClientAjaxHandler.php.
  </verify>
  <done>All three billing fields (id_no, office, att_to) are saved and returned via AJAX. tax_id is now also saved in createClientFromInvoice (was silently lost before). No fields are mandatory. Client title uses company name directly (no parentheses) for company clients. Both ClientAjaxHandler::getClientData and ClientsPage::getClientData return billing fields for editor pre-population.</done>
</task>

<task type="auto">
  <name>Task 2: Fix invoice template to show full client address with city, postal code, state, and country</name>
  <files>src/Services/PdfService.php, templates/pdf/invoice-default.php</files>
  <action>
1. **src/Services/PdfService.php** - `getInvoiceData()` method (~line 279):
   - Add client address components to the returned array, after the existing `client_address` line (~line 315):
     ```php
     'client_city'    => $client_id ? get_post_meta($client_id, '_client_city', true) : '',
     'client_state'   => $client_id ? get_post_meta($client_id, '_client_state', true) : '',
     'client_zip'     => $client_id ? get_post_meta($client_id, '_client_zip', true) : '',
     'client_country' => $client_id ? get_post_meta($client_id, '_client_country', true) : '',
     ```

2. **src/Services/PdfService.php** - `getTemplateContext()` method:
   - No changes needed here because `getTemplateContext` does `array_merge($invoice ?? [], [...])` so the new fields from `getInvoiceData` will flow through automatically.

3. **src/Services/PdfService.php** - `getInvoiceData()` method - Fix `client_name`:
   - Change line 313 from using `$client->post_title` (which has "(Company)" appended) to building the name properly:
     ```php
     'client_name' => $client_id ? $this->getClientDisplayName($client_id) : '',
     ```
   - Add a private helper method `getClientDisplayName(int $client_id): string` that reads `_client_company`, `_client_first_name`, `_client_last_name` meta and returns company name for companies or "First Last" for individuals (same logic as the fixed getDisplayName but non-static):
     ```php
     private function getClientDisplayName(int $client_id): string
     {
         $company = (string) get_post_meta($client_id, '_client_company', true);
         if (!empty($company)) {
             return $company;
         }
         $first = (string) get_post_meta($client_id, '_client_first_name', true);
         $last  = (string) get_post_meta($client_id, '_client_last_name', true);
         $name  = trim($first . ' ' . $last);
         return $name ?: __('Unknown Client', 'invoiceforge');
     }
     ```

4. **templates/pdf/invoice-default.php** - PDF mode header section (buyer column, ~line 150-170):
   - After the existing `client_address` rendering (line 153-155), add city/state/zip/country rendering. Build a secondary address line from the components:
     ```php
     <?php
     $addr_parts = array_filter([
         !empty($client_zip) ? $client_zip : '',
         !empty($client_city) ? $client_city : '',
     ]);
     $addr_line2 = implode(' ', $addr_parts);
     if (!empty($client_state)) {
         $addr_line2 .= ($addr_line2 ? ', ' : '') . $client_state;
     }
     if (!empty($client_country)) {
         $addr_line2 .= ($addr_line2 ? ', ' : '') . $client_country;
     }
     ?>
     <?php if (!empty($addr_line2)) : ?>
         <?php echo esc_html($addr_line2); ?><br>
     <?php endif; ?>
     ```
   - Place this block immediately after the `client_address` nl2br output and before the `client_id_no` check.
   - Apply the same pattern to the SELLER column (~line 188-190) for company address: read `company_city`, `company_state`, `company_zip`, `company_country` the same way. However, looking at the company data, it uses a single `company_address` field from settings. Leave the seller column as-is since company address is a single textarea field in settings.

5. **templates/pdf/invoice-default.php** - Also ensure the template variables docblock at the top (~line 43-50) documents the new client address variables:
   ```php
    *   @var string $client_city
    *   @var string $client_state
    *   @var string $client_zip
    *   @var string $client_country
   ```
  </action>
  <verify>
    <automated>grep -n "client_city\|client_zip\|client_state\|client_country" src/Services/PdfService.php templates/pdf/invoice-default.php | grep -c "client_city\|client_zip" | xargs -I{} test {} -ge 4 && echo "PASS: address components in PdfService and template" || echo "FAIL"</automated>
    Verify: PdfService getInvoiceData returns client_city, client_state, client_zip, client_country. Template renders these in the buyer column. client_name no longer uses post_title directly.
  </verify>
  <done>Invoice PDF and email templates display full client address (street, city/zip, state, country). Client name on invoices shows company name for companies without parentheses. All address components flow from client meta through PdfService to template.</done>
</task>

</tasks>

<verification>
1. Grep `assets/admin/js/admin.js` for `id_no`, `office`, `att_to` in formData -- all three present
2. Grep `ClientAjaxHandler.php` for `_client_id_no` -- present in saveClient, createClientFromInvoice, getClientData
3. Grep `ClientAjaxHandler.php` for `_client_tax_id` in createClientFromInvoice -- present (was missing before)
4. Grep `ClientsPage.php` for `_client_id_no` -- present in getClientData (editor pre-population)
5. Grep `ClientAjaxHandler.php` for `is required` -- zero matches (no mandatory fields)
6. Grep `ClientAjaxHandler.php` for `"($company)"` -- zero matches
7. Grep `PdfService.php` for `client_city` -- present in getInvoiceData
8. Grep `invoice-default.php` for `client_city` -- present in template rendering
9. Grep `PdfService.php` for `getClientDisplayName` -- present as helper method
</verification>

<success_criteria>
- All six billing/address fields (id_no, office, att_to, city, zip, country) persist through the full save-load-render cycle
- tax_id is saved when creating clients from invoices (was silently lost before)
- Client form saves with no mandatory fields -- partial data accepted
- Client name never shows "(Company)" in parentheses in post titles or invoices
- Invoice PDF buyer section shows complete address: street + city/zip + state/country
- Editor form pre-populates id_no, office, att_to on page load (via ClientsPage::getClientData)
- Existing billing fields (tax_id) continue to work unchanged
</success_criteria>

<output>
After completion, create `.planning/quick/4-fix-client-billing-details-not-saving-an/4-SUMMARY.md`
</output>
