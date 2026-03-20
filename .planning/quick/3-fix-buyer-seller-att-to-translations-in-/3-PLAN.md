---
phase: quick
plan: 3
type: execute
wave: 1
depends_on: []
files_modified:
  - languages/invoiceforge-bg_BG.po
  - languages/invoiceforge-bg_BG.mo
autonomous: true
requirements: []
must_haves:
  truths:
    - "Bulgarian invoice PDF/email shows ПОЛУЧАТЕЛ instead of КУПУВАЧ for BUYER"
    - "Bulgarian invoice PDF/email shows ДОСТАВЧИК instead of ПРОДАВАЧ for SELLER"
    - "Bulgarian invoice PDF/email shows МОЛ instead of На вниманието на for Att To"
  artifacts:
    - path: "languages/invoiceforge-bg_BG.po"
      provides: "Corrected Bulgarian translation source"
      contains: "ПОЛУЧАТЕЛ"
    - path: "languages/invoiceforge-bg_BG.mo"
      provides: "Compiled binary translation"
  key_links: []
---

<objective>
Fix three incorrect Bulgarian invoice term translations in the bg_BG locale file and recompile the .mo binary.

Purpose: Bulgarian invoices currently show informal/literal translations (КУПУВАЧ, ПРОДАВАЧ, На вниманието на) instead of standard Bulgarian accounting terms (ПОЛУЧАТЕЛ, ДОСТАВЧИК, МОЛ).
Output: Updated .po and recompiled .mo for bg_BG locale.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@languages/invoiceforge-bg_BG.po
@scripts/compile-mo.cjs
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix Bulgarian invoice terms and recompile .mo</name>
  <files>languages/invoiceforge-bg_BG.po, languages/invoiceforge-bg_BG.mo</files>
  <action>
Edit three msgstr values in languages/invoiceforge-bg_BG.po:

1. Line 361 — BUYER translation: change msgstr from "КУПУВАЧ" to "ПОЛУЧАТЕЛ"
2. Line 364 — SELLER translation: change msgstr from "ПРОДАВАЧ" to "ДОСТАВЧИК"
3. Line 382 — Att To translation: change msgstr from "На вниманието на" to "МОЛ"

Do NOT change any msgid values or any other translations.

After editing, recompile the .mo binary:
```
node scripts/compile-mo.cjs
```

The script compiles ALL .po files in languages/ to .mo. Verify it reports success for bg_BG.
  </action>
  <verify>
    <automated>node -e "const fs=require('fs'); const po=fs.readFileSync('languages/invoiceforge-bg_BG.po','utf8'); const checks=[['BUYER','ПОЛУЧАТЕЛ'],['SELLER','ДОСТАВЧИК'],['Att To','МОЛ']]; let ok=true; for(const [id,expected] of checks){const re=new RegExp('msgid \"'+id+'\"\\s*\\nmsgstr \"'+expected+'\"'); if(!re.test(po)){console.error('FAIL: '+id+' should be '+expected); ok=false;}} if(ok) console.log('All 3 translations correct'); else process.exit(1);"</automated>
  </verify>
  <done>
- invoiceforge-bg_BG.po contains msgstr "ПОЛУЧАТЕЛ" for msgid "BUYER"
- invoiceforge-bg_BG.po contains msgstr "ДОСТАВЧИК" for msgid "SELLER"
- invoiceforge-bg_BG.po contains msgstr "МОЛ" for msgid "Att To"
- invoiceforge-bg_BG.mo recompiled successfully by compile-mo.cjs
  </done>
</task>

</tasks>

<verification>
1. Grep .po file for the three corrected terms
2. Confirm .mo file modification timestamp is newer than .po edit
3. compile-mo.cjs exits with code 0
</verification>

<success_criteria>
Bulgarian locale uses standard invoice accounting terms: ПОЛУЧАТЕЛ (buyer/recipient), ДОСТАВЧИК (seller/supplier), МОЛ (financially responsible person). Compiled .mo binary is up to date.
</success_criteria>

<output>
After completion, create `.planning/quick/3-fix-buyer-seller-att-to-translations-in-/3-SUMMARY.md`
</output>
