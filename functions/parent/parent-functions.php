<?php
/**
 * 保護者機能管理
 * AidUnite統一仕様対応版
 */

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者の子供取得（基本関数）
--------------------------------------------------------------*/
function aidunite_get_parent_children($parent_id) {
    if (!$parent_id) {
        return [];
    }

    $parent_id = (int) $parent_id;
    $children_list = [];
    $seen = [];

    if (function_exists('aidunite_parent_read_linked_child_user_ids')) {
        foreach (aidunite_parent_read_linked_child_user_ids($parent_id) as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id <= 0 || isset($seen[$user_id])) {
                continue;
            }
            $user = get_userdata($user_id);
            $team_id = function_exists('aidunite_get_current_team_id')
                ? (int) aidunite_get_current_team_id($user_id)
                : (int) get_user_meta($user_id, 'team_id', true);

            $children_list[] = [
                'user_id' => $user_id,
                'display_name' => $user ? (string) $user->display_name : '',
                'is_minor' => true,
                'team_id' => $team_id,
            ];
            $seen[$user_id] = true;
        }
    }

    // レガシー: player カスタム投稿タイプ
    $children = get_posts([
        'post_type' => 'player',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'parent_id',
                'value' => $parent_id,
                'compare' => '=',
            ],
        ],
    ]);

    foreach ($children as $child) {
        $user_id = (int) get_post_meta($child->ID, 'user_id', true);
        $team_id = (int) get_post_meta($child->ID, 'team_id', true);

        if ($user_id <= 0 || isset($seen[$user_id])) {
            continue;
        }

        $user = get_userdata($user_id);
        $children_list[] = [
            'user_id' => $user_id,
            'display_name' => $user ? (string) $user->display_name : '',
            'is_minor' => true,
            'team_id' => $team_id,
        ];
        $seen[$user_id] = true;
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
        $sch_par = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle((int) $schedule->ID)
            : [];
        $schedule_date = (string) ($sch_par['date'] ?? '');
        $schedule_start_time = (string) ($sch_par['start_time'] ?? '');
        $schedule_end_time = (string) ($sch_par['end_time'] ?? '');

        $schedule_list[] = [
            'schedule_id' => $schedule->ID,
            'date' => $schedule_date,
            'time' => $schedule_start_time . ($schedule_end_time ? ' - ' . $schedule_end_time : ''),
            'title' => $schedule->post_title,
            'place' => (string) ($sch_par['place'] ?? ''),
            'type' => (string) ($sch_par['schedule_type'] ?? ''),
            'note' => (string) ($sch_par['memo'] ?? ''),
            'matching' => in_array((string) ($sch_par['matching'] ?? '0'), ['1', 'true'], true),
        ];
    }

    return $schedule_list;
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
    aidunite_parent_persist_family_link($parent_id, $child_user_id);
}
}

/*--------------------------------------------------------------
  AidUnite統一仕様：保護者情報紐付け処理
--------------------------------------------------------------*/
function aidunite_link_parent_to_child($child_id, $parent_data) {
    if (!$child_id || !$parent_data) {
        return false;
    }

    return aidunite_parent_persist_contact_on_player($child_id, $parent_data);
}

/**
 * ログイン中の保護者自身の連絡先データ（子供登録時の紐付け用）
 *
 * @param int $parent_user_id
 * @return array<string, string>
 */
function aidunite_parent_build_self_contact_data($parent_user_id) {
    if (function_exists('aidunite_parent_read_self_contact_payload')) {
        return aidunite_parent_read_self_contact_payload($parent_user_id);
    }

    return [];
}

/**
 * 保護者によるお子様（選手）登録
 *
 * @param int                  $parent_user_id
 * @param int                  $team_id
 * @param array<string, mixed> $player_data
 * @return array<string, mixed>
 */
function aidunite_parent_register_child($parent_user_id, $team_id, array $player_data) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;

    if ($parent_user_id <= 0 || $team_id <= 0) {
        return ['success' => false, 'message' => '必要な情報が不足しています'];
    }

    if (function_exists('aidunite_get_user_role') && aidunite_get_user_role($parent_user_id) !== 'parent') {
        return ['success' => false, 'message' => '保護者のみが実行できます'];
    }

    if (function_exists('aidunite_parent_read_user_membership_status')) {
        $status = aidunite_parent_read_user_membership_status($parent_user_id, $team_id);
        if ($status !== 'active') {
            return ['success' => false, 'message' => 'チーム代表者の承認後にご利用いただけます'];
        }
    }

    $team_display = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_category = (string) ($team_display['team_category'] ?? '');
    if (
        !function_exists('aidunite_player_is_minor_team_category')
        || !aidunite_player_is_minor_team_category($team_category)
    ) {
        return [
            'success' => false,
            'message' => 'お子様の登録は小学生・中学生・高校生チームでのみ利用できます',
        ];
    }

    $parent_data = aidunite_parent_build_self_contact_data($parent_user_id);
    if (empty($parent_data['parent_email'])) {
        return ['success' => false, 'message' => '保護者のメールアドレスが登録されていません'];
    }

    $player_name = (string) ($player_data['player_name'] ?? '');
    if ($player_name === '') {
        return ['success' => false, 'message' => '選手名は必須です'];
    }

    $username = aidunite_generate_child_username($player_name, $team_id);
    $temp_email = 'player_' . time() . '_' . wp_generate_password(6, false) . '@temp.aidunite.local';
    $password = wp_generate_password(12, false);

    $player_user_id = wp_create_user($username, $password, $temp_email);
    if (is_wp_error($player_user_id)) {
        return [
            'success' => false,
            'message' => 'アカウント作成に失敗しました: ' . $player_user_id->get_error_message(),
        ];
    }

    wp_update_user([
        'ID' => $player_user_id,
        'display_name' => $player_data['nickname'] ?? $player_name,
        'first_name' => $player_name,
        'nickname' => $player_data['nickname'] ?? $player_name,
    ]);

    if (function_exists('aidunite_player_persist_registration_meta')) {
        aidunite_player_persist_registration_meta($player_user_id, $player_data, [
            'team_leader_id' => 0,
            'registered_by_parent_id' => $parent_user_id,
            'skip_empty' => false,
        ]);
    }

    aidunite_set_user_type($player_user_id, 'player');
    aidunite_set_minor_status($player_user_id, true);

    if (function_exists('aidunite_add_user_to_multiple_teams')) {
        aidunite_add_user_to_multiple_teams($player_user_id, $team_id, 'player');
    } else {
        aidunite_set_user_team($player_user_id, $team_id);
    }

    if (function_exists('aidunite_link_parent_and_player')) {
        aidunite_link_parent_and_player($parent_user_id, $player_user_id);
    }
    aidunite_link_parent_to_child($player_user_id, $parent_data);

    clean_user_cache($player_user_id);

    return [
        'success' => true,
        'player_id' => $player_user_id,
        'username' => $username,
        'password' => $password,
        'parent_id' => $parent_user_id,
        'message' => 'お子様の登録が完了しました。',
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

        aidunite_player_persist_registration_meta($player_user_id, $player_data, [
            'team_leader_id' => $team_leader_id,
            'skip_empty' => true,
            'is_adult' => true,
        ]);
        aidunite_player_persist_emergency_contact_meta($player_user_id, $emergency_contact_data, true);

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

        aidunite_player_persist_registration_meta($player_user_id, $player_data, [
            'team_leader_id' => $team_leader_id,
            'skip_empty' => false,
            'is_adult' => true,
        ]);
        aidunite_player_persist_emergency_contact_meta($player_user_id, $emergency_contact_data, true);

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
function aidunite_add_user_to_multiple_teams($user_id, $team_id, $role_in_team = 'player', $membership_status = 'active') {
    if (!$user_id || !$team_id) {
        return false;
    }

    $ok = aidunite_user_persist_add_team_membership(
        (int) $user_id,
        (int) $team_id,
        (string) $role_in_team,
        (string) $membership_status
    );
    if ($ok) {
        wp_cache_flush();
    }

    return $ok;
}

function aidunite_get_user_teams($user_id) {
    if (!$user_id) {
        return [];
    }

    clean_user_cache($user_id);

    return aidunite_user_read_team_memberships((int) $user_id);
}

function aidunite_set_primary_team($user_id, $team_id) {
    if (!$user_id || !$team_id) {
        return false;
    }

    return aidunite_user_persist_set_primary_team((int) $user_id, (int) $team_id);
}

function aidunite_get_primary_team($user_id) {
    if (!$user_id) {
        return null;
    }

    $primary = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary > 0) {
        return $primary;
    }

    return function_exists('aidunite_user_read_primary_team_id')
        ? aidunite_user_read_primary_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
}

/*--------------------------------------------------------------
  AidUnite統一仕様：学年計算機能
--------------------------------------------------------------*/
function aidunite_calculate_grade_from_birthdate($birth_date) {
    $birth_date = trim((string) $birth_date);
    if ($birth_date === '') {
        return '';
    }

    try {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
    } catch (Exception $e) {
        return '';
    }

    $current_year = (int) $today->format('Y');
    $current_month = (int) $today->format('n');

    // 現在の学年年度（4月1日始まり）
    $school_year = $current_year;
    if ($current_month < 4) {
        $school_year--;
    }

    // 当該年度の4月1日時点の満年齢
    $april1 = new DateTime($school_year . '-04-01');
    $age_at_april = (int) $april1->diff($birth)->y;

    if ($age_at_april < 6) {
        return '未就学';
    }
    if ($age_at_april >= 6 && $age_at_april <= 11) {
        return '小学' . ($age_at_april - 5) . '年生';
    }
    if ($age_at_april >= 12 && $age_at_april <= 14) {
        return '中学' . ($age_at_april - 11) . '年生';
    }
    if ($age_at_april >= 15 && $age_at_april <= 17) {
        return '高校' . ($age_at_april - 14) . '年生';
    }

    return '卒業';
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

    // 最近の出欠回答を取得（子ども分を集約）
    if (function_exists('aidunite_get_parent_recent_attendance')) {
        $dashboard_data['recent_attendance'] = aidunite_get_parent_recent_attendance((int) $parent_id);
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
