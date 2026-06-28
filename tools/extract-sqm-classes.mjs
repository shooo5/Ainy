import fs from 'fs';
const js = fs.readFileSync('assets/js/common/schedule-quick-modal.js', 'utf8');
const classes = new Set();
for (const m of js.matchAll(/\bclass(?:Name)?\s*=\s*["'`]([^"'`]+)["'`]/g)) {
  m[1].split(/\s+/).forEach((c) => {
    if (c && !c.includes('${')) classes.add(c);
  });
}
console.log([...classes].sort().join('\n'));
