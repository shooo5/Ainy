<?php
// 優先順（スラッグ）— あればこれで判定
function au_menu_priority_map_by_slug() {
  return [
    'team_message'       => 10, // チームメッセージ
    'practice_schedule'  => 20, // 練習スケジュール
    'match_room'         => 30, // 試合一覧
    'attendance'         => 40, // 出欠管理
    'membership_fee'     => 50, // チーム月謝管理
    'match_results'      => 60, // 試合結果管理
    'member_list'        => 65, // メンバー一覧
    'team_settings'      => 70, // チーム設定
  ];
}

// タイトルの部分一致マッピング（スラッグが無い場合のフォールバック）
function au_menu_priority_by_title($title) {
  $title = mb_strtolower($title);
  $rules = [
    10 => ['チームメッセ', 'メッセージ', '連絡', '掲示板', 'コミュニケーション'],
    20 => ['練習', 'スケジュール', '予定'],
    30 => ['試合一覧', 'ゲームリスト', 'マッチ', '対戦募集', '試合調整', 'マッチング'],
    40 => ['出欠', '出席', '欠席', '回答', '参加登録'],
    50 => ['会費', '月謝', '支払い', '決済', '徴収'],
    60 => ['試合結果', '成績', 'リザルト', 'スコア', '活動報告'],
    65 => ['メンバー一覧', 'メンバー管理'],
    70 => ['チーム設定', '設定', '管理'],
  ];
  foreach ($rules as $priority => $keywords) {
    foreach ($keywords as $kw) {
      if (mb_strpos($title, $kw) !== false) return $priority;
    }
  }
  return null;
}

// メニュー配列を受け取り、優先順で並べ替える
function au_sort_mypage_menu(array $items) {
  $prioSlug = au_menu_priority_map_by_slug();

  usort($items, function($a, $b) use ($prioSlug) {
    // 1) スラッグ優先
    $aSlug = $a['slug'] ?? ($a['key'] ?? null);
    $bSlug = $b['slug'] ?? ($b['key'] ?? null);

    $aP = ($aSlug && isset($prioSlug[$aSlug])) ? $prioSlug[$aSlug] : null;
    $bP = ($bSlug && isset($prioSlug[$bSlug])) ? $prioSlug[$bSlug] : null;

    // 2) タイトルの部分一致で補完
    if ($aP === null) $aP = au_menu_priority_by_title($a['title'] ?? '');
    if ($bP === null) $bP = au_menu_priority_by_title($b['title'] ?? '');

    // 3) どちらも未分類なら順不同（現状順をできるだけ維持）
    if ($aP === null && $bP === null) return 0;
    if ($aP === null) return 1;   // b を先に
    if ($bP === null) return -1;  // a を先に
    if ($aP === $bP) return 0;

    return ($aP < $bP) ? -1 : 1;
  });

  return $items;
}
