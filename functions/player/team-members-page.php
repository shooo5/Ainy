<?php
/**
 * /team-members ページ用コンテキスト（代表者・保護者）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ページ操作者（administrator|team_leader|parent|''）
 *
 * @param int $user_id
 * @param int $team_id
 * @return string
 */
function aidunite_team_members_resolve_actor($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0) {
        return '';
    }

    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return 'administrator';
    }

    if ($team_id > 0 && function_exists('aidunite_player_resolve_registration_actor')) {
        $actor = (string) aidunite_player_resolve_registration_actor($user_id, $team_id);
        if ($actor === 'parent' || $actor === 'team_leader') {
            return $actor;
        }
    }

    if ($team_id > 0 && function_exists('aidunite_team_settings_user_is_leader_of_team')
        && aidunite_team_settings_user_is_leader_of_team($user_id, $team_id)) {
        return 'team_leader';
    }

    if (function_exists('aidunite_is_team_leader') && aidunite_is_team_leader($user_id)) {
        return 'team_leader';
    }

    return '';
}

/**
 * 保護者が編集可能な選手か
 *
 * @param int $parent_id
 * @param int $player_id
 * @param int $team_id
 * @return bool
 */
function aidunite_team_members_parent_can_manage_player($parent_id, $player_id, $team_id) {
    $parent_id = (int) $parent_id;
    $player_id = (int) $player_id;
    $team_id = (int) $team_id;
    if ($parent_id <= 0 || $player_id <= 0 || $team_id <= 0) {
        return false;
    }

    if (aidunite_player_read_linked_parent_id($player_id) !== $parent_id) {
        return false;
    }

    $player_team_id = (int) aidunite_player_read_team_id($player_id);

    return $player_team_id === $team_id;
}

/**
 * 保護者の登録済みお子様 ID（チーム内）
 *
 * @param int $parent_id
 * @param int $team_id
 * @return int[]
 */
function aidunite_team_members_get_parent_child_ids($parent_id, $team_id) {
    $parent_id = (int) $parent_id;
    $team_id = (int) $team_id;
    if ($parent_id <= 0 || $team_id <= 0 || !function_exists('aidunite_parent_read_linked_child_user_ids')) {
        return [];
    }

    $child_ids = [];
    foreach (aidunite_parent_read_linked_child_user_ids($parent_id) as $player_id) {
        $player_id = (int) $player_id;
        if ($player_id <= 0 || !aidunite_team_members_parent_can_manage_player($parent_id, $player_id, $team_id)) {
            continue;
        }
        $child_ids[] = $player_id;
    }

    return $child_ids;
}

/**
 * チーム情報カード用チップ
 *
 * @param int $team_id
 * @return array<int, array{label:string,value:string}>
 */
function aidunite_team_members_build_team_chips($team_id) {
    $team_id = (int) $team_id;
    $bundle = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];

    if ($bundle === []) {
        return [];
    }

    $chips = [];
    $sport = (string) ($bundle['team_sport'] ?? $bundle['sport_type'] ?? '');
    if ($sport !== '') {
        $chips[] = ['label' => '種目', 'value' => $sport];
    }

    $category = (string) ($bundle['team_category'] ?? '');
    if ($category !== '') {
        $chips[] = ['label' => '対象', 'value' => $category];
    }

    $region = (string) ($bundle['region'] ?? '');
    if ($region !== '' && $region !== '地域未設定') {
        $chips[] = ['label' => '地域', 'value' => $region];
    }

    $gender = (string) ($bundle['team_gender_option'] ?? '');
    if ($gender !== '') {
        $gender_label = function_exists('aidunite_admin_list_format_gender_display')
            ? (string) aidunite_admin_list_format_gender_display($gender)
            : $gender;
        if ($gender_label !== '') {
            $chips[] = ['label' => '性別', 'value' => $gender_label];
        }
    }

    $team_type = (string) ($bundle['team_type'] ?? '');
    if ($team_type !== '') {
        $type_label = function_exists('aidunite_team_type_label')
            ? (string) aidunite_team_type_label($team_type)
            : $team_type;
        if ($type_label !== '') {
            $chips[] = ['label' => '種別', 'value' => $type_label];
        }
    }

    return $chips;
}

/**
 * 一覧用学年（末尾の「生」を除く。例: 中学2年生 → 中学2年）
 *
 * @param mixed $grade
 * @return string
 */
function aidunite_team_members_format_grade_list_display($grade) {
    $grade = trim((string) $grade);
    if ($grade === '') {
        return '';
    }

    return (string) preg_replace('/生$/u', '', $grade);
}

/**
 * 学年ソート用ランク（値が大きいほど学年が高い）
 *
 * @param mixed $grade
 * @return int
 */
function aidunite_team_members_grade_sort_rank($grade) {
    $grade = trim((string) $grade);
    if ($grade === '') {
        return -1;
    }
    if ($grade === '卒業') {
        return 100;
    }
    if ($grade === '未就学') {
        return 0;
    }

    $normalized = (string) preg_replace('/生$/u', '', $grade);
    if (preg_match('/^(小学|中学|高校)(\d+)年$/u', $normalized, $matches)) {
        $bases = [
            '小学' => 10,
            '中学' => 20,
            '高校' => 30,
        ];

        return ($bases[$matches[1]] ?? 0) + (int) $matches[2];
    }

    return -1;
}

/**
 * 選手一覧を学年の高い順に並べ替え
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function aidunite_team_members_sort_rows_by_grade_desc(array $rows) {
    usort($rows, static function ($a, $b) {
        $rank_a = aidunite_team_members_grade_sort_rank($a['player_grade'] ?? '');
        $rank_b = aidunite_team_members_grade_sort_rank($b['player_grade'] ?? '');
        if ($rank_a !== $rank_b) {
            return $rank_b <=> $rank_a;
        }

        $birth_a = (string) ($a['player_birth_date'] ?? '');
        $birth_b = (string) ($b['player_birth_date'] ?? '');
        if ($birth_a !== $birth_b) {
            return strcmp($birth_a, $birth_b);
        }

        $sei_cmp = strcmp((string) ($a['player_name_sei'] ?? ''), (string) ($b['player_name_sei'] ?? ''));
        if ($sei_cmp !== 0) {
            return $sei_cmp;
        }

        return strcmp((string) ($a['player_name_mei'] ?? ''), (string) ($b['player_name_mei'] ?? ''));
    });

    return $rows;
}

/**
 * 身長表示用（数字のみ）
 *
 * @param mixed $height
 * @return string
 */
function aidunite_team_members_format_height_digits($height) {
    $height = trim((string) $height);
    if ($height === '') {
        return '';
    }

    if (preg_match('/(\d+(?:\.\d+)?)/', $height, $matches)) {
        return (string) $matches[1];
    }

    return '';
}

/**
 * 一覧行データ
 *
 * @param int $player_id
 * @return array<string, mixed>
 */
function aidunite_team_members_build_player_row($player_id) {
    $player_id = (int) $player_id;
    $row = function_exists('aidunite_player_get_list_row_display')
        ? aidunite_player_get_list_row_display($player_id)
        : [];

    if ($row === []) {
        return [];
    }

    $position = (string) ($row['player_position'] ?? '');
    $default_logo = get_template_directory_uri() . '/images/default-team-logo.png';

    return array_merge($row, [
        'user_id' => $player_id,
        'avatar_url' => aidunite_player_read_photo_display_url($player_id, 80),
        'player_height_digits' => aidunite_team_members_format_height_digits($row['player_height'] ?? ''),
        'player_grade_list' => aidunite_team_members_format_grade_list_display($row['player_grade'] ?? ''),
        'edit_url' => home_url('/edit-player?player_id=' . $player_id),
        'detail_url' => home_url('/edit-player?player_id=' . $player_id),
    ]);
}

/**
 * list_tab クエリを正規化（旧 parent_tab=pending は保護者一覧・承認待ちへ）
 *
 * @param array<string, mixed> $query
 * @return array{list_tab:string,parent_tab:string}
 */
function aidunite_team_members_resolve_list_tabs(array $query = []) {
    $list_tab = isset($query['list_tab']) ? sanitize_key((string) $query['list_tab']) : '';
    $parent_tab = isset($query['parent_tab']) ? sanitize_key((string) $query['parent_tab']) : '';

    if ($list_tab === '' && $parent_tab === 'pending') {
        $list_tab = 'parents';
    }
    if ($list_tab !== 'parents') {
        $list_tab = 'members';
    }
    if ($parent_tab !== 'pending') {
        $parent_tab = 'registered';
    }

    return [
        'list_tab' => $list_tab,
        'parent_tab' => $parent_tab,
    ];
}

/**
 * 保護者氏名（性・名）を分解
 *
 * @param WP_User|null         $user
 * @param array<string, mixed> $profile
 * @return array{sei:string,mei:string}
 */
function aidunite_team_members_format_parent_name_parts($user, array $profile) {
    $sei = trim((string) ($profile['parent_name_sei'] ?? ''));
    $mei = trim((string) ($profile['parent_name_mei'] ?? ''));

    if ($user instanceof WP_User) {
        if ($sei === '') {
            $sei = trim((string) $user->last_name);
        }
        if ($mei === '') {
            $mei = trim((string) $user->first_name);
        }
        if ($sei === '' && $mei === '') {
            $display_name = trim((string) $user->display_name);
            if ($display_name !== '') {
                $parts = preg_split('/\s+/u', $display_name, 2);
                $sei = trim((string) ($parts[0] ?? ''));
                $mei = trim((string) ($parts[1] ?? ''));
            }
        }
    }

    return [
        'sei' => $sei,
        'mei' => $mei,
    ];
}

/**
 * 保護者フリガナ（セイ・メイ）を分解
 *
 * @param array<string, mixed> $profile
 * @return array{kana_sei:string,kana_mei:string}
 */
function aidunite_team_members_format_parent_kana_parts(array $profile) {
    $kana_sei = trim((string) ($profile['parent_kana_sei'] ?? ''));
    $kana_mei = trim((string) ($profile['parent_kana_mei'] ?? ''));

    if ($kana_sei === '' && $kana_mei === '') {
        $full_kana = trim((string) ($profile['parent_kana'] ?? ''));
        if ($full_kana === '') {
            $full_kana = trim((string) ($profile['parent_name_kana'] ?? ''));
        }
        if ($full_kana !== '') {
            $parts = preg_split('/\s+/u', $full_kana, 2);
            $kana_sei = trim((string) ($parts[0] ?? ''));
            $kana_mei = trim((string) ($parts[1] ?? ''));
        }
    }

    return [
        'kana_sei' => $kana_sei,
        'kana_mei' => $kana_mei,
    ];
}

/**
 * 保護者一覧行（チーム内 active）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_members_build_parent_row($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return [];
    }

    $user = get_userdata($parent_user_id);
    if (!$user) {
        return [];
    }

    $profile = function_exists('aidunite_parent_read_guardian_profile_fields')
        ? aidunite_parent_read_guardian_profile_fields($parent_user_id)
        : [];

    $linked_child_ids = aidunite_team_members_get_parent_child_ids($parent_user_id, $team_id);
    $linked_labels = [];
    foreach ($linked_child_ids as $child_id) {
        $child_row = function_exists('aidunite_player_get_list_row_display')
            ? aidunite_player_get_list_row_display((int) $child_id)
            : [];
        $label = trim((string) (($child_row['player_name_sei'] ?? '') . ' ' . ($child_row['player_name_mei'] ?? '')));
        if ($label === '') {
            $label = (string) ($child_row['player_name'] ?? '');
        }
        if ($label !== '') {
            $linked_labels[] = $label;
        }
    }

    $invite_type = (string) ($profile['guardian_invite_type'] ?? '');
    $invite_label = function_exists('aidunite_parent_read_invite_type_label')
        ? aidunite_parent_read_invite_type_label($invite_type)
        : '—';

    $name_parts = aidunite_team_members_format_parent_name_parts($user, $profile);
    $kana_parts = aidunite_team_members_format_parent_kana_parts($profile);
    $parent_email = trim((string) $user->user_email);
    $has_email = $parent_email !== '';

    $tuition_column_enabled = function_exists('aidunite_payment_team_tuition_open_for_parents')
        && aidunite_payment_team_tuition_open_for_parents($team_id);
    $tuition_registered = $tuition_column_enabled
        && function_exists('aidunite_payment_parent_has_tuition_subscription')
        && aidunite_payment_parent_has_tuition_subscription($parent_user_id, $team_id);

    $registered_at = (string) ($profile['registration_date'] ?? '');
    $parent_phone = (string) ($profile['parent_phone'] ?? '');

    return [
        'user_id' => $parent_user_id,
        'display_name' => (string) $user->display_name,
        'parent_name_sei' => $name_parts['sei'],
        'parent_name_mei' => $name_parts['mei'],
        'parent_kana_sei' => $kana_parts['kana_sei'],
        'parent_kana_mei' => $kana_parts['kana_mei'],
        'parent_kana' => trim($kana_parts['kana_sei'] . ' ' . $kana_parts['kana_mei']),
        'parent_email' => $parent_email,
        'has_email' => $has_email,
        'email_mark' => $has_email ? '○' : '—',
        'parent_phone' => $parent_phone,
        'linked_child_count' => count($linked_child_ids),
        'linked_child_labels' => $linked_labels,
        'linked_child_summary' => $linked_labels !== [] ? implode('、', $linked_labels) : '—',
        'tuition_column_enabled' => $tuition_column_enabled,
        'tuition_registered' => $tuition_registered,
        'tuition_payment_mark' => !$tuition_column_enabled ? '—' : ($tuition_registered ? 'あり' : '—'),
        'registered_at' => $registered_at,
        'invite_type' => $invite_type,
        'invite_type_label' => $invite_label,
        'status' => 'active',
        'detail' => [
            'parent_name_sei' => $name_parts['sei'],
            'parent_name_mei' => $name_parts['mei'],
            'parent_kana_sei' => $kana_parts['kana_sei'],
            'parent_kana_mei' => $kana_parts['kana_mei'],
            'parent_email' => $parent_email,
            'parent_phone' => $parent_phone,
            'linked_child_summary' => $linked_labels !== [] ? implode('、', $linked_labels) : '—',
            'tuition_payment_label' => !$tuition_column_enabled
                ? '—'
                : ($tuition_registered ? '登録済み' : '未登録'),
            'invite_type_label' => $invite_label,
            'registered_at' => $registered_at !== '' ? $registered_at : '—',
        ],
    ];
}

/**
 * /team-members 表示コンテキスト
 *
 * @param int                  $user_id
 * @param array<string, mixed> $query
 * @return array<string, mixed>
 */
function aidunite_team_members_get_page_context($user_id, array $query = []) {
    $user_id = (int) $user_id;
    $is_admin = function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($user_id);

    $team_id = $is_admin
        ? (int) aidunite_user_resolve_admin_team_id($user_id, $query)
        : (int) aidunite_player_read_leader_team_id($user_id);

    $actor = aidunite_team_members_resolve_actor($user_id, $team_id);

    if ($actor === '' && !$is_admin) {
        return [
            'ok' => false,
            'redirect' => home_url('/mypage'),
            'actor' => '',
            'team_id' => $team_id,
            'rows' => [],
        ];
    }

    if ($team_id <= 0) {
        return [
            'ok' => false,
            'redirect' => $is_admin
                ? ''
                : home_url('/mypage'),
            'actor' => $actor,
            'team_id' => 0,
            'rows' => [],
            'error_message' => $is_admin
                ? '閲覧可能なチームが見つかりません。URL に ?team_id= を指定してください。'
                : 'チーム情報が見つかりません。',
        ];
    }

    $is_parent_view = ($actor === 'parent');
    $tabs = aidunite_team_members_resolve_list_tabs($query);
    $list_tab = $is_parent_view ? 'members' : $tabs['list_tab'];
    $parent_tab = $is_parent_view ? 'registered' : $tabs['parent_tab'];
    $rows = [];
    $parent_rows = [];

    if ($is_parent_view) {
        foreach (aidunite_team_members_get_parent_child_ids($user_id, $team_id) as $player_id) {
            $built = aidunite_team_members_build_player_row($player_id);
            if ($built !== []) {
                $rows[] = $built;
            }
        }
    } else {
        $players = function_exists('aidunite_get_team_members')
            ? aidunite_get_team_members($team_id, 'player')
            : [];
        foreach ($players as $player) {
            $player_id = is_object($player) ? (int) $player->ID : (int) ($player['user_id'] ?? 0);
            if ($player_id <= 0) {
                continue;
            }
            $built = aidunite_team_members_build_player_row($player_id);
            if ($built !== []) {
                $rows[] = $built;
            }
        }
    }

    $parent_counts = ['active' => 0, 'pending' => 0];
    if (!$is_parent_view && function_exists('aidunite_parent_read_parent_counts')) {
        $parent_counts = aidunite_parent_read_parent_counts($team_id);
        if ($list_tab === 'parents' && $parent_tab === 'registered' && function_exists('aidunite_parent_read_team_parent_rows')) {
            foreach (aidunite_parent_read_team_parent_rows($team_id, 'active') as $parent_row) {
                $parent_user_id = (int) ($parent_row['user_id'] ?? 0);
                $built = aidunite_team_members_build_parent_row($parent_user_id, $team_id);
                if ($built !== []) {
                    $parent_rows[] = $built;
                }
            }
            usort($parent_rows, static function ($a, $b) {
                $sei_cmp = strcmp((string) ($a['parent_name_sei'] ?? ''), (string) ($b['parent_name_sei'] ?? ''));
                if ($sei_cmp !== 0) {
                    return $sei_cmp;
                }
                $mei_cmp = strcmp((string) ($a['parent_name_mei'] ?? ''), (string) ($b['parent_name_mei'] ?? ''));
                if ($mei_cmp !== 0) {
                    return $mei_cmp;
                }

                return strcmp((string) ($a['parent_email'] ?? ''), (string) ($b['parent_email'] ?? ''));
            });
        }
    }

    $rows = aidunite_team_members_sort_rows_by_grade_desc($rows);

    $team_display = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_name = (string) ($team_display['team_name'] ?? 'チーム');
    $default_logo = get_template_directory_uri() . '/images/default-team-logo.png';
    $team_logo = (string) ($team_display['team_logo'] ?? '');
    if ($team_logo === '') {
        $team_logo = $default_logo;
    }

    $theme_key = function_exists('aidunite_get_team_ui_theme_key')
        ? (string) aidunite_get_team_ui_theme_key($team_id)
        : 'boys';
    if (!in_array($theme_key, ['boys', 'girls'], true)) {
        $theme_key = 'boys';
    }

    $is_parents_list = !$is_parent_view && $list_tab === 'parents';
    $is_parent_pending_tab = $is_parents_list && $parent_tab === 'pending';
    $player_count = count($rows);

    $page_title = 'お子さまの一覧（保護者）';
    $add_label = 'お子さまを追加';
    $add_url = home_url('/player-add');
    $empty_message = '登録されているお子さまはいません。';

    if (!$is_parent_view) {
        if ($is_parent_pending_tab) {
            $page_title = '保護者の承認待ち';
            $add_label = '保護者を招待';
            $add_url = add_query_arg(
                'from',
                'team-members',
                function_exists('aidunite_get_invite_guardian_page_url')
                    ? aidunite_get_invite_guardian_page_url()
                    : home_url('/invite-guardian')
            );
            $empty_message = '承認待ちの保護者はいません。';
        } elseif ($is_parents_list) {
            $page_title = '保護者一覧';
            $add_label = '保護者を招待';
            $add_url = add_query_arg(
                'from',
                'team-members',
                function_exists('aidunite_get_invite_guardian_page_url')
                    ? aidunite_get_invite_guardian_page_url()
                    : home_url('/invite-guardian')
            );
            $empty_message = '登録されている保護者はいません。';
        } else {
            $page_title = '選手登録一覧';
            $add_label = '選手を追加';
            $add_url = home_url('/player-add');
            $empty_message = '登録されている選手はいません。';
        }
    }

    return [
        'ok' => true,
        'redirect' => '',
        'actor' => $actor,
        'is_parent_view' => $is_parent_view,
        'is_leader_view' => !$is_parent_view,
        'team_id' => $team_id,
        'team_name' => $team_name,
        'team_logo' => $team_logo,
        'team_chips' => aidunite_team_members_build_team_chips($team_id),
        'team_chips_compact' => $is_parent_view,
        'theme_key' => $theme_key,
        'list_tab' => $list_tab,
        'parent_tab' => $parent_tab,
        'rows' => $rows,
        'parent_rows' => $parent_rows,
        'parent_counts' => $parent_counts,
        'tuition_payment_column_enabled' => function_exists('aidunite_payment_team_tuition_open_for_parents')
            && aidunite_payment_team_tuition_open_for_parents($team_id),
        'player_count' => $player_count,
        'can_delete' => !$is_parent_view && !$is_parents_list,
        'can_export' => !$is_parent_view && !$is_parents_list,
        'show_nickname_column' => $is_parent_view,
        'show_avatar_column' => true,
        'show_parent_column' => !$is_parent_view && !$is_parents_list,
        'show_leader_tabs' => !$is_parent_view,
        'page_title' => $page_title,
        'role_badge' => $is_parent_view ? '保護者' : 'チーム代表者',
        'add_label' => $add_label,
        'add_url' => $add_url,
        'invite_guardian_url' => function_exists('aidunite_get_invite_guardian_page_url')
            ? aidunite_get_invite_guardian_page_url()
            : home_url('/invite-guardian'),
        'player_column_label' => $is_parent_view ? 'お子さま' : '選手',
        'empty_message' => $empty_message,
        'footer_note' => $is_parent_view
            ? '※ 保護者の方は、ご自身のお子さまのみ表示されます。'
            : ($is_parents_list ? '※ 招待メールで登録した保護者は本登録完了後に「登録済み」へ表示されます。QRからの参加申請は承認後に表示されます。' : ''),
    ];
}
