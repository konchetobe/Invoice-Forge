@echo off
REM
REM InvoiceForge - Production build script (Windows)
REM
REM Installs production dependencies (including mpdf/mpdf for PDF generation)
REM and optimizes the autoloader. Run this before packaging a release ZIP.
REM
REM Requires PHP and Composer to be available on PATH.

echo ==^> Installing production dependencies (no-dev, optimized autoloader)...
call composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo ERROR: composer install failed.
    exit /b 1
)

echo ==^> Verifying mpdf/mpdf is installed...
if not exist vendor\mpdf\mpdf (
    echo ERROR: mpdf/mpdf is missing from vendor\. PDF generation will not work.
    echo        Run 'composer install' to resolve dependencies.
    exit /b 1
)

echo ==^> Build complete.
