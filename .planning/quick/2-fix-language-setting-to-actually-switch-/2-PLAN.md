---
phase: quick-2
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - languages/invoiceforge-bg_BG.po
  - languages/invoiceforge-bg_BG.mo
  - languages/invoiceforge-de_DE.po
  - languages/invoiceforge-de_DE.mo
  - languages/invoiceforge-fr_FR.po
  - languages/invoiceforge-fr_FR.mo
  - languages/invoiceforge-es_ES.po
  - languages/invoiceforge-es_ES.mo
  - languages/invoiceforge-it_IT.po
  - languages/invoiceforge-it_IT.mo
  - languages/invoiceforge-nl_NL.po
  - languages/invoiceforge-nl_NL.mo
  - languages/invoiceforge-pl_PL.po
  - languages/invoiceforge-pl_PL.mo
  - languages/invoiceforge-pt_PT.po
  - languages/invoiceforge-pt_PT.mo
  - languages/invoiceforge-ro_RO.po
  - languages/invoiceforge-ro_RO.mo
  - languages/invoiceforge-ru_RU.po
  - languages/invoiceforge-ru_RU.mo
  - scripts/compile-mo.cjs
autonomous: true
requirements: [QUICK-2]

must_haves:
  truths:
    - "Changing language setting to any supported locale causes invoice PDF labels to appear in that language"
    - "Bulgarian .po file contains all translatable strings from invoice template and email template"
    - "All 9 non-English locales have .po and .mo files with complete translations"
    - "The loadTextDomain() code in Plugin.php is unchanged"
  artifacts:
    - path: "languages/invoiceforge-bg_BG.po"
      provides: "Complete Bulgarian translations including invoice/email template strings"
    - path: "languages/invoiceforge-de_DE.po"
      provides: "German translations"
    - path: "languages/invoiceforge-fr_FR.po"
      provides: "French translations"
    - path: "languages/invoiceforge-es_ES.po"
      provides: "Spanish translations"
    - path: "languages/invoiceforge-it_IT.po"
      provides: "Italian translations"
    - path: "languages/invoiceforge-nl_NL.po"
      provides: "Dutch translations"
    - path: "languages/invoiceforge-pl_PL.po"
      provides: "Polish translations"
    - path: "languages/invoiceforge-pt_PT.po"
      provides: "Portuguese translations"
    - path: "languages/invoiceforge-ro_RO.po"
      provides: "Romanian translations"
    - path: "languages/invoiceforge-ru_RU.po"
      provides: "Russian translations"
  key_links:
    - from: "src/Core/Plugin.php:loadTextDomain()"
      to: "languages/invoiceforge-{locale}.mo"
      via: "load_plugin_textdomain reads .mo files from languages/ dir"
      pattern: "load_plugin_textdomain"
---

<objective>
Fix the Language setting so changing it actually translates the plugin UI and invoice labels.

Purpose: The language dropdown in Settings>General lists 12 languages but only Bulgarian has .po/.mo files, and even those are missing all invoice template strings. Without .mo files, WordPress cannot load translations.

Output: Complete .po and .mo translation files for all 10 supported locales (bg_BG + 9 others), plus a reusable Node.js .mo compiler script.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@languages/invoiceforge-bg_BG.po
@templates/pdf/invoice-default.php
@src/Core/Plugin.php (lines 312-333 — loadTextDomain, DO NOT modify)
@src/Admin/Pages/SettingsPage.php (lines 410-423 — supported locales list)

Key facts:
- `loadTextDomain()` in Plugin.php is correct and must NOT be changed
- WordPress reads ONLY .mo (compiled binary) files at runtime; .po files are source
- `msgfmt` is NOT available on this system; use Node.js `gettext-parser` npm package or write a custom .mo binary compiler in Node
- en_US and en_GB need no translation files (English is the source language)
- Supported locales needing files: bg_BG, de_DE, fr_FR, es_ES, it_IT, nl_NL, pl_PL, pt_PT, ro_RO, ru_RU

Complete list of translatable strings from invoice template (missing from bg_BG.po):
- BUYER, SELLER, INVOICE, No., Date, Due, Office, Att To
- Description, Qty, Unit Price, Disc.%, Disc.Amt, Tax %, Tax Amt, Total
- Subtotal, Discount, Tax, TOTAL
- Payment Details, Payment Method, Bank, IBAN, BIC
- VAT exempt — Article 70 VATA.
- Signatures
- Invoice (lowercase, used in email mode), Due Date, Status, Amount Due, Pay Invoice
</context>

<tasks>

<task type="auto">
  <name>Task 1: Update Bulgarian .po with all missing strings and create all locale .po files</name>
  <files>
    languages/invoiceforge-bg_BG.po
    languages/invoiceforge-de_DE.po
    languages/invoiceforge-fr_FR.po
    languages/invoiceforge-es_ES.po
    languages/invoiceforge-it_IT.po
    languages/invoiceforge-nl_NL.po
    languages/invoiceforge-pl_PL.po
    languages/invoiceforge-pt_PT.po
    languages/invoiceforge-ro_RO.po
    languages/invoiceforge-ru_RU.po
  </files>
  <action>
    1. Read the existing `languages/invoiceforge-bg_BG.po` file completely.

    2. Append the following missing msgid/msgstr pairs to the Bulgarian .po file (add a `# Invoice/Email Template` section comment). These are ALL the strings from `templates/pdf/invoice-default.php` and email mode that are not yet in the .po file:

    Bulgarian translations to add:
    - "BUYER" -> "КУПУВАЧ"
    - "SELLER" -> "ПРОДАВАЧ"
    - "INVOICE" -> "ФАКТУРА"
    - "No." -> "No."
    - "Date" -> "Дата"
    - "Due" -> "Срок"
    - "Office" -> "Офис"
    - "Att To" -> "На вниманието на"
    - "Description" -> "Описание"
    - "Qty" -> "Кол."
    - "Unit Price" -> "Ед. цена"
    - "Disc.%" -> "Отст.%"
    - "Disc.Amt" -> "Отст.ст."
    - "Tax %" -> "ДДС %"
    - "Tax Amt" -> "ДДС ст."
    - "Total" -> "Общо"
    - "Subtotal" -> "Междинна сума"
    - "Discount" -> "Отстъпка"
    - "Tax" -> "Данък"
    - "TOTAL" -> "ОБЩО"
    - "VAT exempt — Article 70 VATA." -> "Освободена от ДДС — Член 70 от ЗДДС."
    - "Payment Details" -> "Данни за плащане"
    - "Payment Method" -> "Начин на плащане"
    - "Bank" -> "Банка"
    - "IBAN" -> "IBAN"
    - "BIC" -> "BIC"
    - "Signatures" -> "Подписи"
    - "Amount Due" -> "Дължима сума"
    - "Pay Invoice" -> "Плати фактура"
    - "German (Deutsch)" -> "Немски (Deutsch)"
    - "French (Français)" -> "Френски (Français)"
    - "Spanish (Español)" -> "Испански (Español)"
    - "Italian (Italiano)" -> "Италиански (Italiano)"
    - "Dutch (Nederlands)" -> "Холандски (Nederlands)"
    - "Polish (Polski)" -> "Полски (Polski)"
    - "Portuguese (Português)" -> "Португалски (Português)"
    - "Romanian (Română)" -> "Румънски (Română)"
    - "Russian (Русский)" -> "Руски (Русский)"

    3. Create .po files for each of the 9 other locales. Each file must have:
       - Standard PO header (Project-Id-Version, Language, MIME, Content-Type, Plural-Forms appropriate to the language)
       - ALL msgid entries that exist in the Bulgarian file (the full set including the newly added ones)
       - Proper msgstr translations for EVERY entry in the target language

    Use accurate translations. These are professional invoice/business terms. Key translation notes:
    - "BUYER"/"SELLER" should use the formal business/legal terms in each language
    - "Disc.%" and "Disc.Amt" are abbreviations for Discount Percentage and Discount Amount — use similar abbreviations in each language
    - "Tax %" and "Tax Amt" — use the local tax terminology (TVA in French/Romanian, IVA in Spanish/Italian/Portuguese, BTW in Dutch, MwSt in German, etc.)
    - "VAT exempt — Article 70 VATA." — translate "VAT exempt" to local equivalent; keep "Article 70 VATA" as-is since it refers to Bulgarian law
    - Plural-Forms must be correct per language (most Western European = nplurals=2; Polish/Russian have 3 forms)

    Locale-specific Plural-Forms:
    - de_DE: nplurals=2; plural=(n != 1);
    - fr_FR: nplurals=2; plural=(n > 1);
    - es_ES: nplurals=2; plural=(n != 1);
    - it_IT: nplurals=2; plural=(n != 1);
    - nl_NL: nplurals=2; plural=(n != 1);
    - pl_PL: nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);
    - pt_PT: nplurals=2; plural=(n != 1);
    - ro_RO: nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100>0 && n%100<20)) ? 1 : 2);
    - ru_RU: nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);
  </action>
  <verify>
    <automated>node -e "const fs=require('fs'); const locales=['bg_BG','de_DE','fr_FR','es_ES','it_IT','nl_NL','pl_PL','pt_PT','ro_RO','ru_RU']; let ok=true; for(const l of locales){const p='languages/invoiceforge-'+l+'.po'; if(!fs.existsSync(p)){console.log('MISSING: '+p);ok=false;}else{const c=fs.readFileSync(p,'utf8'); for(const s of ['BUYER','SELLER','INVOICE','Description','Subtotal','Payment Details','Signatures']){if(!c.includes('msgid \"'+s+'\"')){console.log(p+' missing: '+s);ok=false;}}}} if(ok)console.log('ALL PO FILES OK'); process.exit(ok?0:1);"</automated>
  </verify>
  <done>All 10 .po files exist with complete translations for every translatable string used in the plugin, including all invoice template, email template, settings page, and UI strings.</done>
</task>

<task type="auto">
  <name>Task 2: Compile all .po files to .mo binary format</name>
  <files>
    scripts/compile-mo.cjs
    languages/invoiceforge-bg_BG.mo
    languages/invoiceforge-de_DE.mo
    languages/invoiceforge-fr_FR.mo
    languages/invoiceforge-es_ES.mo
    languages/invoiceforge-it_IT.mo
    languages/invoiceforge-nl_NL.mo
    languages/invoiceforge-pl_PL.mo
    languages/invoiceforge-pt_PT.mo
    languages/invoiceforge-ro_RO.mo
    languages/invoiceforge-ru_RU.mo
  </files>
  <action>
    Create a Node.js script `scripts/compile-mo.cjs` that converts .po files to .mo binary format WITHOUT any npm dependencies. The .mo file format is well-documented (GNU gettext):

    .mo binary format specification:
    - Magic number: 0x950412de (little-endian)
    - Revision: 0
    - Number of strings (N)
    - Offset of original strings table (O)
    - Offset of translated strings table (T)
    - Size of hashing table (S) — set to 0
    - Offset of hashing table — set to 0
    - Then: O table (N entries of [length, offset]), T table (N entries of [length, offset]), then all string data (null-terminated)
    - Strings MUST be sorted by original string (byte order)

    The script should:
    1. Parse .po files: extract msgid/msgstr pairs, skip empty msgstr (except the header which has empty msgid), handle multiline strings (continued lines starting with ")
    2. Write binary .mo using Buffer: magic, revision, counts, offset tables, string data
    3. Process all .po files matching `languages/invoiceforge-*.po`
    4. Log each file processed

    Run the script after creation: `node scripts/compile-mo.cjs`

    Verify each .mo file starts with the correct magic number (0xde120495 or 0x950412de depending on endianness) and has non-zero size.
  </action>
  <verify>
    <automated>node scripts/compile-mo.cjs && node -e "const fs=require('fs'); const locales=['bg_BG','de_DE','fr_FR','es_ES','it_IT','nl_NL','pl_PL','pt_PT','ro_RO','ru_RU']; let ok=true; for(const l of locales){const p='languages/invoiceforge-'+l+'.mo'; if(!fs.existsSync(p)){console.log('MISSING: '+p);ok=false;}else{const b=fs.readFileSync(p); if(b.length<100){console.log('TOO SMALL: '+p+' ('+b.length+' bytes)');ok=false;} const magic=b.readUInt32LE(0); if(magic!==0x950412de){console.log('BAD MAGIC: '+p);ok=false;}}} if(ok)console.log('ALL MO FILES OK'); process.exit(ok?0:1);"</automated>
  </verify>
  <done>All 10 .mo files exist in languages/ directory, each with valid .mo binary format and non-trivial size. WordPress can now load translations for any of the 10 supported locales when selected in Settings>General>Language.</done>
</task>

</tasks>

<verification>
1. All 10 .po files exist with complete string coverage
2. All 10 .mo files exist with valid binary format
3. No changes to src/Core/Plugin.php
4. The compile-mo.cjs script is reusable for future translation updates
</verification>

<success_criteria>
- 10 .po files in languages/ directory, one per supported locale
- 10 .mo files in languages/ directory, compiled from corresponding .po files
- Every translatable string from invoice-default.php template appears in every .po file
- Plugin.php loadTextDomain() is untouched
- Setting Language to any supported locale will cause WordPress to load the corresponding .mo file
</success_criteria>

<output>
After completion, create `.planning/quick/2-fix-language-setting-to-actually-switch-/2-SUMMARY.md`
</output>
