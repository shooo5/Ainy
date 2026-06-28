import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const file = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../assets/css/pages/match-invite.css');
let css = fs.readFileSync(file, 'utf8');
const scope = '.match-invite-container';

const skipPrefixes = [scope, '@', '.match-invite-', '.invite-', '.error-', '.success-', '.loading-'];

function shouldPrefix(selector) {
  const sel = selector.trim();
  if (!sel || sel.startsWith('@')) return false;
  for (const p of skipPrefixes) {
    if (sel.startsWith(p)) return false;
  }
  if (sel.startsWith(scope + ' ') || sel === scope) return false;
  return true;
}

function prefixSelectorList(selectorText) {
  return selectorText
    .split(',')
    .map((p) => p.trim())
    .filter(Boolean)
    .map((p) => (shouldPrefix(p) ? `${scope} ${p}` : p))
    .join(', ');
}

css = css.replace(/^(\s*)([^{@][^{]*?)\s*\{/gm, (match, indent, selector) => {
  if (selector.trim().startsWith('@')) return match;
  if (selector.includes(';')) return match;
  return `${indent}${prefixSelectorList(selector)} {`;
});

fs.writeFileSync(file, css, 'utf8');
console.log('Scoped match-invite.css');
