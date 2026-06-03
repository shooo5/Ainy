<?php
/**
 * 保護者機能管理
 * AidUnite統一仕様対応版
 */

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者登録処理
--------------------------------------------------------------*/
function aidunite_register_parent($user_id, $parent_data) {
    if (!$user_id || !$parent_data) {
        return false;
    }

    // 保護者としてユーザータイプを設定
    aidunite_set_user_type($user_id, 'parent');

    // 保護者メタデータを保存
    $meta_fields = [
        'parent_name',
        'parent_name_kana',
        'parent_phone',
        'parent_email',
        'parent_address',
        'parent_emergency_contact',
        'parent_relationship',
        'parent_children_count'
    ];

    foreach ($meta_fields as $field) {
        if (isset($parent_data[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($parent_data[$field]));
        }
    }

    // チームIDが指定されている場合は設定
    if (!empty($parent_data['team_id'])) {
        aidunite_set_user_team($user_id, $parent_data['team_id']);
    }

    return true;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：子供（選手）登録処理
--------------------------------------------------------------*/
function aidunite_register_child_player($parent_id, $child_data) {
    if (!$parent_id || !$child_data) {
        return false;
    }

    // 子供のユーザーアカウント作成（メールアドレスまたはニックネーム対応）
    $team_id_for_slug = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $parent_id)
        : (int) get_user_meta($parent_id, 'team_id', true);
    $username = aidunite_generate_child_username($child_data['child_name'], $team_id_for_slug);

    // メールアドレス設定（任意・正規化）
    $email = !empty($child_data['child_email'])
        ? (function_exists('aidunite_normalize_email') ? aidunite_normalize_email($child_data['child_email']) : sanitize_email($child_data['child_email']))
        : 'child_' . time() . '@temp.aidunite.local';

    $password = wp_generate_password(12, false);
    $child_user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($child_user_id)) {
        return false;
    }

    // 子供のユーザー情報を設定
    wp_update_user([
        'ID' => $child_user_id,
        'display_name' => $child_data['child_name'],
        'first_name' => $child_data['child_name'],
        'nickname' => $child_data['child_name']
    ]);

    // 子供を選手として設定
    aidunite_set_user_type($child_user_id, 'player');
    aidunite_set_minor_status($child_user_id, true);

    // 保護者と子供を連携
    aidunite_link_parent_and_player($parent_id, $child_user_id);

    // 子供の選手メタデータを保存
    $child_meta_fields = [
        'player_name',
        'player_name_kana',
        'birth_date',
        'age',
        'gender',
        'position',
        'jersey_number',
        'height',
        'weight',
        'player_description',
        'medical_info',
        'emergency_contact'
    ];

    foreach ($child_meta_fields as $field) {
        if (isset($child_data[$field])) {
            update_user_meta($child_user_id, $field, sanitize_text_field($child_data[$field]));
        }
    }

    // 年齢自動計算
    if (!empty($child_data['birth_date'])) {
        $birth = new DateTime($child_data['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        update_user_meta($child_user_id, 'age', $age);
    }

    // 保護者のチームIDを子供にも設定（操作中チーム／プライマリを優先）
    $parent_team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $parent_id)
        : (int) get_user_meta($parent_id, 'team_id', true);
    if ($parent_team_id) {
        aidunite_set_user_team($child_user_id, $parent_team_id);
    }

    // 保護者に通知メール送信
    aidunite_notify_parent_child_registration($parent_id, $child_user_id, $password);

    return $child_user_id;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：出欠カスタム投稿タイプ登録
--------------------------------------------------------------*/
function aidunite_register_attendance_post_type() {
    $labels = array(
        'name'               => '出欠管理',
        'singular_name'      => '出欠',
        'menu_name'          => '出欠管理',
        'add_new'            => '新規追加',
        'add_new_item'       => '新しい出欠を追加',
        'edit_item'          => '出欠を編集',
        'new_item'           => '新しい出欠',
        'view_item'          => '出欠を表示',
        'search_items'       => '出欠を検索',
        'not_found'          => '出欠が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に出欠が見つかりませんでした'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'attendance'),
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => null,
        'supports'            => array('title', 'author', 'custom-fields'),
        'menu_icon'           => 'dashicons-calendar-alt'
    );

    register_post_type('attendance', $args);
}
add_action('init', 'aidunite_register_attendance_post_type');

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者の子供取得（基本関数）
--------------------------------------------------------------*/
function aidunite_get_parent_children($parent_id) {
    if (!$parent_id) {
        return [];
    }

    // 保護者の子供を取得（player カスタム投稿タイプから）
    $children = get_posts([
        'post_type' => 'player',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'parent_id',
                'value' => $parent_id,
                'compare' => '='
            ]
        ]
    ]);

    $children_list = [];

    foreach ($children as $child) {
        $user_id = get_post_meta($child->ID, 'user_id', true);
        $team_id = get_post_meta($child->ID, 'team_id', true);

        if ($user_id) {
            $user = get_userdata($user_id);
            $children_list[] = [
                'user_id' => $user_id,
                'display_name' => $user ? $user->display_name : '',
                'is_minor' => true, // 子供は未成年として扱う
                'team_id' => $team_id
            ];
        }
    }

    return $children_list;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者の子供一覧取得（統一命名）
--------------------------------------------------------------*/
function aidunite_get_parent_children_list($parent_id) {
    if (!$parent_id) {
        return [];
    }

    $children = aidunite_get_parent_children($parent_id);
    $children_list = [];

    foreach ($children as $child) {
        $children_list[] = [
            'user_id' => $child['user_id'],
            'display_name' => $child['display_name'],
            'player_name' => get_user_meta($child['user_id'], 'player_name', true),
            'age' => get_user_meta($child['user_id'], 'age', true),
            'gender' => get_user_meta($child['user_id'], 'gender', true),
            'position' => get_user_meta($child['user_id'], 'position', true),
            'jersey_number' => get_user_meta($child['user_id'], 'jersey_number', true),
            'is_minor' => $child['is_minor'],
            'team_id' => $child['team_id']
        ];
    }

    return $children_list;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_parent_children_list() を使用してください
 */
function tunageru_get_parent_children_list($parent_id) {
    return aidunite_get_parent_children_list($parent_id);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：子供のスケジュール取得（統一命名）
--------------------------------------------------------------*/
function aidunite_get_child_schedules($child_id, $date_range = null) {
    if (!$child_id) {
        return [];
    }

    $team_clause = function_exists('aidunite_schedule_team_meta_query_for_user')
        ? aidunite_schedule_team_meta_query_for_user((int) $child_id)
        : null;
    if ($team_clause === null) {
        return [];
    }

    $args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            $team_clause,
        ],
        'meta_key' => 'schedule_date',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ];

    // 日付範囲フィルター
    if ($date_range) {
        if (!empty($date_range['start'])) {
            $args['meta_query'][] = [
                'key' => 'schedule_date',
                'value' => $date_range['start'],
                'compare' => '>='
            ];
        }
        if (!empty($date_range['end'])) {
            $args['meta_query'][] = [
                'key' => 'schedule_date',
                'value' => $date_range['end'],
                'compare' => '<='
            ];
        }
    }

    $schedules = get_posts($args);
    $schedule_list = [];

    foreach ($schedules as $schedule) {
        $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
        $schedule_start_time = get_post_meta($schedule->ID, 'schedule_start_time', true);
        $schedule_end_time = get_post_meta($schedule->ID, 'schedule_end_time', true);

        $schedule_list[] = [
            'schedule_id' => $schedule->ID,
            'date' => $schedule_date,
            'time' => $schedule_start_time . ($schedule_end_time ? ' - ' . $schedule_end_time : ''),
            'title' => $schedule->post_title,
            'place' => get_post_meta($schedule->ID, 'schedule_place', true),
            'type' => get_post_meta($schedule->ID, 'schedule_type', true),
            'note' => get_post_meta($schedule->ID, 'schedule_note', true),
            'matching' => (bool)get_post_meta($schedule->ID, 'matching', true) // 統一されたキー名を使用
        ];
    }

    return $schedule_list;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_child_schedules() を使用してください
 */
function tunageru_get_child_schedules($child_id, $date_range = null) {
    return aidunite_get_child_schedules($child_id, $date_range);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：出欠回答処理
--------------------------------------------------------------*/
function aidunite_submit_attendance($parent_id, $schedule_id, $child_id, $attendance_data) {
    if (!$parent_id || !$schedule_id || !$child_id || !$attendance_data) {
        return false;
    }

    $attendance_team_id = 0;
    if (function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $attendance_team_id = (int) aidunite_resolve_schedule_owner_team_id((int) $schedule_id);
    }
    if ($attendance_team_id <= 0 && function_exists('aidunite_get_current_team_id')) {
        $attendance_team_id = (int) aidunite_get_current_team_id((int) $child_id);
    }
    if ($attendance_team_id <= 0) {
        $attendance_team_id = (int) get_user_meta($child_id, 'team_id', true);
    }

    // 出欠投稿を作成
    $post_data = [
        'post_type' => 'attendance',
        'post_title' => '出欠回答: ' . get_userdata($child_id)->display_name . ' - ' . get_the_title($schedule_id),
        'post_status' => 'publish',
        'post_author' => $parent_id,
        'meta_input' => [
            'schedule_id' => $schedule_id,
            'child_id' => $child_id,
            'parent_id' => $parent_id,
            'attendance_status' => $attendance_data['status'], // attending, not_attending, maybe
            'attendance_note' => isset($attendance_data['note']) ? $attendance_data['note'] : '',
            'attendance_date' => current_time('mysql'),
            'team_id' => $attendance_team_id
        ]
    ];

    $attendance_id = wp_insert_post($post_data);

    if (is_wp_error($attendance_id)) {
        return false;
    }

    // チーム代表者に通知
    if ($attendance_team_id) {
        $team_post = get_post($attendance_team_id);
        if ($team_post) {
            aidunite_notify_team_leader_attendance($team_post->post_author, $attendance_id);
        }
    }

    return $attendance_id;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：出欠一覧取得（統一命名）
--------------------------------------------------------------*/
function aidunite_get_attendance_list($schedule_id) {
    if (!$schedule_id) {
        return [];
    }

    $attendances = get_posts([
        'post_type' => 'attendance',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'schedule_id',
                'value' => $schedule_id,
                'compare' => '='
            ]
        ]
    ]);

    $attendance_list = [];

    foreach ($attendances as $attendance) {
        $child_id = get_post_meta($attendance->ID, 'child_id', true);
        $parent_id = get_post_meta($attendance->ID, 'parent_id', true);

        $child = get_userdata($child_id);
        $parent = get_userdata($parent_id);

        $attendance_list[] = [
            'attendance_id' => $attendance->ID,
            'child_name' => $child ? $child->display_name : '',
            'parent_name' => $parent ? $parent->display_name : '',
            'status' => get_post_meta($attendance->ID, 'attendance_status', true),
            'note' => get_post_meta($attendance->ID, 'attendance_note', true),
            'submitted_date' => get_post_meta($attendance->ID, 'attendance_date', true)
        ];
    }

    return $attendance_list;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_attendance_list() を使用してください
 */
function tunageru_get_attendance_list($schedule_id) {
    return aidunite_get_attendance_list($schedule_id);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：招待トークン生成
--------------------------------------------------------------*/
function aidunite_generate_invite_token($user_id, $team_id, $hours_valid = 72) {
    if (!$team_id) {
        return false;
    }

    // トークンを生成
    $token = bin2hex(random_bytes(32));
    $expires_at = time() + ($hours_valid * 3600);

    // トークンを一時的に保存（オプション：データベースに保存する場合は別途実装）
    // ここでは、トークンと有効期限をメタデータとして保存
    $token_data = [
        'token' => $token,
        'user_id' => $user_id,
        'team_id' => $team_id,
        'expires_at' => $expires_at,
        'created_at' => time()
    ];

    // トークンデータを一時的に保存（セッションまたはオプションとして）
    $option_key = 'aidunite_invite_token_' . md5($token);
    update_option($option_key, $token_data, false);

    return $token;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：招待トークン検証
--------------------------------------------------------------*/
function aidunite_verify_invite_token($token, $team_id = null) {
    if (empty($token)) {
        return false;
    }

    // トークンデータを取得
    $option_key = 'aidunite_invite_token_' . md5($token);
    $token_data = get_option($option_key);

    if (!$token_data || !is_array($token_data)) {
        return false;
    }

    // 有効期限チェック
    if (isset($token_data['expires_at']) && $token_data['expires_at'] < time()) {
        delete_option($option_key);
        return false;
    }

    // トークン一致チェック
    if ($token_data['token'] !== $token) {
        return false;
    }

    // チームIDチェック（指定されている場合）
    if ($team_id !== null && isset($token_data['team_id']) && $token_data['team_id'] != $team_id) {
        return false;
    }

    return $token_data;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者招待処理
--------------------------------------------------------------*/
function aidunite_invite_parent_to_team($team_id, $invite_email, $invite_message = '') {
    if (!$team_id || !$invite_email) {
        return false;
    }

    $team_info = aidunite_get_team_info($team_id);
    if (!$team_info) {
        return false;
    }

    // 招待トークンを生成
    $invite_token = aidunite_generate_invite_token(0, $team_id, 72); // 72時間有効

    // 招待メール送信
    $invite_url = home_url('/guardian-signup/?token=' . $invite_token . '&team_id=' . $team_id);

    $subject = '【AidUnite】チーム参加のご案内（保護者向け）';
    $message = "
{$invite_message}

チーム名：{$team_info['team_name']}
競技：{$team_info['sport_type']}
地域：{$team_info['region']}

以下のURLより保護者として登録し、チームに参加できます：
{$invite_url}

※このリンクは72時間有効です。

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return wp_mail($invite_email, $subject, $message, $headers);
}

/*--------------------------------------------------------------
  親と子供の紐付け（世帯IDを共通で設定）
  保護者ユーザーと選手ユーザーに同じ family_id を付与し、一覧で「親子」を揃えて把握できるようにする。
--------------------------------------------------------------*/
if (!function_exists('aidunite_link_parent_and_player')) {
function aidunite_link_parent_and_player($parent_id, $child_user_id) {
    if (!$parent_id || !$child_user_id) {
        return;
    }
    $parent_id = (int) $parent_id;
    $child_user_id = (int) $child_user_id;
    // 世帯ID = 保護者ユーザーID（同じ保護者に紐づく親子は同じ世帯IDになる）
    $family_id = (string) $parent_id;
    update_user_meta($parent_id, 'family_id', $family_id);
    update_user_meta($child_user_id, 'family_id', $family_id);
    update_user_meta($child_user_id, 'linked_parent_id', $parent_id);
}
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者情報紐付け処理
--------------------------------------------------------------*/
function aidunite_link_parent_to_child($child_id, $parent_data) {
    if (!$child_id || !$parent_data) {
        return false;
    }

    // 保護者情報をメタデータとして保存（選手のユーザーメタに保存）
    $parent_meta_fields = [
        'parent_name' => sanitize_text_field($parent_data['parent_name'] ?? ''),
        'parent_name_sei' => sanitize_text_field($parent_data['parent_name_sei'] ?? ''),
        'parent_name_mei' => sanitize_text_field($parent_data['parent_name_mei'] ?? ''),
        'parent_name_kana' => sanitize_text_field($parent_data['parent_name_kana'] ?? ''),
        'parent_phone' => sanitize_text_field($parent_data['parent_phone'] ?? ''),
        'parent_email' => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($parent_data['parent_email'] ?? '') : sanitize_email($parent_data['parent_email'] ?? ''),
        'parent_relationship' => sanitize_text_field($parent_data['parent_relationship'] ?? '父'),
        'parent_emergency_contact' => sanitize_text_field($parent_data['parent_emergency_contact'] ?? ''),
        'parent_linked_date' => current_time('mysql')
    ];

    foreach ($parent_meta_fields as $key => $value) {
        update_user_meta($child_id, $key, $value);
    }

    // 保護者情報紐付け完了フラグ
    update_user_meta($child_id, 'parent_info_linked', true);
    delete_user_meta($child_id, 'needs_parent_link');

    return true;
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム代表者による選手登録（保護者情報付き）
--------------------------------------------------------------*/
function aidunite_team_leader_register_player_with_parent($team_leader_id, $player_data, $parent_data) {
    if (!aidunite_is_team_leader($team_leader_id)) {
        return ['success' => false, 'message' => 'チーム代表者権限が必要です'];
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $team_leader_id)
        : (int) get_user_meta($team_leader_id, 'team_id', true);
    if (!$team_id) {
        return ['success' => false, 'message' => 'チーム情報が見つかりません'];
    }

    // 既存ユーザーチェック（メールアドレスが指定されている場合・照合時は正規化）
    $existing_user = null;
    if (!empty($player_data['player_email'])) {
        $email_for_check = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($player_data['player_email']) : sanitize_email($player_data['player_email']);
        $existing_user = get_user_by('email', $email_for_check);
    }

    if ($existing_user) {
        // 既存ユーザーの場合: 選手ロールを追加
        $player_user_id = $existing_user->ID;

        // 選手として設定
        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, true);

        // 複数チーム所属対応: 既存のチーム所属を確認
        $existing_teams = aidunite_get_user_teams($player_user_id);

        // team_id が存在するが team_memberships が空の場合、team_id から team_memberships を作成
        if (empty($existing_teams)) {
            $managed_ids = function_exists('aidunite_get_managed_team_ids')
                ? aidunite_get_managed_team_ids((int) $player_user_id)
                : [];
            $existing_team_id = !empty($managed_ids) ? (int) $managed_ids[0] : (int) get_user_meta($player_user_id, 'team_id', true);
            if ($existing_team_id) {
                // 既存の team_id を team_memberships に変換
                $existing_teams = [
                    $existing_team_id => [
                        'team_id' => $existing_team_id,
                        'role' => 'player',
                        'joined_date' => current_time('mysql'),
                        'status' => 'active'
                    ]
                ];
                update_user_meta($player_user_id, 'team_memberships', $existing_teams);
                // キャッシュをクリアして、次の処理で最新の値を取得できるようにする
                clean_user_cache($player_user_id);
            }
        }

        // 新しいチームに追加（既存チームがある場合もない場合も統一処理）
        aidunite_add_user_to_multiple_teams($player_user_id, $team_id, 'player');

        // 選手情報を更新（既存ユーザーの場合も情報を更新可能）
        if (!empty($player_data['nickname']) || !empty($player_data['player_name'])) {
            wp_update_user([
                'ID' => $player_user_id,
                'display_name' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->display_name,
                'first_name' => $player_data['player_name'] ?? get_userdata($player_user_id)->first_name,
                'nickname' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->nickname
            ]);
        }

        // 選手メタデータ保存（既存のメタデータを更新）
        $player_meta_fields = [
            'player_name' => $player_data['player_name'] ?? '',
            'player_name_sei' => $player_data['player_name_sei'] ?? '',
            'player_name_mei' => $player_data['player_name_mei'] ?? '',
            'player_name_kana' => $player_data['player_name_kana'] ?? '',
            'player_kana_sei' => $player_data['player_kana_sei'] ?? '',
            'player_kana_mei' => $player_data['player_kana_mei'] ?? '',
            'player_birth_date' => $player_data['birth_date'] ?? '',
            'player_grade' => $player_data['grade'] ?? '',
            'player_position' => $player_data['position'] ?? '',
            'player_nickname' => $player_data['nickname'] ?? '',
            'player_club_team' => $player_data['club_team'] ?? '',
            'player_email' => $player_data['player_email'] ?? '',
            'player_height' => $player_data['height'] ?? '',
            'player_weight' => $player_data['weight'] ?? '',
            'jersey_number' => $player_data['jersey_number'] ?? '',
            'registered_by_team_leader' => $team_leader_id,
            'registration_date' => current_time('mysql')
        ];

        // 後方互換性のため、旧メタキーも保存
        $legacy_meta_fields = [
            'birth_date' => $player_data['birth_date'] ?? '',
            'grade' => $player_data['grade'] ?? '',
            'position' => $player_data['position'] ?? '',
        ];

        foreach ($player_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        foreach ($legacy_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        // 年齢・学年自動計算
        if (!empty($player_data['birth_date'])) {
            $birth = new DateTime($player_data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
            update_user_meta($player_user_id, 'age', $age);

            // 学年計算（4月基準）
            if (empty($player_data['grade'])) {
                $grade = aidunite_calculate_grade_from_birthdate($player_data['birth_date']);
                update_user_meta($player_user_id, 'player_grade', $grade);
                // 後方互換性のため旧キーも保存
                update_user_meta($player_user_id, 'grade', $grade);
            }
        }

        // 既存ユーザーの場合、パスワードは変更しない（ユーザーが既に設定済み）
        $password = null;
        $username = $existing_user->user_login;

        // キャッシュをクリアしてメタデータの更新を確実に反映
        clean_user_cache($player_user_id);
        wp_cache_flush();
    } else {
        // 新規ユーザーの場合: アカウント作成
        $username = aidunite_generate_child_username($player_data['player_name'], $team_id);
        $temp_email = !empty($player_data['player_email'])
            ? (function_exists('aidunite_normalize_email') ? aidunite_normalize_email($player_data['player_email']) : sanitize_email($player_data['player_email']))
            : 'player_' . time() . '@temp.aidunite.local';

        // パスワード設定（カスタムまたは自動生成）
        if (!empty($player_data['password_option']) && $player_data['password_option'] === 'manual' && !empty($player_data['custom_password'])) {
            $password = $player_data['custom_password'];
        } else {
            $password = wp_generate_password(12, false);
        }

        $player_user_id = wp_create_user($username, $password, $temp_email);

        if (is_wp_error($player_user_id)) {
            return ['success' => false, 'message' => 'アカウント作成に失敗しました: ' . $player_user_id->get_error_message()];
        }

        // 選手情報設定
        wp_update_user([
            'ID' => $player_user_id,
            'display_name' => $player_data['nickname'] ?? $player_data['player_name'],
            'first_name' => $player_data['player_name'],
            'nickname' => $player_data['nickname'] ?? $player_data['player_name']
        ]);

        // 選手メタデータ保存
        $player_meta_fields = [
            'player_name' => $player_data['player_name'],
            'player_name_sei' => $player_data['player_name_sei'] ?? '',
            'player_name_mei' => $player_data['player_name_mei'] ?? '',
            'player_name_kana' => $player_data['player_name_kana'] ?? '',
            'player_kana_sei' => $player_data['player_kana_sei'] ?? '',
            'player_kana_mei' => $player_data['player_kana_mei'] ?? '',
            'player_birth_date' => $player_data['birth_date'] ?? '',
            'player_grade' => $player_data['grade'] ?? '',
            'player_position' => $player_data['position'] ?? '',
            'player_nickname' => $player_data['nickname'] ?? '',
            'player_club_team' => $player_data['club_team'] ?? '',
            'player_email' => $player_data['player_email'] ?? '',
            'player_height' => $player_data['height'] ?? '',
            'player_weight' => $player_data['weight'] ?? '',
            'jersey_number' => $player_data['jersey_number'] ?? '',
            'registered_by_team_leader' => $team_leader_id,
            'registration_date' => current_time('mysql')
        ];

        // 後方互換性のため、旧メタキーも保存
        $legacy_meta_fields = [
            'birth_date' => $player_data['birth_date'] ?? '',
            'grade' => $player_data['grade'] ?? '',
            'position' => $player_data['position'] ?? '',
        ];

        foreach ($player_meta_fields as $key => $value) {
            update_user_meta($player_user_id, $key, $value);
        }

        foreach ($legacy_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        // 年齢・学年自動計算
        if (!empty($player_data['birth_date'])) {
            $birth = new DateTime($player_data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
            update_user_meta($player_user_id, 'age', $age);

            // 学年計算（4月基準）
            if (empty($player_data['grade'])) {
                $grade = aidunite_calculate_grade_from_birthdate($player_data['birth_date']);
                update_user_meta($player_user_id, 'player_grade', $grade);
                // 後方互換性のため旧キーも保存
                update_user_meta($player_user_id, 'grade', $grade);
            }
        }

        // 選手設定
        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, true);
        aidunite_set_user_team($player_user_id, $team_id);
    }

    // 保護者情報紐付け・アカウント作成
    $parent_user_id = null;
    $parent_password = null;

    if (!empty($parent_data)) {
        // 保護者アカウント作成が指定されている場合
        if (!empty($parent_data['create_account']) && !empty($parent_data['parent_email'])) {
            $parent_result = aidunite_create_parent_account($parent_data, $team_id);

            if ($parent_result['success']) {
                $parent_user_id = $parent_result['parent_id'];
                $parent_password = $parent_result['password'];

                // 保護者と選手を紐付け
                aidunite_link_parent_and_player($parent_user_id, $player_user_id);
            }
        } else {
            // 保護者情報のみ紐付け（アカウント作成なし）
            aidunite_link_parent_to_child($player_user_id, $parent_data);
        }

        // 保護者にメール通知
        if (!empty($parent_data['parent_email'])) {
            aidunite_notify_parent_child_registration_by_team(
                $parent_data['parent_email'],
                $player_data,
                $username,
                $password,
                $parent_password
            );
        }
    } else {
        // 保護者情報は後で紐付け
        update_user_meta($player_user_id, 'needs_parent_link', true);
    }

    return [
        'success' => true,
        'player_id' => $player_user_id,
        'username' => $username,
        'password' => $password,
        'parent_id' => $parent_user_id,
        'parent_password' => $parent_password
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：成人チーム向け選手登録（緊急連絡先付き）
--------------------------------------------------------------*/
function aidunite_team_leader_register_adult_player($team_leader_id, $player_data, $emergency_contact_data) {
    if (!aidunite_is_team_leader($team_leader_id)) {
        return ['success' => false, 'message' => 'チーム代表者権限が必要です'];
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $team_leader_id)
        : (int) get_user_meta($team_leader_id, 'team_id', true);
    if (!$team_id) {
        return ['success' => false, 'message' => 'チーム情報が見つかりません'];
    }

    // 既存ユーザーチェック（メールアドレスが指定されている場合）
    $existing_user = null;
    if (!empty($player_data['player_email'])) {
        $email_for_check = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($player_data['player_email']) : sanitize_email($player_data['player_email']);
        $existing_user = get_user_by('email', $email_for_check);
    }

    if ($existing_user) {
        // 既存ユーザーの場合: 選手ロールを追加
        $player_user_id = $existing_user->ID;

        // 選手として設定
        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, false); // 成人選手

        // 複数チーム所属対応: 既存のチーム所属を確認
        $existing_teams = aidunite_get_user_teams($player_user_id);
        if (!empty($existing_teams)) {
            // 既に他のチームに所属している場合、複数チーム所属として追加
            aidunite_add_user_to_multiple_teams($player_user_id, $team_id, 'player');
        } else {
            // 初回のチーム所属の場合
            aidunite_set_user_team($player_user_id, $team_id);
        }

        // 選手情報を更新（既存ユーザーの場合も情報を更新可能）
        if (!empty($player_data['nickname']) || !empty($player_data['player_name'])) {
            wp_update_user([
                'ID' => $player_user_id,
                'display_name' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->display_name,
                'first_name' => $player_data['player_name'] ?? get_userdata($player_user_id)->first_name,
                'nickname' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->nickname
            ]);
        }

        // 選手メタデータ保存（既存のメタデータを更新）
        $player_meta_fields = [
            'player_name' => $player_data['player_name'] ?? '',
            'player_name_sei' => $player_data['player_name_sei'] ?? '',
            'player_name_mei' => $player_data['player_name_mei'] ?? '',
            'player_name_kana' => $player_data['player_name_kana'] ?? '',
            'player_kana_sei' => $player_data['player_kana_sei'] ?? '',
            'player_kana_mei' => $player_data['player_kana_mei'] ?? '',
            'player_birth_date' => $player_data['birth_date'] ?? '',
            'player_grade' => $player_data['grade'] ?? '',
            'player_position' => $player_data['position'] ?? '',
            'player_nickname' => $player_data['nickname'] ?? '',
            'player_club_team' => $player_data['club_team'] ?? '',
            'player_email' => $player_data['player_email'] ?? '',
            'player_height' => $player_data['height'] ?? '',
            'player_weight' => $player_data['weight'] ?? '',
            'jersey_number' => $player_data['jersey_number'] ?? '',
            'registered_by_team_leader' => $team_leader_id,
            'registration_date' => current_time('mysql'),
            'is_adult_player' => true
        ];

        // 後方互換性のため、旧メタキーも保存
        $legacy_meta_fields = [
            'birth_date' => $player_data['birth_date'] ?? '',
            'grade' => $player_data['grade'] ?? '',
            'position' => $player_data['position'] ?? '',
        ];

        foreach ($player_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        foreach ($legacy_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        // 年齢・学年自動計算
        if (!empty($player_data['birth_date'])) {
            $birth = new DateTime($player_data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
            update_user_meta($player_user_id, 'age', $age);

            // 学年計算（4月基準）
            if (empty($player_data['grade'])) {
                $grade = aidunite_calculate_grade_from_birthdate($player_data['birth_date']);
                update_user_meta($player_user_id, 'player_grade', $grade);
                // 後方互換性のため旧キーも保存
                update_user_meta($player_user_id, 'grade', $grade);
            }
        }

        // 緊急連絡先情報保存
        if (!empty($emergency_contact_data)) {
            $emergency_contact_fields = [
                'emergency_contact_name' => $emergency_contact_data['emergency_contact_name'] ?? '',
                'emergency_contact_relationship' => $emergency_contact_data['emergency_contact_relationship'] ?? '',
                'emergency_contact_phone' => $emergency_contact_data['emergency_contact_phone'] ?? '',
                'emergency_contact_email' => $emergency_contact_data['emergency_contact_email'] ?? '',
                'emergency_contact_linked_date' => current_time('mysql')
            ];

            foreach ($emergency_contact_fields as $key => $value) {
                if (!empty($value)) {
                    update_user_meta($player_user_id, $key, $value);
                }
            }
        }

        // 既存ユーザーの場合、パスワードは変更しない（ユーザーが既に設定済み）
        $password = null;
        $username = $existing_user->user_login;
    } else {
        // 新規ユーザーの場合: アカウント作成
        $username = aidunite_generate_child_username($player_data['player_name'], $team_id);
        $temp_email = !empty($player_data['player_email'])
            ? (function_exists('aidunite_normalize_email') ? aidunite_normalize_email($player_data['player_email']) : sanitize_email($player_data['player_email']))
            : 'player_' . time() . '@temp.aidunite.local';

        // パスワード設定（カスタムまたは自動生成）
        if (!empty($player_data['password_option']) && $player_data['password_option'] === 'manual' && !empty($player_data['custom_password'])) {
            $password = $player_data['custom_password'];
        } else {
            $password = wp_generate_password(12, false);
        }

        $player_user_id = wp_create_user($username, $password, $temp_email);

        if (is_wp_error($player_user_id)) {
            return ['success' => false, 'message' => 'アカウント作成に失敗しました: ' . $player_user_id->get_error_message()];
        }

        // 選手情報設定
        wp_update_user([
            'ID' => $player_user_id,
            'display_name' => $player_data['nickname'] ?? $player_data['player_name'],
            'first_name' => $player_data['player_name'],
            'nickname' => $player_data['nickname'] ?? $player_data['player_name']
        ]);

        // 選手メタデータ保存
        $player_meta_fields = [
            'player_name' => $player_data['player_name'],
            'player_name_sei' => $player_data['player_name_sei'] ?? '',
            'player_name_mei' => $player_data['player_name_mei'] ?? '',
            'player_name_kana' => $player_data['player_name_kana'] ?? '',
            'player_kana_sei' => $player_data['player_kana_sei'] ?? '',
            'player_kana_mei' => $player_data['player_kana_mei'] ?? '',
            'player_birth_date' => $player_data['birth_date'] ?? '',
            'player_grade' => $player_data['grade'] ?? '',
            'player_position' => $player_data['position'] ?? '',
            'player_nickname' => $player_data['nickname'] ?? '',
            'player_club_team' => $player_data['club_team'] ?? '',
            'player_email' => $player_data['player_email'] ?? '',
            'player_height' => $player_data['height'] ?? '',
            'player_weight' => $player_data['weight'] ?? '',
            'jersey_number' => $player_data['jersey_number'] ?? '',
            'registered_by_team_leader' => $team_leader_id,
            'registration_date' => current_time('mysql'),
            'is_adult_player' => true
        ];

        // 後方互換性のため、旧メタキーも保存
        $legacy_meta_fields = [
            'birth_date' => $player_data['birth_date'] ?? '',
            'grade' => $player_data['grade'] ?? '',
            'position' => $player_data['position'] ?? '',
        ];

        foreach ($player_meta_fields as $key => $value) {
            update_user_meta($player_user_id, $key, $value);
        }

        foreach ($legacy_meta_fields as $key => $value) {
            if (!empty($value)) {
                update_user_meta($player_user_id, $key, $value);
            }
        }

        // 年齢・学年自動計算
        if (!empty($player_data['birth_date'])) {
            $birth = new DateTime($player_data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birth)->y;
            update_user_meta($player_user_id, 'age', $age);

            // 学年計算（4月基準）
            if (empty($player_data['grade'])) {
                $grade = aidunite_calculate_grade_from_birthdate($player_data['birth_date']);
                update_user_meta($player_user_id, 'player_grade', $grade);
                // 後方互換性のため旧キーも保存
                update_user_meta($player_user_id, 'grade', $grade);
            }
        }

        // 緊急連絡先情報保存
        if (!empty($emergency_contact_data)) {
            $emergency_contact_fields = [
                'emergency_contact_name' => $emergency_contact_data['emergency_contact_name'] ?? '',
                'emergency_contact_relationship' => $emergency_contact_data['emergency_contact_relationship'] ?? '',
                'emergency_contact_phone' => $emergency_contact_data['emergency_contact_phone'] ?? '',
                'emergency_contact_email' => $emergency_contact_data['emergency_contact_email'] ?? '',
                'emergency_contact_linked_date' => current_time('mysql')
            ];

            foreach ($emergency_contact_fields as $key => $value) {
                if (!empty($value)) {
                    update_user_meta($player_user_id, $key, $value);
                }
            }
        }

        // 選手設定
        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, false); // 成人選手
        aidunite_set_user_team($player_user_id, $team_id);
    }

    // 選手本人にメール通知（緊急連絡先がある場合は併せて通知）
    if (!empty($player_data['player_email'])) {
        aidunite_notify_adult_player_registration($player_data['player_email'], $player_data, $username, $password, $emergency_contact_data);
    }

    return [
        'success' => true,
        'player_id' => $player_user_id,
        'username' => $username,
        'password' => $password
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：成人選手登録通知
--------------------------------------------------------------*/
function aidunite_notify_adult_player_registration($player_email, $player_data, $username, $password, $emergency_contact_data = null) {
    $subject = '【AidUnite】アカウント登録完了（チーム登録）';

    $nickname = $player_data['nickname'] ?? $player_data['player_name'];
    $password_note = (!empty($player_data['password_option']) && $player_data['password_option'] === 'manual')
        ? '※チーム代表者が設定したパスワードです'
        : '※セキュリティのため、初回ログイン後にパスワードの変更をお勧めします';

    // 緊急連絡先情報セクション
    $emergency_contact_info = '';
    if (!empty($emergency_contact_data['emergency_contact_name'])) {
        $emergency_contact_info = "
■ 緊急連絡先情報
氏名：{$emergency_contact_data['emergency_contact_name']}
続柄：" . ($emergency_contact_data['emergency_contact_relationship'] ?? '') . "
電話番号：" . ($emergency_contact_data['emergency_contact_phone'] ?? '') . "
メールアドレス：" . ($emergency_contact_data['emergency_contact_email'] ?? '') . "

";
    }

    $message = "
{$player_data['player_name']} 様

あなたのアカウントがチーム代表者により登録されました。

■ ログイン情報
ユーザー名：{$username}
ニックネーム：{$nickname}
パスワード：{$password}

■ ログイン方法
以下のいずれかでログインできます：
- ユーザー名とパスワード
- ニックネームとパスワード

{$emergency_contact_info}■ 選手情報
氏名：{$player_data['player_name']}
フリガナ：" . ($player_data['player_name_kana'] ?? '') . "
生年月日：" . ($player_data['birth_date'] ?? '') . "
学年：" . ($player_data['grade'] ?? '') . "

{$password_note}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    return wp_mail($player_email, $subject, $message, $headers);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者アカウント作成
--------------------------------------------------------------*/
function aidunite_create_parent_account($parent_data, $team_id) {
    if (empty($parent_data['parent_email']) || empty($parent_data['parent_name'])) {
        return ['success' => false, 'message' => '保護者の必須情報が不足しています'];
    }

    $parent_email = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($parent_data['parent_email']) : sanitize_email($parent_data['parent_email']);

    // メールアドレス重複チェック
    if (email_exists($parent_email)) {
        return ['success' => false, 'message' => 'このメールアドレスは既に使用されています'];
    }

    // パスワード設定（カスタムまたは自動生成）
    if (!empty($parent_data['password_option']) && $parent_data['password_option'] === 'manual' && !empty($parent_data['custom_password'])) {
        $password = $parent_data['custom_password'];
    } else {
        $password = wp_generate_password(12, false);
    }

    // 保護者アカウント作成（メールは正規化済み）
    $parent_user_id = wp_create_user(
        $parent_email, // ユーザー名はメールアドレス
        $password,
        $parent_email
    );

    if (is_wp_error($parent_user_id)) {
        return ['success' => false, 'message' => '保護者アカウント作成に失敗しました: ' . $parent_user_id->get_error_message()];
    }

    // 保護者情報設定（共通メタ: 姓・名。parent_name のみの場合は first_name に、last_name は空）
    $parent_sei = $parent_data['parent_name_sei'] ?? '';
    $parent_mei = $parent_data['parent_name_mei'] ?? '';
    wp_update_user([
        'ID' => $parent_user_id,
        'display_name' => $parent_data['parent_name'],
        'first_name' => $parent_mei !== '' ? $parent_mei : $parent_data['parent_name'],
        'last_name' => $parent_sei,
        'nickname' => $parent_data['parent_name']
    ]);

    // 保護者メタデータ保存
    $parent_meta_fields = [
        'parent_name' => $parent_data['parent_name'],
        'parent_name_kana' => $parent_data['parent_name_kana'] ?? '',
        'parent_phone' => $parent_data['parent_phone'] ?? '',
        'parent_email' => $parent_data['parent_email'],
        'parent_relationship' => $parent_data['parent_relationship'] ?? '',
        'parent_emergency_contact' => $parent_data['parent_emergency_contact'] ?? '',
        'registration_date' => current_time('mysql')
    ];

    foreach ($parent_meta_fields as $key => $value) {
        if (!empty($value)) {
            update_user_meta($parent_user_id, $key, $value);
        }
    }

    // 保護者設定
    aidunite_set_user_type($parent_user_id, 'parent');
    aidunite_set_user_team($parent_user_id, $team_id);
    update_user_meta($parent_user_id, 'family_id', (string) $parent_user_id);

    return [
        'success' => true,
        'parent_id' => $parent_user_id,
        'password' => $password
    ];
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者通知（チーム代表者登録版）
--------------------------------------------------------------*/
function aidunite_notify_parent_child_registration_by_team($parent_email, $player_data, $username, $password, $parent_password = null) {
    $subject = '【AidUnite】お子様のアカウント登録完了（チーム登録）';

    $nickname = $player_data['nickname'] ?? $player_data['player_name'];
    $password_note = (!empty($player_data['password_option']) && $player_data['password_option'] === 'manual')
        ? '※チーム代表者が設定したパスワードです'
        : '※セキュリティのため、初回ログイン後にパスワードの変更をお勧めします';

    // 保護者アカウント情報セクション
    $parent_account_info = '';
    if (!empty($parent_password)) {
        $parent_password_note = (!empty($player_data['parent_password_option']) && $player_data['parent_password_option'] === 'manual')
            ? '※チーム代表者が設定したパスワードです'
            : '※セキュリティのため、初回ログイン後にパスワードの変更をお勧めします';

        $parent_account_info = "
■ 保護者アカウント情報
メールアドレス：{$parent_email}
パスワード：{$parent_password}
{$parent_password_note}

";
    }

    $message = "
保護者 様

お子様「{$player_data['player_name']}」のアカウントがチーム代表者により登録されました。

■ お子様のログイン情報
ユーザー名：{$username}
ニックネーム：{$nickname}
パスワード：{$password}

■ お子様のログイン方法
以下のいずれかでログインできます：
- ユーザー名とパスワード
- ニックネームとパスワード

{$parent_account_info}■ 選手情報
氏名：{$player_data['player_name']}
フリガナ：" . ($player_data['player_name_kana'] ?? '') . "
生年月日：" . ($player_data['birth_date'] ?? '') . "
学年：" . ($player_data['grade'] ?? '') . "

{$password_note}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    return wp_mail($parent_email, $subject, $message, $headers);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者通知
--------------------------------------------------------------*/
function aidunite_notify_parent_child_registration($parent_id, $child_id, $password) {
    $parent = get_userdata($parent_id);
    $child = get_userdata($child_id);

    if (!$parent || !$child) {
        return false;
    }

    $subject = '【AidUnite】お子様のアカウント登録完了';
    $message = "
{$parent->display_name} 様

お子様「{$child->display_name}」のアカウント登録が完了しました。

ログイン情報：
メールアドレス：{$child->user_email}
パスワード：{$password}

※セキュリティのため、初回ログイン後にパスワードの変更をお勧めします。

AidUnite運営チーム
";

    return wp_mail($parent->user_email, $subject, $message);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：複数チーム所属機能
--------------------------------------------------------------*/
function aidunite_add_user_to_multiple_teams($user_id, $team_id, $role_in_team = 'player') {
    if (!$user_id || !$team_id) {
        return false;
    }

    // 既存のチーム所属情報を取得
    $team_memberships = get_user_meta($user_id, 'team_memberships', true) ?: [];

    // 新しいチーム情報を追加
    $team_memberships[$team_id] = [
        'team_id' => $team_id,
        'role' => $role_in_team,
        'joined_date' => current_time('mysql'),
        'status' => 'active'
    ];

    update_user_meta($user_id, 'team_memberships', $team_memberships);

    // メタデータの更新を確実にするため、キャッシュをクリア
    clean_user_cache($user_id);
    wp_cache_flush();

    // プライマリチーム設定（初回のみ）
    if (!get_user_meta($user_id, 'primary_team_id', true)) {
        update_user_meta($user_id, 'primary_team_id', $team_id);
    }

    // 後方互換性のため既存のteam_idも更新
    if (!get_user_meta($user_id, 'team_id', true)) {
        update_user_meta($user_id, 'team_id', $team_id);
    }

    return true;
}

function aidunite_get_user_teams($user_id) {
    if (!$user_id) {
        return [];
    }

    // キャッシュをクリアして最新の値を取得
    clean_user_cache($user_id);

    $teams = get_user_meta($user_id, 'team_memberships', true) ?: [];
    return $teams;
}

function aidunite_set_primary_team($user_id, $team_id) {
    if (!$user_id || !$team_id) {
        return false;
    }

    $teams = aidunite_get_user_teams($user_id);
    if (!isset($teams[$team_id])) {
        return false; // 所属していないチームはプライマリに設定できない
    }

    update_user_meta($user_id, 'primary_team_id', $team_id);
    update_user_meta($user_id, 'team_id', $team_id); // 後方互換性

    return true;
}

function aidunite_get_primary_team($user_id) {
    if (!$user_id) {
        return null;
    }

    return get_user_meta($user_id, 'primary_team_id', true) ?: get_user_meta($user_id, 'team_id', true);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：学年計算機能
--------------------------------------------------------------*/
function aidunite_calculate_grade_from_birthdate($birth_date) {
    if (empty($birth_date)) {
        return '';
    }

    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $current_year = (int)$today->format('Y');
    $current_month = (int)$today->format('n');

    // 4月基準での学年計算
    $school_year = $current_year;
    if ($current_month < 4) {
        $school_year--;
    }

    $birth_year = (int)$birth->format('Y');
    $age_at_april = $school_year - $birth_year;

    // 学年判定（小学生想定）
    if ($age_at_april >= 6 && $age_at_april <= 11) {
        return '小学' . ($age_at_april - 5) . '年生';
    } elseif ($age_at_april >= 12 && $age_at_april <= 14) {
        return '中学' . ($age_at_april - 11) . '年生';
    } elseif ($age_at_april >= 15 && $age_at_april <= 17) {
        return '高校' . ($age_at_april - 14) . '年生';
    }

    return $age_at_april . '歳';
}

/*--------------------------------------------------------------
  AidUnite統一仕様：子供用ユーザー名生成
--------------------------------------------------------------*/
function aidunite_generate_child_username($child_name, $team_id) {
    // 日本語名をローマ字に変換（簡易版）
    $romanized = aidunite_simple_romanize($child_name);
    $base_username = sanitize_user($romanized . '_' . $team_id);

    // 重複チェック
    $counter = 1;
    $username = $base_username;
    while (username_exists($username)) {
        $username = $base_username . '_' . $counter;
        $counter++;
    }

    return $username;
}

function aidunite_simple_romanize($japanese_name) {
    // 簡易的なローマ字変換（実際の実装では適切なライブラリを使用）
    $kana_map = [
        'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
        'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
        'さ' => 'sa', 'し' => 'shi', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
        'た' => 'ta', 'ち' => 'chi', 'つ' => 'tsu', 'て' => 'te', 'と' => 'to',
        'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
        'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
        'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
        'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
        'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
        'わ' => 'wa', 'ん' => 'n'
    ];

    $romanized = strtr($japanese_name, $kana_map);

    // 漢字や変換できない文字は削除してプレフィックス追加
    $romanized = preg_replace('/[^\w]/', '', $romanized);

    return !empty($romanized) ? $romanized : 'player';
}

/*--------------------------------------------------------------
  AidUnite統一仕様：チーム代表者出欠通知
--------------------------------------------------------------*/
function aidunite_notify_team_leader_attendance($user_id, $attendance_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $schedule_id = get_post_meta($attendance_id, 'schedule_id', true);
    $child_id = get_post_meta($attendance_id, 'child_id', true);
    $status = get_post_meta($attendance_id, 'attendance_status', true);
    $note = get_post_meta($attendance_id, 'attendance_note', true);

    $child = get_userdata($child_id);
    $schedule = get_post($schedule_id);

    if (!$child || !$schedule) {
        return false;
    }

    $status_labels = [
        'attending' => '参加',
        'not_attending' => '不参加',
        'maybe' => '未定'
    ];

    $subject = '【AidUnite】出欠回答がありました';
    $message = "
{$user->display_name} 様

出欠回答がありました。

選手：{$child->display_name}
スケジュール：{$schedule->post_title}
回答：" . (isset($status_labels[$status]) ? $status_labels[$status] : $status) . "
メッセージ：{$note}

AidUnite運営チーム
";

    return wp_mail($user->user_email, $subject, $message);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者ダッシュボード情報取得（統一命名）
--------------------------------------------------------------*/
function aidunite_get_parent_dashboard_data($parent_id) {
    if (!$parent_id) {
        return [];
    }

    $children = aidunite_get_parent_children_list($parent_id);

    $dashboard_data = [
        'children' => $children,
        'upcoming_schedules' => [],
        'recent_attendance' => [],
        'team_info' => null
    ];

    // 子供の今月の予定を取得
    if (!empty($children)) {
        $current_month = date('Y-m');
        $date_range = [
            'start' => $current_month . '-01',
            'end' => $current_month . '-31'
        ];

        foreach ($children as $child) {
            $child_schedules = aidunite_get_child_schedules($child['user_id'], $date_range);
            $dashboard_data['upcoming_schedules'] = array_merge($dashboard_data['upcoming_schedules'], $child_schedules);
        }
    }

    // 最近の出欠回答を取得
    $recent_attendance = get_posts([
        'post_type' => 'attendance',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'author' => $parent_id,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_attendance as $attendance) {
        $schedule_id = get_post_meta($attendance->ID, 'schedule_id', true);
        $child_id = get_post_meta($attendance->ID, 'child_id', true);

        $schedule = get_post($schedule_id);
        $child = get_userdata($child_id);

        $dashboard_data['recent_attendance'][] = [
            'attendance_id' => $attendance->ID,
            'schedule_title' => $schedule ? $schedule->post_title : '',
            'child_name' => $child ? $child->display_name : '',
            'status' => get_post_meta($attendance->ID, 'attendance_status', true),
            'submitted_date' => get_post_meta($attendance->ID, 'attendance_date', true)
        ];
    }

    // チーム情報を取得
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $parent_id)
        : (int) get_user_meta($parent_id, 'team_id', true);
    if ($team_id) {
        $dashboard_data['team_info'] = aidunite_get_team_info($team_id);
    }

    return $dashboard_data;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_parent_dashboard_data() を使用してください
 */
function tunageru_get_parent_dashboard_data($parent_id) {
    return aidunite_get_parent_dashboard_data($parent_id);
}
