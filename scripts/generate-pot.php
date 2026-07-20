<?php
/**
 * Generate .pot file by scanning all PHP source files for translatable strings.
 * Usage: php scripts/generate-pot.php
 */

$root = dirname(__DIR__);
$textDomain = 'invoiceforge';

// Files to scan
$scanDirs = [
    $root . '/invoiceforge.php',
    $root . '/uninstall.php',
];
$scanDirs = array_merge($scanDirs, glob($root . '/src/**/*.php'));
$scanDirs = array_merge($scanDirs, glob($root . '/templates/**/*.php'));
$scanDirs = array_merge($scanDirs, glob($root . '/src/*.php'));

// Remove duplicates
$scanDirs = array_unique($scanDirs);

// Functions to search for: __(, esc_html__(, esc_attr__(, _e(, esc_html_e(, esc_attr_e(, _x(, esc_html_x(, esc_attr_x(, _n(, _nx(
$patterns = [
    '/__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_html__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_attr__\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_html_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_attr_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/_e\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/_x\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_html_x\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/esc_attr_x\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
    '/_n\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*,\s*[^,]+,\s*[\'"]' . preg_quote($textDomain, '/') . '[\'"]\s*\)/s',
];

$strings = [];

foreach ($scanDirs as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    $relPath = str_replace('\\', '/', $relPath);

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $fullMatch) {
                $msgid = $matches[1][$i][0];
                $context = isset($matches[2]) && !empty($matches[2][$i][0]) ? $matches[2][$i][0] : null;

                // Unescape
                $msgid = stripcslashes($msgid);
                if ($context) $context = stripcslashes($context);

                $key = $context ? 'ctx:' . $context . "\x04" . $msgid : $msgid;
                if (!isset($strings[$key])) {
                    $strings[$key] = [
                        'msgid' => $msgid,
                        'context' => $context,
                        'references' => [],
                    ];
                }
                $strings[$key]['references'][] = $relPath;
            }
        }
    }
}

// Sort by msgid
ksort($strings);

// Generate .pot content
$pot = "# Translation template for InvoiceForge\n";
$pot .= "# Copyright (C) 2026 InvoiceForge\n";
$pot .= "# This file is distributed under the GPL-2.0-or-later license.\n";
$pot .= 'msgid ""' . "\n";
$pot .= 'msgstr ""' . "\n";
$pot .= '"Project-Id-Version: InvoiceForge 1.2.9\n"' . "\n";
$pot .= '"Report-Msgid-Bugs-To: https://github.com/konchetobe/Invoice-Forge/issues\n"' . "\n";
$pot .= '"POT-Creation-Date: ' . date('Y-m-d H:i:s') . '+0000\n"' . "\n";
$pot .= '"MIME-Version: 1.0\n"' . "\n";
$pot .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
$pot .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
$pot .= '"X-Generator: InvoiceForge POT Generator\n"' . "\n";
$pot .= '"X-Domain: invoiceforge\n"' . "\n\n";

foreach ($strings as $entry) {
    $references = array_unique($entry['references']);
    $refLine = '#: ' . implode(' ', array_slice($references, 0, 5)) . "\n";

    if ($entry['context']) {
        $pot .= $refLine;
        $pot .= 'msgctxt "' . escapePoString($entry['context']) . '"' . "\n";
        $pot .= 'msgid "' . escapePoString($entry['msgid']) . '"' . "\n";
        $pot .= 'msgstr ""' . "\n\n";
    } else {
        $pot .= $refLine;
        $pot .= 'msgid "' . escapePoString($entry['msgid']) . '"' . "\n";
        $pot .= 'msgstr ""' . "\n\n";
    }
}

$outputFile = $root . '/languages/invoiceforge.pot';
file_put_contents($outputFile, $pot);
echo "Generated " . count($strings) . " strings in " . $outputFile . "\n";

function escapePoString(string $s): string
{
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace('"', '\\"', $s);
    $s = str_replace("\n", '\\n', $s);
    $s = str_replace("\t", '\\t', $s);
    $s = str_replace("\r", '\\r', $s);
    return $s;
}
