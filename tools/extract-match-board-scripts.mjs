import fs from 'fs';

const phpPath = new URL('../page-match-board-own.php', import.meta.url);
const outPath = new URL('../assets/js/pages/match-board-page.js', import.meta.url);
const content = fs.readFileSync(phpPath, 'utf8');
const scripts = [...content.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1].trim());

let body = scripts.slice(1).join('\n\n');
body = body.replace(
  /var nonce = <\?php echo json_encode\(wp_create_nonce\('au_match_nonce'\)\); \?>;/,
  "var nonce = (typeof aiduniteMatchBoardPage !== 'undefined' && aiduniteMatchBoardPage.matchNonce) || '';"
);
body = body.replace(
  /var ajaxUrl = <\?php echo json_encode\(admin_url\('admin-ajax\.php'\)\); \?>;/,
  "var ajaxUrl = (typeof aiduniteMatchBoardPage !== 'undefined' && aiduniteMatchBoardPage.ajaxUrl) || '';"
);

const header = `/**
 * マッチボード（page-match-board-own.php）
 * aiduniteMatchBoardConfig / aiduniteMatchBoardPage は wp_localize_script で注入
 */
(function () {
  'use strict';

`;
const footer = '\n})();\n';

fs.writeFileSync(outPath, header + body + footer);
console.log('match-board-page.js written');
