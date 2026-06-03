/**
 * カバレッジサマリーを取得するスクリプト
 */
const fs = require('fs');
const path = require('path');

const lcovPath = path.join(__dirname, 'tests', 'coverage', 'lcov.info');

if (!fs.existsSync(lcovPath)) {
    console.log('カバレッジレポートが見つかりません。先に `npm test -- --coverage` を実行してください。');
    process.exit(1);
}

console.log('カバレッジレポートを読み込んでいます...\n');

const lcovContent = fs.readFileSync(lcovPath, 'utf8');
const lines = lcovContent.split('\n');

let totalLF = 0;  // Lines Found
let totalLH = 0;  // Lines Hit
let totalBRF = 0; // Branches Found
let totalBRH = 0; // Branches Hit
let totalFNF = 0; // Functions Found
let totalFNH = 0; // Functions Hit

let currentLF = 0;
let currentLH = 0;
let currentBRF = 0;
let currentBRH = 0;
let currentFNF = 0;
let currentFNH = 0;

lines.forEach(line => {
    if (line.startsWith('LF:')) {
        currentLF = parseInt(line.split(':')[1]) || 0;
        totalLF += currentLF;
    } else if (line.startsWith('LH:')) {
        currentLH = parseInt(line.split(':')[1]) || 0;
        totalLH += currentLH;
    } else if (line.startsWith('BRF:')) {
        currentBRF = parseInt(line.split(':')[1]) || 0;
        totalBRF += currentBRF;
    } else if (line.startsWith('BRH:')) {
        currentBRH = parseInt(line.split(':')[1]) || 0;
        totalBRH += currentBRH;
    } else if (line.startsWith('FNF:')) {
        currentFNF = parseInt(line.split(':')[1]) || 0;
        totalFNF += currentFNF;
    } else if (line.startsWith('FNH:')) {
        currentFNH = parseInt(line.split(':')[1]) || 0;
        totalFNH += currentFNH;
    }
});

// パーセンテージを計算
const statementsPercent = totalLF > 0 ? (totalLH / totalLF * 100).toFixed(2) : '0.00';
const branchesPercent = totalBRF > 0 ? (totalBRH / totalBRF * 100).toFixed(2) : '0.00';
const functionsPercent = totalFNF > 0 ? (totalFNH / totalFNF * 100).toFixed(2) : '0.00';
const linesPercent = totalLF > 0 ? (totalLH / totalLF * 100).toFixed(2) : '0.00';

console.log('\n=== カバレッジサマリー ===\n');
console.log(`Statements: ${statementsPercent}% (${totalLH}/${totalLF})`);
console.log(`Branches:   ${branchesPercent}% (${totalBRH}/${totalBRF})`);
console.log(`Functions:  ${functionsPercent}% (${totalFNH}/${totalFNF})`);
console.log(`Lines:      ${linesPercent}% (${totalLH}/${totalLF})`);
console.log('\n');

// 70%未満の項目をチェック
const issues = [];
if (parseFloat(statementsPercent) < 70) {
    issues.push(`Statements: ${statementsPercent}% (目標: 70%)`);
}
if (parseFloat(branchesPercent) < 70) {
    issues.push(`Branches: ${branchesPercent}% (目標: 70%)`);
}
if (parseFloat(functionsPercent) < 70) {
    issues.push(`Functions: ${functionsPercent}% (目標: 70%)`);
}
if (parseFloat(linesPercent) < 70) {
    issues.push(`Lines: ${linesPercent}% (目標: 70%)`);
}

if (issues.length > 0) {
    console.log('⚠️  70%未満の項目:');
    issues.forEach(issue => console.log(`   - ${issue}`));
} else {
    console.log('✅ すべての項目が70%以上です！');
}

