<?php
/*====================================================================
  通知処理集約ファイル：functions/notify/notify.php
  仕様: docs/spec/notification.md
  送信実装は functions/notify/notification-api.php に集約
====================================================================*/

require_once __DIR__ . '/notification-delivery-log.php';
require_once __DIR__ . '/notification-persist.php';
require_once __DIR__ . '/notification-api.php';

/**
 * 管理者向けテスト通知メールの宛先許可（admin_email に加える）
 */
add_filter('aidunite_notification_test_mail_allowlist', static function (array $emails) {
    $extra = sanitize_email('shogo.saito@aidunite-inc.com');
    if (is_email($extra)) {
        $emails[] = strtolower($extra);
    }
    return $emails;
});

/*--------------------------------------------------------------
  No.1 通知共通処理｜aidunite_notification_send に委譲
--------------------------------------------------------------*/
function aidunite_notify_user($user_id, $title, $message, $type = 'general', $related_post_id = null) {
  $data = [
    'title'   => $title,
    'message' => $message,
    'related_id' => $related_post_id ? (int) $related_post_id : null,
  ];
  // 一覧でクリック時に遷移できるよう link_url を付与
  if ($related_post_id) {
    $rid = (int) $related_post_id;
    if (strpos($type, 'match') !== false) {
      $data['link_url'] = home_url('/match-detail/?id=' . $rid);
    } elseif (strpos($type, 'schedule') !== false) {
      $data['link_url'] = home_url('/schedule-edit/?id=' . $rid);
    } elseif ($type === 'team_approval' || strpos($type, 'team') !== false) {
      $data['link_url'] = home_url('/team-detail/?id=' . $rid);
    }
  }
  $result = aidunite_notification_send((int) $user_id, $type, $data);
  return !empty($result['success']);
}


if (!function_exists('aidunite_notification_should_link_to_match_detail')) {
    /**
     * 通知タップでマッチ詳細へ飛ばすか（結果確定系は一覧モーダルのみで本文を固定表示）
     *
     * @param string $type canonical type
     * @return bool
     */
    function aidunite_notification_should_link_to_match_detail($type) {
        $type = strtolower(trim((string) $type));
        if ($type === '' || strpos($type, 'match') === false) {
            return false;
        }
        $snapshot_only = [
            'match_canceled',
            'match_cancelled',
            'match_rejected',
            'match_updated',
            'match_participant_withdrawn',
        ];

        return !in_array($type, $snapshot_only, true);
    }
}

/*--------------------------------------------------------------
  No.2 統一通知作成関数（aidunite_create_notification）
--------------------------------------------------------------*/
function aidunite_create_notification($notification_data) {
    if (!isset($notification_data['user_id']) || !isset($notification_data['title']) || !isset($notification_data['message'])) {
        error_log('❌ 通知作成失敗：必須パラメータが不足しています');
        return false;
    }

    $user_id = (int) $notification_data['user_id'];
    $title = sanitize_text_field($notification_data['title']);
    $message = sanitize_textarea_field($notification_data['message']);
    $type = sanitize_text_field($notification_data['type'] ?? 'general');
    // meta と data の両方を受理（マッチ通知は data に match_request_id を渡す経路がある）
    $meta_raw = [];
    if (!empty($notification_data['meta']) && is_array($notification_data['meta'])) {
        $meta_raw = $notification_data['meta'];
    } elseif (!empty($notification_data['data']) && is_array($notification_data['data'])) {
        $meta_raw = $notification_data['data'];
    }
    $related_from_meta = $meta_raw['request_id'] ?? $meta_raw['match_request_id'] ?? null;
    $related_from_meta = $related_from_meta !== null && $related_from_meta !== '' ? (int) $related_from_meta : null;

    $data = [
        'title'      => $title,
        'message'    => $message,
        'related_id' => $related_from_meta ?: null,
    ];
    if (!empty($notification_data['link_url'])) {
        $data['link_url'] = $notification_data['link_url'];
    }

    // マッチ関連は一覧からマッチ詳細へ（明示リンクが無く related_id がある場合）
    if (empty($data['link_url']) && !empty($data['related_id'])) {
        $tid = (string) $type;
        if ($tid === 'match_game_dissolved') {
            // related_id は募集 schedule。編集画面へ。
            $data['link_url'] = home_url('/schedule-edit/?id=' . (int) $data['related_id']);
        } elseif ($tid === 'match_participant_withdrawn') {
            $data['link_url'] = home_url('/match-board-own');
        } elseif ($tid === 'match_feedback_survey') {
            $data['link_url'] = function_exists('aidunite_get_match_feedback_survey_url')
                ? aidunite_get_match_feedback_survey_url((int) $data['related_id'])
                : home_url('/match-feedback/?match_id=' . (int) $data['related_id']);
        } elseif (function_exists('aidunite_notification_should_link_to_match_detail')
            ? aidunite_notification_should_link_to_match_detail($tid)
            : (strpos($tid, 'match') !== false)) {
            $data['link_url'] = home_url('/match-detail/?id=' . (int) $data['related_id']);
        }
    }

    $result = aidunite_notification_send($user_id, $type, $data);
    $ok = !empty($result['success']);
    if ($ok) {
        error_log("✅ 通知作成成功：user_id={$user_id} type={$type}");
    } else {
        error_log("❌ 通知作成失敗：user_id={$user_id} type={$type}");
    }
    return $ok;
}

/*--------------------------------------------------------------
  No.3 通知設定の初期化
--------------------------------------------------------------*/
function aidunite_init_notification_settings() {
    // 通知設定のデフォルト値を設定
    $default_settings = array(
        'email_notifications' => true,
        'line_notifications' => false,
        'match_notifications' => true,
        'team_notifications' => true,
        'schedule_notifications' => true
    );

    // 既存の設定がない場合のみデフォルト値を設定
    if (!get_option('aidunite_notification_settings')) {
        update_option('aidunite_notification_settings', $default_settings);
    }

    // 通知カスタム投稿タイプの登録（存在しない場合）
    if (!post_type_exists('notification')) {
        register_post_type('notification', array(
            'labels' => array(
                'name' => '通知',
                'singular_name' => '通知',
                'add_new' => '新規追加',
                'add_new_item' => '新規通知を追加',
                'edit_item' => '通知を編集',
                'new_item' => '新規通知',
                'view_item' => '通知を表示',
                'search_items' => '通知を検索',
                'not_found' => '通知が見つかりませんでした',
                'not_found_in_trash' => 'ゴミ箱に通知が見つかりませんでした'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=notification',
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => false,
            'supports' => array('title', 'editor', 'author', 'custom-fields'),
            'menu_icon' => 'dashicons-bell'
        ));
    }

    // 通知メタフィールドの登録
    // WordPressの標準的なメタフィールドを使用
}
