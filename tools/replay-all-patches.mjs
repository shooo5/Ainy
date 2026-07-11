import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const norm = (s) => s.replace(/\r\n/g, '\n');

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const transcript =
  'C:/Users/sshog/.cursor/projects/d-LocalSites-aidunite-local-app-public-wp-content-themes-aidunite-original/agent-transcripts/42577368-11cd-4e62-a458-a06f20f42f01/42577368-11cd-4e62-a458-a06f20f42f01.jsonl';

const patches = [];
for (const line of fs.readFileSync(transcript, 'utf8').split('\n')) {
  if (!line.includes('schedule-management-page.css')) continue;
  let obj;
  try {
    obj = JSON.parse(line);
  } catch {
    continue;
  }
  for (const part of obj.message?.content || []) {
    if (part.type !== 'tool_use' || part.name !== 'StrReplace') continue;
    const input = part.input || {};
    if (!input.path?.includes('schedule-management-page.css')) continue;
    patches.push({ old: norm(input.old_string), neu: norm(input.new_string) });
  }
}

let css = norm(fs.readFileSync(path.join(root, 'assets/css/pages/schedule-management-page.css'), 'utf8'));
let applied = 0;
const failed = [];
patches.forEach((p, idx) => {
  if (css.includes(p.old)) {
    css = css.replace(p.old, p.neu);
    applied++;
  } else {
    failed.push(idx + 1);
  }
});

console.log('patches total', patches.length, 'applied', applied, 'failed', failed.length);
if (failed.length) console.log('first failed', failed[0]);

const marker = '/* クイック登録・カード操作モーダル */';
let start = css.indexOf(marker);
if (start < 0) start = css.indexOf('.aidunite-schedule-quick-modal {');
const end = css.indexOf('/* 閲覧専用（保護者・選手） */', start);

if (start < 0) {
  // Manual insert patch 1 if only patch 1 failed due to trailing whitespace
  if (failed.length === patches.length && patches[0]) {
    const p0 = patches[0];
    const anchor = '.schedule-line-5 {\n        font-size: 0.5rem;\n    }\n}';
    if (css.includes(anchor)) {
      css = css.replace(anchor, p0.neu.trimEnd());
      console.log('Applied patch 1 via anchor fallback');
      for (let i = 1; i < patches.length; i++) {
        const p = patches[i];
        if (css.includes(p.old)) {
          css = css.replace(p.old, p.neu);
          applied++;
        }
      }
      start = css.indexOf(marker);
    }
  }
}

const start2 = css.indexOf(marker);
const end2 = css.indexOf('/* 閲覧専用（保護者・選手） */', start2);
if (start2 < 0) {
  fs.writeFileSync(path.join(root, 'tools/replay-failed-full.css'), css, 'utf8');
  console.error('Quick modal not found');
  process.exit(1);
}

const quickCss = css.slice(start2, end2 > start2 ? end2 : undefined).trim();
const pageCss = (end2 > start2 ? css.slice(0, start2) + css.slice(end2) : css.slice(0, start2)).trim() + '\n';

fs.writeFileSync(
  path.join(root, 'assets/css/components/schedule-quick-modal.css'),
  `/**\n * スケジュールクイックモーダル（aidunite-schedule-quick-modal）\n * マイページ・スケジュール管理など複数画面で共有。ページ固有 CSS とは分離。\n */\n\n${quickCss}\n`,
  'utf8'
);
fs.writeFileSync(path.join(root, 'assets/css/pages/schedule-management-page.css'), pageCss, 'utf8');
console.log('quick lines', quickCss.split('\n').length, 'page lines', pageCss.split('\n').length, 'final applied', applied);
