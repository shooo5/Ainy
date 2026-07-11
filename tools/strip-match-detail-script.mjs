import fs from 'fs';

const phpPath = new URL('../page-match-detail.php', import.meta.url);
let content = fs.readFileSync(phpPath, 'utf8');
content = content.replace(/<script>\s*\/\/ ※ 旧仕様[\s\S]*?<\/script>\s*\n*/, '');
fs.writeFileSync(phpPath, content);
console.log('inline script removed from page-match-detail.php');
