import fs from 'fs';

const phpPath = new URL('../page-match-board-own.php', import.meta.url);
let content = fs.readFileSync(phpPath, 'utf8');
content = content.replace(
  /<script>\s*window\.aiduniteMatchBoardConfig[\s\S]*?<\/script>\s*\n?/m,
  ''
);
content = content.replace(/<script>[\s\S]*?<\/script>\s*\n?/g, '');
fs.writeFileSync(phpPath, content);
console.log('inline scripts removed from page-match-board-own.php');
