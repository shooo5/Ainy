import fs from 'fs';

const phpPath = new URL('../page-payment-setup.php', import.meta.url);
const content = fs.readFileSync(phpPath, 'utf8');

const styleMatch = content.match(/<style>\s*\.payment-setup-container[\s\S]*?<\/style>/);
const scriptMatch = content.match(/<script>\s*jQuery\(document\)\.ready[\s\S]*?<\/script>/);

if (!styleMatch || !scriptMatch) {
  console.error('style or script block not found');
  process.exit(1);
}

const cssPath = new URL('../assets/css/pages/payment-setup.css', import.meta.url);
const jsPath = new URL('../assets/js/pages/payment-setup.js', import.meta.url);

let css = styleMatch[0].replace(/^<style>\s*/, '').replace(/<\/style>\s*$/, '');
let js = scriptMatch[0].replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');

css = `/**\n * 支払い設定ページ（page-payment-setup.php）\n */\n` + css;

js = js
  .replace(/jQuery\(document\)\.ready\(function\(\$\) \{/, `(function ($) {\n  'use strict';\n\n  var cfg = typeof aidunitePaymentSetupPage !== 'undefined' ? aidunitePaymentSetupPage : {};\n\n`)
  .replace(/\}\);\s*$/, `})(jQuery);\n`);

js = js.replace(
  /\/\/ お試しカード[\s\S]*?<\?php if \(\$plan_selection_required && \$plan_display_mode === 'trial_card'\): \?>/,
  'if (cfg.features && cfg.features.trialCard) {'
);
js = js.replace(/<\?php endif; \?>\s*\n\s*\/\/ プラン選択時/, '}\n\n  // プラン選択時');
js = js.replace(
  /<\?php if \(\$plan_selection_required && \$plan_display_mode !== 'coming_soon' && \$plan_display_mode !== 'trial_card'\): \?>/,
  'if (cfg.features && cfg.features.planRadio) {'
);
js = js.replace(/<\?php endif; \?>\s*\n\s*\/\/ 支払い方法変更/, '}\n\n  // 支払い方法変更');

js = js
  .replace(/'<\?php echo esc_url\(admin_url\('admin-ajax\.php'\)\); \?>'/g, 'cfg.ajaxUrl || \'\'')
  .replace(/'<\?php echo admin_url\('admin-ajax\.php'\); \?>'/g, 'cfg.ajaxUrl || \'\'')
  .replace(/'<\?php echo wp_create_nonce\('aidunite_plan_selection_nonce'\); \?>'/g, 'cfg.planSelectionNonce || \'\'')
  .replace(/'<\?php echo wp_create_nonce\('aidunite_payment_nonce'\); \?>'/g, 'cfg.paymentNonce || \'\'')
  .replace(/const paymentTeamId = <\?php echo \(int\) \$team_id; \?>;/, 'const paymentTeamId = parseInt(String(cfg.teamId || 0), 10);')
  .replace(/const paymentRestNonce = '<\?php echo esc_js\(wp_create_nonce\('wp_rest'\)\); \?>';/, 'const paymentRestNonce = cfg.restNonce || \'\';');

js = js.replace(
  "fetch('/wp-json/aidunite/v1/payment-exit/cancel'",
  "fetch((cfg.restBase || '/wp-json/aidunite/v1/') + 'payment-exit/cancel'"
);
js = js.replace(
  "fetch('/wp-json/aidunite/v1/payment-exit/evaluate?team_id=' + paymentTeamId",
  "fetch((cfg.restBase || '/wp-json/aidunite/v1/') + 'payment-exit/evaluate?team_id=' + paymentTeamId"
);

js = js.replace(
  /toast\.style\.opacity = '0';\s*toast\.style\.transform = 'translateY\(-20px\)';/,
  "toast.classList.add('is-leaving');"
);

const jsHeader = `/**\n * 支払い設定ページ（page-payment-setup.php）\n */\n`;
const jsFooter = '\n';

fs.writeFileSync(cssPath, css);
fs.writeFileSync(jsPath, jsHeader + js + jsFooter);

let php = content.replace(styleMatch[0] + '\n', '').replace(scriptMatch[0] + '\n', '');
fs.writeFileSync(phpPath, php);
console.log('payment-setup CSS/JS extracted and inline blocks removed');
