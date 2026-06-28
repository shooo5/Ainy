#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const srcPath = path.join(root, 'assets/css/pages/schedule-management-page.css');
const dstPath = path.join(root, 'assets/css/components/schedule-quick-modal.css');

const lines = fs.readFileSync(srcPath, 'utf8').split(/\r?\n/);

// 1-based line numbers from audit: quick modal block 1843-2709, tail from 2711 stays in page css
const quickStart = 1843;
const quickEnd = 2709;

const header = `/**
 * スケジュールクイックモーダル（aidunite-schedule-quick-modal）
 * マイページ・スケジュール管理など複数画面で共有。ページ固有 CSS とは分離。
 */
`;

const quickLines = lines.slice(quickStart - 1, quickEnd);
const remaining = [...lines.slice(0, quickStart - 1), ...lines.slice(quickEnd)];

fs.writeFileSync(dstPath, header + quickLines.join('\n') + '\n', 'utf8');
fs.writeFileSync(srcPath, remaining.join('\n'), 'utf8');

console.log(`Wrote ${quickLines.length} lines to ${path.relative(root, dstPath)}`);
console.log(`Remaining page css: ${remaining.length} lines`);
