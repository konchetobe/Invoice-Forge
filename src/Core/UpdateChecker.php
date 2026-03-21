<?php
/**
 * GitHub Update Checker
 *
 * Integrates with the yahnis-elsts/plugin-update-checker library
 * to provide automatic updates via GitHub Releases.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles GitHub-based plugin auto-updates.
 *
 * @since 1.1.0
 */
class UpdateChecker
{
    /**
     * GitHub repository slug (username/repo).
     *
     * @since 1.1.0
     * @var string
     */
    private const GITHUB_REPO = 'konchetobe/Invoice-Forge';

    /**
     * Initialize the update checker.
     *
     * Requires yahnis-elsts/plugin-update-checker to be installed
     * via Composer. Silently skips if the library is not available.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function init(): void
    {
        // Guard: Library must be installed via composer require yahnis-elsts/plugin-update-checker
        if (!class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
            return;
        }

        try {
            $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/' . self::GITHUB_REPO,
                INVOICEFORGE_PLUGIN_FILE,
                'invoiceforge'
            );

            // Point at the main branch so the library resolves tags correctly
            $updateChecker->setBranch('main');

            // Require a release asset ZIP (do not fall back to GitHub's auto-generated
            // source archive, which never contains the vendor/ directory).
            $updateChecker->getVcsApi()->enableReleaseAssets(
                null,
                \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\Api::REQUIRE_RELEASE_ASSETS
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[InvoiceForge] UpdateChecker initialized for ' . self::GITHUB_REPO . ' (current version: ' . INVOICEFORGE_VERSION . ')');
            }

        } catch (\Throwable $e) {
            // Silently fail — update checking is non-critical
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[InvoiceForge] UpdateChecker failed: ' . $e->getMessage());
            }
        }
    }
}
