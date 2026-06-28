import fs from 'fs';

const phpPath = new URL('../page-team-management.php', import.meta.url);
const outPath = new URL('../assets/js/pages/team-management.js', import.meta.url);
let content = fs.readFileSync(phpPath, 'utf8');
const match = content.match(/<script>\s*\(function\(\)[\s\S]*?<\/script>/);
if (!match) {
  console.error('team-management script not found');
  process.exit(1);
}

let body = match[0].replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');
const header = `/**
 * チーム管理（page-team-management.php）
 */
`;
fs.writeFileSync(outPath, header + body + '\n');
content = content.replace(match[0] + '\n', '');
fs.writeFileSync(phpPath, content);
console.log('team-management.js written');
