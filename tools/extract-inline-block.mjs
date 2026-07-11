import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = fileURLToPath(new URL('..', import.meta.url));
const [sourceRel, startMarker, endMarker, outRel, header] = process.argv.slice(2);

const source = fs.readFileSync(path.join(root, sourceRel), 'utf8');
const start = source.indexOf(startMarker);
const end = source.indexOf(endMarker, start + startMarker.length);
if (start === -1 || end === -1) {
  console.error('markers not found', sourceRel);
  process.exit(1);
}
let body = source.slice(start + startMarker.length, end).trim();
if (header) {
  body = header + '\n' + body + '\n';
}
const outPath = path.join(root, outRel);
fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, body);
console.log('wrote', outRel, body.length, 'chars');
