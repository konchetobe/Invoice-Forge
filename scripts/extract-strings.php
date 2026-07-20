<?php
/**
 * Extract all msgids from the .pot file for translation reference.
 */

$potFile = dirname(__DIR__) . '/languages/invoiceforge.pot';
$content = file_get_contents($potFile);
preg_match_all('/^msgid "([^"]*(?:\\\\.[^"]*)*)"$/m', $content, $matches);

$strings = [];
foreach ($matches[1] as $msgid) {
    $msgid = str_replace(['\\n', '\\t', '\\r', '\\"', '\\\\'], ["\n", "\t", "\r", '"', '\\'], $msgid);
    if ($msgid !== '' && !isset($strings[$msgid])) {
        $strings[$msgid] = true;
    }
}

echo count($strings) . " unique strings\n";
foreach (array_keys($strings) as $s) {
    echo "  " . $s . "\n";
}