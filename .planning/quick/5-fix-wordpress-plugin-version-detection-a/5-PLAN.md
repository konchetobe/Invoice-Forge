---
phase: quick-5
plan: 5
type: execute
wave: 1
depends_on: []
files_modified:
  - invoiceforge.php
  - src/Core/UpdateChecker.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Plugin header Version field matches INVOICEFORGE_VERSION constant"
    - "Plugin version matches the latest GitHub release tag (1.2.0)"
    - "WordPress update checker correctly detects newer GitHub releases and offers updates"
    - "UpdateChecker explicitly targets the main branch for release detection"
  artifacts:
    - path: "invoiceforge.php"
      provides: "Plugin header and version constant"
      contains: "Version:           1.2.0"
    - path: "src/Core/UpdateChecker.php"
      provides: "GitHub-based auto-update integration"
      contains: "setBranch"
  key_links:
    - from: "invoiceforge.php"
      to: "src/Core/UpdateChecker.php"
      via: "INVOICEFORGE_VERSION constant and INVOICEFORGE_PLUGIN_FILE"
      pattern: "INVOICEFORGE_PLUGIN_FILE"
    - from: "src/Core/UpdateChecker.php"
      to: "GitHub Releases API"
      via: "plugin-update-checker library comparing local version to release tag"
      pattern: "PucFactory::buildUpdateChecker"
---

<objective>
Fix WordPress plugin version detection and GitHub auto-update mechanism.

Purpose: WordPress shows v1.1.5 while GitHub has v1.2.0 because the plugin file header and INVOICEFORGE_VERSION constant were never bumped. The UpdateChecker also needs to explicitly set the branch so the plugin-update-checker library reliably finds releases.

Output: Plugin reporting correct version (1.2.0), UpdateChecker properly configured to detect and offer GitHub-based updates.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@invoiceforge.php
@src/Core/UpdateChecker.php
@src/Core/Plugin.php
@composer.json
@.github/workflows/release.yml
</context>

<tasks>

<task type="auto">
  <name>Task 1: Bump plugin version to 1.2.0 and harden UpdateChecker</name>
  <files>invoiceforge.php, src/Core/UpdateChecker.php</files>
  <action>
    In invoiceforge.php, update TWO locations — they MUST match exactly:
    1. Line 14: Change plugin header `Version: 1.1.5` to `Version: 1.2.0`
    2. Line 36: Change `define('INVOICEFORGE_VERSION', '1.1.5')` to `define('INVOICEFORGE_VERSION', '1.2.0')`

    In src/Core/UpdateChecker.php, inside the `init()` method, after the `buildUpdateChecker()` call and before the `enableReleaseAssets()` call, add:
    ```php
    // Point at the main branch so the library resolves tags correctly
    $updateChecker->setBranch('main');
    ```

    The `setBranch('main')` call tells the plugin-update-checker library to look at the main branch for version comparisons, which is important when the repo uses tags on main for releases (as confirmed by `.github/workflows/release.yml` triggering on `v*` tags). Without this, the library may default to checking the default branch metadata which can miss tagged releases.

    Also add a debug log line after successful initialization (inside the try block, after enableReleaseAssets):
    ```php
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[InvoiceForge] UpdateChecker initialized for ' . self::GITHUB_REPO . ' (current version: ' . INVOICEFORGE_VERSION . ')');
    }
    ```
    This helps diagnose future update detection issues via wp-content/debug.log.
  </action>
  <verify>
    <automated>cd C:/GitHubRepos/Invoice-Forge && php -r "
      \$content = file_get_contents('invoiceforge.php');
      \$headerMatch = preg_match('/Version:\s+1\.2\.0/', \$content);
      \$constMatch = str_contains(\$content, \"define('INVOICEFORGE_VERSION', '1.2.0')\");
      \$ucContent = file_get_contents('src/Core/UpdateChecker.php');
      \$branchMatch = str_contains(\$ucContent, \"setBranch('main')\");
      \$debugMatch = str_contains(\$ucContent, 'UpdateChecker initialized');
      echo 'Header: ' . (\$headerMatch ? 'OK' : 'FAIL') . PHP_EOL;
      echo 'Constant: ' . (\$constMatch ? 'OK' : 'FAIL') . PHP_EOL;
      echo 'setBranch: ' . (\$branchMatch ? 'OK' : 'FAIL') . PHP_EOL;
      echo 'DebugLog: ' . (\$debugMatch ? 'OK' : 'FAIL') . PHP_EOL;
      exit((\$headerMatch && \$constMatch && \$branchMatch && \$debugMatch) ? 0 : 1);
    "</automated>
  </verify>
  <done>
    - invoiceforge.php header Version is 1.2.0
    - INVOICEFORGE_VERSION constant is '1.2.0'
    - UpdateChecker calls setBranch('main') before enableReleaseAssets()
    - Debug logging added for update checker initialization
    - PHP syntax check passes on both files
  </done>
</task>

</tasks>

<verification>
1. `php -l invoiceforge.php` — no syntax errors
2. `php -l src/Core/UpdateChecker.php` — no syntax errors
3. Header Version and INVOICEFORGE_VERSION constant both read 1.2.0
4. UpdateChecker includes setBranch('main') call
5. After deploying to WordPress: Plugins page shows v1.2.0; with WP_DEBUG enabled, debug.log shows UpdateChecker initialization message
</verification>

<success_criteria>
- Plugin file header and constant both report version 1.2.0
- UpdateChecker explicitly sets branch to 'main' for reliable release detection
- Debug logging provides visibility into update checker status
- After next git tag + GitHub release, WordPress installations running older versions will see the update notification
</success_criteria>

<output>
After completion, create `.planning/quick/5-fix-wordpress-plugin-version-detection-a/5-SUMMARY.md`
</output>
</task>
