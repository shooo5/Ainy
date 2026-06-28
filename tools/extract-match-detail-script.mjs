import fs from 'fs';

const phpPath = new URL('../page-match-detail.php', import.meta.url);
const outPath = new URL('../assets/js/pages/match-detail-page.js', import.meta.url);
const content = fs.readFileSync(phpPath, 'utf8');
const match = content.match(/<script>\s*\/\/ ※ 旧仕様[\s\S]*?<\/script>/);
if (!match) {
  console.error('script block not found');
  process.exit(1);
}

let body = match[0].replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');

const pairs = [
  ["'<?php echo $my_schedule_data['start']; ?>'", "(cfg.mySchedule && cfg.mySchedule.start) || ''"],
  ["'<?php echo $my_schedule_data['end']; ?>'", "(cfg.mySchedule && cfg.mySchedule.end) || ''"],
  ["'<?php echo $other_schedule_data['start']; ?>'", "(cfg.otherSchedule && cfg.otherSchedule.start) || ''"],
  ["'<?php echo $other_schedule_data['end']; ?>'", "(cfg.otherSchedule && cfg.otherSchedule.end) || ''"],
  ["'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'", "cfg.restNonce || ''"],
  ["'<?php echo esc_url(rest_url('aidunite/v1/match-request')); ?>'", "cfg.matchRequestUrl || ''"],
  ["'<?php echo esc_js($resolved_gender_value ?? 'both'); ?>'", "cfg.resolvedGender || 'both'"],
  ["'<?php echo esc_js($resolved_place_value ?? \"home\"); ?>'", "cfg.resolvedPlace || 'home'"],
  ["'<?php echo esc_js($my_schedule_data['start'] ?? ''); ?>'", "(cfg.mySchedule && cfg.mySchedule.start) || ''"],
  ["'<?php echo esc_js($my_schedule_data['end'] ?? ''); ?>'", "(cfg.mySchedule && cfg.mySchedule.end) || ''"],
  ["'<?php echo esc_js($other_schedule_data['start'] ?? ''); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.start) || ''"],
  ["'<?php echo esc_js($other_schedule_data['end'] ?? ''); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.end) || ''"],
  ["parseInt('<?php echo esc_js($my_schedule_id); ?>', 10)", 'parseInt(String(cfg.myScheduleId || 0), 10)'],
  ["parseInt('<?php echo esc_js($other_schedule_id); ?>', 10)", 'parseInt(String(cfg.otherScheduleId || 0), 10)'],
  ["'<?php echo home_url('/match-board-own/'); ?>#progress-view'", "(cfg.matchBoardUrl || '') + '#progress-view'"],
  ["'<?php echo esc_js($other_schedule_data[\"place\"] ?? \"either\"); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.place) || 'either'"],
  ["'<?php echo esc_js($other_schedule_data[\"gender\"] ?? \"both\"); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.gender) || 'both'"],
  ["'<?php echo esc_js($other_schedule_data[\"start\"] ?? \"\"); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.start) || ''"],
  ["'<?php echo esc_js($other_schedule_data[\"end\"] ?? \"\"); ?>'", "(cfg.otherSchedule && cfg.otherSchedule.end) || ''"],
  ["'<?php echo esc_url(home_url('/match-board-own/')); ?>#progress-view'", "(cfg.matchBoardUrl || '') + '#progress-view'"],
  ["'<?php echo wp_create_nonce('au_match_nonce'); ?>'", "cfg.matchNonce || ''"],
  ["'<?php echo esc_url( admin_url('admin-ajax.php') ); ?>'", "cfg.ajaxUrl || ''"],
  ["parseInt('<?php echo $latest_request ? (int) $latest_request->ID : 0; ?>', 10)", 'parseInt(String(cfg.matchRequestId || 0), 10)'],
  ["'<?php echo esc_url(admin_url('admin-ajax.php')); ?>'", "cfg.ajaxUrl || ''"],
  ["'<?php echo admin_url('admin-ajax.php'); ?>'", "cfg.ajaxUrl || ''"],
  ["'<?php echo wp_create_nonce('aidunite_checklist_nonce'); ?>'", "cfg.checklistNonce || ''"],
  ['<?php echo !empty($needs_place_adjustment) ? \'true\' : \'false\'; ?>', '!!cfg.needsPlaceAdjustment'],
  ['<?php echo (!empty($needs_gender_adjustment) && isset($gender_options) && count($gender_options) > 1) ? \'true\' : \'false\'; ?>', '!!cfg.needsGenderAdjustment'],
  ["defaultPlaceFromBtn || '<?php echo esc_js($resolved_place_value ?? 'home'); ?>'", "defaultPlaceFromBtn || cfg.resolvedPlace || 'home'"],
];

for (const [from, to] of pairs) {
  body = body.split(from).join(to);
}

if (body.includes('<?php')) {
  console.warn('WARNING: PHP remnants remain in extracted JS');
  const remnants = body.match(/<\?php[\s\S]*?\?>/g);
  if (remnants) console.warn(remnants.slice(0, 5));
}

const header = `/**
 * マッチ詳細（page-match-detail.php）
 * aiduniteMatchDetailPage は wp_localize_script で注入
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = typeof aiduniteMatchDetailPage !== 'undefined' ? aiduniteMatchDetailPage : {};

`;

const footer = `
  });
})();

`;

body = body.replace(/^document\.addEventListener\('DOMContentLoaded', function\(\) \{\s*/, '');
body = body.replace(/\}\);\s*$/, '');

fs.writeFileSync(outPath, header + body + footer);
console.log('match-detail-page.js written');
