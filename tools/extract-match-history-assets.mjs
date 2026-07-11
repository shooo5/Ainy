import fs from 'fs';

const phpPath = new URL('../page-match-history.php', import.meta.url);
const content = fs.readFileSync(phpPath, 'utf8');

const styleMatch = content.match(/<style>\s*\/\* 申請履歴ページ専用スタイル \*\/[\s\S]*?<\/style>/);
const scriptMatch = content.match(/<script>\s*jQuery\(document\)\.ready[\s\S]*?<\/script>/);

if (!styleMatch || !scriptMatch) {
  console.error('match-history assets not found');
  process.exit(1);
}

let css = styleMatch[0].replace(/^<style>\s*/, '').replace(/<\/style>\s*$/, '');
let js = scriptMatch[0].replace(/^<script>\s*/, '').replace(/<\/style>\s*$/, '').replace(/<\/script>\s*$/, '');

css = `/**\n * 申請履歴（page-match-history.php）\n */\n` + css;

js = js.replace(
  /jQuery\(document\)\.ready\(function\(\$\) \{/,
  `(function ($) {
  'use strict';
  var cfg = typeof aiduniteMatchHistoryPage !== 'undefined' ? aiduniteMatchHistoryPage : {};

  $(function () {`
);

js = js
  .replace(/'<\?php echo date\('Y-m-d', strtotime\('-3 months'\)\); \?>'/g, "cfg.defaultDateFrom || ''")
  .replace(/'<\?php echo date\('Y-m-d'\); \?>'/g, "cfg.defaultDateTo || ''")
  .replace(/'<\?php echo esc_url\(rest_url\('aidunite\/v1\/match-history'\)\); \?>'/g, "cfg.historyUrl || ''")
  .replace(/'<\?php echo wp_create_nonce\("wp_rest"\); \?>'/g, "cfg.restNonce || ''");

js = js.replace(/\}\);\s*$/, `  });
})(jQuery);\n`);

fs.writeFileSync(new URL('../assets/css/pages/match-history.css', import.meta.url), css);
fs.writeFileSync(new URL('../assets/js/pages/match-history.js', import.meta.url), `/**\n * 申請履歴（page-match-history.php）\n */\n` + js);

let php = content.replace(styleMatch[0] + '\n', '').replace(scriptMatch[0] + '\n', '');
fs.writeFileSync(phpPath, php);
console.log('match-history assets extracted');
