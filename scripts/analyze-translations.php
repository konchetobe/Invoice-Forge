<?php
/**
 * Analyze translation completeness: find empty or English-fallback strings.
 * Usage: php scripts/analyze-translations.php
 */

$root = dirname(__DIR__);
$langDir = $root . '/languages';
$languages = ['bg_BG', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pl_PL', 'pt_PT', 'ro_RO', 'ru_RU'];

// Parse .pot for total strings
$potContent = file_get_contents($langDir . '/invoiceforge.pot');
$potEntries = parsePoFile($potContent);
$totalStrings = count($potEntries);
echo "POT total strings: $totalStrings\n\n";

foreach ($languages as $lang) {
    $poFile = $langDir . '/invoiceforge-' . $lang . '.po';
    if (!file_exists($poFile)) {
        echo "$lang: FILE MISSING\n";
        continue;
    }
    $content = file_get_contents($poFile);
    $entries = parsePoFile($content);

    $empty = [];
    $englishFallback = [];
    $translated = [];

    foreach ($entries as $key => $entry) {
        if ($entry['msgid'] === '') continue;
        $msgstr = $entry['msgstr'];
        if ($msgstr === '') {
            $empty[] = $entry['msgid'];
        } elseif ($msgstr === $entry['msgid']) {
            // English fallback (msgstr == msgid)
            $englishFallback[] = $entry['msgid'];
        } else {
            $translated[] = $entry['msgid'];
        }
    }

    echo "=== $lang ===\n";
    echo "  Total entries: " . count($entries) . "\n";
    echo "  Translated (native): " . count($translated) . "\n";
    echo "  English fallback (msgstr==msgid): " . count($englishFallback) . "\n";
    echo "  Empty: " . count($empty) . "\n";

    if (!empty($englishFallback)) {
        echo "  English-fallback strings (first 40):\n";
        $i = 0;
        foreach ($englishFallback as $s) {
            if ($i++ >= 40) { echo "    ... and " . (count($englishFallback) - 40) . " more\n"; break; }
            echo "    - $s\n";
        }
    }
    echo "\n";
}

function parsePoFile(string $content): array
{
    $entries = [];
    $lines = explode("\n", $content);
    $currentMsgctxt = null;
    $currentMsgid = null;
    $currentMsgstr = null;
    $state = null;

    foreach ($lines as $line) {
        if (strpos($line, 'msgctxt ') === 0) {
            $currentMsgctxt = unquote(substr($line, 8));
            $state = 'msgctxt';
        } elseif (strpos($line, 'msgid ') === 0) {
            if ($currentMsgid !== null) {
                $key = $currentMsgctxt !== null ? "ctx:" . $currentMsgctxt . "\x04" . $currentMsgid : $currentMsgid;
                if (!isset($entries[$key])) {
                    $entries[$key] = ['msgid' => $currentMsgid, 'msgstr' => $currentMsgstr ?? '', 'context' => $currentMsgctxt];
                }
            }
            $currentMsgid = unquote(substr($line, 6));
            $currentMsgstr = null;
            $state = 'msgid';
        } elseif (strpos($line, 'msgstr ') === 0) {
            $currentMsgstr = unquote(substr($line, 7));
            $state = 'msgstr';
        } elseif ($line === '') {
            if ($currentMsgid !== null) {
                $key = $currentMsgctxt !== null ? "ctx:" . $currentMsgctxt . "\x04" . $currentMsgid : $currentMsgid;
                if (!isset($entries[$key])) {
                    $entries[$key] = ['msgid' => $currentMsgid, 'msgstr' => $currentMsgstr ?? '', 'context' => $currentMsgctxt];
                }
            }
            $currentMsgctxt = null;
            $state = null;
        } elseif ($line[0] === '"') {
            if ($state === 'msgid') $currentMsgid .= unquote($line);
            elseif ($state === 'msgstr') $currentMsgstr .= unquote($line);
            elseif ($state === 'msgctxt') $currentMsgctxt .= unquote($line);
        }
    }

    if ($currentMsgid !== null) {
        $key = $currentMsgctxt !== null ? "ctx:" . $currentMsgctxt . "\x04" . $currentMsgid : $currentMsgid;
        if (!isset($entries[$key])) {
            $entries[$key] = ['msgid' => $currentMsgid, 'msgstr' => $currentMsgstr ?? '', 'context' => $currentMsgctxt];
        }
    }

    return $entries;
}

function unquote(string $s): string
{
    $s = trim($s);
    if (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') {
        $s = substr($s, 1, -1);
    }
    $s = str_replace('\\"', '"', $s);
    $s = str_replace('\\n', "\n", $s);
    $s = str_replace('\\t', "\t", $s);
    $s = str_replace('\\\\', '\\', $s);
    return $s;
}
