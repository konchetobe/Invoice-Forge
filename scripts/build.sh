#!/usr/bin/env bash
#
# InvoiceForge — Production build script
#
# Installs production dependencies (including mpdf/mpdf for PDF generation)
# and optimizes the autoloader. Run this before packaging a release ZIP.
#
# Usage:
#   bash scripts/build.sh
#
# Requires PHP and Composer to be available on PATH.

set -euo pipefail

echo "==> Installing production dependencies (no-dev, optimized autoloader)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Verifying mpdf/mpdf is installed..."
if [ ! -d vendor/mpdf/mpdf ]; then
    echo "ERROR: mpdf/mpdf is missing from vendor/. PDF generation will not work."
    echo "       Run 'composer install' to resolve dependencies."
    exit 1
fi

echo "==> Build complete."
