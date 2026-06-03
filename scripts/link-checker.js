const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

// リンクチェック結果を保存する配列
const linkResults = [];

// 主要なページURLとそのリンク先を定義
const pagesToCheck = [
  {
    name: 'マイページ',
    url: '/mypage',
    expectedStatus: 200
  },
  {
    name: 'ログイン',
    url: '/login',
    expectedStatus: 200
  },
  {
    name: '会員登録',
    url: '/member-register',
    expectedStatus: 200
  },
  {
    name: 'マイページ',
    url: '/mypage',
    expectedStatus: 200
  },
  {
    name: 'チーム登録',
    url: '/team-registration',
    expectedStatus: 200
  },
  {
    name: 'チーム一覧',
    url: '/team-list',
    
    expectedStatus: 200
  },
  {
    name: 'スケジュール管理',
    url: '/schedule-management',
    expectedStatus: 200
  },
  {
    name: 'マッチ掲示板',
    url: '/match-board-own',
    expectedStatus: 200
  },
  {
    name: 'マッチ申請',
    url: '/match-requests',
    expectedStatus: 200
  },
  {
    name: '支払い管理',
    url: '/payment-management',
    expectedStatus: 200
  },
  {
    name: 'プロフィール編集',
    url: '/profile-edit',
    expectedStatus: 200
  },
  {
    name: '通知一覧',
    url: '/notifications',
    expectedStatus: 200
  },
  {
    name: 'お問い合わせ',
    url: '/contact',
    expectedStatus: 200
  },
  {
    name: 'ガイド',
    url: '/guide',
    expectedStatus: 200
  },
  {
    name: 'レギュレーション',
    url: '/regulation',
    expectedStatus: 200
  }
];

// 問題のある可能性があるリンク
const problematicLinks = [
  '/team-search',
  '/team-members',
  '/attendance-management',
  '/attendance-response',
  '/attendance',
  '/team-notifications',
  '/match-history',
  '/team-board',
  '/send-message',
  '/team-discovery',
  '/support-team',
  '/schedule-edit',
  '/match-board-own',
  '/match-result-report',
  '/match-result-history',
  '/payment-settings',
  '/team-chat',
  '/message-board',
  '/team-settings',
  '/player-add',
  '/invite-guardian',
  '/team-parents',
  '/team-apply',
  '/team-approval',
  '/match-management',
  '/parent-dashboard',
  '/player-dashboard'
];

async function checkLink(baseUrl, path, pageName = '') {
  const fullUrl = `${baseUrl}${path}`;
  
  try {
    const response = await fetch(fullUrl, { 
      method: 'HEAD',
      redirect: 'follow'
    });
    
    const status = response.status;
    const finalUrl = response.url;
    const isRedirect = finalUrl !== fullUrl;
    
    return {
      url: fullUrl,
      path: path,
      status: status,
      isRedirect: isRedirect,
      finalUrl: finalUrl,
      pageName: pageName,
      timestamp: new Date().toISOString()
    };
  } catch (error) {
    return {
      url: fullUrl,
      path: path,
      status: 'ERROR',
      error: error.message,
      pageName: pageName,
      timestamp: new Date().toISOString()
    };
  }
}

async function checkAllLinks(baseUrl = 'http://aidunitemvp.xsrv.jp') {
  console.log('🔍 リンク遷移チェックを開始します...');
  console.log(`📡 ベースURL: ${baseUrl}`);
  console.log('');
  
  // 主要ページのチェック
  console.log('📋 主要ページのステータスチェック:');
  for (const page of pagesToCheck) {
    const result = await checkLink(baseUrl, page.url, page.name);
    linkResults.push(result);
    
    const statusIcon = result.status === 200 ? '✅' : 
                      result.status === 404 ? '❌' : 
                      result.status === 301 || result.status === 302 ? '🔄' : '⚠️';
    
    console.log(`${statusIcon} ${page.name} (${page.url}) → ${result.status} ${result.isRedirect ? `[リダイレクト: ${result.finalUrl}]` : ''}`);
  }
  
  console.log('');
  console.log('🔍 問題のある可能性があるリンクのチェック:');
  
  // 問題のある可能性があるリンクのチェック
  for (const link of problematicLinks) {
    const result = await checkLink(baseUrl, link);
    linkResults.push(result);
    
    const statusIcon = result.status === 200 ? '✅' : 
                      result.status === 404 ? '❌' : 
                      result.status === 301 || result.status === 302 ? '🔄' : '⚠️';
    
    console.log(`${statusIcon} ${link} → ${result.status} ${result.isRedirect ? `[リダイレクト: ${result.finalUrl}]` : ''}`);
  }
  
  // 結果をCSVファイルに保存
  saveResultsToCSV();
  
  // 結果をJSONファイルに保存
  saveResultsToJSON();
  
  console.log('');
  console.log('📊 チェック完了！結果は以下のファイルに保存されました:');
  console.log('- link-check-results.csv');
  console.log('- link-check-results.json');
}

function saveResultsToCSV() {
  const csvHeader = 'URL,パス,ステータス,リダイレクト,最終URL,ページ名,タイムスタンプ\n';
  const csvRows = linkResults.map(result => {
    return `"${result.url}","${result.path}","${result.status}","${result.isRedirect}","${result.finalUrl || ''}","${result.pageName || ''}","${result.timestamp}"`;
  }).join('\n');
  
  const csvContent = csvHeader + csvRows;
  fs.writeFileSync('link-check-results.csv', csvContent, 'utf8');
}

function saveResultsToJSON() {
  const jsonContent = JSON.stringify(linkResults, null, 2);
  fs.writeFileSync('link-check-results.json', jsonContent, 'utf8');
}

// スクリプト実行
if (require.main === module) {
  const baseUrl = process.argv[2] || 'http://aidunitemvp.xsrv.jp';
  checkAllLinks(baseUrl).catch(console.error);
}

module.exports = { checkAllLinks, checkLink }; 