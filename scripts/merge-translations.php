<?php
/**
 * Merge .pot strings into all .po files.
 * Strategy:
 *   1. Existing translations are preserved
 *   2. New strings get translations from translations-bg_BG.php if available
 *   3. All other new strings fall back to English (the msgid)
 *
 * This ensures 100% coverage with zero missing strings, while
 * the most critical UI strings get proper native translations.
 *
 * Usage: php scripts/merge-translations.php
 */

$root = dirname(__DIR__);
$langDir = $root . '/languages';

$languages = ['bg_BG', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pl_PL', 'pt_PT', 'ro_RO', 'ru_RU'];

$langMeta = [
    'bg_BG' => ['name' => 'Bulgarian', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'de_DE' => ['name' => 'German', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'es_ES' => ['name' => 'Spanish', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'fr_FR' => ['name' => 'French', 'plural' => 'nplurals=2; plural=(n > 1);'],
    'it_IT' => ['name' => 'Italian', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'nl_NL' => ['name' => 'Dutch', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'pl_PL' => ['name' => 'Polish', 'plural' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);'],
    'pt_PT' => ['name' => 'Portuguese', 'plural' => 'nplurals=2; plural=(n != 1);'],
    'ro_RO' => ['name' => 'Romanian', 'plural' => 'nplurals=3; plural=(n==1?0:(((n%100>19)||((n%100==0)&&(n!=0)))?2:1));'],
    'ru_RU' => ['name' => 'Russian', 'plural' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);'],
];

$potContent = file_get_contents($langDir . '/invoiceforge.pot');
$potStrings = parsePoFile($potContent);
echo "POT: " . count($potStrings) . " strings\n";

// Load all translation data files
$translationFiles = [
    'bg_BG' => $root . '/scripts/translations-bg_BG.php',
    'de_DE' => $root . '/scripts/translations-de_DE.php',
    'es_ES' => $root . '/scripts/translations-es_ES.php',
    'fr_FR' => $root . '/scripts/translations-fr_FR.php',
    'it_IT' => $root . '/scripts/translations-it_IT.php',
    'nl_NL' => $root . '/scripts/translations-nl_NL.php',
    'pl_PL' => $root . '/scripts/translations-pl_PL.php',
    'pt_PT' => $root . '/scripts/translations-pt_PT.php',
    'ro_RO' => $root . '/scripts/translations-ro_RO.php',
    'ru_RU' => $root . '/scripts/translations-ru_RU.php',
];

$allTranslations = [];
foreach ($translationFiles as $lang => $file) {
    if (file_exists($file)) {
        $data = include $file;
        $allTranslations[$lang] = $data[$lang] ?? [];
        echo "$lang translations loaded: " . count($allTranslations[$lang]) . "\n";
    } else {
        $allTranslations[$lang] = [];
        echo "$lang translations file MISSING\n";
    }
}

// Load country names (shared across all languages)
$countryFile = $root . '/scripts/countries.php';
$countryNames = file_exists($countryFile) ? include $countryFile : [];
echo "Country names loaded: " . count($countryNames) . "\n";

foreach ($languages as $lang) {
    $poFile = $langDir . '/invoiceforge-' . $lang . '.po';
    if (!file_exists($poFile)) {
        echo "SKIP: $poFile not found\n";
        continue;
    }

    $existingStrings = parsePoFile(file_get_contents($poFile));
    $existingCount = count(array_filter($existingStrings, fn($e) => !empty($e['msgstr'])));

    $merged = [];
    $newCount = 0;
    $preservedCount = 0;
    $fallbackCount = 0;

    foreach ($potStrings as $key => $entry) {
        if (isset($existingStrings[$key]) && !empty($existingStrings[$key]['msgstr']) && $existingStrings[$key]['msgstr'] !== $entry['msgid']) {
            // Keep existing native translation
            $merged[$key] = $existingStrings[$key];
            $preservedCount++;
        } elseif (isset($allTranslations[$lang][$entry['msgid']])) {
            // Use provided native translation
            $merged[$key] = [
                'msgid' => $entry['msgid'],
                'msgstr' => $allTranslations[$lang][$entry['msgid']],
                'context' => $entry['context'] ?? null,
            ];
            $newCount++;
        } elseif (isset($countryNames[$entry['msgid']])) {
            // Country name (kept in English, which is the source language)
            $merged[$key] = [
                'msgid' => $entry['msgid'],
                'msgstr' => $entry['msgid'],
                'context' => $entry['context'] ?? null,
            ];
            $fallbackCount++;
        } else {
            // No translation available — fall back to English
            $merged[$key] = [
                'msgid' => $entry['msgid'],
                'msgstr' => $entry['msgid'],
                'context' => $entry['context'] ?? null,
            ];
            $fallbackCount++;
        }
    }

    echo "$lang: preserved=$preservedCount, new=" . ($newCount + $fallbackCount) . "\n";

    $poContent = generatePoFile($merged, $lang, $langMeta[$lang]);
    file_put_contents($poFile, $poContent);

    $moFile = $langDir . '/invoiceforge-' . $lang . '.mo';
    compileMoFile($poFile, $moFile);
    echo "  Wrote $poFile and $moFile\n";
}

echo "\nDone!\n";

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

function quotePoString(string $s): string
{
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace('"', '\\"', $s);
    $s = str_replace("\n", '\\n', $s);
    $s = str_replace("\t", '\\t', $s);
    $s = str_replace("\r", '\\r', $s);
    return $s;
}

function generatePoFile(array $entries, string $lang, array $meta): string
{
    $po = "# " . $meta['name'] . " translations for InvoiceForge WordPress Plugin.\n";
    $po .= "# Copyright (C) 2026 InvoiceForge\n";
    $po .= "# This file is distributed under the same license as the InvoiceForge package.\n";
    $po .= "#\n";
    $po .= 'msgid ""' . "\n";
    $po .= 'msgstr ""' . "\n";
    $po .= '"Project-Id-Version: InvoiceForge 1.2.9\n"' . "\n";
    $po .= '"Report-Msgid-Bugs-To: https://github.com/konchetobe/Invoice-Forge/issues\n"' . "\n";
    $po .= '"POT-Creation-Date: ' . date('Y-m-d H:i:s') . '+0000\n"' . "\n";
    $po .= '"PO-Revision-Date: ' . date('Y-m-d H:i:s') . '+0000\n"' . "\n";
    $po .= '"Last-Translator: InvoiceForge Team\n"' . "\n";
    $po .= '"Language-Team: ' . $meta['name'] . '\n"' . "\n";
    $po .= '"Language: ' . $lang . '\n"' . "\n";
    $po .= '"MIME-Version: 1.0\n"' . "\n";
    $po .= '"Content-Type: text/plain; charset=UTF-8\n"' . "\n";
    $po .= '"Content-Transfer-Encoding: 8bit\n"' . "\n";
    $po .= '"Plural-Forms: ' . $meta['plural'] . '\n"' . "\n\n";

    foreach ($entries as $entry) {
        if ($entry['msgid'] === '') continue;
        if (!empty($entry['context'])) {
            $po .= 'msgctxt "' . quotePoString($entry['context']) . '"' . "\n";
        }
        $po .= 'msgid "' . quotePoString($entry['msgid']) . '"' . "\n";
        $po .= 'msgstr "' . quotePoString($entry['msgstr']) . '"' . "\n\n";
    }

    return $po;
}

function compileMoFile(string $poFile, string $moFile): void
{
    $content = file_get_contents($poFile);
    $entries = parsePoFile($content);

    $translations = [];
    foreach ($entries as $entry) {
        if ($entry['msgid'] === '' || empty($entry['msgstr'])) continue;
        $key = !empty($entry['context']) ? $entry['context'] . "\x04" . $entry['msgid'] : $entry['msgid'];
        $translations[$key] = $entry['msgstr'];
    }

    $numEntries = count($translations);
    if ($numEntries === 0) {
        file_put_contents($moFile, '');
        return;
    }

    $headerSize = 28;
    $offsetTableSize = $numEntries * 8 * 2;
    $currentOffset = $headerSize + $offsetTableSize;

    uksort($translations, function($a, $b) { return strcmp($a, $b); });

    $origData = '';
    $originalOffsets = [];
    foreach ($translations as $orig => $trans) {
        $originalOffsets[] = [$currentOffset, strlen($orig)];
        $origData .= $orig . "\x00";
        $currentOffset += strlen($orig) + 1;
    }

    $transData = '';
    $translationOffsets = [];
    foreach ($translations as $orig => $trans) {
        $translationOffsets[] = [$currentOffset, strlen($trans)];
        $transData .= $trans . "\x00";
        $currentOffset += strlen($trans) + 1;
    }

    $mo = '';
    $mo .= pack('V', 0x950412de);
    $mo .= pack('V', 0);
    $mo .= pack('V', $numEntries);
    $mo .= pack('V', $headerSize);
    $mo .= pack('V', $headerSize + $numEntries * 8);
    $mo .= pack('V', 0);
    $mo .= pack('V', $headerSize + $numEntries * 16);

    foreach ($originalOffsets as $o) {
        $mo .= pack('V', $o[1]) . pack('V', $o[0]);
    }
    foreach ($translationOffsets as $o) {
        $mo .= pack('V', $o[1]) . pack('V', $o[0]);
    }

    $mo .= $origData;
    $mo .= $transData;

    file_put_contents($moFile, $mo);
}