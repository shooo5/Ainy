<?php
/**
 * 管理者用スケジュール一覧（データ表形式）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * intent の表示ラベル
 */
/**
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_admin_schedule_list_canonical($schedule_id) {
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle((int) $schedule_id);
        if ($bundle !== []) {
            return $bundle;
        }
    }
    if (function_exists('aidunite_schedule_get_canonical_meta')) {
        $c = aidunite_schedule_get_canonical_meta((int) $schedule_id);
        if ($c !== []) {
            return [
                'team_id' => (int) ($c['team_id'] ?? 0),
                'date' => (string) ($c['date'] ?? ''),
                'start_time' => (string) ($c['start_time'] ?? ''),
                'end_time' => (string) ($c['end_time'] ?? ''),
                'schedule_type' => (string) ($c['schedule_type'] ?? ''),
                'intent' => (string) ($c['intent'] ?? ''),
                'gender' => (string) ($c['schedule_gender'] ?? ''),
                'matching' => (string) ($c['matching'] ?? '0'),
                'place' => (string) ($c['schedule_place'] ?? ''),
                'venue_name' => (string) ($c['venue_name'] ?? ''),
            ];
        }
    }

    return [];
}

function aidunite_admin_schedule_list_intent_label($intent) {
    if (function_exists('aidunite_schedule_intent_label')) {
        return aidunite_schedule_intent_label($intent);
    }
    return (string) $intent !== '' ? (string) $intent : '—';
}

/**
 * 登録時の会場スナップショットを保存（未保存の schedule のみ。成立後の home/away 変更では上書きしない）
 *
 * @param int    $schedule_id
 * @param string $place       schedule_place 相当（home / away / either 等）
 * @param string $venue_name  会場名（任意）
 */
function aidunite_schedule_save_registration_venue_snapshot($schedule_id, $place = '', $venue_name = '') {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return;
    }
    if (metadata_exists('post', $schedule_id, 'schedule_place_at_registration')) {
        return;
    }

    $place = sanitize_text_field((string) $place);
    $venue_name = sanitize_text_field((string) $venue_name);
    if ($place === '' || $venue_name === '') {
        $canonical = aidunite_admin_schedule_list_canonical($schedule_id);
        if ($place === '') {
            $place = (string) ($canonical['place'] ?? '');
        }
        if ($venue_name === '') {
            $venue_name = (string) ($canonical['venue_name'] ?? '');
        }
    }

    update_post_meta($schedule_id, 'schedule_place_at_registration', $place);
    update_post_meta($schedule_id, 'venue_name_at_registration', $venue_name);
}

/**
 * 管理者一覧用: 登録時会場の表示文字列
 *
 * @param int $schedule_id
 * @return string
 */
function aidunite_admin_schedule_list_registration_place_display($schedule_id) {
    if (!function_exists('aidunite_admin_list_get_schedule_venue_parts')) {
        return '—';
    }
    $parts = aidunite_admin_list_get_schedule_venue_parts($schedule_id);

    return $parts['place_label'];
}

/**
 * フィルター選択肢
 *
 * @param string[] $schedule_types 動的に収集した schedule_type
 * @return array<string, array{label:string, options:array<string,string>}>
 */
function aidunite_admin_schedule_list_get_filter_groups(array $schedule_types = []) {
    $type_options = ['' => 'すべて'];
    foreach ($schedule_types as $type) {
        $type = (string) $type;
        if ($type !== '') {
            $type_options[$type] = $type;
        }
    }

    return [
        'intent_filter' => [
            'label' => '目的（intent）',
            'options' => [
                '' => 'すべて',
                'confirmed' => '確定',
                'recruit' => 'マッチ募集',
                'tentative' => '仮押さえ',
            ],
        ],
        'post_status_filter' => [
            'label' => '投稿状態',
            'options' => [
                '' => 'すべて',
                'publish' => '公開',
                'pending' => '承認待ち',
                'draft' => '下書き',
                'private' => '非公開',
                'trash' => 'ゴミ箱',
            ],
        ],
        'type_filter' => [
            'label' => '種別',
            'options' => $type_options,
        ],
    ];
}

/**
 * チーム名フィルター用プルダウン（`aidunite_team_management_get_team_name_filter_options` のエイリアス）
 *
 * @return array<string, string>
 */
function aidunite_admin_schedule_list_get_team_filter_options() {
    if (function_exists('aidunite_team_management_get_team_name_filter_options')) {
        return aidunite_team_management_get_team_name_filter_options();
    }

    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'pending', 'draft'],
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    $options = ['' => 'すべて'];
    foreach ($teams as $team) {
        $options[(string) $team->ID] = $team->post_title . '（ID:' . $team->ID . '）';
    }

    return $options;
}

/**
 * @return array{team_filter:string,date_from:string,date_to:string,intent_filter:string,post_status_filter:string,type_filter:string}
 */
function aidunite_admin_schedule_list_get_filters_from_request(array $schedule_types = []) {
    $groups = aidunite_admin_schedule_list_get_filter_groups($schedule_types);
    $team_options = aidunite_admin_schedule_list_get_team_filter_options();
    $team_filter_raw = isset($_GET['team_filter']) ? sanitize_text_field(wp_unslash($_GET['team_filter'])) : '';
    if ($team_filter_raw === '' && isset($_GET['team_id_filter'])) {
        $team_filter_raw = sanitize_text_field(wp_unslash($_GET['team_id_filter']));
    }
    $filters = [
        'team_filter' => array_key_exists($team_filter_raw, $team_options) ? $team_filter_raw : '',
        'date_from' => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
        'date_to' => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
    ];

    foreach ($groups as $key => $group) {
        $raw = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $filters[$key] = array_key_exists($raw, $group['options']) ? $raw : '';
    }

    return $filters;
}

/**
 * @param array $filters
 */
function aidunite_admin_schedule_list_has_active_filters(array $filters) {
    if (($filters['team_filter'] ?? '') !== '') {
        return true;
    }
    if (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') {
        return true;
    }
    foreach (aidunite_admin_schedule_list_get_filter_groups() as $key => $group) {
        if (($filters[$key] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

/**
 * @param array $filters
 * @return array<string, string>
 */
function aidunite_admin_schedule_list_filters_to_query_args(array $filters) {
    $args = [];
    foreach ($filters as $key => $val) {
        if ($val !== '' && $val !== null) {
            $args[$key] = (string) $val;
        }
    }

    return $args;
}

/**
 * @param array $filters
 * @return array<string, string>
 */
function aidunite_admin_schedule_list_get_active_filter_labels(array $filters) {
    $active = [];
    $team_filter = $filters['team_filter'] ?? '';
    if ($team_filter !== '') {
        $team_options = aidunite_admin_schedule_list_get_team_filter_options();
        $active['チーム名'] = $team_options[$team_filter] ?? ('ID ' . $team_filter);
    }
    if (($filters['date_from'] ?? '') !== '') {
        $active['日付（から）'] = $filters['date_from'];
    }
    if (($filters['date_to'] ?? '') !== '') {
        $active['日付（まで）'] = $filters['date_to'];
    }

    foreach (aidunite_admin_schedule_list_get_filter_groups() as $key => $group) {
        $val = $filters[$key] ?? '';
        if ($val !== '' && isset($group['options'][$val])) {
            $active[$group['label']] = $group['options'][$val];
        }
    }

    return $active;
}

/**
 * 登録済み schedule_type の一覧（フィルター用）
 *
 * @return string[]
 */
function aidunite_admin_schedule_list_collect_schedule_types() {
    global $wpdb;
    $sql = "
        SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = 'schedule'
          AND pm.meta_key = 'schedule_type'
          AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
        LIMIT 200
    ";

    $values = $wpdb->get_col($sql);
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $values)));
}

/**
 * @param WP_Post $post
 * @param array   $filters
 */
function aidunite_admin_schedule_list_matches_filters(WP_Post $post, array $filters) {
    $schedule_id = (int) $post->ID;
    $canonical = aidunite_admin_schedule_list_canonical($schedule_id);

    $team_filter = $filters['team_filter'] ?? '';
    if ($team_filter !== '') {
        $team_id = (int) ($canonical['team_id'] ?? (function_exists('aidunite_schedule_read_team_id') ? aidunite_schedule_read_team_id($schedule_id) : 0));
        if ((string) $team_id !== (string) (int) $team_filter) {
            return false;
        }
    }

    $schedule_date = (string) ($canonical['date'] ?? (function_exists('aidunite_schedule_read_normalized_date') ? aidunite_schedule_read_normalized_date($schedule_id) : ''));
    $date_from = $filters['date_from'] ?? '';
    if ($date_from !== '' && ($schedule_date === '' || $schedule_date < $date_from)) {
        return false;
    }
    $date_to = $filters['date_to'] ?? '';
    if ($date_to !== '' && ($schedule_date === '' || $schedule_date > $date_to)) {
        return false;
    }

    $intent_filter = $filters['intent_filter'] ?? '';
    $intent = (string) ($canonical['intent'] ?? (function_exists('aidunite_schedule_read_intent') ? aidunite_schedule_read_intent($schedule_id) : ''));
    if ($intent_filter !== '' && $intent !== $intent_filter) {
        return false;
    }

    $post_status_filter = $filters['post_status_filter'] ?? '';
    if ($post_status_filter !== '' && $post->post_status !== $post_status_filter) {
        return false;
    }

    $type_filter = $filters['type_filter'] ?? '';
    $schedule_type = (string) ($canonical['schedule_type'] ?? '');
    if ($type_filter !== '' && $schedule_type !== $type_filter) {
        return false;
    }

    return true;
}

/**
 * 一覧行・CSV用データ
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_admin_schedule_list_row_data($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $post = get_post($schedule_id);
    if (!$post || $post->post_type !== 'schedule') {
        return [];
    }

    $canonical = aidunite_admin_schedule_list_canonical($schedule_id);

    $team_id = (int) ($canonical['team_id'] ?? (function_exists('aidunite_schedule_read_team_id') ? aidunite_schedule_read_team_id($schedule_id) : 0));
    $team_name = '—';
    if ($team_id > 0) {
        $team_post = get_post($team_id);
        $team_name = $team_post ? $team_post->post_title : ('ID:' . $team_id);
    }

    $start = (string) ($canonical['start_time'] ?? '');
    $end = (string) ($canonical['end_time'] ?? '');
    $legacy_time = (string) ($canonical['legacy_time'] ?? '');
    if ($start !== '' && $end !== '') {
        $time_display = $start . '〜' . $end;
    } elseif ($legacy_time !== '') {
        $time_display = $legacy_time;
    } else {
        $time_display = '—';
    }

    $venue_parts = function_exists('aidunite_admin_list_get_schedule_venue_parts')
        ? aidunite_admin_list_get_schedule_venue_parts($schedule_id)
        : ['place_label' => '—', 'venue_name' => '—'];

    $gender_raw = (string) ($canonical['gender'] ?? '');
    if ($gender_raw === '') {
        $gender_raw = function_exists('aidunite_schedule_read_gender_raw')
            ? (string) aidunite_schedule_read_gender_raw($schedule_id)
            : '';
    }
    $gender_label = function_exists('aidunite_admin_list_format_gender_display')
        ? aidunite_admin_list_format_gender_display($gender_raw)
        : $gender_raw;

    $intent = (string) ($canonical['intent'] ?? (function_exists('aidunite_schedule_read_intent') ? aidunite_schedule_read_intent($schedule_id) : ''));
    $matching = (string) ($canonical['matching'] ?? '0');
    $schedule_date_raw = (string) ($canonical['date'] ?? (function_exists('aidunite_schedule_read_normalized_date') ? aidunite_schedule_read_normalized_date($schedule_id) : ''));
    $schedule_type = (string) ($canonical['schedule_type'] ?? '');

    $author_id = (int) $post->post_author;
    $author = $author_id > 0 ? get_userdata($author_id) : false;
    $author_name = $author ? $author->display_name : '—';

    $created = $post->post_date
        ? (class_exists('AidUniteDateUtils')
            ? AidUniteDateUtils::formatDate($post->post_date, AidUniteDateUtils::DATE_DISPLAY_SHORT)
            : date_i18n('Y-m-d H:i', strtotime($post->post_date)))
        : '—';

    return [
        'id' => $schedule_id,
        'schedule_date' => function_exists('aidunite_admin_list_format_date_display')
            ? aidunite_admin_list_format_date_display($schedule_date_raw)
            : ($schedule_date_raw ?: '—'),
        'time_display' => $time_display,
        'schedule_type' => $schedule_type !== '' ? $schedule_type : '—',
        'intent' => $intent,
        'intent_label' => aidunite_admin_schedule_list_intent_label($intent),
        'gender_label' => $gender_label !== '' && $gender_label !== '不明' ? $gender_label : '—',
        'place_label' => $venue_parts['place_label'],
        'venue_name' => $venue_parts['venue_name'],
        'place_display' => $venue_parts['place_label'],
        'team_id' => $team_id > 0 ? $team_id : '—',
        'team_name' => $team_name,
        'matching' => $matching === '1' || $matching === 'yes' || $matching === 'true' ? 'あり' : '—',
        'author_name' => $author_name,
        'author_id' => $author_id > 0 ? $author_id : '—',
        'created' => $created,
        'edit_url' => function_exists('aidunite_get_schedule_edit_url')
            ? aidunite_get_schedule_edit_url((int) $schedule_id)
            : add_query_arg('edit_schedule', (int) $schedule_id, home_url('/schedule-management')),
        'wp_edit_url' => get_edit_post_link($schedule_id, 'raw'),
        'all_meta' => get_post_meta($schedule_id),
    ];
}

/**
 * 管理者用スケジュール一覧: 1件削除（`aidunite_perform_safe_schedule_deletion`）
 *
 * @param int $schedule_id
 * @return array{schedule_id:int,title:string}|WP_Error
 */
function aidunite_admin_schedule_list_delete_schedule($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return new WP_Error('invalid_schedule', '無効なスケジュールIDです。');
    }

    $title = get_the_title($schedule_id);

    if (!function_exists('aidunite_perform_safe_schedule_deletion')) {
        $guards_path = get_template_directory() . '/functions/schedule/schedule-dependency-guards.php';
        if (is_readable($guards_path)) {
            require_once $guards_path;
        }
    }

    if (!function_exists('aidunite_perform_safe_schedule_deletion')) {
        return new WP_Error('missing_deletion', 'スケジュール削除処理が利用できません。');
    }

    $result = aidunite_perform_safe_schedule_deletion($schedule_id);
    if (!is_wp_error($result)) {
        return [
            'schedule_id' => $schedule_id,
            'title' => $title !== '' ? $title : ('スケジュール #' . $schedule_id),
        ];
    }

    // 管理者一覧: マッチ申請ありでも関連データを除去して削除（安全削除がブロックした場合）
    $match_requests = aidunite_get_schedule_match_requests($schedule_id);
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
    if (!wp_delete_post($schedule_id, true)) {
        return $result;
    }

    return [
        'schedule_id' => $schedule_id,
        'title' => $title !== '' ? $title : ('スケジュール #' . $schedule_id),
    ];
}
