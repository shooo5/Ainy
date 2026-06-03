/**
 * PHP / JS / CSS コードクリーンアップスクリプト
 * - 行末の余分な空白を削除
 * - ファイル末尾を単一の改行で終了
 * - テストファイル・node_modules・vendor は対象外
 */

const fs = require('fs');
const path = require('path');

const THEME_ROOT = path.resolve(__dirname, '..');

const EXCLUDE_DIRS = ['node_modules', 'vendor', 'bin', 'tests', '.git', 'screenshots'];
const EXCLUDE_JS_FILES = ['simple-test.js', 'ui-test-automation.js', 'link-checker.js', 'get-coverage.js', 'jest.config.js'];


function cleanup(content) {
  const lines = content.split(/\r?\n/);
  const trimmed = lines.map(line => line.replace(/\s+$/, ''));
  const joined = trimmed.join('\n').replace(/\n+$/, '') + '\n';
  return joined;
}

function run() {
  const phpFiles = [];
  const jsFiles = [];
  const cssFiles = [];

  const scanDir = (dir, relStart) => {
    if (!fs.existsSync(dir)) return;
    const list = fs.readdirSync(dir);
    for (const file of list) {
      const full = path.join(dir, file);
      const rel = path.relative(THEME_ROOT, full);
      const parts = rel.split(path.sep);
      if (EXCLUDE_DIRS.some(d => parts.includes(d))) continue;
      const stat = fs.statSync(full);
      if (stat.isDirectory()) {
        scanDir(full, relStart);
      } else {
        const ext = path.extname(file).toLowerCase();
        if (ext === '.php') phpFiles.push(full);
        else if (ext === '.js' && !EXCLUDE_JS_FILES.includes(file)) jsFiles.push(full);
        else if (ext === '.css') cssFiles.push(full);
      }
    }
  };

  scanDir(THEME_ROOT);
  const all = [...phpFiles, ...jsFiles, ...cssFiles];
  let changed = 0;
  for (const file of all) {
    const raw = fs.readFileSync(file, 'utf8');
    const cleaned = cleanup(raw);
    if (raw !== cleaned) {
      fs.writeFileSync(file, cleaned, 'utf8');
      changed++;
      console.log('Cleaned: ' + path.relative(THEME_ROOT, file));
    }
  }
  console.log(`Done. ${changed} files updated of ${all.length} checked.`);
}

run();
