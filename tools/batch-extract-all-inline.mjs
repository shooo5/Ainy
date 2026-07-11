import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const rootPath = fileURLToPath(new URL('..', import.meta.url));

const pages = [
  'page-about.php',
  'page-disclaimer.php',
  'page-cookie-policy.php',
  'page-privacy-policy.php',
  'page-terms-of-service.php',
  'page-plan-info.php',
  'page-service.php',
  'page-payment-required.php',
  'page-team-approval.php',
  'page-player-stats.php',
  'page-match-log-view.php',
  'page-edit-player.php',
  'page-contact.php',
  'page-faq.php',
  'page-feedback.php',
  'page-guide.php',
  'page-press.php',
  'page-regulation.php',
  'page-system-maintenance.php',
  'page-template-dashboard.php',
  'page-admin-schedule-list.php',
  'page-admin-match-feedback-list.php',
  'page-admin-user-list.php',
  'page-match-analytics.php',
  'page-parent-payment.php',
  'page-team-payment-management.php',
  'page-registration-preview.php',
  'page-team-registration-preview.php',
  'page-sample-preview.php',
];

const phpReplacements = [
  [/url: '<\?php echo admin_url\('admin-ajax\.php'\); \?>'/g, "url: (cfg.ajaxUrl || '')"],
  [/url: '<\?php echo esc_url\(admin_url\('admin-ajax\.php'\)\); \?>'/g, "url: (cfg.ajaxUrl || '')"],
  [/nonce: '<\?php echo wp_create_nonce\('aidunite_payment_nonce'\); \?>'/g, "nonce: (cfg.paymentNonce || '')"],
  [/nonce: '<\?php echo wp_create_nonce\("wp_rest"\); \?>'/g, "nonce: (cfg.restNonce || '')"],
  [/xhr\.setRequestHeader\('X-WP-Nonce', '<\?php echo wp_create_nonce\("wp_rest"\); \?>'\)/g, "xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce || '')"],
  [/team_id: <\?php echo \$team_id; \?>/g, 'team_id: parseInt(String(cfg.teamId || 0), 10)'],
  [/const boardTierOrder = <\?php echo wp_json_encode\(\$match_analytics_board_tier_order\); \?>;/g, 'const boardTierOrder = cfg.boardTierOrder || [];'],
  [/const boardTierLabels = <\?php echo wp_json_encode\(\$match_analytics_board_tier_labels\); \?>;/g, 'const boardTierLabels = cfg.boardTierLabels || {};'],
  [/url: '<\?php echo esc_url\(rest_url\('aidunite\/v1\/match-analytics'\)\); \?>'/g, "url: (cfg.analyticsUrl || '')"],
];

function extractStyles(content) {
  const parts = [];
  let m;
  const styleRe = /<style>([\s\S]*?)<\/style>/g;
  while ((m = styleRe.exec(content)) !== null) {
    parts.push(m[1].trim());
  }
  const echoStyleRe = /echo\s+'<style>([\s\S]*?)<\/style>';/g;
  while ((m = echoStyleRe.exec(content)) !== null) {
    parts.push(m[1].trim());
  }
  return parts;
}

function extractScripts(content) {
  const parts = [];
  let m;
  const scriptRe = /<script>([\s\S]*?)<\/script>/g;
  while ((m = scriptRe.exec(content)) !== null) {
    parts.push(m[1].trim());
  }
  return parts;
}

function stripInline(content) {
  let out = content.replace(/<style>[\s\S]*?<\/style>\s*\n?/g, '');
  out = out.replace(/<script>[\s\S]*?<\/script>\s*\n?/g, '');
  out = out.replace(/echo\s+'<style>[\s\S]*?<\/style>';\s*\n?/g, '');
  return out;
}

const manifest = [];

for (const pageFile of pages) {
  const phpPath = path.join(rootPath, pageFile);
  if (!fs.existsSync(phpPath)) {
    console.warn('skip missing', pageFile);
    continue;
  }
  let content = fs.readFileSync(phpPath, 'utf8');
  const slug = pageFile.replace(/^page-/, '').replace(/\.php$/, '');
  const styles = extractStyles(content);
  const scripts = extractScripts(content);

  if (styles.length === 0 && scripts.length === 0) {
    console.log('no inline assets:', pageFile);
    continue;
  }

  if (styles.length > 0) {
    const css = `/**\n * ${pageFile}\n */\n` + styles.join('\n\n');
    const cssPath = path.join(rootPath, 'assets/css/pages', slug + '.css');
    fs.writeFileSync(cssPath, css);
  }

  if (scripts.length > 0) {
    let jsBody = scripts.join('\n\n');
    for (const [re, rep] of phpReplacements) {
      jsBody = jsBody.replace(re, rep);
    }
    const hasPhp = jsBody.includes('<?php');
    const needsCfg = jsBody.includes('cfg.') || hasPhp;
    let js = `/**\n * ${pageFile}\n */\n`;
    if (needsCfg && !jsBody.includes('aidunite') && !jsBody.includes('cfg')) {
      // noop
    }
    if (jsBody.includes('jQuery') || jsBody.includes('$(')) {
      js += `(function ($) {\n  'use strict';\n  var cfg = typeof aidunitePage_${slug.replace(/-/g, '_')} !== 'undefined' ? aidunitePage_${slug.replace(/-/g, '_')} : {};\n\n`;
      if (jsBody.includes('jQuery(document).ready') || jsBody.includes('jQuery(function')) {
        jsBody = jsBody.replace(/jQuery\(document\)\.ready\(function\s*\(\$\)\s*\{/, '$(function () {');
        jsBody = jsBody.replace(/jQuery\(function\s*\(\$\)\s*\{/, '$(function () {');
      }
      js += jsBody + '\n})(jQuery);\n';
    } else {
      js += `(function () {\n  'use strict';\n  var cfg = typeof aidunitePage_${slug.replace(/-/g, '_')} !== 'undefined' ? aidunitePage_${slug.replace(/-/g, '_')} : {};\n\n`;
      js += jsBody + '\n})();\n';
    }
    const jsPath = path.join(rootPath, 'assets/js/pages', slug + '.js');
    fs.writeFileSync(jsPath, js);
    if (hasPhp) {
      console.warn('PHP remnants in', slug + '.js');
    }
  }

  fs.writeFileSync(phpPath, stripInline(content));
  manifest.push({
    page: pageFile,
    slug,
    css: styles.length > 0,
    js: scripts.length > 0,
  });
  console.log('extracted', pageFile, styles.length, 'css', scripts.length, 'js');
}

fs.writeFileSync(
  path.join(rootPath, 'tools/page-asset-manifest.json'),
  JSON.stringify(manifest, null, 2)
);
console.log('manifest written,', manifest.length, 'pages');
