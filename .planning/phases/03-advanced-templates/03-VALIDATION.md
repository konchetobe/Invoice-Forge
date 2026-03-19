---
phase: 3
slug: advanced-templates
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x + Brain\Monkey |
| **Config file** | `phpunit.xml` (Wave 0 installs) |
| **Quick run command** | `./vendor/bin/phpunit --filter Phase3 --no-coverage` |
| **Full suite command** | `./vendor/bin/phpunit --no-coverage` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit --filter Phase3 --no-coverage`
- **After every plan wave:** Run `./vendor/bin/phpunit --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 0 | Test infra | unit | `./vendor/bin/phpunit --filter Smoke` | ❌ W0 | ⬜ pending |
| 03-02-01 | 02 | 1 | Template context | unit | `./vendor/bin/phpunit --filter PdfServiceTemplateContext` | ❌ W0 | ⬜ pending |
| 03-02-02 | 02 | 1 | Email render mode | unit | `./vendor/bin/phpunit --filter PdfServiceEmailMode` | ❌ W0 | ⬜ pending |
| 03-03-01 | 03 | 1 | Accent color sanitize | unit | `./vendor/bin/phpunit --filter SettingsPageAccentColor` | ❌ W0 | ⬜ pending |
| 03-03-02 | 03 | 1 | Section order reindex | unit | `./vendor/bin/phpunit --filter SettingsPageSectionOrder` | ❌ W0 | ⬜ pending |
| 03-03-03 | 03 | 1 | Logo upload path stored | integration | `./vendor/bin/phpunit --filter SettingsPageLogoUpload` | ❌ W0 | ⬜ pending |
| 03-04-01 | 04 | 2 | Discount columns conditional | unit | `./vendor/bin/phpunit --filter TemplateDiscountColumns` | ❌ W0 | ⬜ pending |
| 03-04-02 | 04 | 2 | Bank section conditional | unit | `./vendor/bin/phpunit --filter TemplateBankSection` | ❌ W0 | ⬜ pending |
| 03-05-01 | 05 | 2 | Payment method meta saved | integration | `./vendor/bin/phpunit --filter InvoicePaymentMethod` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/bootstrap.php` — WordPress test bootstrap (Brain\Monkey)
- [ ] `phpunit.xml` — test suite configuration
- [ ] `tests/Unit/Services/PdfServiceTest.php` — stubs for template context and render modes
- [ ] `tests/Unit/Admin/SettingsPageTest.php` — stubs for template tab sanitization
- [ ] Framework install: `composer require --dev phpunit/phpunit brain/monkey`

*Wave 0 must be the first plan executed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| PDF visual layout matches reference | Template fidelity | Visual comparison required | Generate PDF, compare side-by-side with Bulgarian reference PDF |
| Email renders in Gmail/Outlook | Email compat | Client-specific rendering | Send test email, verify in at least 2 clients |
| Drag-and-drop section reorder | UX interaction | Browser interaction | Open Settings → Template tab, drag sections, save, verify order persisted |
| Logo displays in PDF at correct position | Logo rendering | mPDF visual output | Upload logo, generate PDF, verify top-left of seller section |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
