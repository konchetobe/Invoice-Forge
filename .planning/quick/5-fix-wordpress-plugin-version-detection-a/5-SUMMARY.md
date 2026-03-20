---
phase: quick-5
plan: 5
subsystem: core/update-checker
tags: [version-bump, auto-update, wordpress, plugin-update-checker]
dependency_graph:
  requires: []
  provides: [correct-plugin-version-reporting, reliable-github-update-detection]
  affects: [invoiceforge.php, src/Core/UpdateChecker.php]
tech_stack:
  added: []
  patterns: [yahnis-elsts/plugin-update-checker, setBranch, enableReleaseAssets]
key_files:
  created: []
  modified:
    - invoiceforge.php
    - src/Core/UpdateChecker.php
decisions:
  - "setBranch('main') placed before enableReleaseAssets() to match library call ordering expectations"
  - "WP_DEBUG guard used for debug log to avoid polluting production logs"
metrics:
  duration: 5m
  completed_date: "2026-03-20"
---

# Phase quick-5 Plan 5: Fix WordPress Plugin Version Detection Summary

**One-liner:** Bumped plugin version to 1.2.0 in both the file header and INVOICEFORGE_VERSION constant, and hardened UpdateChecker with explicit setBranch('main') and WP_DEBUG initialization logging.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Bump plugin version to 1.2.0 and harden UpdateChecker | bff7eaf | invoiceforge.php, src/Core/UpdateChecker.php |

## Changes Made

### invoiceforge.php

- Line 14 (plugin header): `Version: 1.1.5` -> `Version: 1.2.0`
- Line 36 (constant): `define('INVOICEFORGE_VERSION', '1.1.5')` -> `define('INVOICEFORGE_VERSION', '1.2.0')`

Both values now match the latest GitHub release tag (v1.2.0).

### src/Core/UpdateChecker.php

Added two items inside the `init()` try block, between `buildUpdateChecker()` and `enableReleaseAssets()`:

1. `$updateChecker->setBranch('main')` — explicitly targets the main branch so the plugin-update-checker library resolves release tags reliably. Without this the library may default to checking branch metadata and miss tags.
2. WP_DEBUG conditional `error_log` after `enableReleaseAssets()` — logs repo slug and current version to `wp-content/debug.log` when debug mode is active, providing a diagnostic trail for update detection issues.

## Verification

- Header match: OK
- Constant match: OK
- setBranch present: OK
- Debug log present: OK
- PHP syntax (invoiceforge.php): No errors
- PHP syntax (src/Core/UpdateChecker.php): No errors

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- invoiceforge.php modified and committed: bff7eaf (FOUND)
- src/Core/UpdateChecker.php modified and committed: bff7eaf (FOUND)
- All verification checks returned OK
