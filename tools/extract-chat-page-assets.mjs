import fs from 'fs';

const phpPath = new URL('../page-chat.php', import.meta.url);
const content = fs.readFileSync(phpPath, 'utf8');

const styleMatch = content.match(/<style>\s*\.chat-container[\s\S]*?<\/style>/);
const scriptMatch = content.match(/<script>\s*document\.addEventListener\('DOMContentLoaded'[\s\S]*?<\/script>/);

if (!styleMatch || !scriptMatch) {
  console.error('chat style/script not found');
  process.exit(1);
}

let css = styleMatch[0].replace(/^<style>\s*/, '').replace(/<\/style>\s*$/, '');
let js = scriptMatch[0].replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');

css = `/**\n * チャット画面（page-chat.php）\n */\n` + css;

js = js.replace(
  /^document\.addEventListener\('DOMContentLoaded', function\(\) \{/,
  `(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = typeof aiduniteChatPage !== 'undefined' ? aiduniteChatPage : {};
    var msgCfg = typeof aidunite_messaging !== 'undefined' ? aidunite_messaging : {};
    var restBase = (msgCfg.rest_url || cfg.restUrl || '/wp-json/aidunite/v1/').replace(/\\/?$/, '/');
`
);

js = js
  .replace(/let currentRoomId = <\?php echo \(int\) \$chat_id; \?>;/, 'let currentRoomId = parseInt(String(cfg.chatId || 0), 10) || 0;')
  .replace(/const nonce = '<\?php echo wp_create_nonce\('wp_rest'\); \?>';/, "const nonce = cfg.restNonce || msgCfg.nonce || '';")
  .replace(/const userId = <\?php echo get_current_user_id\(\); \?>;/, 'const userId = parseInt(String(cfg.userId || msgCfg.user_id || 0), 10) || 0;')
  .replace(/const roomType = '<\?php echo \$room_type; \?>';/, "const roomType = cfg.roomType || '';")
  .replace(/const chatIconsUrl = '<\?php echo esc_js\(aidunite_get_theme_icons_uri\(\)\); \?>';/, "const chatIconsUrl = cfg.chatIconsUrl || '';")
  .replace(/let newTitle = '<\?php echo esc_js\(\$chat_header_title\); \?>';/, "let newTitle = cfg.defaultHeaderTitle || '';")
  .replace(/fetch\('\/wp-json\/aidunite\/v1\//g, "fetch(restBase + '")
  .replace(/<\?php if \(\$room_type === 'match'\): \?>/g, "if (roomType === 'match') {")
  .replace(/<\?php if \(\$room_type === 'match' \|\| \$room_type === 'group'\): \?>/g, "if (roomType === 'match' || roomType === 'group') {")
  .replace(/<\?php endif; \?>/g, '}');

js = js.replace(/\}\);\s*$/, `  });
})();\n`);

const cssPath = new URL('../assets/css/pages/chat-page.css', import.meta.url);
const jsPath = new URL('../assets/js/pages/chat-page.js', import.meta.url);

fs.writeFileSync(cssPath, css);
fs.writeFileSync(jsPath, js);

let php = content.replace(styleMatch[0] + '\n', '').replace(scriptMatch[0] + '\n', '');
fs.writeFileSync(phpPath, php);
console.log('chat-page assets extracted');
