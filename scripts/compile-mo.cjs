#!/usr/bin/env node
/**
 * compile-mo.cjs
 *
 * Compiles GNU gettext .po files to .mo binary format without any npm dependencies.
 * Reads all languages/invoiceforge-*.po files and writes corresponding .mo files.
 *
 * .mo file format (GNU gettext, little-endian):
 *   Offset  Size  Description
 *   0       4     Magic number: 0x950412de
 *   4       4     File format revision: 0
 *   8       4     Number of strings N
 *   12      4     Offset of table with original strings (O)
 *   16      4     Offset of table with translated strings (T)
 *   20      4     Size of hashing table (S): 0
 *   24      4     Offset of hashing table: 0
 *   O       N*8   Original string offset table: [length, offset] pairs
 *   T       N*8   Translated string offset table: [length, offset] pairs
 *   ...           String data (null-terminated)
 *
 * Strings in offset tables must be sorted by original string (byte order).
 */

'use strict';

const fs   = require('fs');
const path = require('path');

// ─── PO PARSER ────────────────────────────────────────────────────────────────

/**
 * Parse a .po file and return an array of {msgid, msgstr} objects.
 * Handles multiline strings (lines continuing with `"..."`).
 * Skips entries with empty msgstr (except the header entry with empty msgid).
 *
 * @param {string} content  Raw .po file content.
 * @returns {{ msgid: string, msgstr: string }[]}
 */
function parsePo(content) {
  const lines   = content.split(/\r?\n/);
  const entries = [];

  let currentMsgid   = null;
  let currentMsgstr  = null;
  let inMsgid        = false;
  let inMsgstr       = false;

  const unescape = (s) =>
    s
      .replace(/\\n/g, '\n')
      .replace(/\\t/g, '\t')
      .replace(/\\r/g, '\r')
      .replace(/\\\\/g, '\\')
      .replace(/\\"/g, '"');

  const extractQuoted = (line) => {
    const match = line.match(/^"(.*)"$/);
    return match ? unescape(match[1]) : null;
  };

  const flushEntry = () => {
    if (currentMsgid !== null && currentMsgstr !== null) {
      // Keep header (empty msgid) even though msgstr is non-empty.
      // Skip non-header entries with empty msgstr.
      if (currentMsgid === '' || currentMsgstr !== '') {
        entries.push({ msgid: currentMsgid, msgstr: currentMsgstr });
      }
    }
    currentMsgid  = null;
    currentMsgstr = null;
    inMsgid       = false;
    inMsgstr      = false;
  };

  for (const line of lines) {
    const trimmed = line.trim();

    if (trimmed === '' || trimmed.startsWith('#')) {
      // Blank line or comment: flush the current entry.
      if (inMsgid || inMsgstr) {
        flushEntry();
      }
      continue;
    }

    if (trimmed.startsWith('msgid ')) {
      if (inMsgid || inMsgstr) {
        flushEntry();
      }
      const val = trimmed.slice(6);
      const quoted = extractQuoted(val);
      currentMsgid = quoted !== null ? quoted : '';
      inMsgid  = true;
      inMsgstr = false;
      continue;
    }

    if (trimmed.startsWith('msgstr ')) {
      const val = trimmed.slice(7);
      const quoted = extractQuoted(val);
      currentMsgstr = quoted !== null ? quoted : '';
      inMsgid  = false;
      inMsgstr = true;
      continue;
    }

    // Plural msgstr[N] — treat as the first translation (index 0) for simplicity.
    if (trimmed.startsWith('msgstr[0] ')) {
      const val = trimmed.slice(10);
      const quoted = extractQuoted(val);
      currentMsgstr = quoted !== null ? quoted : '';
      inMsgid  = false;
      inMsgstr = true;
      continue;
    }

    // Continuation line: starts with `"`.
    if (trimmed.startsWith('"')) {
      const quoted = extractQuoted(trimmed);
      if (quoted !== null) {
        if (inMsgid && currentMsgid !== null) {
          currentMsgid += quoted;
        } else if (inMsgstr && currentMsgstr !== null) {
          currentMsgstr += quoted;
        }
      }
      continue;
    }
  }

  // Flush final entry.
  if (inMsgid || inMsgstr) {
    flushEntry();
  }

  return entries;
}

// ─── MO WRITER ────────────────────────────────────────────────────────────────

/**
 * Convert an array of {msgid, msgstr} entries to a binary .mo Buffer.
 *
 * @param {{ msgid: string, msgstr: string }[]} entries
 * @returns {Buffer}
 */
function buildMo(entries) {
  // Sort by original string (byte order, i.e. Buffer comparison).
  const sorted = entries.slice().sort((a, b) => {
    const ba = Buffer.from(a.msgid, 'utf8');
    const bb = Buffer.from(b.msgid, 'utf8');
    return ba.compare(bb);
  });

  const N = sorted.length;

  // Encode all strings as UTF-8 Buffers.
  const origBufs  = sorted.map(e => Buffer.from(e.msgid,   'utf8'));
  const transBufs = sorted.map(e => Buffer.from(e.msgstr,  'utf8'));

  // Header: 7 uint32 = 28 bytes.
  // Original offset table:   N * 8 bytes (length + offset per entry).
  // Translated offset table: N * 8 bytes.
  const HEADER_SIZE = 28;
  const TABLE_SIZE  = N * 8;

  const O = HEADER_SIZE;              // Offset of original table.
  const T = O + TABLE_SIZE;           // Offset of translated table.
  let   D = T + TABLE_SIZE;           // Offset of first string data.

  // Pre-compute string data offsets.
  const origOffsets  = [];
  const transOffsets = [];

  // Interleave: original strings first (each null-terminated), then translated.
  let offset = D;
  for (let i = 0; i < N; i++) {
    origOffsets.push(offset);
    offset += origBufs[i].length + 1; // +1 for null terminator
  }
  for (let i = 0; i < N; i++) {
    transOffsets.push(offset);
    offset += transBufs[i].length + 1;
  }

  const totalSize = offset;
  const buf = Buffer.alloc(totalSize, 0);

  // Write header.
  buf.writeUInt32LE(0x950412de, 0);  // magic
  buf.writeUInt32LE(0,           4);  // revision
  buf.writeUInt32LE(N,           8);  // number of strings
  buf.writeUInt32LE(O,          12);  // original table offset
  buf.writeUInt32LE(T,          16);  // translated table offset
  buf.writeUInt32LE(0,          20);  // hash table size
  buf.writeUInt32LE(0,          24);  // hash table offset

  // Write original string offset table.
  for (let i = 0; i < N; i++) {
    buf.writeUInt32LE(origBufs[i].length, O + i * 8);
    buf.writeUInt32LE(origOffsets[i],     O + i * 8 + 4);
  }

  // Write translated string offset table.
  for (let i = 0; i < N; i++) {
    buf.writeUInt32LE(transBufs[i].length, T + i * 8);
    buf.writeUInt32LE(transOffsets[i],     T + i * 8 + 4);
  }

  // Write string data.
  let writePos = D;
  for (let i = 0; i < N; i++) {
    origBufs[i].copy(buf, writePos);
    writePos += origBufs[i].length;
    buf[writePos++] = 0; // null terminator
  }
  for (let i = 0; i < N; i++) {
    transBufs[i].copy(buf, writePos);
    writePos += transBufs[i].length;
    buf[writePos++] = 0; // null terminator
  }

  return buf;
}

// ─── MAIN ─────────────────────────────────────────────────────────────────────

const repoRoot   = path.resolve(__dirname, '..');
const langDir    = path.join(repoRoot, 'languages');
const poGlob     = fs.readdirSync(langDir).filter(f => /^invoiceforge-.*\.po$/.test(f));

if (poGlob.length === 0) {
  console.error('No .po files found in ' + langDir);
  process.exit(1);
}

let allOk = true;

for (const poFile of poGlob) {
  const poPath = path.join(langDir, poFile);
  const moFile = poFile.replace(/\.po$/, '.mo');
  const moPath = path.join(langDir, moFile);

  try {
    const content = fs.readFileSync(poPath, 'utf8');
    const entries = parsePo(content);

    if (entries.length === 0) {
      console.warn('WARNING: No entries found in ' + poFile);
    }

    const moBuf = buildMo(entries);
    fs.writeFileSync(moPath, moBuf);

    console.log(
      'Compiled ' + poFile + ' -> ' + moFile +
      ' (' + entries.length + ' strings, ' + moBuf.length + ' bytes)'
    );
  } catch (err) {
    console.error('ERROR processing ' + poFile + ': ' + err.message);
    allOk = false;
  }
}

if (allOk) {
  console.log('\nAll .mo files compiled successfully.');
} else {
  console.error('\nSome files failed to compile.');
  process.exit(1);
}
