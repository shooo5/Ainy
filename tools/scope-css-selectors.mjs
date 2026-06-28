#!/usr/bin/env node
/**
 * Prefix top-level CSS selector lines (heuristic: ends with {, no semicolon before {, not @-rule).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const [fileArg, scopeClass, ...rest] = process.argv.slice(2);
const skipArg = rest.find((a) => a.startsWith('--skip='));
const skipPrefixes = skipArg
  ? skipArg.replace('--skip=', '').split(',').map((s) => s.trim()).filter(Boolean)
  : [];

if (!fileArg || !scopeClass) {
  console.error('Usage: node scope-css-selectors.mjs <file> <scope-class> [--skip=a,b]');
  process.exit(1);
}

const filePath = path.resolve(__dirname, '..', fileArg);
const scope = scopeClass.startsWith('.') ? scopeClass : `.${scopeClass}`;
const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);

function isSelectorLine(line) {
  const trimmed = line.trim();
  if (!trimmed.endsWith('{')) return false;
  if (trimmed.startsWith('@')) return false;
  const beforeBrace = trimmed.slice(0, -1);
  if (beforeBrace.includes(';')) return false;
  return true;
}

function shouldPrefix(selector) {
  const sel = selector.trim();
  if (!sel) return false;
  for (const p of skipPrefixes) {
    if (sel.startsWith(p)) return false;
  }
  if (sel.startsWith(scope + ' ') || sel === scope) return false;
  return true;
}

function prefixSelectorList(selectorText) {
  return selectorText
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => (shouldPrefix(part) ? `${scope} ${part}` : part))
    .join(', ');
}

const out = lines.map((line) => {
  if (!isSelectorLine(line)) return line;
  const m = line.match(/^(\s*)(.+)\{\s*$/);
  if (!m) return line;
  const [, indent, selector] = m;
  return `${indent}${prefixSelectorList(selector)} {`;
});

fs.writeFileSync(filePath, out.join('\n'), 'utf8');
console.log(`Scoped ${filePath} with ${scope}`);
