import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const transcript =
  'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl';

let css = fs.readFileSync(
  path.join(root, 'assets/css/pages/schedule-management-page.css'),
  'utf8'
);

const lines = fs.readFileSync(transcript, 'utf8').split('\n');
let applied = 0;
let skipped = 0;

for (const line of lines) {
  if (!line.includes('schedule-management-page.css')) continue;
  let obj;
  try {
    obj = JSON.parse(line);
  } catch {
    continue;
  }
  const parts = obj.message?.content;
  if (!Array.isArray(parts)) continue;
  for (const part of parts) {
    if (part.type !== 'tool_use' || part.name !== 'StrReplace') continue;
    const input = part.input;
    if (!input?.path?.includes('schedule-management-page.css')) continue;
    const { old_string: oldStr, new_string: newStr } = input;
    if (!oldStr || !newStr) continue;
    if (!css.includes(oldStr)) {
      skipped++;
      continue;
    }
    css = css.replace(oldStr, newStr);
    applied++;
  }
}

const marker = '/* クイック登録・カード操作モーダル */';
const idx = css.indexOf(marker);
const altIdx = css.indexOf('.aidunite-schedule-quick-modal {');
const start = idx >= 0 ? idx : altIdx;
if (start < 0) {
  console.error('Quick modal block not found after replay. applied=', applied, 'skipped=', skipped);
  process.exit(1);
}

const tailMarker = '/* 閲覧専用（保護者・選手） */';
const end = css.indexOf(tailMarker, start);
const quickCss = css.slice(start, end > start ? end : undefined).trim();
const header = `/**
 * スケジュールクイックモーダル（aidunite-schedule-quick-modal）
 * マイページ・スケジュール管理など複数画面で共有。ページ固有 CSS とは分離。
 */

`;
const outPath = path.join(root, 'assets/css/components/schedule-quick-modal.css');
fs.writeFileSync(outPath, header + quickCss + '\n', 'utf8');

// Remove quick modal from page css
const pageCss = (end > start ? css.slice(0, start) + css.slice(end) : css.slice(0, start)).trim() + '\n';
fs.writeFileSync(path.join(root, 'assets/css/pages/schedule-management-page.css'), pageCss, 'utf8');

console.log('Replay applied:', applied, 'skipped:', skipped);
console.log('Quick modal lines:', quickCss.split('\n').length);
console.log('Page css lines:', pageCss.split('\n').length);
