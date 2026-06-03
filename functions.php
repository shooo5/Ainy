<?php
/**
 * AidUnite Original Theme Functions
 * 完全自作のオリジナルテーマ
 */

// 直下の functions.php が読み込まれているかテスト（重複防止）
if (!defined('AIDUNITE_ROOT_FUNCTIONS_LOADED')) {
    define('AIDUNITE_ROOT_FUNCTIONS_LOADED', true);
} else {
    return; // 既に読み込まれている場合は処理を停止
}

// マッチ申請フロー用デバッグ（AIDUNITE_MATCH_FLOW_DEBUG 等。他の match より先に定義）
require_once get_stylesheet_directory() . '/functions/match/match-flow-debug-log.php';

// メッセージ機能を読み込み
require_once get_template_directory() . '/functions/messaging/messaging-init.php';

// WordPressの読み込み状態もチェック
if (did_action('after_setup_theme') && !defined('AIDUNITE_THEME_SETUP_COMPLETE')) {
    define('AIDUNITE_THEME_SETUP_COMPLETE', true);
}

// テーマのセットアップ（ロゴ機能含む）
function aidunite_theme_setup() {
    // 重複実行防止
    if (defined('AIDUNITE_THEME_SETUP_COMPLETE')) {
        return;
    }

    // テーマサポート
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));

    define('AIDUNITE_THEME_SETUP_COMPLETE', true);
}
add_action('after_setup_theme', 'aidunite_theme_setup');

// 通知系
require_once get_stylesheet_directory() . '/functions/notify/notify.php';
// 旧通知実装（archive/）は 2026-06-02 に削除。現行は notification-api.php の aidunite_notification_send()

// 共通関数
require_once get_stylesheet_directory() . '/functions/common/common-functions.php'; // ← ここだけでOK。他ファイルからは読み込まない
require_once get_stylesheet_directory() . '/functions/common/web-app-page-registry.php';
require_once get_stylesheet_directory() . '/functions/common/web-app-integrated-ui.php';
require_once get_stylesheet_directory() . '/functions/common/mail-utils.php'; // wp_mail 共通・ローカル SMTP
require_once get_stylesheet_directory() . '/functions/common/label-functions.php'; // ラベル変換共通（place/gender/status）
require_once get_stylesheet_directory() . '/functions/common/gender-meta.php'; // 性別メタ canonical（male/female/both）・募集検証・DB移行
require_once get_stylesheet_directory() . '/functions/common/normalize-service.php'; // payload → normalize → DB（辞書準拠）
require_once get_stylesheet_directory() . '/functions/common/team-context.php'; // マルチチーム: managed / current_operating（auth より前に必須）
require_once get_stylesheet_directory() . '/functions/common/header-operating-team.php'; // ヘッダー操作中チーム切替
require_once get_stylesheet_directory() . '/functions/common/auth-middleware.php'; // 統一認証・権限チェックミドルウェア
require_once get_stylesheet_directory() . '/functions/common/tooltip-functions.php'; // ツールチップ共通関数
require_once get_stylesheet_directory() . '/functions/common/cache-manager.php'; // 統一キャッシュマネージャー
require_once get_stylesheet_directory() . '/functions/common/date-utils.php'; // 統一日付・時間ユーティリティ
require_once get_stylesheet_directory() . '/functions/common/admin-list-display.php'; // 管理者一覧セル表示統一
require_once get_stylesheet_directory() . '/functions/common/query-utils.php'; // 統一クエリユーティリティ
require_once get_stylesheet_directory() . '/functions/common/redirect-utils.php'; // 統一リダイレクトユーティリティ
require_once get_stylesheet_directory() . '/functions/common/status-utils.php'; // 統一ステータス管理ユーティリティ
require_once get_stylesheet_directory() . '/functions/common/empty-state.php'; // 統一空の状態コンポーネント
require_once get_stylesheet_directory() . '/functions/common/icon-helpers.php'; // アイコンインラインSVG（icon-usage-guide.md 準拠）
require_once get_stylesheet_directory() . '/functions/common/theme-icon-registry.php';
require_once get_stylesheet_directory() . '/functions/common/theme-assets.php'; // CSS/JS パスヘルパー
require_once get_stylesheet_directory() . '/functions/common/enqueue.php'; // テーマ共通 CSS/JS 読込
require_once get_stylesheet_directory() . '/functions/common/theme-init.php'; // ロゴディレクトリ作成等

// 一覧系ページの1ページあたり件数（必要に応じて wp-config.php で上書き可）
if (!defined('AIDUNITE_TEAM_MANAGEMENT_PER_PAGE')) {
    define('AIDUNITE_TEAM_MANAGEMENT_PER_PAGE', 10);
}
if (!defined('AIDUNITE_ADMIN_USER_LIST_PER_PAGE')) {
    define('AIDUNITE_ADMIN_USER_LIST_PER_PAGE', 20);
}
if (!defined('AIDUNITE_MATCH_REQUESTS_PER_PAGE')) {
    define('AIDUNITE_MATCH_REQUESTS_PER_PAGE', 20);
}

require_once get_stylesheet_directory() . '/functions/user/user-functions.php';
require_once get_stylesheet_directory() . '/functions/user/profile-functions.php';
require_once get_stylesheet_directory() . '/functions/user/favorite-teams.php';
require_once get_stylesheet_directory() . '/functions/member/withdrawal-functions.php'; // 退会処理（プラグイン非依存）
require_once get_stylesheet_directory() . '/functions/user/registration-approval.php';  // 本登録リンク処理（init）
require_once get_stylesheet_directory() . '/functions/user/login-handler.php';        // ログインフォーム処理（init）
require_once get_stylesheet_directory() . '/functions/user/profile-edit-handler.php';  // プロフィール編集フォーム処理（init）
require_once get_stylesheet_directory() . '/functions/member/withdrawal-init.php';    // 退会 init / template_redirect
require_once get_stylesheet_directory() . '/functions/common/notification-utils.php';

// スケジュール機能
require_once get_stylesheet_directory() . '/functions/schedule/schedule-edit-ui.php';
require_once get_stylesheet_directory() . '/functions/schedule/schedule-functions.php';
require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php'; // 登録・更新の本体（normalize 経由）
require_once get_stylesheet_directory() . '/functions/schedule/schedule-registration.php'; // 統一スケジュール登録システム（REST API含む）
require_once get_stylesheet_directory() . '/functions/schedule/meta-key-migration.php'; // Phase 4: メタキー統一移行スクリプト

// 出欠管理機能
require_once get_stylesheet_directory() . '/functions/attendance/attendance-functions.php';
require_once get_stylesheet_directory() . '/functions/attendance/attendance-notification.php';

// 管理画面メニュー
require_once get_stylesheet_directory() . '/functions/admin/admin-menu.php';
if (is_admin()) {
    require_once get_stylesheet_directory() . '/functions/admin/notification-delivery-admin.php';
}
require_once get_stylesheet_directory() . '/functions/admin/page-setup.php'; // 固定ページ自動作成（スケジュール・決済・コミュニケーション・通知設定）

// Ainy 管理者ダッシュボード KPI・分析基盤
require_once get_stylesheet_directory() . '/functions/dashboard/ainy-dashboard-kpi.php';
require_once get_stylesheet_directory() . '/functions/analytics/analytics-init.php';
require_once get_stylesheet_directory() . '/functions/dashboard/ainy-dashboard-charts.php';
require_once get_stylesheet_directory() . '/functions/dashboard/ainy-dashboard-export.php';

// マッチング機能共通・依存関数
require_once get_stylesheet_directory() . '/functions/match/match-common-functions.php';
require_once get_stylesheet_directory() . '/functions/common/team-activity-options.php';
require_once get_stylesheet_directory() . '/functions/match/match-board-bootstrap.php';
require_once get_stylesheet_directory() . '/functions/match/match-board-tier.php';
require_once get_stylesheet_directory() . '/functions/match/match-board-market-axis.php';
require_once get_stylesheet_directory() . '/functions/match/match-board-page-helpers.php';
require_once get_stylesheet_directory() . '/functions/match/match-state-sync.php';
require_once get_stylesheet_directory() . '/functions/match/match-notifications.php';
require_once get_stylesheet_directory() . '/functions/match/match-game-id.php';

// schedule 編集・削除の match_request / match_board / chat 依存（第1段階・ガード）
require_once get_stylesheet_directory() . '/functions/schedule/schedule-dependency-guards.php';
// データ整合性チェック・修復 REST（schedule ガード関数に依存）
require_once get_stylesheet_directory() . '/functions/common/data-integrity-manager.php';

// E2E用テストデータAPI（デフォルト無効。有効化は wp-config で AIDUNITE_E2E_API_ENABLED=true と E2E_TEST_KEY）
require_once get_stylesheet_directory() . '/functions/e2e/test-data-api.php';

// チーム投稿タイプ（管理画面の post.php 編集に必須。未読込だと「Invalid post type.」）
require_once get_stylesheet_directory() . '/functions/post-types/team.php';

// チーム機能
require_once get_stylesheet_directory() . '/functions/team/team-functions.php';
require_once get_stylesheet_directory() . '/functions/team/team-registration-dual.php';
require_once get_stylesheet_directory() . '/functions/team/team-registration-pages.php';
require_once get_stylesheet_directory() . '/functions/team/team-registration-complete.php';
require_once get_stylesheet_directory() . '/functions/team/team-application-status.php';
require_once get_stylesheet_directory() . '/functions/team/team-activation.php';
require_once get_stylesheet_directory() . '/functions/team/team-onboarding-bot.php';
require_once get_stylesheet_directory() . '/functions/team/team-activation-pages.php';
require_once get_stylesheet_directory() . '/functions/team/team-activation-restrictions.php';
require_once get_stylesheet_directory() . '/functions/member/mypage-general.php';
require_once get_stylesheet_directory() . '/functions/common/test-fixture.php'; // テスト用チーム識別・整理（team-functions 依存）
require_once get_stylesheet_directory() . '/functions/team/team-settings-dashboard.php';
require_once get_stylesheet_directory() . '/functions/team/team-public-profile.php';

// 選手機能
require_once get_stylesheet_directory() . '/functions/player/player-functions.php';

// 保護者機能
require_once get_stylesheet_directory() . '/functions/parent/parent-functions.php';

// 決済機能
require_once get_stylesheet_directory() . '/functions/payment/stripe-core.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-config.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-functions.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-admin-ajax.php';
require_once get_stylesheet_directory() . '/functions/payment/stripe-connect.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-tuition.php';
require_once get_stylesheet_directory() . '/functions/payment/stripe-checkout.php';
require_once get_stylesheet_directory() . '/functions/payment/stripe-webhook.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-restrictions.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-ajax.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-cancel.php';
require_once get_stylesheet_directory() . '/functions/payment/payment-reminder.php';

// 大会機能（仕様: docs/specs/tournament-feature-spec.md）
require_once get_stylesheet_directory() . '/functions/post-types/tournament.php';
require_once get_stylesheet_directory() . '/functions/tournament/tournament-functions.php';

// 複数チームマッチ機能
require_once get_stylesheet_directory() . '/functions/post-types/multi-match.php';
require_once get_stylesheet_directory() . '/functions/post-types/multi-match-chat.php';
require_once get_stylesheet_directory() . '/functions/multi-match/multi-match-tournament-generator.php';
require_once get_stylesheet_directory() . '/functions/multi-match/multi-match-chat-functions.php';
require_once get_stylesheet_directory() . '/functions/multi-match/multi-court-scheduler.php';
require_once get_stylesheet_directory() . '/functions/multi-match/multi-court-api.php';
require_once get_stylesheet_directory() . '/functions/multi-match/update-gender-labels.php';




/*--------------------------------------------------------------
  body_class：ページタイプ・テンプレート別クラス追加（1本に統合）
--------------------------------------------------------------*/
add_filter('body_class', function($classes) {
  // マイページテンプレート
  if (is_page_template('page-mypage.php')) {
    $classes[] = 'page-body';
    $classes[] = 'page-type-dashboard';
  }

  // 会員登録ページ
  if (is_page('member-register') || is_page('member-registration')) {
    $classes[] = 'page-type-form';
  }

  // ログインページ
  if (is_page('login')) {
    $classes[] = 'page-type-form';
  }

  // チーム登録・編集ページ
  if (is_page('team-registration')) {
    $classes[] = 'page-type-form';
  }

  if (function_exists('aidunite_is_team_settings_screen') && aidunite_is_team_settings_screen()) {
    $classes[] = 'page-team-settings';
    $classes[] = 'page-type-dashboard';
  }

  // プレイヤー追加・編集ページ
  if (is_page('player-add') || is_page('edit-player')) {
    $classes[] = 'page-type-form';
  }

  // チーム申請ページ
  if (is_page('team-apply')) {
    $classes[] = 'page-type-form';
  }

  // スケジュール管理ページ
  if (is_page('schedule-management') || is_page_template('page-schedule-management.php')) {
    $classes[] = 'page-schedule-management';
  }

  // マイスケジュールページ
  if (is_page('my-schedule')) {
    $classes[] = 'page-my-schedule';
  }

  // ページタイプ（web-app / web）
  if (function_exists('aidunite_is_web_app_page') && aidunite_is_web_app_page()) {
    $classes[] = 'web-app-page';
  } else {
    $classes[] = 'web-page';
  }

  // マッチ掲示板（自チーム）
  if (is_page('match-board-own') || is_page_template('page-match-board-own.php')) {
    $classes[] = 'page-match-board-own';
  }

  // マッチ詳細（操作中チーム切替時の遷移判定用）
  if (is_page('match-detail') || is_page_template('page-match-detail.php')) {
    $classes[] = 'page-match-detail';
  }

  return $classes;
});

/* 本登録・退会・ログイン・プロフィール編集の init/template_redirect は functions/user/* と functions/member/withdrawal-init.php に移設済み */

/*====================================================================
  No.3 新規登録ページ_ユーザー作成＆メール送信処理_Forminator連携（ダブルオプトイン対応）
====================================================================*/
add_action('forminator_custom_form_submit_before_set_fields', 'aidunite_register_user', 10, 3);

function aidunite_register_user($entry, $form_id, $form_settings) {
  if ($form_id != 271) return;

  // POSTデータを取得（メールは正規化）
  $email    = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['email-1'] ?? '') : sanitize_email($_POST['email-1'] ?? '');
  $password = sanitize_text_field($_POST['text-1'] ?? '');
  $nickname = sanitize_text_field($_POST['text-2'] ?? '');
  $first    = sanitize_text_field($_POST['name-1-first-name'] ?? '');
  $last     = sanitize_text_field($_POST['name-1-last-name'] ?? '');
  $name     = $first . $last;

  if (email_exists($email)) {
    return;
  }

  // ログイン名はメールアドレスを使用
  $username = sanitize_user($email);

  // 仮登録ユーザー作成
  $user_id = wp_create_user($username, $password, $email);
  if (is_wp_error($user_id)) {
    return;
  }

  // ユーザーメタ保存
  wp_update_user([
    'ID' => $user_id,
    'display_name' => $name,
    'nickname'     => $nickname,
  ]);
  aidunite_update_user_registration_status_meta($user_id, 'pending');

  // トークン生成（ダブルオプトイン用）
  $token = bin2hex(random_bytes(16));
  update_user_meta($user_id, 'registration_token', $token);
  update_user_meta($user_id, 'registration_token_time', time());

  $confirm_url = add_query_arg([
    'user_id' => $user_id,
    'token'   => $token,
  ], home_url('/approve-registration'));

  // メール送信
  $subject = '【AidUnite】仮登録のご確認';
  $message = <<<EOT
{$name} さん

このたびは、AidUniteにご登録いただきありがとうございます。

仮登録が完了しました。
本登録を完了するには、以下のリンクをクリックしてください。

▼本登録はこちら
{$confirm_url}

※このリンクは24時間以内に有効です。
　期限を過ぎた場合は、再度登録をお願いいたします。

引き続き、よろしくお願いいたします。

AidUnite運営チーム
https://aidunite.jp
EOT;

  $headers = ['Content-Type: text/plain; charset=UTF-8'];

  wp_mail($email, $subject, $message, $headers);
}

/*--------------------------------------------------------------
  No.3/11 会員登録／ログイン関連_メニュー表示切り替え
--------------------------------------------------------------*/
add_filter('wp_nav_menu_items', 'add_logout_link_to_menu', 10, 2);
function add_logout_link_to_menu($items, $args) {
    if ($args->theme_location === 'primary' && is_user_logged_in()) {
        $logout_url = wp_logout_url(home_url('/login'));
        $items .= '<li><a href="' . esc_url($logout_url) . '">ログアウト</a></li>';
    }
    return $items;
}

/*--------------------------------------------------------------
  No.9 会員登録／ログイン関連_退会（プラグイン非依存）
  申請は会員退会申請ページ → AJAX で確認メール送信 → リンククリックで本処理。
  データ取り決め: docs/withdrawal-data-policy.md
--------------------------------------------------------------*/
// Forminator 依存の退会メール送信は廃止。wp_ajax_member_withdrawal で送信。

/*--------------------------------------------------------------
  No.10 会員登録／ログイン関連_ログイン後リダイレクト制御
--------------------------------------------------------------*/
function aidunite_login_redirect($redirect_to, $request, $user) {
    if (!isset($user->roles)) {
        return home_url();
    }

    if ($user instanceof WP_User && function_exists('aidunite_get_post_login_url_for_user')) {
        return aidunite_get_post_login_url_for_user($user);
    }

    $role = $user->roles[0];
    switch ($role) {
        case 'administrator':
            return admin_url();
        case 'author':
            return home_url('/mypage?login=success');
        case 'subscriber':
            return home_url('/mypage?login=success');
        default:
            return home_url();
    }
}
add_filter('login_redirect', 'aidunite_login_redirect', 10, 3);

/* ログイン・プロフィール編集の init 処理は functions/user/login-handler.php と profile-edit-handler.php に移設済み */

/*--------------------------------------------------------------
  No.15 CPT：選手情報（player）を作成
--------------------------------------------------------------*/
function register_player_post_type() {
  $labels = array(
    'name' => '選手',
    'singular_name' => '選手',
    'menu_name' => '選手',
    'name_admin_bar' => '選手を追加',
    'add_new' => '新規追加',
    'add_new_item' => '新しい選手を追加',
    'new_item' => '新規選手',
    'edit_item' => '選手を編集',
    'view_item' => '選手を表示',
    'all_items' => '全選手',
    'search_items' => '選手を検索',
    'not_found' => '選手が見つかりませんでした',
    'not_found_in_trash' => 'ゴミ箱に選手はいません',
  );

  $args = array(
    'labels' => $labels,
    'public' => true,
    'has_archive' => true,
    'show_in_menu' => true,
    'menu_position' => 5,
    'menu_icon' => 'dashicons-groups', // プレイヤーっぽいアイコン！
    'supports' => array('title', 'editor'),
    'capability_type' => 'post',
    'show_in_rest' => true, // ブロックエディター対応
  );

  register_post_type('player', $args);
}
add_action('init', 'register_player_post_type');

/*--------------------------------------------------------------
  No.15 選手登録ページ_選手登録時に所属チームIDを自動セット
--------------------------------------------------------------*/
add_action('save_post', function($post_id) {
  // 新規投稿時のみ実行（投稿タイプ：player）
  if (get_post_type($post_id) !== 'player' || is_admin()) return;

  $current_user = wp_get_current_user();

  // 現在のユーザーのチーム投稿を取得
  $team_post = get_posts([
    'post_type' => 'team',
    'author'    => $current_user->ID,
    'post_status' => ['publish', 'pending', 'draft'],
    'numberposts' => 1,
  ]);

  if ($team_post) {
    $team_id = $team_post[0]->ID;

    // 所属チームID（カスタムフィールド）に自動で保存
    if (function_exists('update_field')) {
      update_field('team_post_id', $team_id, $post_id);
    } else {
      update_post_meta($post_id, 'team_post_id', $team_id);
    }
  }
}, 20);



/*--------------------------------------------------------------
  No.15 チーム登録ページ_所属選手情報の登録_年齢の自動化
--------------------------------------------------------------*/
function calculate_age($birth_date) {
  $birth = new DateTime($birth_date);
  $today = new DateTime();
  $age = $today->diff($birth)->y;
  return $age;
}

/*--------------------------------------------------------------
  No.16 チーム申請_登録ステータスをpendingに強制
--------------------------------------------------------------*/
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) === 'team' && get_post_status($post_id) === 'auto-draft') {
        wp_update_post(['ID' => $post_id, 'post_status' => 'pending']);
    }
}, 5);

/*--------------------------------------------------------------
  No.16 チーム登録ページ_ACF保存時にタイトルを自動設定（チーム名 → post_title）
--------------------------------------------------------------*/
add_action('save_post', function ($post_id) {
  // 管理画面などは除外
  if (is_admin()) return;

  // 投稿タイプが team であることを確認
  if (get_post_type($post_id) !== 'team') return;

  // POSTデータからチーム名を取得
  $team_name = sanitize_text_field($_POST['team_name'] ?? '');

  if (empty($team_name)) return;

  // 既に同じタイトルなら更新しない（無限再入の抑止）
  $current_title = get_post_field('post_title', $post_id);
  if ($current_title === $team_name) return;

  // wp_update_post() を save_post の中で呼ぶため、同一 post_id の再入を防ぐ
  static $in_progress = [];
  if (!empty($in_progress[$post_id])) return;
  $in_progress[$post_id] = true;
  try {
    wp_update_post([
      'ID'         => $post_id,
      'post_title' => $team_name,
    ]);
  } finally {
    unset($in_progress[$post_id]);
  }
}, 20); // 優先度20で保存処理後に実行

/*--------------------------------------------------------------
  No.17 チーム申請_管理者へ通知（修正版）
--------------------------------------------------------------*/
add_action('save_post', function($post_id) {
    if (get_post_type($post_id) !== 'team' || get_post_status($post_id) !== 'pending') return;
    if (get_post_meta($post_id, '_admin_notified', true)) return;

    $team_name  = get_post_meta($post_id, 'team_name', true);
    $registrant = get_post_meta($post_id, 'registrant_name', true);
    $contact    = get_post_meta($post_id, 'contact_mail', true);

    // ✅ 確実に編集リンクを生成！
    $edit_url = admin_url("post.php?post={$post_id}&action=edit");

    $subject = '【AidUnite】新しいチーム申請があります';
    $message = "
📝 チーム名：{$team_name}<br>
👤 申請者名：{$registrant}<br>
📧 メール：{$contact}<br>
📅 申請日時：" . current_time('Y-m-d H:i:s') . "<br><br>
✅ <a href='{$edit_url}'>→チーム承認はこちら</a>
";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    if (wp_mail(get_option('admin_email'), $subject, $message, $headers)) {
        update_post_meta($post_id, '_admin_notified', 1);
    }
}, 20);

/*--------------------------------------------------------------
  No.19 チーム編集リンク_ショートコード（author以上のみ）→削除済み
--------------------------------------------------------------*/
/*--------------------------------------------------------------
  No.18 会員登録／ログイン関連_チーム承認時に代表者へ通知メール

add_action('transition_post_status', function($new_status, $old_status, $post) {
  if ($post->post_type !== 'team' || $old_status !== 'pending' || $new_status !== 'publish') return;

  $author_id = $post->post_author;
  if (!$author_id) return;

  $user = get_userdata($author_id);
  $email = $user->user_email;
  $team_name = get_the_title($post);
  $edit_url = get_edit_post_link($post->ID);

  $subject = '【AidUnite】あなたのチームが承認されました';
  $message = "{$user->display_name} 様\n\n" .
             "あなたが申請したチーム『{$team_name}』が承認されました。\n\n" .
             "以下のリンクからチームの編集・管理が可能です：\n{$edit_url}\n\n" .
             "引き続き、AidUniteをよろしくお願いいたします。\n\nAidUnite 運営チーム";

  wp_mail($email, $subject, $message);
}, 10, 3);
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  No.18　20 チーム承認時に代表者ロール昇格＋通知
  ※ team_id 直書きは廃止（第18.7節: aidunite_handle_team_application_status_change /
    aidunite_user_attach_approved_team_membership が managed_team_ids をマージする）。
  旧フックが残ると2件目承認で team_id が上書きされ、managed が1件に潰れる。
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  決済機能は要件整理後に再実装予定
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  No.21 チーム登録ページ_チーム専用ページ自動生成
--------------------------------------------------------------*/
function create_team_page_for_user($user_id, $team_name) {
    $page_id = wp_insert_post([
        'post_title'   => $team_name . ' の専用ページ',
        'post_content' => 'ここにチームの紹介を記載してください。',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => $user_id,
    ]);
    if (!is_wp_error($page_id)) update_user_meta($user_id, 'team_page_id', $page_id);
}

/*--------------------------------------------------------------
  No.22_23 チーム登録ページ_公開チームへのユーザー申請受付
--------------------------------------------------------------*/
add_action('template_redirect', function () {
  if (!is_user_logged_in()) return;

  // 公開チーム申請（No.22）
  if (
    is_page('team-apply') &&
    isset($_POST['submit_public_request']) &&
    !empty($_POST['team_id'])
  ) {
    $user_id = get_current_user_id();
    $team_id = intval($_POST['team_id']);
    add_user_meta($user_id, 'team_application_pending', $team_id);
    wp_redirect(add_query_arg('applied', '1', get_permalink()));
    exit;
  }

  // シークレットコード申請（No.23）
  if (
    is_page('team-apply') &&
    isset($_POST['submit_secret_request']) &&
    !empty($_POST['secret_code'])
  ) {
    $entered_code = sanitize_text_field($_POST['secret_code']);

    $team_query = new WP_Query([
      'post_type' => 'team',
      'meta_query' => [
        [
          'key' => 'secret_code',
          'value' => $entered_code,
          'compare' => '='
        ]
      ],
      'posts_per_page' => 1
    ]);

    if ($team_query->have_posts()) {
      $team = $team_query->posts[0];
      $user_id = get_current_user_id();
      add_user_meta($user_id, 'team_application_pending', $team->ID);
      wp_redirect(add_query_arg('secret_applied', '1', get_permalink()));
    } else {
      wp_redirect(add_query_arg('secret_failed', '1', get_permalink()));
    }
    exit;
  }
});


/*--------------------------------------------------------------
  No.24 チーム登録ページ_招待URLでチーム参加／紐付け
--------------------------------------------------------------*/
add_action('template_redirect', function () {
  if (!is_user_logged_in()) return;

  // 招待URL経由のチーム参加処理
  if (
    is_page('team-apply') &&
    isset($_GET['join_code']) &&
    !empty($_GET['join_code'])
  ) {
    $code = sanitize_text_field($_GET['join_code']);

    // 該当チームを invite_code で検索
    $query = new WP_Query([
      'post_type' => 'team',
      'meta_query' => [
        [
          'key' => 'invite_code',
          'value' => $code,
          'compare' => '='
        ]
      ],
      'posts_per_page' => 1
    ]);

    if ($query->have_posts()) {
      $team = $query->posts[0];
      $user_id = get_current_user_id();

      // チームと紐付け（申請処理やmeta登録など）
      add_user_meta($user_id, 'team_application_pending', $team->ID);

      // メッセージ表示用にリダイレクト
      wp_redirect(add_query_arg('invited', '1', get_permalink()));
      exit;
    } else {
      wp_redirect(add_query_arg('invite_failed', '1', get_permalink()));
      exit;
    }
  }
});

/*--------------------------------------------------------------
  No.25 チーム登録ページ_保護者招待フォーム
--------------------------------------------------------------*/
add_action('template_redirect', function () {
  if (!is_user_logged_in()) return;
  if (!is_page('invite-guardian')) return;

  if (isset($_POST['send_invite']) && isset($_POST['guardian_email'])) {
    $email = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['guardian_email']) : sanitize_email($_POST['guardian_email']);
    $user_id = get_current_user_id();

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if (!$team_id) {
      wp_redirect(home_url('/invite-guardian/?error=no_team'));
      exit;
    }

    // aidunite_invite_parent_to_team() を使用（トークンベースの保護者専用フロー）
    $result = aidunite_invite_parent_to_team($team_id, $email);

    if ($result) {
      wp_redirect(home_url('/invite-guardian/?sent=1'));
    } else {
      wp_redirect(home_url('/invite-guardian/?error=send_failed'));
    }
    exit;
  }
});

/*--------------------------------------------------------------
No.27 練習・スケジュール登録_CPT：スケジュール（schedule）を作成
--------------------------------------------------------------*/
function register_schedule_post_type() {
  $labels = array(
    'name' => 'スケジュール',
    'singular_name' => 'スケジュール',
    'menu_name' => 'スケジュール',
    'name_admin_bar' => 'スケジュールを追加',
    'add_new' => '新規追加',
    'add_new_item' => '新しいスケジュールを追加',
    'new_item' => '新規スケジュール',
    'edit_item' => 'スケジュールを編集',
    'view_item' => 'スケジュールを表示',
    'all_items' => '全スケジュール',
    'search_items' => 'スケジュールを検索',
    'not_found' => 'スケジュールが見つかりませんでした',
    'not_found_in_trash' => 'ゴミ箱にスケジュールはいません',
  );

  $args = array(
    'labels' => $labels,
    'public' => true,
    'has_archive' => true,
    'show_in_menu' => false,
    'menu_position' => 6,
    'menu_icon' => 'dashicons-calendar-alt',
    'supports' => array('title', 'author'),
    'capability_type' => 'post',
    'show_in_rest' => true,
    'map_meta_cap' => true,
  );

  register_post_type('schedule', $args);
}
add_action('init', 'register_schedule_post_type');

/*--------------------------------------------------------------
  No.27-1 スケジュールのREST API削除権限設定
--------------------------------------------------------------*/
// REST APIでのスケジュール削除権限を設定
add_filter('rest_schedule_collection_params', function($params, $post_type) {
    return $params;
}, 10, 2);

// スケジュール削除時の権限チェック
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    // スケジュールの削除リクエストの場合
    if ($request->get_route() === '/wp/v2/schedule' && $request->get_method() === 'DELETE') {
        // 管理者または編集者権限があるかチェック
        if (!current_user_can('delete_posts') && !current_user_can('administrator')) {
            return new WP_Error('rest_forbidden', 'スケジュールの削除権限がありません。', array('status' => 403));
        }
    }

    // 特定のスケジュール削除リクエストの場合
    if (preg_match('/^\/wp\/v2\/schedule\/(\d+)$/', $request->get_route(), $matches) && $request->get_method() === 'DELETE') {
        $schedule_id = intval($matches[1]);

        // 管理者または編集者権限があるかチェック
        if (!current_user_can('delete_posts') && !current_user_can('administrator')) {
            return new WP_Error('rest_forbidden', 'スケジュールの削除権限がありません。', array('status' => 403));
        }

        // スケジュールが存在するかチェック
        $schedule = get_post($schedule_id);
        if (!$schedule || $schedule->post_type !== 'schedule') {
            return new WP_Error('rest_not_found', 'スケジュールが見つかりません。', array('status' => 404));
        }
    }

    return $result;
}, 10, 3);

/*--------------------------------------------------------------
  No.42 スケジュール保存時｜会場未設定バグ対策（チェックボックス対応）
--------------------------------------------------------------*/
add_action('save_post', function($post_id) {
  if (get_post_type($post_id) !== 'schedule' || is_admin()) return;

  $place = get_post_meta($post_id, 'schedule_place', true);
  $place_option = get_post_meta($post_id, 'schedule_place_option', true);

  // ✅ チェックボックス形式に対応（配列 → 文字列）
  if (is_array($place_option)) {
    $place_option = $place_option[0] ?? '';
  }

  if (empty($place) && !empty($place_option)) {
    if (function_exists('update_field')) {
      update_field('schedule_place', $place_option, $post_id);
    } else {
      update_post_meta($post_id, 'schedule_place', $place_option);
    }
  }
}, 20);

/*--------------------------------------------------------------
  No.43 スケジュール保存時｜match_board 自動生成処理（+ 自動match_request生成）
--------------------------------------------------------------*/
add_action('save_post', function($post_id) {
  if (get_post_type($post_id) !== 'schedule') return;


  // すでに match_board があるか確認
  $existing = get_posts([
    'post_type'  => 'match_board',
    'meta_query' => [
      ['key' => 'schedule_id', 'value' => $post_id]
    ],
    'post_status' => ['publish', 'draft'],
    'numberposts' => 1
  ]);

  if ($existing) {
    return;
  }

  // スケジュールの所有者チーム（post meta 優先、欠損時は著者の操作中チーム）
  $author_id = (int) get_post_field('post_author', $post_id);
  $team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
      ? (int) aidunite_resolve_schedule_owner_team_id((int) $post_id)
      : (int) get_user_meta($author_id, 'team_id', true);

  if (!$author_id || !$team_id) {
    return;
  }

  // match_board 作成
  $new_board_id = wp_insert_post([
    'post_type'    => 'match_board',
    'post_title'   => '自動作成-' . $post_id,
    'post_status'  => 'draft',
    'post_author'  => $author_id,
    'meta_input'   => [
      'team_id'     => $team_id,
      'schedule_id' => $post_id
    ]
  ]);

  // 自動マッチ対象スケジュールを取得（マッチ度問わず）
  // match-common-functions.phpは既に読み込み済みのため、重複読み込みを防止
  // 未定義関数のエラーを回避するため、代替処理を実装
  if (!function_exists('aidunite_get_all_matchable_schedules')) {
    // 代替処理：マッチ可能なスケジュールを直接取得
    $candidates = get_posts([
      'post_type' => 'schedule',
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query' => [
        'relation' => 'AND',
        [
          'key' => 'matching',
          'value' => '1',
          'compare' => '='
        ],
        [
          'key' => 'team_id',
          'value' => $team_id,
          'compare' => '!='
        ]
      ]
    ]);
  } else {
    $candidates = aidunite_get_all_matchable_schedules($post_id, $team_id);
  }

  if (!empty($candidates)) {
    foreach ($candidates as $candidate) {
      $to_schedule_id = $candidate->ID;
      $to_author_id   = (int) get_post_field('post_author', $to_schedule_id);
      $to_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
          ? (int) aidunite_resolve_schedule_owner_team_id((int) $to_schedule_id)
          : (int) get_user_meta($to_author_id, 'team_id', true);

      // 重複チェック
      if (function_exists('aidunite_get_existing_match_request')) {
        $existing_request = aidunite_get_existing_match_request($team_id, $to_team_id, $post_id, $to_schedule_id);
        if ($existing_request) {
          continue;
        }
      }

      // 防御：空チェック
      if (!$post_id || !$to_schedule_id || !$team_id) {
        continue;
      }

      // match_request 登録（メタは後で安全に追加）
      $match_request_id = wp_insert_post([
        'post_type'   => 'match_request',
        'post_status' => 'draft',
        'post_title'  => 'マッチ申請（自動生成） ' . current_time('mysql'),
        'post_author' => $author_id
      ]);

      if ($match_request_id) {
        // チームID取得
        $from_author_id = (int) get_post_field('post_author', $post_id);
        $to_author_id = (int) get_post_field('post_author', $to_schedule_id);
        $from_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
            ? (int) aidunite_resolve_schedule_owner_team_id((int) $post_id)
            : (int) get_user_meta($from_author_id, 'team_id', true);
        $to_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
            ? (int) aidunite_resolve_schedule_owner_team_id((int) $to_schedule_id)
            : (int) get_user_meta($to_author_id, 'team_id', true);

        // 必要なメタデータを設定
        update_post_meta($match_request_id, 'from_schedule_id', $post_id);
        update_post_meta($match_request_id, 'to_schedule_id', $to_schedule_id);
        update_post_meta($match_request_id, 'from_team_id', $from_team_id);
        update_post_meta($match_request_id, 'request_team_id', $from_team_id);
        update_post_meta($match_request_id, 'my_schedule_id', $post_id);
        aidunite_update_match_request_status_meta($match_request_id, 'draft'); // 自動マッチは draft 状態
        update_post_meta($match_request_id, 'request_status', 'draft'); // 自動マッチは draft 状態
        update_post_meta($match_request_id, 'type', 'auto');
        update_post_meta($match_request_id, 'is_auto_match', '1'); // 自動マッチの識別フラグ

        // match_board.match_request_id 単一ポインタへの依存は廃止。状態は MR 投稿および募集側のゲーム同期で管理する。
      }

      break; // 1件だけ紐づければOK（複数紐づけたい場合は break 削除）
    }
  }

}, 30);



/*--------------------------------------------------------------
No.28 練習・スケジュール登録_ショートコード登録＆表示
--------------------------------------------------------------*/
function schedule_form_shortcode() {
    ob_start();
    include get_stylesheet_directory() . '/schedule-form-template.php';
    return ob_get_clean();
}
add_shortcode('schedule_form', 'schedule_form_shortcode');

// No.28 練習・スケジュール登録 REST API（実装は functions/schedule/rest-schedule-legacy.php に移設）
require_once get_stylesheet_directory() . '/functions/schedule/rest-schedule-legacy.php';

/*--------------------------------------------------------------
No.28 共通JS：時間セレクトの分を10分刻みにする → js/common/minute-select.js で読込
---------------------------------------------------------------*/

/*--------------------------------------------------------------
No.28 共通JS：REST API用nonceをJavaScriptに渡す
--------------------------------------------------------------*/
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_script('jquery');
  wp_localize_script('jquery', 'wpApiSettings', array(
    'nonce' => wp_create_nonce('wp_rest'),
    'root'  => esc_url_raw(rest_url())
  ));
});


/*--------------------------------------------------------------
No.30 練習・スケジュール登録_カレンダー表示（ショートコード）
--------------------------------------------------------------*/
function schedule_calendar_shortcode() {
  ob_start();
  include get_stylesheet_directory() . '/schedule-calendar-template.php';
  return ob_get_clean();
}
add_shortcode('schedule_calendar', 'schedule_calendar_shortcode');


/*--------------------------------------------------------------
  No.36 練習試合マッチング機能_自チーム情報取得_REST API・マッチ度ロジック（functions/match/match-init.php に移設）
--------------------------------------------------------------*/
require_once get_stylesheet_directory() . '/functions/match/match-init.php';

/*--------------------------------------------------------------
  新仕様：マッチングロジック（ラベル + 理由チップ方式）
--------------------------------------------------------------*/

// 時間判定の閾値（定数化）
if (!defined('TIME_STRONG_START_DIFF')) {
  define('TIME_STRONG_START_DIFF', 60); // 分（🟢成立可能: 開始ずれの上限）
  define('TIME_STRONG_END_DIFF', 60); // 分（🟢成立可能: 終了ずれの上限）
  define('TIME_STRONG_OVERLAP_RATIO', 0.9); // 90%
  define('TIME_OK_OVERLAP_MINUTES', 60); // 分
  define('TIME_OK_OVERLAP_RATIO', 0.5); // 50%
  define('LONG_TIME_DURATION_THRESHOLD', 360); // 6時間（分）
}

// 時間を分に変換（time-utils.phpが読み込まれていない場合のフォールバック）
if (!function_exists('aidunite_time_to_minutes')) {
  function aidunite_time_to_minutes($time) {
    $parts = explode(':', $time);
    return intval($parts[0]) * 60 + intval($parts[1]);
  }
}

/**
 * 時間判定：TIME_STRONG / TIME_OK / TIME_WEAK
 *
 * @param string $my_start 自分の開始時間
 * @param string $my_end 自分の終了時間
 * @param string $other_start 相手の開始時間
 * @param string $other_end 相手の終了時間
 * @return array ['level' => 'TIME_STRONG|TIME_OK|TIME_WEAK', 'chip' => '理由チップ', 'overlap_minutes' => 重なり時間（分）]
 */
if (!function_exists('aidunite_get_time_level')) {
  function aidunite_get_time_level($my_start, $my_end, $other_start, $other_end) {
    // 時間を分に変換
    $my_start_minutes = aidunite_time_to_minutes($my_start);
    $my_end_minutes = aidunite_time_to_minutes($my_end);
    $other_start_minutes = aidunite_time_to_minutes($other_start);
    $other_end_minutes = aidunite_time_to_minutes($other_end);

    // 登録時間の長さを計算
    $my_duration = $my_end_minutes - $my_start_minutes;
    if ($my_duration < 0) $my_duration += 24 * 60; // 24時間を超える場合

    // 長い時間登録（6時間以上）の場合の特別処理
    if ($my_duration >= LONG_TIME_DURATION_THRESHOLD) {
      // 相手の時間が自分の登録時間内に完全に含まれる場合
      if ($other_start_minutes >= $my_start_minutes && $other_end_minutes <= $my_end_minutes) {
        return [
          'level' => 'TIME_STRONG',
          'chip' => '時間ぴったり',
          'overlap_minutes' => $other_end_minutes - $other_start_minutes
        ];
      }
      // 相手の時間が自分の登録時間と重なる場合
      if ($other_start_minutes < $my_end_minutes && $other_end_minutes > $my_start_minutes) {
        return [
          'level' => 'TIME_OK',
          'chip' => '時間重なり十分',
          'overlap_minutes' => min($my_end_minutes, $other_end_minutes) - max($my_start_minutes, $other_start_minutes)
        ];
      }
      return [
        'level' => 'TIME_WEAK',
        'chip' => '時間重なり短い',
        'overlap_minutes' => 0
      ];
    }

    // 通常の時間判定
    // 重なり時間を計算
    $overlap_start = max($my_start_minutes, $other_start_minutes);
    $overlap_end = min($my_end_minutes, $other_end_minutes);
    $overlap_minutes = max(0, $overlap_end - $overlap_start);

    // 短い方の時間を計算
    $my_duration_minutes = $my_end_minutes - $my_start_minutes;
    if ($my_duration_minutes < 0) $my_duration_minutes += 24 * 60;
    $other_duration_minutes = $other_end_minutes - $other_start_minutes;
    if ($other_duration_minutes < 0) $other_duration_minutes += 24 * 60;
    $duration_min = min($my_duration_minutes, $other_duration_minutes);

    // 開始・終了の差を計算
    $start_diff = abs($my_start_minutes - $other_start_minutes);
    $end_diff = abs($my_end_minutes - $other_end_minutes);

    // TIME_STRONG判定
    if (($start_diff <= TIME_STRONG_START_DIFF && $end_diff <= TIME_STRONG_END_DIFF) ||
        ($duration_min > 0 && $overlap_minutes >= $duration_min * TIME_STRONG_OVERLAP_RATIO)) {
      return [
        'level' => 'TIME_STRONG',
        'chip' => '時間ぴったり',
        'overlap_minutes' => $overlap_minutes
      ];
    }

    // TIME_OK判定
    if ($overlap_minutes >= TIME_OK_OVERLAP_MINUTES ||
        ($duration_min > 0 && $overlap_minutes >= $duration_min * TIME_OK_OVERLAP_RATIO)) {
      return [
        'level' => 'TIME_OK',
        'chip' => '時間重なり十分',
        'overlap_minutes' => $overlap_minutes
      ];
    }

    // TIME_WEAK判定（重なりがある場合のみ）
    if ($overlap_minutes > 0) {
      return [
        'level' => 'TIME_WEAK',
        'chip' => '時間重なり短い',
        'overlap_minutes' => $overlap_minutes
      ];
    }

    // 重なりなし（候補から除外）
    return null;
  }
}

/**
 * 会場判定：PLACE_EASY / PLACE_MEDIUM / PLACE_HEAVY
 *
 * @param string $my_place 自分の会場条件
 * @param string $other_place 相手の会場条件
 * @return array ['level' => 'PLACE_EASY|PLACE_MEDIUM|PLACE_HEAVY', 'chip' => '理由チップ']
 */
if (!function_exists('aidunite_get_place_level')) {
  function aidunite_get_place_level($my_place, $other_place) {
    $my_place = normalize_place_value($my_place);
    $other_place = normalize_place_value($other_place);

    // PLACE_HEAVY：同じ方向（home-home / away-away）— 提案型調整が必要
    if ($my_place === $other_place && in_array($my_place, ['home', 'away'], true)) {
      return [
        'level' => 'PLACE_HEAVY',
        'chip' => '会場調整重め'
      ];
    }

    // PLACE_EASY：ホーム×アウェイ、または either を含む融通◎の組み合わせ
    if (($my_place === 'home' && $other_place === 'away') ||
        ($my_place === 'away' && $other_place === 'home') ||
        $my_place === 'either' || $other_place === 'either') {
      $chip = '会場そのままOK';
      if ($my_place === 'either' || $other_place === 'either') {
        $chip = ($my_place === 'either' && $other_place === 'either')
          ? '会場を選んで申請'
          : '会場融通◎';
      }
      return [
        'level' => 'PLACE_EASY',
        'chip' => $chip
      ];
    }

    return null;
  }
}

/**
 * 性別判定：Hard除外チェック
 *
 * @param string $my_gender 自分の性別条件
 * @param string $other_gender 相手の性別条件
 * @return array ['is_compatible' => bool, 'is_mixed' => bool]
 */
if (!function_exists('aidunite_check_gender_compatibility')) {
  function aidunite_check_gender_compatibility($my_gender, $other_gender) {
    if (function_exists('aidunite_normalize_gender_canonical')) {
      $a = aidunite_normalize_gender_canonical((string) $my_gender);
      $b = aidunite_normalize_gender_canonical((string) $other_gender);
      if (!function_exists('aidunite_mvp_gender_is_valid') || !aidunite_mvp_gender_is_valid($a) || !aidunite_mvp_gender_is_valid($b)) {
        return [
          'is_compatible' => false,
          'is_mixed'      => false,
        ];
      }
      $ok = ($a === $b);

      return [
        'is_compatible' => $ok,
        'is_mixed'      => false,
      ];
    }

    return [
      'is_compatible' => ((string) $my_gender === (string) $other_gender),
      'is_mixed'      => false,
    ];
  }
}

/**
 * 4ラベルへの割り当て（最終判定）
 *
 * @param string $time_level TIME_STRONG|TIME_OK|TIME_WEAK
 * @param string $place_level PLACE_EASY|PLACE_MEDIUM|PLACE_HEAVY
 * @param bool $gender_mixed 性別混在フラグ
 * @return string ベストマッチ|高マッチ|中マッチ|低マッチ
 */
if (!function_exists('aidunite_get_final_label')) {
  function aidunite_get_final_label($time_level, $place_level, $gender_mixed = false) {
    // ステップ1：時間で初期ラベル決定
    switch ($time_level) {
      case 'TIME_STRONG':
        $initial_label = 'ベストマッチ';
        break;
      case 'TIME_OK':
        $initial_label = '高マッチ';
        break;
      case 'TIME_WEAK':
        $initial_label = '中マッチ';
        break;
      default:
        $initial_label = '低マッチ';
        break;
    }

    // ステップ2：会場で調整
    if ($place_level === 'PLACE_HEAVY') {
      switch ($initial_label) {
        case 'ベストマッチ':
          $initial_label = '高マッチ';
          break;
        case '高マッチ':
          $initial_label = '中マッチ';
          break;
        case '中マッチ':
          $initial_label = '低マッチ';
          break;
        default:
          $initial_label = '低マッチ';
          break;
      }
    }

    // ステップ3：性別混在で調整（オプション）
    if ($gender_mixed && $initial_label !== '低マッチ') {
      // 落としすぎ注意：ベストマッチは維持、高マッチ→中マッチ、中マッチ→低マッチ
      if ($initial_label === '高マッチ') {
        $initial_label = '中マッチ';
      } elseif ($initial_label === '中マッチ') {
        $initial_label = '低マッチ';
      }
    }

    return $initial_label;
  }
}

/**
 * 理由チップ生成（最大2つ）
 *
 * @param string $time_level TIME_STRONG|TIME_OK|TIME_WEAK
 * @param string $place_level PLACE_EASY|PLACE_MEDIUM|PLACE_HEAVY
 * @param bool $gender_mixed 性別混在フラグ
 * @return array 理由チップ配列（最大2つ）
 */
if (!function_exists('aidunite_generate_reason_chips')) {
  function aidunite_generate_reason_chips($time_level, $place_level, $gender_mixed = false) {
    $chips = [];

    // 時間系チップ（必ず1つ）
    $time_chips = [
      'TIME_STRONG' => '時間ぴったり',
      'TIME_OK' => '時間重なり十分',
      'TIME_WEAK' => '時間重なり短い'
    ];
    if (isset($time_chips[$time_level])) {
      $chips[] = $time_chips[$time_level];
    }

    // 会場系チップ（必ず1つ）
    $place_chips = [
      'PLACE_EASY' => '会場そのままOK',
      'PLACE_MEDIUM' => '会場相談あり',
      'PLACE_HEAVY' => '会場調整重め'
    ];
    if (isset($place_chips[$place_level])) {
      $chips[] = $place_chips[$place_level];
    }

    // 性別系チップ（必要なら、ただし最大2つまで）
    if ($gender_mixed && count($chips) < 2) {
      $chips[] = '性別要確認';
    }

    // 最大2つまで
    return array_slice($chips, 0, 2);
  }
}

/**
 * マッチ結論メッセージを生成
 *
 * @param string $match_label マッチ度ラベル（ベストマッチ/高マッチ/中マッチ/低マッチ）
 * @param array $adjustment_items 調整が必要な項目の配列（例: ['会場', '時間']）
 * @return array ['message' => 'メッセージ', 'class' => 'CSSクラス']
 */
if (!function_exists('aidunite_get_match_conclusion_message')) {
  function aidunite_get_match_conclusion_message($match_label, $adjustment_items = []) {
    $messages = [
      'ベストマッチ' => [
        'message' => '✅ 条件が完璧です！申請しましょう！',
        'class' => 'match-perfect'
      ],
      '高マッチ' => [
        'message' => '⚠️ ' . (!empty($adjustment_items) ? implode('or', $adjustment_items) . '調整で試合成立します！' : '一部調整で試合成立します！'),
        'class' => 'match-high'
      ],
      '中マッチ' => [
        'message' => '⚠️ ' . (!empty($adjustment_items) ? implode('と', $adjustment_items) . 'の調整しましょう！' : '複数項目の調整が必要です！'),
        'class' => 'match-medium'
      ],
      '低マッチ' => [
        'message' => '⚠️ 調整箇所が複数ありますが調整ができれば試合できます！',
        'class' => 'match-low'
      ]
    ];

    $result = isset($messages[$match_label]) ? $messages[$match_label] : [
      'message' => '⚠️ 調整が必要です',
      'class' => 'match-low'
    ];
    // ベストマッチでも調整項目がある場合（例：性別要確認）は「〇の調整」メッセージに統一
    if ($match_label === 'ベストマッチ' && !empty($adjustment_items)) {
      $result = [
        'message' => '⚠️ ' . implode('と', $adjustment_items) . 'の調整しましょう！',
        'class' => 'match-high'
      ];
    }
    return $result;
  }
}

/**
 * 申請中カード用：最新マッチリクエストの申請内容を1行で要約（会場＋時間）
 * match_request の selected_place / selected_start_time〜selected_end_time から生成
 *
 * @param int $current_user_team_id 現在ユーザーのチームID
 * @param int $my_schedule_id 自チームのスケジュールID
 * @param int $candidate_team_id 相手チームID
 * @param int $candidate_schedule_id 相手スケジュールID（候補カードの schedule post ID）
 * @return string 例「相手ホーム・16:00～18:00で申請中」「アウェイで申請中」、取得できない場合は「申請中」
 */
if (!function_exists('aidunite_get_application_summary_line')) {
  function aidunite_get_application_summary_line($current_user_team_id, $my_schedule_id, $candidate_team_id, $candidate_schedule_id) {
    if (!function_exists('get_latest_match_request_bidirectional')) {
      return '申請中';
    }
    $latest = get_latest_match_request_bidirectional($current_user_team_id, $my_schedule_id, $candidate_team_id, $candidate_schedule_id, [
      'restrict_to_to_schedule_id' => (int) $candidate_schedule_id,
    ]);
    if (!$latest) {
      return '申請中';
    }
    if ((int) get_post_meta($latest->ID, 'requires_reconfirm', true) === 1) {
      return '募集条件変更のため再申請待ち';
    }
    $mr_outcome = (string) get_post_meta($latest->ID, 'mr_outcome_code', true);
    if ($mr_outcome !== '' && $mr_outcome !== 'keep_pending' && function_exists('aidunite_match_request_outcome_label_jp')) {
      $outcome_label = aidunite_match_request_outcome_label_jp($mr_outcome);
      if ($outcome_label !== '') {
        return $outcome_label;
      }
    }
    if ((int) get_post_meta($latest->ID, 'proposal_pending_accept', true) === 1) {
      return '最新条件の提案に対する承諾待ち';
    }
    $place = get_post_meta($latest->ID, 'selected_place', true);
    $start = get_post_meta($latest->ID, 'selected_start_time', true);
    $end = get_post_meta($latest->ID, 'selected_end_time', true);
    $parts = [];
    if ($place === 'home') {
      $parts[] = '相手ホーム';
    } elseif ($place === 'away') {
      $parts[] = 'アウェイ';
    } elseif ($place === 'either') {
      $parts[] = '会場どちらでも';
    }
    if ($start && $end) {
      $parts[] = $start . '～' . $end;
    }
    if (empty($parts)) {
      return '申請中';
    }
    return implode('・', $parts) . 'で申請中';
  }
}

/**
 * マッチカード上部の結論メッセージをステータスに応じて取得（仕様 3.2）
 * 未申請のときだけマッチ度メッセージ、それ以外はステータス別メッセージ
 *
 * @param string $status_text 申請ステータス表示（未申請/申請中/申請受付中/承認済み/試合確定/拒否済み/キャンセル済み/相手キャンセル）
 * @param string $match_label マッチ度ラベル（未申請時用）
 * @param array $adjustment_items 調整項目（未申請時用）
 * @param int|null $current_user_team_id 申請中要約用
 * @param int|null $schedule_id 申請中要約用
 * @param int|null $candidate_team_id 申請中要約用
 * @param int|null $candidate_schedule_id 申請中要約用
 * @return array ['message' => string, 'class' => string]
 */
if (!function_exists('aidunite_get_match_card_conclusion_by_status')) {
  function aidunite_get_match_card_conclusion_by_status($status_text, $match_label, $adjustment_items = [], $current_user_team_id = null, $schedule_id = null, $candidate_team_id = null, $candidate_schedule_id = null) {
    $norm_status = function_exists('aidunite_normalize_match_request_status')
      ? aidunite_normalize_match_request_status((string) $status_text, '')
      : (string) $status_text;
    if ($norm_status === 'not_applied' || $status_text === '' || $status_text === '未申請') {
      return aidunite_get_match_conclusion_message($match_label, $adjustment_items);
    }
    $status_messages = [
      '申請中' => ['message' => '申請中', 'class' => 'match-status-applying'],
      '申請受付中' => ['message' => '〇の条件で申請が届いています', 'class' => 'match-status-received'],
      '承認済み' => ['message' => '試合が確定しました', 'class' => 'match-established'],
      '試合確定' => ['message' => '試合が確定しました', 'class' => 'match-established'],
      '再確認待ち' => ['message' => '募集条件変更のため再申請待ちです', 'class' => 'match-canceled'],
      '相手承諾待ち' => ['message' => '最新条件の提案に対する承諾待ちです', 'class' => 'match-pending'],
      '拒否済み' => ['message' => 'この申請は拒否されました', 'class' => 'match-rejected'],
      'キャンセル済み' => ['message' => 'あなたがキャンセルしました', 'class' => 'match-canceled'],
      '相手キャンセル' => ['message' => '相手がキャンセルしました', 'class' => 'match-canceled'],
    ];
    $result = isset($status_messages[$status_text]) ? $status_messages[$status_text] : ['message' => '条件を確認してください', 'class' => 'match-low'];
    if ($status_text === '申請中' && $current_user_team_id && $schedule_id && $candidate_team_id && $candidate_schedule_id && function_exists('aidunite_get_application_summary_line')) {
      $summary = aidunite_get_application_summary_line($current_user_team_id, $schedule_id, $candidate_team_id, $candidate_schedule_id);
      $result['message'] = $summary;
    }
    return $result;
  }
}

/**
 * 理由チップを具体化（新仕様）
 *
 * @param string $type タイプ（time/place/gender）
 * @param string $level レベル（TIME_STRONG/TIME_OK/TIME_WEAK/PLACE_EASY/PLACE_MEDIUM/PLACE_HEAVY）
 * @param bool $gender_mixed 性別混在フラグ
 * @param string $my_value 自分の値
 * @param string $other_value 相手の値
 * @return array ['text' => '表示テキスト', 'class' => 'CSSクラス（match-ok/match-adjustable）']
 */
if (!function_exists('aidunite_get_detailed_reason_chip')) {
  function aidunite_get_detailed_reason_chip($type, $level, $gender_mixed = false, $my_value = '', $other_value = '') {
    $chips = [];

    // 時間系
    if ($type === 'time') {
      switch ($level) {
        case 'TIME_STRONG':
          $chips = ['text' => 'OK', 'class' => 'match-ok'];
          break;
        case 'TIME_OK':
        case 'TIME_WEAK':
          $chips = ['text' => '時間の調整が必要。', 'class' => 'match-adjustable'];
          break;
        default:
          $chips = ['text' => '時間の調整が必要。', 'class' => 'match-adjustable'];
      }
    }

    // 会場系
    if ($type === 'place') {
      switch ($level) {
        case 'PLACE_EASY':
          $chips = ['text' => 'OK', 'class' => 'match-ok'];
          break;
        case 'PLACE_MEDIUM':
        case 'PLACE_HEAVY':
          $chips = ['text' => '会場の調整が必要。', 'class' => 'match-adjustable'];
          break;
        default:
          $chips = ['text' => '会場の調整が必要。', 'class' => 'match-adjustable'];
      }
    }

    // 性別系
    if ($type === 'gender') {
      if ($gender_mixed) {
        $chips = ['text' => '性別の調整が必要。', 'class' => 'match-adjustable'];
      } else {
        $chips = ['text' => 'OK', 'class' => 'match-ok'];
      }
    }

    return $chips;
  }
}

/**
 * 新仕様のマッチング判定（メイン関数）
 *
 * @param array $my_schedule 自分のスケジュール ['schedule_date', 'start', 'end', 'gender', 'place']
 * @param array $other_schedule 相手のスケジュール ['schedule_date', 'start', 'end', 'gender', 'place']
 * @return array ['label' => 'ラベル', 'chips' => ['理由チップ'], 'time_level' => 'TIME_STRONG|TIME_OK|TIME_WEAK', 'place_level' => 'PLACE_EASY|PLACE_MEDIUM|PLACE_HEAVY', 'overlap_minutes' => 重なり時間（分）]
 */
if (!function_exists('aidunite_get_match_label_new')) {
  function aidunite_get_match_label_new($my_schedule, $other_schedule) {
    // Hard除外：日付不一致
    if ($my_schedule['schedule_date'] !== $other_schedule['schedule_date']) {
      return null; // 候補から除外
    }

    // Hard除外：性別完全不一致
    $gender_check = aidunite_check_gender_compatibility($my_schedule['gender'], $other_schedule['gender']);
    if (!$gender_check['is_compatible']) {
      return null; // 候補から除外
    }

    // Soft分類：時間判定
    $time_result = aidunite_get_time_level(
      $my_schedule['start'],
      $my_schedule['end'],
      $other_schedule['start'],
      $other_schedule['end']
    );

    if (!$time_result) {
      return null; // 重なりなし（候補から除外）
    }

    // Soft分類：会場判定
    $place_result = aidunite_get_place_level($my_schedule['place'], $other_schedule['place']);
    if (!$place_result) {
      return null; // 会場条件が判定できない
    }

    // 4ラベルへの割り当て（性別混在による減点は廃止・仕様6.1）
    $final_label = aidunite_get_final_label(
      $time_result['level'],
      $place_result['level'],
      false
    );

    // 理由チップ生成（gender_mixed はラベルに使わない）
    $chips = aidunite_generate_reason_chips(
      $time_result['level'],
      $place_result['level'],
      false
    );

    return [
      'label' => $final_label,
      'chips' => $chips,
      'time_level' => $time_result['level'],
      'place_level' => $place_result['level'],
      'gender_mixed' => $gender_check['is_mixed'],
      'overlap_minutes' => $time_result['overlap_minutes']
    ];
  }
}

if (!function_exists('get_adjustable_options')) {
  function get_adjustable_options($type, $my_value, $other_value) {
    if ($type === 'place') {
      $my_value = normalize_place_value($my_value);
      $other_value = normalize_place_value($other_value);

      // 追加部分：どちらもOK × どちらもOK → ['home', 'away'] を返す
      if ($my_value === 'either' && $other_value === 'either') {
        return ['home', 'away'];
      }

      if (
        ($my_value === 'home' && $other_value === 'away') ||
        ($my_value === 'away' && $other_value === 'home')
      ) {
        return []; // 完全マッチ → 調整不要
      }

      if ($my_value === 'either' && $other_value === 'home') return ['home'];
      if ($my_value === 'either' && $other_value === 'away') return ['away'];
      if ($my_value === 'home' && $other_value === 'either') return ['home'];
      if ($my_value === 'away' && $other_value === 'either') return ['away'];

      if ($my_value === $other_value) return [$my_value];

      return [];
    }

    if ($type === 'gender') {
      if ($other_value === '男子のみ') return ['男子のみ'];
      if ($other_value === '女子のみ') return ['女子のみ'];
      return ['男子・女子可', '男子のみ', '女子のみ'];
    }

    return [];
  }
}

/*--------------------------------------------------------------
  No.38 自チーム情報取得（安全チェック付き）
--------------------------------------------------------------*/
function aidunite_get_my_team_info($user_id = null) {
  if (!$user_id) {
    $user_id = get_current_user_id();
  }

  $team_id = function_exists('aidunite_get_current_team_id')
      ? (int) aidunite_get_current_team_id((int) $user_id)
      : (int) get_user_meta($user_id, 'team_id', true);
  if (!$team_id) return null;

  $team_category = get_post_meta($team_id, 'team_category', true);
  $team_type     = get_post_meta($team_id, 'team_type', true);
  if (function_exists('aidunite_team_type_to_canonical')) {
      $team_type = aidunite_team_type_to_canonical($team_type);
  }
  $sport_type    = get_post_meta($team_id, 'sport_type', true);
  $region        = get_post_meta($team_id, 'region', true);
  $gender        = get_post_meta($team_id, 'team_gender_option', true);

  return [
    'team_id'       => $team_id,
    'team_category' => $team_category,
    'team_type'     => $team_type,
    'sport_type'    => $sport_type,
    'region'        => $region,
    'gender'        => $gender
  ];
}

/*--------------------------------------------------------------
  No.43 練習試合マッチング機能_申請済みかどうかを判定する関数
--------------------------------------------------------------*/
function aidunite_has_already_applied($user_id, $to_team_id) {
  $args = [
    'post_type' => 'match_log',
    'post_status' => 'publish',
    'meta_query' => [
      [
        'key' => 'from_user_id',
        'value' => $user_id,
      ],
      [
        'key' => 'to_team_id',
        'value' => $to_team_id,
      ],
    ],
  ];
  $query = new WP_Query($args);
  return $query->have_posts();
}

/*--------------------------------------------------------------
  No.41-50 連絡・通知機能_通知投稿時に link_url を自動付与する処理
type（通知タイプ）と related_post_id（関連投稿ID）に応じて、
通知に link_url を自動で設定します。
対象タイプとURLテンプレートは $url_templates に定義。
--------------------------------------------------------------*/
function aidunite_set_notification_link($post_id, $post) {
  if ($post->post_type !== 'notification' || wp_is_post_revision($post_id)) return;

  $type        = get_post_meta($post_id, 'type', true);
  $related_id  = get_post_meta($post_id, 'related_post_id', true); // 関連する投稿ID（例：練習IDやマッチID）

  // 通知タイプ別URLテンプレート
  $url_templates = [
    'match_established' => '/match-detail/?id=%d',
    'match_accepted'    => '/match-detail/?id=%d', // legacy type 読取互換
    'practice_added'    => '/practice-detail/?id=%d',
    'message_received'  => '/messages/?chat_id=%d',
    'reward_available'  => '/rewards',
  ];

  if (isset($url_templates[$type])) {
    $url = $related_id ? sprintf(home_url($url_templates[$type]), $related_id) : home_url($url_templates[$type]);
  } else {
    $url = home_url('/notifications'); // フォールバック
  }

  if (function_exists('update_field')) {
    update_field('link_url', esc_url_raw($url), $post_id);
  } else {
    update_post_meta($post_id, 'link_url', esc_url_raw($url));
  }
}
add_action('save_post_notification', 'aidunite_set_notification_link', 10, 2);



/*--------------------------------------------------------------
  No.40 通知保存ユーティリティ関数（マッチング関連通知）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  No.41 通知ログの既読処理（REST API）
--------------------------------------------------------------*/
require_once get_stylesheet_directory() . '/functions/rest-notification-update.php';
require_once get_stylesheet_directory() . '/functions/rest-notification-test.php';

/*--------------------------------------------------------------
  No.42 マッチ履歴保存用 CPT：match_log を登録
--------------------------------------------------------------*/
function register_match_log_post_type() {
  $labels = array(
    'name'               => 'マッチ履歴',
    'singular_name'      => 'マッチ履歴',
    'menu_name'          => 'マッチ履歴',
    'name_admin_bar'     => 'マッチ履歴',
    'add_new'            => '新規追加',
    'add_new_item'       => 'マッチ履歴を追加',
    'new_item'           => '新規マッチログ',
    'edit_item'          => 'マッチ履歴を編集',
    'view_item'          => 'マッチ履歴を表示',
    'all_items'          => 'すべての履歴',
    'search_items'       => '履歴を検索',
    'not_found'          => '履歴が見つかりませんでした',
    'not_found_in_trash' => 'ゴミ箱に履歴はありません',
  );

  $args = array(
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => true,
    'show_in_menu'       => true,
    'menu_position'      => 35,
    'menu_icon'          => 'dashicons-list-view',
    'supports'           => array('title', 'custom-fields'),
    'capability_type'    => 'post',
    'show_in_rest'       => true,
  );

  register_post_type('match_log', $args);
}
add_action('init', 'register_match_log_post_type');

/*--------------------------------------------------------------
  No.XX マッチアンケート保存用 CPT：match_feedback を登録
--------------------------------------------------------------*/
function register_match_feedback_post_type() {
  $labels = array(
    'name'               => 'マッチアンケート',
    'singular_name'      => 'マッチアンケート',
    'menu_name'          => 'マッチアンケート',
    'name_admin_bar'     => 'マッチアンケート',
    'add_new'            => '新規追加',
    'add_new_item'       => 'マッチアンケートを追加',
    'new_item'           => '新規アンケート',
    'edit_item'          => 'マッチアンケートを編集',
    'view_item'          => 'マッチアンケートを表示',
    'all_items'          => 'すべてのアンケート',
    'search_items'       => 'アンケートを検索',
    'not_found'          => 'アンケートが見つかりませんでした',
    'not_found_in_trash' => 'ゴミ箱にアンケートはありません',
  );

  $args = array(
    'labels'             => $labels,
    'public'             => false,
    'show_ui'            => false,
    'show_in_menu'       => false,
    'menu_position'      => 36,
    'menu_icon'          => 'dashicons-clipboard',
    'supports'           => array('title', 'custom-fields'),
    'capability_type'    => 'post',
    'show_in_rest'       => true,
  );

  register_post_type('match_feedback', $args);
}
add_action('init', 'register_match_feedback_post_type');





/*--------------------------------------------------------------
  No.998 match-actions.js
  読込は functions/common/enqueue.php（match-detail のみ）
--------------------------------------------------------------*/
/*--------------------------------------------------------------
 <input type="hidden" id="my_team_id" value="<?php echo esc_attr($my_team_id); ?>">
<input type="hidden" id="my_schedule_id" value="<?php echo esc_attr($my_schedule_id); ?>">
<input type="hidden" id="other_team_id" value="<?php echo esc_attr($other_team_id); ?>">
<input type="hidden" id="other_schedule_id" value="<?php echo esc_attr($other_schedule_id); ?>">
--------------------------------------------------------------*/


/*--------------------------------------------------------------
  No.999 JS読み込み：match-board の申請ボタン状態制御（No.10対応）
--------------------------------------------------------------*/
function enqueue_match_board_scripts() {
  if (is_page('match-board-own')) {
    wp_enqueue_script(
      'match-board-button-js',
      get_stylesheet_directory_uri() . '/assets/js/match/match-board-button-control.js',
      [],
      null,
      true
    );
  }
}
add_action('wp_enqueue_scripts', 'enqueue_match_board_scripts');

/*--------------------------------------------------------------
  No.1000 JS読み込み：ヘッダー・フッター機能（モバイルメニュー、検索、通知等）
--------------------------------------------------------------*/
function enqueue_header_footer_scripts() {
  wp_enqueue_script(
    'aidunite-header-footer',
    get_stylesheet_directory_uri() . '/assets/js/common/header-footer.js',
    ['jquery', 'aidunite-theme-icons'],
    '1.0.1',
    true
  );
}
add_action('wp_enqueue_scripts', 'enqueue_header_footer_scripts');

/**
 * ヘッダー: 操作中チーム切替（複数 managed の team_leader のみ有効）
 */
function aidunite_enqueue_operating_team_switch_assets() {
  if (!is_user_logged_in()) {
    return;
  }
  $cfg = aidunite_get_operating_team_header_switcher_config(get_current_user_id());
  wp_register_script(
    'aidunite-operating-team-switch',
    get_stylesheet_directory_uri() . '/assets/js/common/operating-team-switch.js',
    [],
    '1.0.3',
    true
  );
  wp_enqueue_script('aidunite-operating-team-switch');
  wp_localize_script('aidunite-operating-team-switch', 'aiduniteOperatingTeam', $cfg);
  if (!empty($cfg['enabled'])) {
    wp_enqueue_style(
      'aidunite-operating-team-switch',
      get_stylesheet_directory_uri() . '/assets/css/components/operating-team-switcher.css',
      [],
      '1.0.0'
    );
  }
}
add_action('wp_enqueue_scripts', 'aidunite_enqueue_operating_team_switch_assets', 21);


/*--------------------------------------------------------------
  チーム作成申請フロー
--------------------------------------------------------------*/

// チーム作成申請のAJAX処理
// save_team: テーマのメイン申請は page-team-registration の action=team_registration のみ。
// 本経路はレガシー互換の封印候補。処理は aidunite_register_team に委譲（pending_team_id のみ・承認まで team_id 非付与）。
add_action('wp_ajax_save_team', 'aidunite_save_team_application');
add_action('wp_ajax_nopriv_save_team', 'aidunite_save_team_application');

function aidunite_save_team_application() {
    // 統一認証・権限チェック
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_auth(false);
    if (!$auth_result->is_valid()) {
        wp_die($auth_result->error ?: 'ログインが必要です。');
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('team_metabox_nonce', 'save_team_metabox');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message());
    }

    $user_id = $auth_result->user_id;

    // 必須フィールドのチェック
    $required_fields = ['team_name', 'sport_type', 'team_category', 'team_type', 'region', 'registrant_name', 'contact_mail'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error('必須項目が入力されていません: ' . $field);
        }
    }

    $team_data = [
        'team_name' => sanitize_text_field($_POST['team_name']),
        'team_name_kana' => isset($_POST['team_name_kana']) ? sanitize_text_field($_POST['team_name_kana']) : '',
        'team_description' => isset($_POST['team_description']) ? sanitize_textarea_field($_POST['team_description']) : '',
        'team_achievements' => isset($_POST['team_achievements']) ? sanitize_text_field($_POST['team_achievements']) : '',
        'sport_type' => sanitize_text_field($_POST['sport_type']),
        'team_category' => sanitize_text_field($_POST['team_category']),
        'team_type' => sanitize_text_field($_POST['team_type']),
        'team_gender_option' => isset($_POST['team_gender_option']) ? sanitize_text_field($_POST['team_gender_option']) : '',
        'region' => sanitize_text_field($_POST['region']),
        'team_logo' => isset($_POST['team_logo']) ? sanitize_text_field($_POST['team_logo']) : '',
        'registrant_name' => sanitize_text_field($_POST['registrant_name']),
        'contact_mail' => sanitize_email($_POST['contact_mail']),
        'contact_phone' => isset($_POST['contact_phone']) ? sanitize_text_field($_POST['contact_phone']) : '',
    ];

    $team_id = aidunite_register_team($user_id, $team_data);

    if (!$team_id) {
        $detail = function_exists('aidunite_register_team_take_last_error')
            ? aidunite_register_team_take_last_error()
            : '';
        wp_send_json_error(
            $detail !== ''
                ? $detail
                : 'チーム作成に失敗しました。'
        );
    }

    wp_send_json_success([
        'message' => 'チーム作成申請が送信されました。承認までお待ちください。',
        'team_id' => $team_id,
    ]);
}

// チーム申請の承認・却下処理
add_action('transition_post_status', 'aidunite_handle_team_application_status_change', 10, 3);

function aidunite_handle_team_application_status_change($new_status, $old_status, $post) {
    // チーム投稿タイプのみ処理
    if ($post->post_type !== 'team') {
        return;
    }

    // pendingからpublishに変更された場合（承認）
    if ($old_status === 'pending' && $new_status === 'publish') {
        aidunite_approve_team_creation_application($post->ID);
    }

    // pendingからtrashに変更された場合（完全却下・WP管理画面等）
    if ($old_status === 'pending' && $new_status === 'trash') {
        aidunite_reject_team_creation_application($post->ID);
    }
}

// チーム申請承認処理（transition_post_status および既に publish の救済呼び出し用）
function aidunite_approve_team_creation_application($team_id) {
    $user_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : (int) get_post_field('post_author', $team_id);
    if ($user_id <= 0) {
        return;
    }

    if (function_exists('aidunite_team_sync_post_leader_linkage')) {
        aidunite_team_sync_post_leader_linkage($team_id, $user_id);
    }

    $promoted = (int) get_post_meta($team_id, 'aidunite_team_applicant_promoted_uid', true);
    if ($promoted === $user_id) {
        return;
    }

    $team_name = get_post_meta($team_id, 'team_name', true);
    $registrant_name = get_post_meta($team_id, 'registrant_name', true);

    if (function_exists('aidunite_user_attach_approved_team_membership')) {
        aidunite_user_attach_approved_team_membership($user_id, $team_id);
    } else {
        update_user_meta($user_id, 'team_id', $team_id);
        delete_user_meta($user_id, 'pending_team_id');
        update_user_meta($user_id, 'aidunite_role', 'team_leader');
    }

    update_post_meta($team_id, 'team_status', 'active');
    update_post_meta($team_id, 'aidunite_team_applicant_promoted_uid', (string) $user_id);

    if (function_exists('aidunite_clear_user_needs_revision_state')) {
        aidunite_clear_user_needs_revision_state($user_id, $team_id);
    }

    if (function_exists('aidunite_team_activation_init_on_approval')) {
        aidunite_team_activation_init_on_approval($team_id);
    }

    if (function_exists('aidunite_send_team_application_approved_notification')) {
        aidunite_send_team_application_approved_notification($user_id, $team_id);
    } elseif (function_exists('aidunite_notify_team_leader_approval')) {
        aidunite_notify_team_leader_approval($user_id, $team_id);
    } else {
        $user = get_userdata($user_id);
        if ($user && !empty($user->user_email)) {
            $subject = 'チーム作成申請が承認されました';
            $message = "
{$registrant_name} 様

チーム作成申請が承認されました。

【承認されたチーム】
チーム名: {$team_name}

これでチーム管理機能をご利用いただけます。
マイページからチーム管理を行ってください。

---
Ainy システム
";
            wp_mail($user->user_email, $subject, $message);
        }
    }
}

// チーム申請却下処理
function aidunite_reject_team_creation_application($team_id) {
    $user_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : (int) get_post_field('post_author', $team_id);
    $team_name = get_post_meta($team_id, 'team_name', true);
    $registrant_name = get_post_meta($team_id, 'registrant_name', true);

    if ($user_id > 0 && function_exists('aidunite_user_detach_rejected_pending_team')) {
        aidunite_user_detach_rejected_pending_team($user_id, $team_id);
    } else {
        delete_user_meta($user_id, 'pending_team_id');
    }

    if ($user_id > 0 && function_exists('aidunite_clear_user_needs_revision_state')) {
        aidunite_clear_user_needs_revision_state($user_id, $team_id);
    }

    delete_post_meta($team_id, 'aidunite_team_applicant_promoted_uid');

    $user = get_userdata($user_id);
    if ($user && !empty($user->user_email)) {
        $subject = 'チーム作成申請について';
        $message = "
{$registrant_name} 様

チーム作成申請について、審査の結果、承認を見送らせていただきました。

【申請チーム】
チーム名: {$team_name}

詳細な理由や改善点について、別途ご連絡いたします。
ご不明な点がございましたら、お気軽にお問い合わせください。

---
Ainy システム
";

        wp_mail($user->user_email, $subject, $message);
    }
}

// 管理者向け承認・却下処理
add_action('admin_post_approve_team_creation', 'aidunite_admin_approve_team_creation');
add_action('admin_post_reject_team_creation', 'aidunite_admin_reject_team_creation');

// スケジュール保存処理（レガシー admin-post。正ルートは page-schedule-edit.php POST または REST v2）
add_action('admin_post_aidunite_save_schedule', 'aidunite_admin_save_schedule');

/**
 * @deprecated 2026-06-02 正ルートは page-schedule-edit.php のフォーム POST または REST `/update-schedule-v2`。
 *             互換のため残置。新規画面からは呼ばないこと。
 */
function aidunite_admin_save_schedule() {
    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('deprecated_admin_save_schedule', ['user_id' => get_current_user_id()]);
    }
    // 統一認証・権限チェック
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
    if (!$auth_result->is_valid()) {
        // リダイレクトは自動で実行される
        return;
    }

    $user_id = $auth_result->user_id;
    $redirect_base = home_url('/schedule-edit');

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('schedule_nonce', 'aidunite_schedule_nonce');
    if (is_wp_error($nonce_result)) {
        wp_safe_redirect(add_query_arg('error', rawurlencode($nonce_result->get_error_message()), $redirect_base));
        exit;
    }

    // 統一認証・権限チェック（チーム代表者のみ許可・操作中チーム）
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    $auth_result = AidUniteAuthMiddleware::require_team_leader($team_id, false);
    if (!$auth_result->is_valid()) {
        wp_safe_redirect(add_query_arg('error', rawurlencode($auth_result->error ?: '権限がありません'), $redirect_base));
        exit;
    }

    // スケジュール編集権限チェック（managed に含まれる team のみ）
    if (!empty($_POST['post_id'])) {
        $edit_id = (int) $_POST['post_id'];
        $schedule_team_id = (int) get_post_meta($edit_id, 'team_id', true);
        if ($schedule_team_id > 0 && function_exists('aidunite_user_has_managed_team_access')) {
            if (!aidunite_user_has_managed_team_access((int) $user_id, $schedule_team_id)) {
                wp_safe_redirect(add_query_arg('error', rawurlencode('このスケジュールを編集する権限がありません'), $redirect_base));
                exit;
            }
        }
    }

    // 入力値
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';
    $schedule_type = isset($_POST['schedule_type']) ? sanitize_text_field($_POST['schedule_type']) : '';
    $intent = isset($_POST['intent']) ? sanitize_text_field($_POST['intent']) : '';
    $certainty = isset($_POST['certainty']) ? sanitize_text_field($_POST['certainty']) : '';
    $sh = sanitize_text_field($_POST['start_hour'] ?? '');
    $sm = sanitize_text_field($_POST['start_minute'] ?? '');
    $eh = sanitize_text_field($_POST['end_hour'] ?? '');
    $em = sanitize_text_field($_POST['end_minute'] ?? '');
    $start_time = ($sh !== '' && $sm !== '') ? sprintf('%02d:%02d', (int)$sh, (int)$sm) : '';
    $end_time   = ($eh !== '' && $em !== '') ? sprintf('%02d:%02d', (int)$eh, (int)$em) : '';

    // 必須バリデーション
    if (empty($start_date) || empty($schedule_type)) {
        wp_safe_redirect(add_query_arg('error', rawurlencode('必須項目が不足しています（期間/種別）'), $redirect_base));
        exit;
    }

    // 投稿データ
    $title_map = [
        'practice' => '練習',
        'official_match' => '公式試合',
        'practice_match' => '練習試合',
        'joint_practice' => '合同練習',
        'rest' => '休み',
        'event' => 'イベント'
    ];
    $post_title = $title_map[$schedule_type] ?? 'スケジュール';

    $post_args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'post_title' => $post_title,
    ];
    if (!empty($_POST['post_id'])) {
        $post_args['ID'] = intval($_POST['post_id']);
        $saved_id = wp_update_post($post_args, true);
    } else {
        $post_args['post_author'] = get_current_user_id();
        $saved_id = wp_insert_post($post_args, true);
    }

    if (is_wp_error($saved_id) || !$saved_id) {
        $msg = is_wp_error($saved_id) ? $saved_id->get_error_message() : '保存に失敗しました';
        wp_safe_redirect(add_query_arg('error', rawurlencode($msg), $redirect_base));
        exit;
    }

    // メタ保存
    update_post_meta($saved_id, 'schedule_date', $start_date);
    if (!empty($end_date)) update_post_meta($saved_id, 'schedule_end_date', $end_date);
    if (!empty($start_time)) update_post_meta($saved_id, 'schedule_start_time', $start_time);
    if (!empty($end_time)) update_post_meta($saved_id, 'schedule_end_time', $end_time);
    if (!empty($schedule_type)) update_post_meta($saved_id, 'schedule_type', $schedule_type);
    $venue_condition = isset($_POST['venue_condition']) ? sanitize_text_field($_POST['venue_condition']) : '';
    $gender_condition = isset($_POST['gender_condition']) ? sanitize_text_field($_POST['gender_condition']) : '';
    if ($venue_condition !== '' || $gender_condition !== '' || $intent !== '' || $certainty !== '') {
        $schedule_norm = aidunite_normalize_schedule_payload([
            'intent' => $intent,
            'certainty' => $certainty,
            'place_type' => $venue_condition,
            'gender' => $gender_condition,
            'gender_condition' => $gender_condition,
            'venue_condition' => $venue_condition,
        ]);
        if (!empty($schedule_norm['intent'])) {
            update_post_meta($saved_id, 'intent', $schedule_norm['intent']);
        }
        if (!empty($schedule_norm['certainty'])) {
            update_post_meta($saved_id, 'certainty', $schedule_norm['certainty']);
        }
        aidunite_apply_normalized_schedule_meta($saved_id, $schedule_norm);
    }

    // 完了
    wp_safe_redirect(add_query_arg('saved', '1', $redirect_base));
    exit;
}

function aidunite_admin_approve_team_creation() {
    // セキュリティチェック
    if (!current_user_can('administrator')) {
        wp_die('権限がありません。');
    }

    $team_id = intval($_GET['team_id']);
    if (!wp_verify_nonce($_GET['_wpnonce'], 'approve_team_creation_' . $team_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }

    // チーム投稿を承認（publishに変更）
    $result = wp_update_post([
        'ID' => $team_id,
        'post_status' => 'publish'
    ]);

    if (is_wp_error($result)) {
        wp_die('承認処理に失敗しました: ' . $result->get_error_message());
    }

    // transition が走らない環境向けに明示呼び出し（2回目は promoted で no-op）
    if (function_exists('aidunite_approve_team_creation_application')) {
        aidunite_approve_team_creation_application($team_id);
    }

    wp_redirect(home_url('/team-approval?approved=1'));
    exit;
}

function aidunite_admin_reject_team_creation() {
    if (!current_user_can('administrator')) {
        wp_die('権限がありません。');
    }

    wp_die(
        '修正依頼フォームから確認事項を入力して送信してください。',
        '操作できません',
        ['response' => 400, 'back_link' => true]
    );
}

// mode切替時のキャッシュ・セッションのクリア
add_action('init', function() {
    if (!session_id()) {
        session_start();
    }
    $prev_mode = isset($_SESSION['__aidunite_prev_mode']) ? $_SESSION['__aidunite_prev_mode'] : null;
    $current_mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : null;
    if ($prev_mode !== $current_mode) {
        // セッション変数クリア
        $_SESSION = [];
        // WP Object Cacheクリア（必要に応じて）
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        // 必要ならユーザーキャッシュやトランジェントもクリア
        // delete_transient('some_transient_key');
    }
    $_SESSION['__aidunite_prev_mode'] = $current_mode;
});

// モバイルメニューは js/header-footer.js の toggleMobileMenu に統合済み（インライン廃止）
// CSS/JS 読込は functions/common/enqueue.php の aidunite_enqueue_styles に移設済み
add_action('wp_enqueue_scripts', 'aidunite_enqueue_styles');

/**
 * フィルタリングされたスケジュールを取得するAjaxハンドラー
 */
add_action('wp_ajax_get_filtered_schedules', 'aidunite_get_filtered_schedules');
add_action('wp_ajax_nopriv_get_filtered_schedules', 'aidunite_get_filtered_schedules');

function aidunite_get_filtered_schedules() {
    // セキュリティチェック
    if (!wp_verify_nonce($_GET['nonce'], 'wp_rest')) {
        wp_die('セキュリティチェックに失敗しました。');
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('ログインが必要です。');
    }

    $team_clause = function_exists('aidunite_schedule_team_meta_query_for_operating_team')
        ? aidunite_schedule_team_meta_query_for_operating_team((int) $user_id)
        : null;
    if ($team_clause === null) {
        wp_send_json_success([]);
    }

    // フィルターパラメータを取得
    $display_mode = sanitize_text_field($_GET['display_mode'] ?? 'calendar');
    $month = intval($_GET['month'] ?? date('n'));
    $type = sanitize_text_field($_GET['type'] ?? 'all');
    $keyword = sanitize_text_field($_GET['keyword'] ?? '');

    // クエリ条件を構築
    $meta_query = [
        $team_clause,
    ];

    // 月フィルター
    if ($month > 0) {
        $year = date('Y');
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime($start_date)));

        $meta_query[] = [
            'key' => 'schedule_date',
            'value' => [$start_date, $end_date],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ];
    }

    // 種別フィルター
    if ($type !== 'all') {
        $meta_query[] = [
            'key' => 'schedule_type',
            'value' => $type,
            'compare' => '='
        ];
    }

    // キーワード検索
    $search_query = '';
    if (!empty($keyword)) {
        $search_query = $keyword;
    }

    // スケジュールを取得
    $args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => $meta_query,
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ];

    if (!empty($search_query)) {
        $args['s'] = $search_query;
    }

    $schedules = get_posts($args);

    $schedule_data = [];
    foreach ($schedules as $schedule) {
        $date = get_post_meta($schedule->ID, 'schedule_date', true);
        $day = date('j', strtotime($date));

        $schedule_data[] = [
            'id' => $schedule->ID,
            'title' => $schedule->post_title,
            'date' => $date,
            'day' => $day,
            'start_time' => get_post_meta($schedule->ID, 'schedule_start_time', true),
            'end_time' => get_post_meta($schedule->ID, 'schedule_end_time', true),
            'type' => get_post_meta($schedule->ID, 'schedule_type', true),
            'place' => get_post_meta($schedule->ID, 'schedule_place', true),
            'note' => get_post_meta($schedule->ID, 'schedule_note', true),
            'matching' => get_post_meta($schedule->ID, 'matching', true) // 統一されたキー名を使用
        ];
    }

    wp_send_json_success($schedule_data);
}



/*--------------------------------------------------------------
  固定ページ自動作成・ロゴディレクトリは functions/common/theme-init.php と functions/admin/page-setup.php に移設済み
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  No.44 過去のスケジュール自動処理
--------------------------------------------------------------*/
function process_past_schedules() {
    global $wpdb;

    // 今日の日付を取得
    $today = date('Y-m-d');

    // 過去のスケジュールを取得
    $past_schedules = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, pm.meta_value as schedule_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'schedule'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'schedule_date'
        AND pm.meta_value < %s
        ORDER BY pm.meta_value DESC
    ", $today));

    if (empty($past_schedules)) {
        return;
    }

    foreach ($past_schedules as $schedule) {
        $schedule_id = $schedule->ID;
        $schedule_date = $schedule->schedule_date;

        // スケジュールのステータスを「完了」に更新
        $status_updated = update_post_meta($schedule_id, 'schedule_status', 'completed');

        // 完了日時を記録
        $completion_updated = update_post_meta($schedule_id, 'completion_date', current_time('mysql'));

        // 処理結果を記録
        if ($status_updated && $completion_updated) {
            // スケジュール完了処理成功
        } else {
            // スケジュール完了処理失敗
        }
    }


}

// 毎日の自動処理（WordPressのcronを使用）
add_action('wp_scheduled_delete', 'process_past_schedules');

// 管理画面からも手動実行可能
add_action('admin_init', function() {
    if (isset($_GET['process_past_schedules']) && current_user_can('administrator')) {
        process_past_schedules();
        wp_redirect(admin_url('admin.php?page=schedule-list&message=past_schedules_processed'));
        exit;
    }
});

// 通知設定の初期化
add_action('init', 'aidunite_init_notification_settings');

// マッチ成立時の通知アクションフックを設定
add_action('aidunite_notify_match_status_change', 'aidunite_handle_match_status_change', 10, 5);

/*--------------------------------------------------------------
  管理者用: マッチ申請一覧取得
--------------------------------------------------------------*/
add_action('wp_ajax_au_admin_list_match_applications', function () {
  if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'no_permission'], 403);
  $result = [];
  $paged = max(1, intval($_GET['paged'] ?? 1));
  $per_page = min(200, max(20, intval($_GET['per_page'] ?? 100)));
  $args = [ 'number' => $per_page, 'paged' => $paged, 'meta_key' => 'au_match_applications', 'fields' => ['ID'] ];
  $user_query = new WP_User_Query($args);
  $users = $user_query->get_results();
  foreach ($users as $u) {
    $apps = get_user_meta($u->ID, 'au_match_applications', true);
    if (!is_array($apps)) continue;
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $u->ID)
        : (int) get_user_meta($u->ID, 'team_id', true);
    foreach ($apps as $i => $row) {
      $result[] = [
        'user_id' => $u->ID,
        'team_id' => intval($team_id),
        'index' => $i,
        'my_schedule_id' => intval($row['my_schedule_id'] ?? 0),
        'other_schedule_id' => intval($row['other_schedule_id'] ?? 0),
        'status' => strval($row['status'] ?? ''),
        'selected_time' => strval($row['selected_time'] ?? ''),
        'selected_place' => strval($row['selected_place'] ?? ''),
        'selected_gender' => strval($row['selected_gender'] ?? ''),
        'timestamp' => strval($row['timestamp'] ?? '')
      ];
    }
  }
  wp_send_json_success(['items' => $result]);
});

/*--------------------------------------------------------------
  管理者用: ステータス更新
--------------------------------------------------------------*/
add_action('wp_ajax_au_admin_update_match_application', function () {
  if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'no_permission'], 403);
  check_ajax_referer('au_match_nonce', 'security');
  $target_user = intval($_POST['user_id'] ?? 0);
  $index = intval($_POST['index'] ?? -1);
  $new_status = sanitize_text_field($_POST['status'] ?? '');
  $valid = ['申請中','承認待ち','拒否された','成立','不成立','キャンセル'];
  if (!$target_user || $index < 0 || !in_array($new_status, $valid, true)) wp_send_json_error(['message' => 'invalid_params']);

  $list = get_user_meta($target_user, 'au_match_applications', true);
  if (!is_array($list) || !isset($list[$index])) wp_send_json_error(['message' => 'not_found']);

  $prev = $list[$index]['status'] ?? '';
  $list[$index]['status'] = $new_status;

  $other_schedule_id = intval($list[$index]['other_schedule_id'] ?? 0);
  if ($other_schedule_id) {
    $meta_key = 'accepted_count';
    $count = intval(get_post_meta($other_schedule_id, $meta_key, true));
    if ($prev !== '成立' && $new_status === '成立') {
      update_post_meta($other_schedule_id, $meta_key, max(0, $count + 1));
    } elseif ($prev === '成立' && in_array($new_status, ['キャンセル','拒否された','不成立'], true)) {
      update_post_meta($other_schedule_id, $meta_key, max(0, $count - 1));
    }
  }

  update_user_meta($target_user, 'au_match_applications', $list);
  wp_send_json_success(['message' => 'updated']);
});

/*--------------------------------------------------------------
  管理者用: 申請削除
--------------------------------------------------------------*/
add_action('wp_ajax_au_admin_delete_match_application', function () {
  if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'no_permission'], 403);
  check_ajax_referer('au_match_nonce', 'security');
  $target_user = intval($_POST['user_id'] ?? 0);
  $index = intval($_POST['index'] ?? -1);
  if (!$target_user || $index < 0) wp_send_json_error(['message' => 'invalid_params']);
  $list = get_user_meta($target_user, 'au_match_applications', true);
  if (!is_array($list) || !isset($list[$index])) wp_send_json_error(['message' => 'not_found']);

  $prev = $list[$index]['status'] ?? '';
  if ($prev === '成立') {
    $other_schedule_id = intval($list[$index]['other_schedule_id'] ?? 0);
    if ($other_schedule_id) {
      $meta_key = 'accepted_count';
      $count = intval(get_post_meta($other_schedule_id, $meta_key, true));
      update_post_meta($other_schedule_id, $meta_key, max(0, $count - 1));
    }
  }

  unset($list[$index]);
  update_user_meta($target_user, 'au_match_applications', array_values($list));
  wp_send_json_success(['message' => 'deleted']);
});

/*--------------------------------------------------------------
  選手削除（AJAX）
--------------------------------------------------------------*/
add_action('wp_ajax_delete_player_ajax', 'aidunite_delete_player_ajax');
function aidunite_delete_player_ajax() {
    $player_id = intval($_POST['player_id'] ?? 0);
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');

    if (!$player_id || !wp_verify_nonce($nonce, 'delete_player_' . $player_id)) {
        wp_send_json_error(['message' => '無効なパラメータです']);
        return;
    }

    // 権限チェック（チーム代表者のみ・共有所属チームがあれば可）
    $current_user_id = get_current_user_id();
    $user_info = aidunite_get_user_info($current_user_id);
    $can_manage = false;
    if (($user_info['user_type'] ?? '') === 'team_leader') {
        if (function_exists('aidunite_users_share_managed_team')) {
            $can_manage = aidunite_users_share_managed_team((int) $current_user_id, (int) $player_id);
        } else {
            $can_manage = ((int) ($user_info['team_id'] ?? 0)) === (int) get_user_meta($player_id, 'team_id', true);
        }
    }

    if (!$can_manage) {
        wp_send_json_error(['message' => '権限がありません']);
        return;
    }

    // ユーザーを削除
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    $result = wp_delete_user($player_id);

    if ($result) {
        wp_send_json_success(['message' => '選手を削除しました']);
    } else {
        wp_send_json_error(['message' => '削除に失敗しました']);
    }
}

/*--------------------------------------------------------------
  選手ステータス更新（引退・移籍）
--------------------------------------------------------------*/
add_action('wp_ajax_update_player_status', 'aidunite_update_player_status');
function aidunite_update_player_status() {
    check_ajax_referer('update_player_status', 'nonce');

    $player_id = intval($_POST['player_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');

    if (!$player_id || !in_array($status, ['active', 'retired', 'transferred'])) {
        wp_send_json_error(['message' => '無効なパラメータです']);
        return;
    }

    // 権限チェック（チーム代表者のみ・共有所属チームがあれば可）
    $current_user_id = get_current_user_id();
    $user_info = aidunite_get_user_info($current_user_id);
    $can_manage = false;
    if (($user_info['user_type'] ?? '') === 'team_leader') {
        if (function_exists('aidunite_users_share_managed_team')) {
            $can_manage = aidunite_users_share_managed_team((int) $current_user_id, (int) $player_id);
        } else {
            $can_manage = ((int) ($user_info['team_id'] ?? 0)) === (int) get_user_meta($player_id, 'team_id', true);
        }
    }

    if (!$can_manage) {
        wp_send_json_error(['message' => '権限がありません']);
        return;
    }

    update_user_meta($player_id, 'player_status', $status);
    wp_send_json_success(['message' => 'ステータスを更新しました']);
}

/*--------------------------------------------------------------
  選手クイック編集
--------------------------------------------------------------*/
add_action('wp_ajax_quick_edit_player', 'aidunite_quick_edit_player');
function aidunite_quick_edit_player() {
    check_ajax_referer('quick_edit_player', 'nonce');

    $player_id = intval($_POST['player_id'] ?? 0);
    $field = sanitize_text_field($_POST['field'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');

    if (!$player_id || !in_array($field, ['name', 'kana', 'position'])) {
        wp_send_json_error(['message' => '無効なパラメータです']);
        return;
    }

    // 権限チェック（チーム代表者のみ・共有所属チームがあれば可）
    $current_user_id = get_current_user_id();
    $user_info = aidunite_get_user_info($current_user_id);
    $can_manage = false;
    if (($user_info['user_type'] ?? '') === 'team_leader') {
        if (function_exists('aidunite_users_share_managed_team')) {
            $can_manage = aidunite_users_share_managed_team((int) $current_user_id, (int) $player_id);
        } else {
            $can_manage = ((int) ($user_info['team_id'] ?? 0)) === (int) get_user_meta($player_id, 'team_id', true);
        }
    }

    if (!$can_manage) {
        wp_send_json_error(['message' => '権限がありません']);
        return;
    }

    // フィールド名をマッピング
    $meta_key_map = [
        'name' => 'player_name',
        'kana' => 'player_name_kana',
        'position' => 'player_position'
    ];

    $meta_key = $meta_key_map[$field] ?? '';
    if ($meta_key) {
        update_user_meta($player_id, $meta_key, $value);
        wp_send_json_success(['message' => '更新しました']);
    } else {
        wp_send_json_error(['message' => '無効なフィールドです']);
    }
}

/*--------------------------------------------------------------
  注: スケジュール削除 API は /functions/schedule/rest-schedule-legacy.php の
  aidunite_delete_schedule へ統一（二重登録廃止・依存ガード内蔵・2026-04）
--------------------------------------------------------------*/

/*--------------------------------------------------------------
  申請ステータスリセット用REST APIエンドポイント
--------------------------------------------------------------*/
add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/reset-match-statuses', [
        'methods' => 'POST',
        'callback' => 'aidunite_reset_match_statuses',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);
});

  function aidunite_reset_match_statuses($request) {
    $reset_type = $request->get_param('reset_type'); // 'all' or 'pending'

    if (!in_array($reset_type, ['all', 'pending'])) {
        return new WP_Error('invalid_type', '無効なリセットタイプです。', ['status' => 400]);
    }

    $deleted_count = 0;
    $affected_users = 0;

    // 全ユーザーを取得
    $users = get_users(['fields' => 'ID']);

    foreach ($users as $user_id) {
        $applications = get_user_meta($user_id, 'au_match_applications', true);

        if (!is_array($applications) || empty($applications)) {
            continue;
        }

        $original_count = count($applications);
        $filtered_applications = [];

        foreach ($applications as $application) {
            $current_status = $application['status'] ?? '';

            // 削除条件をチェック
            $should_delete = false;
            if ($reset_type === 'all') {
                // 全ての申請を削除
                $should_delete = true;
            } elseif ($reset_type === 'pending') {
                // 申請中のみを削除
                $should_delete = ($current_status === '申請中');
            }

            if (!$should_delete) {
                $filtered_applications[] = $application;
            } else {
                $deleted_count++;
            }
        }

        // 変更があった場合は保存
        if (count($filtered_applications) !== $original_count) {
            if (empty($filtered_applications)) {
                // 全て削除された場合は空配列を保存
                update_user_meta($user_id, 'au_match_applications', []);
            } else {
                update_user_meta($user_id, 'au_match_applications', $filtered_applications);
            }
            $affected_users++;
        }
    }

    return rest_ensure_response([
        'success' => true,
        'deleted_count' => $deleted_count,
        'affected_users' => $affected_users,
        'reset_type' => $reset_type,
        'message' => $reset_type === 'all' ?
            "全ての申請を削除しました（{$deleted_count}件、{$affected_users}ユーザー）" :
            "申請中の申請を削除しました（{$deleted_count}件、{$affected_users}ユーザー）"
    ]);
}

/*--------------------------------------------------------------
  ページ分類機能（Web / Webアプリ空間）
  MYPAGE-REDESIGN-PROPOSAL.md に基づく実装
--------------------------------------------------------------*/

/**
 * @see functions/common/web-app-page-registry.php aidunite_is_web_app_page()
 */

/**
 * ページタイプを取得する
 *
 * @return string 'web-app' または 'web'
 */
function aidunite_get_page_type() {
    return aidunite_is_web_app_page() ? 'web-app' : 'web';
}

/**
 * チャット画面を1テンプレートに統一：/team-chat でも page-chat.php を使用
 */
add_filter('page_template', function($template) {
    if (is_page('team-chat')) {
        $path = get_template_directory() . '/page-chat.php';
        if (file_exists($path)) {
            return $path;
        }
    }
    return $template;
}, 5);
