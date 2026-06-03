<?php
/**
 * チーム機能管理
 * AidUnite統一仕様対応版
 */

require_once get_template_directory() . '/functions/common/error-handler.php';

/**
 * `aidunite_register_team()` が false を返したときの人間可読な理由（Ajax 用）。取得後はクリアされる。
 *
 * @return string
 */
function aidunite_register_team_take_last_error() {
    $e = isset($GLOBALS['aidunite_register_team_last_error'])
        ? (string) $GLOBALS['aidunite_register_team_last_error']
        : '';
    unset($GLOBALS['aidunite_register_team_last_error']);
    return $e;
}

/**
 * @param string $message
 */
function aidunite_register_team_set_last_error($message) {
    $GLOBALS['aidunite_register_team_last_error'] = (string) $message;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム登録処理
--------------------------------------------------------------*/
/**
 * @param int                  $user_id
 * @param array<string, mixed> $team_data
 * @param array<string, bool>  $options {
 *   @type bool $skip_pending_limit  同一ユーザーの承認待ち1件制限をスキップ（男女同時申請の2件目）
 *   @type bool $set_pending_team_id pending_team_id を更新する（2件目は false）
 * }
 */
function aidunite_register_team($user_id, $team_data, $options = []) {
    if (!$user_id || !$team_data) {
        return false;
    }

    $options = wp_parse_args($options, [
        'skip_pending_limit'  => false,
        'set_pending_team_id' => true,
    ]);

    unset($GLOBALS['aidunite_register_team_last_error']);

    // MVP: 同一ユーザーは「承認待ちチーム申請」を同時に1件まで（男女同時申請の2件目は skip_pending_limit）。
    $enforce_single_pending = !$options['skip_pending_limit']
        && apply_filters('aidunite_register_team_mvp_single_pending_limit', true, (int) $user_id, $team_data);
    if ($enforce_single_pending) {
        $existing = (int) get_user_meta((int) $user_id, 'pending_team_id', true);
        if ($existing > 0) {
            $pending_post = get_post($existing);
            $valid = $pending_post
                && $pending_post->post_type === 'team'
                && $pending_post->post_status === 'pending'
                && (int) $pending_post->post_author === (int) $user_id;
            if ($valid) {
                aidunite_register_team_set_last_error(
                    '既に承認待ちのチーム申請があります。結果が出るまでお待ちください。'
                );
                return false;
            }
            delete_user_meta((int) $user_id, 'pending_team_id');
        }
    }

    // チーム投稿を作成
    $post_data = [
        'post_type' => 'team',
        'post_title' => $team_data['team_name'],
        'post_status' => 'pending', // 承認待ち状態
        'post_author' => $user_id,
    ];

    $team_id = wp_insert_post($post_data);

    if (is_wp_error($team_id)) {
        return false;
    }

    // チームメタデータを保存
    $meta_fields = [
        'team_name',
        'team_name_kana',
        'team_description',
        'team_achievements',
        'sport_type',
        'team_category',
        'team_type',
        'team_gender_option',
        'region',
        'team_place',
        'team_logo',
        'registrant_name',
        'contact_mail',
        'contact_phone'
    ];

    foreach ($meta_fields as $field) {
        if (isset($team_data[$field])) {
            $value = sanitize_text_field($team_data[$field]);
            if ($field === 'team_gender_option' && function_exists('aidunite_normalize_team_gender_option')) {
                $value = aidunite_normalize_team_gender_option($value);
                $ban_both = apply_filters('aidunite_mvp_ban_new_team_gender_both', true);
                if ($ban_both && ($value === '' || in_array($value, ['both', 'mixed'], true))) {
                    continue;
                }
                if ($value === '' && !$ban_both) {
                    $value = 'both';
                }
            }
            update_post_meta($team_id, $field, $value);
        }
    }

    // 招待コードを生成
    $invite_code = aidunite_generate_team_invite_code($team_id);
    if ($invite_code) {
        update_post_meta($team_id, 'invite_code', $invite_code);
    }

    // チーム管理画面のステータス表示用（承認待ち）
    update_post_meta($team_id, 'team_status', 'pending');

    // 承認までは所属扱いにしない（マルチチーム整合）: pending のみ。team_id / managed_team_ids は付与しない。
    if (!empty($options['set_pending_team_id'])) {
        update_user_meta($user_id, 'pending_team_id', $team_id);
    }

    if (function_exists('aidunite_clear_user_needs_revision_state')) {
        $meta_key = defined('AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID')
            ? AIDUNITE_USER_META_NEEDS_REVISION_TEAM_ID
            : 'needs_revision_team_id';
        $revision_id = (int) get_user_meta((int) $user_id, $meta_key, true);
        if ($revision_id > 0 && $revision_id !== (int) $team_id) {
            wp_trash_post($revision_id);
        }
        aidunite_clear_user_needs_revision_state((int) $user_id);
    }

    // チーム代表者（申請者）を投稿メタに記録（承認後に user と同期）
    update_post_meta($team_id, 'team_leader_id', $user_id);

    if (function_exists('aidunite_maybe_mark_team_fixture_on_register')) {
        aidunite_maybe_mark_team_fixture_on_register($team_id, (int) $user_id, $team_data);
    }

    // 管理者に通知（エラーハンドリング付き）
    try {
        if (function_exists('aidunite_notify_admin_team_registration')) {
            aidunite_notify_admin_team_registration($team_id, $team_data);
        }
    } catch (Exception $e) {
        error_log('管理者通知エラー: ' . $e->getMessage());
    }

    return $team_id;
}

/**
 * 管理者用: チーム投稿と team_id 所属メンバー・関連投稿を削除する。
 *
 * @param int $team_id
 * @return array{team_id:int,team_name:string,members:int,related_posts:int}|\WP_Error
 */
function aidunite_admin_delete_team($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return new WP_Error('invalid_team', '無効なチームIDです。');
    }

    $team = get_post($team_id);
    if (!$team || $team->post_type !== 'team') {
        return new WP_Error('not_found', '指定されたチームが見つかりません。');
    }

    $affiliated_ids = function_exists('aidunite_get_team_affiliated_user_ids')
        ? aidunite_get_team_affiliated_user_ids($team_id)
        : array_map('intval', (array) get_users([
            'meta_key' => 'team_id',
            'meta_value' => (string) $team_id,
            'number' => -1,
            'fields' => 'ID',
        ]));

    foreach ($affiliated_ids as $member_id) {
        if (function_exists('aidunite_user_remove_team_membership')) {
            aidunite_user_remove_team_membership($member_id, $team_id);
        } else {
            delete_user_meta($member_id, 'team_id');
            update_user_meta($member_id, 'aidunite_role', 'general');
        }
    }

    $schedules = get_posts([
        'post_type' => 'schedule',
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
            ],
        ],
        'numberposts' => -1,
        'post_status' => 'any',
    ]);

    $guards_path = get_template_directory() . '/functions/schedule/schedule-dependency-guards.php';
    if (!function_exists('aidunite_get_schedule_match_requests') && is_readable($guards_path)) {
        require_once $guards_path;
    }

    foreach ($schedules as $schedule_post) {
        $schedule_id = (int) $schedule_post->ID;
        if ($schedule_id <= 0) {
            continue;
        }
        $match_requests = function_exists('aidunite_get_schedule_match_requests')
            ? aidunite_get_schedule_match_requests($schedule_id)
            : [];
        $match_request_ids = array_map(static function ($p) {
            return (int) $p->ID;
        }, $match_requests);

        if (function_exists('aidunite_close_match_chat_rooms_for_schedule')) {
            aidunite_close_match_chat_rooms_for_schedule($schedule_id, $match_request_ids);
        }
        foreach ($match_requests as $mr) {
            wp_delete_post((int) $mr->ID, true);
        }
        if (function_exists('aidunite_get_schedule_match_boards')) {
            foreach (aidunite_get_schedule_match_boards($schedule_id) as $board) {
                wp_delete_post((int) $board->ID, true);
            }
        }
        wp_delete_post($schedule_id, true);
    }

    $related_posts = get_posts([
        'post_type' => ['notification', 'match_log'],
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
            ],
        ],
        'numberposts' => -1,
        'post_status' => 'any',
    ]);

    foreach ($related_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    $deleted = wp_delete_post($team_id, true);
    if (!$deleted) {
        return new WP_Error('delete_failed', 'チームの削除に失敗しました。');
    }

    return [
        'team_id' => $team_id,
        'team_name' => $team->post_title,
        'members' => count($affiliated_ids),
        'related_posts' => count($schedules) + count($related_posts),
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム承認処理
  pending → publish は transition_post_status で申請者への所属付与・通知を一括実行する。
--------------------------------------------------------------*/
function aidunite_approve_team($team_id) {
    if (!$team_id) {
        return false;
    }

    $team_post = get_post($team_id);
    if (!$team_post || $team_post->post_type !== 'team') {
        return false;
    }

    if ($team_post->post_status === 'pending') {
        $result = wp_update_post([
            'ID' => $team_id,
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($result)) {
            return false;
        }
        return true;
    }

    // 既に公開済み（管理画面等）で transition が走っていない場合の保険
    if ($team_post->post_status === 'publish' && function_exists('aidunite_approve_team_creation_application')) {
        aidunite_approve_team_creation_application($team_id);
    }

    return true;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム招待コード生成（重複チェック強化版）
--------------------------------------------------------------*/
function aidunite_generate_team_invite_code($team_id) {
    if (!$team_id) {
        return false;
    }

    $max_attempts = 10;
    $attempt = 0;

    do {
        // より強固なユニークコード生成
        $code = 'TEAM' . strtoupper(substr(md5($team_id . time() . rand(1000, 9999)), 0, 8));

        // 重複チェック
        $existing = get_posts([
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

        $attempt++;

        // 重複がなければ使用
        if (empty($existing)) {
            update_post_meta($team_id, 'invite_code', $code);
            error_log("招待コード生成成功: {$code} (試行回数: {$attempt})");
            return $code;
        }

        error_log("招待コード重複検出: {$code} (試行回数: {$attempt})");

    } while ($attempt < $max_attempts);

    // 最大試行回数を超えた場合はタイムスタンプを含む確実にユニークなコードを生成
    $fallback_code = 'TEAM' . strtoupper(substr(md5($team_id . microtime(true)), 0, 8));
    update_post_meta($team_id, 'invite_code', $fallback_code);
    error_log("フォールバック招待コード生成: {$fallback_code}");

    return $fallback_code;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：QRコード生成機能
--------------------------------------------------------------*/
function aidunite_generate_invite_qr_code($team_id) {
    if (!$team_id) {
        return false;
    }

    $invite_code = get_post_meta($team_id, 'invite_code', true);
    if (!$invite_code) {
        $invite_code = aidunite_generate_team_invite_code($team_id);
    }

    $invite_url = home_url('/team-apply/?join_code=' . $invite_code);

    // Google Charts QR Code API を使用（外部サービス）
    $qr_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($invite_url);

    return [
        'qr_image_url' => $qr_url,
        'invite_url' => $invite_url,
        'invite_code' => $invite_code
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム招待情報取得
--------------------------------------------------------------*/
function aidunite_get_team_invite_info($team_id) {
    if (!$team_id) {
        return false;
    }

    $invite_code = get_post_meta($team_id, 'invite_code', true);
    if (!$invite_code) {
        $invite_code = aidunite_generate_team_invite_code($team_id);
    }

    // チーム名を取得
    $team_name = get_post_meta($team_id, 'team_name', true);
    if (empty($team_name)) {
        $team_post = get_post($team_id);
        $team_name = $team_post ? $team_post->post_title : 'チーム';
    }

    $invite_url = home_url('/team-apply/?join_code=' . $invite_code);
    $qr_info = aidunite_generate_invite_qr_code($team_id);

    return [
        'invite_code' => $invite_code,
        'invite_url' => $invite_url,
        'qr_image_url' => $qr_info['qr_image_url'],
        'team_name' => $team_name,
        'share_message' => "【{$team_name}】への参加はこちら！\n{$invite_url}"
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム招待コード検証
--------------------------------------------------------------*/
function aidunite_verify_team_invite_code($invite_code) {
    if (!$invite_code) {
        return false;
    }

    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'invite_code',
                'value' => $invite_code,
                'compare' => '='
            ]
        ],
        'numberposts' => 1
    ]);

    if (empty($teams)) {
        return false;
    }

    return $teams[0];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム参加申請処理
--------------------------------------------------------------*/
function aidunite_apply_team_join($user_id, $team_id, $application_data = []) {
    if (!$user_id || !$team_id) {
        return false;
    }

    // 申請投稿を作成
    $post_data = [
        'post_type' => 'team_application',
        'post_title' => 'チーム参加申請: ' . get_userdata($user_id)->display_name,
        'post_status' => 'pending',
        'post_author' => $user_id,
        'meta_input' => [
            'team_id' => $team_id,
            'applicant_id' => $user_id,
            'application_type' => isset($application_data['type']) ? $application_data['type'] : 'general',
            'application_message' => isset($application_data['message']) ? $application_data['message'] : '',
            'application_date' => current_time('mysql')
        ]
    ];

    $application_id = wp_insert_post($post_data);

    if (is_wp_error($application_id)) {
        return false;
    }

    // チーム代表者に通知
    $team_post = get_post($team_id);
    if ($team_post) {
        aidunite_notify_team_leader_application($team_post->post_author, $application_id);
    }

    return $application_id;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム参加申請承認処理
--------------------------------------------------------------*/
function aidunite_approve_team_application($application_id, $approved_role = 'general') {
    if (!$application_id) {
        return false;
    }

    $application = get_post($application_id);
    if (!$application || $application->post_type !== 'team_application') {
        return false;
    }

    $user_id = get_post_meta($application_id, 'applicant_id', true);
    $team_id = get_post_meta($application_id, 'team_id', true);

    if (!$user_id || !$team_id) {
        return false;
    }

    // 申請ステータスを承認に変更
    wp_update_post([
        'ID' => $application_id,
        'post_status' => 'publish'
    ]);

    // ユーザーをチームに参加させる
    aidunite_set_user_team($user_id, $team_id);
    aidunite_set_user_type($user_id, $approved_role);

    // 申請者に通知
    aidunite_notify_user_application_approved($user_id, $team_id);

    return true;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム情報取得
--------------------------------------------------------------*/
function aidunite_get_team_info($team_id) {
    if (!$team_id) {
        return null;
    }

    $team = get_post($team_id);
    if (!$team || $team->post_type !== 'team') {
        return null;
    }

    return [
        'team_id' => $team_id,
        'team_name' => $team->post_title,
        'sport_type' => get_post_meta($team_id, 'sport_type', true),
        'region' => get_post_meta($team_id, 'region', true),
        'team_category' => get_post_meta($team_id, 'team_category', true),
        'description' => $team->post_content,
        'created_date' => $team->post_date
    ];
}

/**
 * チーム名を取得（ヘルパー関数）
 *
 * @param int $team_id チームID
 * @return string チーム名
 */
function aidunite_get_team_name($team_id) {
    if (!$team_id) {
        return 'チーム';
    }

    if (function_exists('aidunite_is_onboarding_bot_team') && aidunite_is_onboarding_bot_team((int) $team_id)) {
        return '練習相手チーム';
    }

    // まずメタデータから取得を試す
    $team_name = get_post_meta($team_id, 'team_name', true);
    if (!empty($team_name)) {
        return $team_name;
    }

    // 投稿タイトルから取得
    $team_post = get_post($team_id);
    if ($team_post && $team_post->post_type === 'team') {
        return $team_post->post_title;
    }

    return 'チーム';
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チームに紐づくユーザー ID（team_id 所属 + 代表者）
  @see docs/reports/team-affiliation-consistency-audit.md
--------------------------------------------------------------*/
function aidunite_get_team_affiliated_user_ids($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $member_ids = get_users([
        'meta_key' => 'team_id',
        'meta_value' => (string) $team_id,
        'number' => -1,
        'fields' => 'ID',
    ]);
    if (!is_array($member_ids)) {
        $member_ids = [];
    }
    $member_ids = array_values(array_unique(array_map('intval', $member_ids)));

    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : (int) get_post_meta($team_id, 'team_leader_id', true);
    if ($leader_id > 0 && !in_array($leader_id, $member_ids, true)) {
        $member_ids[] = $leader_id;
    }

    return $member_ids;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チームメンバー取得
--------------------------------------------------------------*/
function aidunite_get_team_members($team_id, $user_type = null) {
    if (!$team_id) {
        return [];
    }

    $member_ids = aidunite_get_team_affiliated_user_ids($team_id);
    if ($member_ids === []) {
        return [];
    }

    $users = [];
    foreach ($member_ids as $user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            continue;
        }
        if ($user_type !== null && $user_type !== '') {
            $ut = (string) get_user_meta($user_id, 'user_type', true);
            if ($ut !== (string) $user_type) {
                continue;
            }
        }
        $users[] = $user;
    }

    return $users;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チームメンバー一覧取得
--------------------------------------------------------------*/
function aidunite_get_team_members_list($team_id) {
    if (!$team_id) {
        return [];
    }

    $members = aidunite_get_team_members($team_id);
    $members_list = [];

    foreach ($members as $member) {
        $user_info = aidunite_get_user_info($member->ID);
        $members_list[] = [
            'user_id' => $member->ID,
            'display_name' => $member->display_name,
            'user_email' => $member->user_email,
            'user_type' => $user_info['user_type'],
            'aidunite_role' => $user_info['aidunite_role'],
            'is_supporter' => $user_info['is_supporter'],
            'is_minor' => $user_info['is_minor'],
            'join_date' => $member->user_registered
        ];
    }

    return $members_list;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム検索
--------------------------------------------------------------*/
function aidunite_search_teams($search_params = []) {
    $args = [
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => 12,
        'meta_query' => []
    ];

    // 地域フィルター
    if (!empty($search_params['region'])) {
        $args['meta_query'][] = [
            'key' => 'region',
            'value' => $search_params['region'],
            'compare' => '='
        ];
    }

    // 競技フィルター
    if (!empty($search_params['sport_type'])) {
        $args['meta_query'][] = [
            'key' => 'sport_type',
            'value' => $search_params['sport_type'],
            'compare' => '='
        ];
    }

    // 年代フィルター
    if (!empty($search_params['team_category'])) {
        $args['meta_query'][] = [
            'key' => 'team_category',
            'value' => $search_params['team_category'],
            'compare' => '='
        ];
    }

    // 性別フィルター
    if (!empty($search_params['team_gender_option'])) {
        $args['meta_query'][] = [
            'key' => 'team_gender_option',
            'value' => $search_params['team_gender_option'],
            'compare' => '='
        ];
    }

    // キーワード検索
    if (!empty($search_params['keyword'])) {
        $args['s'] = $search_params['keyword'];
    }

    return get_posts($args);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム招待メール送信
--------------------------------------------------------------*/
function aidunite_send_team_invitation($team_id, $invite_email, $invite_message = '') {
    if (!$team_id || !$invite_email) {
        return false;
    }

    $team_info = aidunite_get_team_info($team_id);
    if (!$team_info) {
        return false;
    }

    $invite_url = home_url('/team-apply/?join_code=' . $team_info['invite_code']);

    $subject = '【AidUnite】チーム参加のご案内';
    $message = "
{$invite_message}

チーム名：{$team_info['team_name']}
競技：{$team_info['sport_type']}
地域：{$team_info['region']}

以下のURLよりチームに参加できます：
{$invite_url}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return wp_mail($invite_email, $subject, $message, $headers);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：管理者通知
--------------------------------------------------------------*/
function aidunite_notify_admin_team_registration($team_id, $team_data) {
    $admin_email = get_option('admin_email');
    $team_name = $team_data['team_name'];
    $registrant_name = $team_data['registrant_name'];
    $contact_mail = isset($team_data['contact_mail']) ? $team_data['contact_mail'] : '';

    $edit_url = admin_url("post.php?post={$team_id}&action=edit");
    $approval_page = home_url('/team-approval');

    $subject = '【AidUnite】新しいチーム申請があります';
    $message = "
📝 チーム名：{$team_name}<br>
👤 申請者名：{$registrant_name}<br>
📧 メール：{$contact_mail}<br>
📅 申請日時：" . current_time('Y-m-d H:i:s') . "<br><br>
✅ <a href='{$edit_url}'>→チーム承認はこちら</a>
";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $mail_ok = wp_mail($admin_email, $subject, $message, $headers);

    // お知らせCPTを作成し、ヘッダーのお知らせアイコン・一覧に表示する
    if (function_exists('aidunite_notification_send')) {
        $title_short = '新しいチーム申請：「' . $team_name . '」';
        $message_short = "チーム名: {$team_name}\n申請者: {$registrant_name}\nメール: {$contact_mail}";
        $admin_users = get_users(['role' => 'administrator', 'fields' => 'ID']);
        if (empty($admin_users)) {
            $admin = get_user_by('email', $admin_email);
            if ($admin) {
                $admin_users = [$admin->ID];
            }
        }
        foreach ($admin_users as $admin_id) {
            aidunite_notification_send((int) $admin_id, 'admin_team_application', [
                'title'     => $title_short,
                'message'   => $message_short,
                'related_id' => $team_id,
                'link_url'  => $approval_page,
            ]);
        }
    }

    return $mail_ok;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム代表者承認通知
--------------------------------------------------------------*/
function aidunite_notify_team_leader_approval($user_id, $team_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $team_info = aidunite_get_team_info($team_id);
    if (!$team_info) {
        return false;
    }

    $subject = '【AidUnite】チーム登録が承認されました';
    $message = "
{$user->display_name} 様

あなたのチーム「{$team_info['team_name']}」が承認されました。
チーム情報の編集が可能です。

マイページからチーム管理を行ってください：
" . home_url('/mypage') . "

AidUnite運営チーム
";

    return wp_mail($user->user_email, $subject, $message);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム代表者申請通知
--------------------------------------------------------------*/
function aidunite_notify_team_leader_application($user_id, $application_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $applicant_id = get_post_meta($application_id, 'applicant_id', true);
    $team_id = get_post_meta($application_id, 'team_id', true);
    $application_message = get_post_meta($application_id, 'application_message', true);

    $applicant = get_userdata($applicant_id);
    $team_info = aidunite_get_team_info($team_id);

    if (!$applicant || !$team_info) {
        return false;
    }

    $subject = '【AidUnite】チーム参加申請があります';
    $message = "
{$user->display_name} 様

チーム「{$team_info['team_name']}」に参加申請があります。

申請者：{$applicant->display_name}
メール：{$applicant->user_email}
メッセージ：{$application_message}

管理画面で承認・拒否を選択してください：
" . admin_url('edit.php?post_type=team_application') . "

AidUnite運営チーム
";

    return wp_mail($user->user_email, $subject, $message);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：申請者承認通知
--------------------------------------------------------------*/
function aidunite_notify_user_application_approved($user_id, $team_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $team_info = aidunite_get_team_info($team_id);
    if (!$team_info) {
        return false;
    }

    $subject = '【AidUnite】チーム参加申請が承認されました';
    $message = "
{$user->display_name} 様

チーム「{$team_info['team_name']}」への参加申請が承認されました。
マイページからチーム情報を確認できます：
" . home_url('/mypage') . "

AidUnite運営チーム
";

    return wp_mail($user->user_email, $subject, $message);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム申請投稿タイプ登録
--------------------------------------------------------------*/
function aidunite_register_team_application_post_type() {
    register_post_type('team_application', [
        'labels' => [
            'name' => 'チーム申請',
            'singular_name' => 'チーム申請',
            'add_new' => '新規追加',
            'add_new_item' => '新規チーム申請を追加',
            'edit_item' => 'チーム申請を編集',
            'new_item' => '新しいチーム申請',
            'view_item' => 'チーム申請を表示',
            'search_items' => 'チーム申請を検索',
            'not_found' => 'チーム申請が見つかりませんでした',
            'not_found_in_trash' => 'ゴミ箱にチーム申請が見つかりませんでした'
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'capability_type' => 'post',
        'hierarchical' => false,
        'rewrite' => false,
        'supports' => ['title', 'custom-fields'],
        'menu_icon' => 'dashicons-groups'
    ]);
}
add_action('init', 'aidunite_register_team_application_post_type');

/*--------------------------------------------------------------
  AidUnite統一仕様：支援者ダッシュボード情報取得
--------------------------------------------------------------*/
function aidunite_get_supporter_dashboard() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return [];
    }

    $dashboard_data = [
        'supported_teams' => [],
        'recent_supports' => [],
        'support_stats' => []
    ];

    // 支援中のチームを取得
    $supported_teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'supporters',
                'value' => $user_id,
                'compare' => 'LIKE'
            ]
        ]
    ]);

    $dashboard_data['supported_teams'] = $supported_teams;

    // 最近の支援履歴を取得
    $recent_supports = get_posts([
        'post_type' => 'support_log',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'author' => $user_id,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_supports as $support) {
        $team_id = get_post_meta($support->ID, 'team_id', true);
        $support_amount = get_post_meta($support->ID, 'support_amount', true);
        $support_type = get_post_meta($support->ID, 'support_type', true);

        $team = get_post($team_id);

        $dashboard_data['recent_supports'][] = [
            'support_id' => $support->ID,
            'team_name' => $team ? $team->post_title : '',
            'support_amount' => $support_amount,
            'support_type' => $support_type,
            'support_date' => get_post_meta($support->ID, 'support_date', true),
            'status' => get_post_meta($support->ID, 'support_status', true)
        ];
    }

    return $dashboard_data;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：月間支援回数取得
--------------------------------------------------------------*/
function aidunite_get_monthly_support_count($user_id) {
    if (!$user_id) {
        return 0;
    }

    $current_month = date('Y-m');
    $monthly_supports = get_posts([
        'post_type' => 'support_log',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'author' => $user_id,
        'meta_query' => [
            [
                'key' => 'support_date',
                'value' => $current_month,
                'compare' => 'LIKE'
            ]
        ]
    ]);

    return count($monthly_supports);
}

/*--------------------------------------------------------------
  チームロゴの表示位置（申請フォームで調整）
--------------------------------------------------------------*/
if (!function_exists('aidunite_sanitize_team_logo_crop')) {
    /**
     * @return array{x:int,y:int,zoom:int}
     */
    function aidunite_sanitize_team_logo_crop($offset_x, $offset_y, $zoom) {
        $max = 120;
        return [
            'x' => max(-$max, min($max, (int) $offset_x)),
            'y' => max(-$max, min($max, (int) $offset_y)),
            'zoom' => max(50, min(250, (int) $zoom)),
        ];
    }
}

if (!function_exists('aidunite_save_team_logo_crop_meta')) {
    /**
     * @param int $team_id
     * @param int $offset_x
     * @param int $offset_y
     * @param int $zoom 50–250（%・100が基準）
     */
    function aidunite_save_team_logo_crop_meta($team_id, $offset_x, $offset_y, $zoom) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            return;
        }
        $crop = aidunite_sanitize_team_logo_crop($offset_x, $offset_y, $zoom);
        update_post_meta($team_id, 'team_logo_offset_x', $crop['x']);
        update_post_meta($team_id, 'team_logo_offset_y', $crop['y']);
        update_post_meta($team_id, 'team_logo_zoom', $crop['zoom']);
    }
}

if (!function_exists('aidunite_get_team_logo_crop')) {
    /**
     * @param int $team_id
     * @return array{x:int,y:int,zoom:int}
     */
    function aidunite_get_team_logo_crop($team_id) {
        $zoom = (int) get_post_meta((int) $team_id, 'team_logo_zoom', true);
        if ($zoom < 50) {
            $zoom = 100;
        }
        return aidunite_sanitize_team_logo_crop(
            get_post_meta((int) $team_id, 'team_logo_offset_x', true),
            get_post_meta((int) $team_id, 'team_logo_offset_y', true),
            $zoom > 0 ? $zoom : 100
        );
    }
}

/*--------------------------------------------------------------
  チームロゴアップロード上限（申請・設定共通）
--------------------------------------------------------------*/
if (!function_exists('aidunite_get_team_logo_max_upload_bytes')) {
    function aidunite_get_team_logo_max_upload_bytes() {
        return (int) apply_filters('aidunite_team_logo_max_upload_bytes', 10 * 1024 * 1024);
    }
}

if (!function_exists('aidunite_get_team_logo_max_upload_label')) {
    function aidunite_get_team_logo_max_upload_label() {
        $mb = (int) round(aidunite_get_team_logo_max_upload_bytes() / (1024 * 1024));
        return $mb > 0 ? $mb . 'MB' : '10MB';
    }
}

if (!function_exists('aidunite_get_team_logo_upload_script_config')) {
    /**
     * ロゴアップロード JS 用設定（申請・設定で共通）
     *
     * @param string $nonce_action
     * @param string $nonce_field
     * @param string $upload_action
     * @return array<string, mixed>
     */
    function aidunite_get_team_logo_upload_script_config($nonce_action, $nonce_field, $upload_action) {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($nonce_action),
            'nonceField' => $nonce_field,
            'uploadAction' => $upload_action,
            'logoMaxBytes' => aidunite_get_team_logo_max_upload_bytes(),
            'logoMaxLabel' => aidunite_get_team_logo_max_upload_label(),
        ];
    }
}

if (!function_exists('aidunite_team_logo_upload_error_message')) {
    /**
     * @param int $error_code $_FILES['error']
     */
    function aidunite_team_logo_upload_error_message($error_code) {
        $max_label = aidunite_get_team_logo_max_upload_label();
        switch ((int) $error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'ファイルサイズが大きすぎます（' . $max_label . '以下、またはサーバーのアップロード上限以内にしてください）。';
            case UPLOAD_ERR_PARTIAL:
                return '画像のアップロードが途中で中断されました。ページを再読み込みしてから、もう一度お試しください。'
                    . ' Local の Live Links 経由の場合はオフにし、aidunite-d.local などローカル URL で開いてください。';
            case UPLOAD_ERR_NO_FILE:
                return '画像ファイルを選択してください。';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return 'サーバー側で画像の保存に失敗しました。管理者にお問い合わせください。';
            default:
                return 'アップロードに失敗しました。';
        }
    }
}

/*--------------------------------------------------------------
  Ajax：チーム申請 — ロゴ画像アップロード
--------------------------------------------------------------*/
add_action('wp_ajax_team_registration_logo_upload', 'ajax_team_registration_logo_upload');
add_action('wp_ajax_team_settings_logo_upload', 'ajax_team_registration_logo_upload');

/**
 * チーム申請フォーム用ロゴアップロード（jpg / png / webp）
 */
function ajax_team_registration_logo_upload() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';

    if (!is_user_logged_in()) {
        wp_send_json(['success' => false, 'message' => 'ログインが必要です。'], 403);
    }

    $nonce_result = AidUniteAuthMiddleware::verify_nonce('team_metabox_nonce', 'save_team_metabox');
    if (is_wp_error($nonce_result)) {
        $nonce_result = AidUniteAuthMiddleware::verify_nonce(
            'aidunite_team_settings_nonce',
            'aidunite_team_settings_save'
        );
    }
    if (is_wp_error($nonce_result)) {
        wp_send_json(['success' => false, 'message' => $nonce_result->get_error_message()], 403);
    }

    if (empty($_FILES['team_logo_file']) || !is_array($_FILES['team_logo_file'])) {
        wp_send_json(['success' => false, 'message' => '画像ファイルを選択してください。'], 400);
    }

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }
    @ini_set('max_execution_time', '300');

    $file = $_FILES['team_logo_file'];
    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json([
            'success' => false,
            'message' => aidunite_team_logo_upload_error_message((int) $file['error']),
        ], 400);
    }

    $max_bytes = aidunite_get_team_logo_max_upload_bytes();
    $max_label = aidunite_get_team_logo_max_upload_label();
    if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
        wp_send_json([
            'success' => false,
            'message' => 'ファイルサイズは' . $max_label . '以下にしてください。',
        ], 400);
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('wp_check_filetype')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $checked = wp_check_filetype($file['name'] ?? '', [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ]);
    if (empty($checked['ext']) || empty($checked['type'])) {
        wp_send_json([
            'success' => false,
            'message' => 'JPG / PNG / WebP の画像を選択してください。',
        ], 400);
    }

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $upload_overrides = [
        'test_form' => false,
        'mimes' => [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ],
    ];

    $movefile = wp_handle_upload($file, $upload_overrides);
    if (!$movefile || isset($movefile['error'])) {
        wp_send_json([
            'success' => false,
            'message' => isset($movefile['error']) ? $movefile['error'] : '画像のアップロードに失敗しました。',
        ], 400);
    }

    $resized_path = $movefile['file'];
    if (function_exists('aidunite_resize_image')) {
        require_once get_template_directory() . '/functions/messaging/file-upload-functions.php';
        $maybe = aidunite_resize_image($movefile['file'], 480, 480);
        if ($maybe) {
            $resized_path = $maybe;
        }
    }

    $upload_dir = wp_upload_dir();
    $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $resized_path);
    $url = esc_url_raw($url);

    wp_send_json([
        'success' => true,
        'url' => $url,
        'message' => 'ロゴをアップロードしました。',
    ]);
}

/*--------------------------------------------------------------
  Ajaxハンドラー：チーム登録
--------------------------------------------------------------*/
add_action('wp_ajax_team_registration', 'ajax_team_registration');
add_action('wp_ajax_nopriv_team_registration', 'ajax_team_registration');

function ajax_team_registration() {
    try {
        // デバッグログ
        error_log('=== チーム登録Ajax開始 ===');
        error_log('POST data: ' . print_r($_POST, true));

        // CSRF対策（統一版）
        require_once get_template_directory() . '/functions/common/auth-middleware.php';
        $nonce_result = AidUniteAuthMiddleware::verify_nonce('team_metabox_nonce', 'save_team_metabox');
        if (is_wp_error($nonce_result)) {
            error_log('Nonce check failed');
            wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
        }

        // チームデータを準備
        $current_user = wp_get_current_user();
        $rep_name = sanitize_text_field(wp_unslash($_POST['representative_name'] ?? ''));
        $rep_email = sanitize_email(wp_unslash($_POST['representative_email'] ?? ''));
        $rep_phone = sanitize_text_field(wp_unslash($_POST['representative_phone'] ?? ''));
        if ($current_user && $current_user->ID > 0) {
            if ($rep_name === '') {
                $rep_name = $current_user->display_name;
            }
            if ($rep_email === '') {
                $rep_email = $current_user->user_email;
            }
        }

        $user_id = get_current_user_id() ?: 1;
        $scope   = sanitize_key(wp_unslash($_POST['registration_scope'] ?? ''));

        if ($scope === 'both' && function_exists('aidunite_register_dual_gender_teams')) {
            $dual = aidunite_register_dual_gender_teams((int) $user_id, $_POST);
            if (is_wp_error($dual)) {
                wp_send_json([
                    'success' => false,
                    'message' => $dual->get_error_message(),
                ]);
            }
            $male_id   = (int) $dual['male_id'];
            $female_id = (int) $dual['female_id'];
            wp_send_json([
                'success'      => true,
                'message'      => '男子・女子のチーム申請を受け付けました。',
                'team_id'      => $male_id,
                'paired_team_ids' => [$male_id, $female_id],
                'redirect_url' => add_query_arg(
                    [
                        'team_id'       => $male_id,
                        'paired_team_id' => $female_id,
                    ],
                    home_url('/team-registration-complete/')
                ),
            ]);
        }

        $gender_scope = in_array($scope, ['male', 'female'], true) ? $scope : '';
        if ($gender_scope === '' && function_exists('aidunite_normalize_team_gender_option')) {
            $gender_scope = aidunite_normalize_team_gender_option((string) ($_POST['team_gender_option'] ?? ''));
        }
        if ($gender_scope === '') {
            wp_send_json([
                'success' => false,
                'message' => '申請するチーム（男子／女子／男女両方）を選択してください。',
            ]);
        }

        $suffix = $gender_scope === 'male' ? '男子' : '女子';
        if (function_exists('aidunite_build_team_registration_data_from_post')) {
            $team_data = aidunite_build_team_registration_data_from_post($_POST, $gender_scope, $suffix);
        } else {
            $team_data = [
                'team_name'          => $_POST['team_name'] ?? '',
                'team_name_kana'     => $_POST['team_name_kana'] ?? '',
                'team_description'   => $_POST['team_description'] ?? '',
                'sport_type'         => $_POST['sport_type'] ?? '',
                'team_category'      => $_POST['team_category'] ?? '',
                'team_type'          => $_POST['team_type'] ?? '',
                'region'             => $_POST['activity_prefecture'] ?? '',
                'team_gender_option' => $gender_scope,
                'team_place'         => $_POST['team_place'] ?? '',
                'team_logo'          => esc_url_raw(wp_unslash($_POST['team_logo'] ?? '')),
                'registrant_name'    => $rep_name,
                'contact_mail'       => $rep_email,
                'contact_phone'      => $rep_phone,
            ];
        }

        if (apply_filters('aidunite_mvp_ban_new_team_gender_both', true)) {
            $g = (string) ($team_data['team_gender_option'] ?? '');
            if (in_array($g, ['both', 'mixed'], true) || !in_array($g, ['male', 'female'], true)) {
                wp_send_json([
                    'success' => false,
                    'message' => '性別（男子／女子）を選択してください。',
                ]);
            }
        }

        error_log('チームデータ: ' . print_r($team_data, true));

        $team_id = aidunite_register_team($user_id, $team_data);

        if ($team_id) {
            error_log('チーム登録成功、ID: ' . $team_id);

            if (function_exists('aidunite_save_team_registration_post_meta_from_request')) {
                aidunite_save_team_registration_post_meta_from_request((int) $team_id, $_POST, '');
            }

            // プラン・決済は申請フォームからは送信しない（価値体験後に別導線）。レガシーPOSTがあればのみ処理。
            $selected_plan_id = sanitize_text_field($_POST['selected_plan_id'] ?? '');
            $selected_payment_method = sanitize_text_field($_POST['selected_payment_method'] ?? '');
            $board_code = sanitize_text_field($_POST['board_registration_code'] ?? '');

            if ($selected_plan_id !== '' || $board_code !== '') {
                require_once get_template_directory() . '/functions/payment/payment-config.php';
                require_once get_template_directory() . '/functions/payment/payment-functions.php';
            }

            if (!empty($board_code)) {
                // 専用コードが使用済みかチェック
                if (aidunite_is_registration_code_used($board_code)) {
                    // バリデーションエラー（フォームエラーとして扱う）
                    AidUniteApiResponse::send_validation_error(
                        ['board_registration_code' => 'この専用コードは既に使用されています'],
                        '入力内容を確認してください'
                    );
                    return;
                }

                // 専用コードを設定
                aidunite_set_board_registration_code($team_id, $board_code);
                aidunite_set_team_payment_mode($team_id, 'board');

                // デフォルトプランを適用
                $config = aidunite_get_payment_config();
                $config_key = function_exists('aidunite_team_type_payment_config_key')
                    ? aidunite_team_type_payment_config_key($team_id)
                    : (function_exists('aidunite_team_type_is_club') && aidunite_team_type_is_club($team_type) ? 'club' : 'school');
                $plans = $config[$config_key]['plans'];

                foreach ($plans as $plan) {
                    if (!empty($plan['is_default'])) {
                        $selected_plan_id = $plan['id'];
                        break;
                    }
                }
            }

            if (!empty($selected_plan_id) && function_exists('aidunite_set_selected_plan_id')) {
                aidunite_set_selected_plan_id($team_id, $selected_plan_id);
                if (function_exists('aidunite_set_trial_start_date')) {
                    aidunite_set_trial_start_date($team_id);
                }
                if ($selected_payment_method !== '') {
                    update_post_meta($team_id, 'selected_payment_method', $selected_payment_method);
                }
                if (empty($board_code)) {
                    if ($selected_payment_method === 'invoice' && function_exists('aidunite_set_team_payment_mode')) {
                        aidunite_set_team_payment_mode($team_id, 'school');
                    } elseif (function_exists('aidunite_set_team_payment_mode')) {
                        aidunite_set_team_payment_mode($team_id, 'personal');
                    }
                }
                if (function_exists('aidunite_set_payment_status')) {
                    $payment_user_id = get_current_user_id() ?: (int) $user_id;
                    aidunite_set_payment_status($payment_user_id, 'trial');
                }
            }

            wp_send_json(array(
                'success' => true,
                'message' => 'チーム申請を受け付けました。',
                'team_id' => $team_id,
                'redirect_url' => add_query_arg(
                    'team_id',
                    (int) $team_id,
                    home_url('/team-registration-complete/')
                )
            ));
        } else {
            error_log('チーム登録失敗');

            $detail = function_exists('aidunite_register_team_take_last_error')
                ? aidunite_register_team_take_last_error()
                : '';

            wp_send_json(array(
                'success' => false,
                'message' => $detail !== ''
                    ? $detail
                    : 'チームの作成に失敗しました。再度お試しください。',
            ));
        }

    } catch (Exception $e) {
        error_log('チーム登録Ajaxエラー: ' . $e->getMessage());
        error_log('スタックトレース: ' . $e->getTraceAsString());

        // 汎用的なエラーメッセージを返す（技術的詳細はログに記録済み）
        AidUniteApiResponse::send_error(
            'チームの作成に失敗しました。しばらく時間をおいて再度お試しください。問題が続く場合は、お問い合わせください。',
            null,
            'normal',
            'team_registration_failed'
        );
    }
}
