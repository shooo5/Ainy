<?php
/**
 * 試合後アンケート — Ainy データ管理一覧（WP 左メニューは使わない）
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIDUNITE_ADMIN_MATCH_FEEDBACK_LIST_PER_PAGE', 25);

/**
 * @return string
 */
function aidunite_admin_match_feedback_list_base_url() {
    return home_url('/admin-match-feedback-list');
}

/**
 * @return array<string, string>
 */
function aidunite_admin_match_feedback_list_satisfaction_reason_labels() {
    return [
        'time'       => '時間の使い方',
        'venue'      => '会場・環境',
        'opponent'   => '相手チームの対応',
        'atmosphere' => '雰囲気・マナー',
    ];
}

/**
 * @param mixed $reasons
 * @return string[]
 */
function aidunite_admin_match_feedback_list_format_reasons($reasons) {
    $labels = aidunite_admin_match_feedback_list_satisfaction_reason_labels();
    if (!is_array($reasons)) {
        $reasons = maybe_unserialize($reasons);
    }
    if (!is_array($reasons)) {
        return [];
    }
    $out = [];
    foreach ($reasons as $key) {
        $key = (string) $key;
        $out[] = $labels[$key] ?? $key;
    }
    return $out;
}

/**
 * @param int $match_id
 * @param int $team_id
 * @return int
 */
function aidunite_admin_match_feedback_list_opponent_team_id($match_id, $team_id) {
    $match_id = (int) $match_id;
    $team_id = (int) $team_id;
    if ($match_id <= 0 || $team_id <= 0) {
        return 0;
    }
    $from = (int) get_post_meta($match_id, 'from_team_id', true);
    $to = (int) get_post_meta($match_id, 'to_team_id', true);
    if ($team_id === $from) {
        return $to;
    }
    if ($team_id === $to) {
        return $from;
    }
    return 0;
}

/**
 * @return array<string, mixed>
 */
function aidunite_admin_match_feedback_list_get_filters_from_request() {
    return [
        'match_id'         => isset($_GET['match_id']) ? max(0, (int) $_GET['match_id']) : 0,
        'team_id'          => isset($_GET['team_id']) ? max(0, (int) $_GET['team_id']) : 0,
        'rematch_interest' => isset($_GET['rematch_interest']) ? sanitize_key(wp_unslash($_GET['rematch_interest'])) : '',
        'date_from'        => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
        'date_to'          => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
        'search'           => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
    ];
}

/**
 * @param array<string, mixed> $filters
 * @return bool
 */
function aidunite_admin_match_feedback_list_has_active_filters(array $filters) {
    return (int) ($filters['match_id'] ?? 0) > 0
        || (int) ($filters['team_id'] ?? 0) > 0
        || ($filters['rematch_interest'] ?? '') !== ''
        || ($filters['date_from'] ?? '') !== ''
        || ($filters['date_to'] ?? '') !== ''
        || ($filters['search'] ?? '') !== '';
}

/**
 * @param array<string, mixed> $filters
 * @param WP_Post $post
 * @return bool
 */
function aidunite_admin_match_feedback_list_matches_filters(array $filters, $post) {
    $row = aidunite_admin_match_feedback_list_row_data($post->ID);
    if (empty($row)) {
        return false;
    }

    if ((int) ($filters['match_id'] ?? 0) > 0 && (int) $row['match_id'] !== (int) $filters['match_id']) {
        return false;
    }
    if ((int) ($filters['team_id'] ?? 0) > 0 && (int) $row['team_id'] !== (int) $filters['team_id']) {
        return false;
    }

    $rematch = (string) ($filters['rematch_interest'] ?? '');
    if ($rematch !== '' && (string) $row['rematch_interest'] !== $rematch) {
        return false;
    }

    $created = (string) ($row['created_at'] ?? '');
    if ($created !== '') {
        $created_date = substr($created, 0, 10);
        if (($filters['date_from'] ?? '') !== '' && $created_date < $filters['date_from']) {
            return false;
        }
        if (($filters['date_to'] ?? '') !== '' && $created_date > $filters['date_to']) {
            return false;
        }
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $haystack = implode(' ', [
            (string) $row['team_name'],
            (string) $row['opponent_team_name'],
            (string) $row['user_display'],
            (string) $row['comment'],
            (string) $row['rematch_reason'],
        ]);
        if (mb_stripos($haystack, $search, 0, 'UTF-8') === false) {
            return false;
        }
    }

    return true;
}

/**
 * @param int $post_id
 * @return array<string, mixed>
 */
function aidunite_admin_match_feedback_list_row_data($post_id) {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'match_feedback') {
        return [];
    }

    $match_id = (int) get_post_meta($post_id, 'match_id', true);
    $team_id = (int) get_post_meta($post_id, 'team_id', true);
    $user_id = (int) get_post_meta($post_id, 'user_id', true);
    $opponent_team_id = aidunite_admin_match_feedback_list_opponent_team_id($match_id, $team_id);

    $user = $user_id > 0 ? get_userdata($user_id) : null;
    $user_display = $user ? ($user->display_name ?: $user->user_login) : ('ユーザー #' . $user_id);

    $satisfaction = (int) get_post_meta($post_id, 'satisfaction', true);
    $reasons_raw = get_post_meta($post_id, 'satisfaction_reasons', true);
    $rematch = (string) get_post_meta($post_id, 'rematch_interest', true);

    return [
        'id'                    => $post_id,
        'match_id'              => $match_id,
        'team_id'               => $team_id,
        'team_name'             => function_exists('aidunite_get_team_name') ? aidunite_get_team_name($team_id) : ('チーム #' . $team_id),
        'opponent_team_id'      => $opponent_team_id,
        'opponent_team_name'    => function_exists('aidunite_get_team_name') ? aidunite_get_team_name($opponent_team_id) : ('チーム #' . $opponent_team_id),
        'user_id'               => $user_id,
        'user_display'          => $user_display,
        'satisfaction'          => $satisfaction,
        'satisfaction_reasons'  => aidunite_admin_match_feedback_list_format_reasons($reasons_raw),
        'opponent_rating'       => (int) get_post_meta($post_id, 'opponent_rating', true),
        'opponent_reasons'      => get_post_meta($post_id, 'opponent_reasons', true),
        'venue_rating'          => (int) get_post_meta($post_id, 'venue_rating', true),
        'venue_improvement'     => (string) get_post_meta($post_id, 'venue_improvement', true),
        'rematch_interest'      => $rematch,
        'rematch_interest_label'=> $rematch === 'yes' ? 'はい' : ($rematch === 'no' ? 'いいえ' : '—'),
        'rematch_reason'        => (string) get_post_meta($post_id, 'rematch_reason', true),
        'comment'               => (string) get_post_meta($post_id, 'comment', true),
        'created_at'            => (string) get_post_meta($post_id, 'created_at', true) ?: $post->post_date,
        'post_date'             => $post->post_date,
    ];
}

/**
 * @param array<string, mixed> $filters
 * @return array<string, string>
 */
function aidunite_admin_match_feedback_list_filters_to_query_args(array $filters) {
    $args = [];
    foreach ($filters as $key => $value) {
        if ($value === '' || $value === 0) {
            continue;
        }
        $args[$key] = (string) $value;
    }
    return $args;
}

/**
 * @param int $rating
 * @return string
 */
function aidunite_admin_match_feedback_list_star_display($rating) {
    $rating = max(0, min(5, (int) $rating));
    if ($rating <= 0) {
        return '—';
    }
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . ' (' . $rating . '/5)';
}

/**
 * @param string $datetime
 * @return array{date:string, time:string}
 */
function aidunite_admin_match_feedback_list_split_datetime($datetime) {
    $datetime = trim((string) $datetime);
    if ($datetime === '') {
        return ['date' => '—', 'time' => '—'];
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return ['date' => $datetime, 'time' => '—'];
    }
    return [
        'date' => wp_date('Y-m-d', $ts),
        'time' => wp_date('H:i', $ts),
    ];
}

/**
 * @param string[] $reasons
 * @return string
 */
function aidunite_admin_match_feedback_list_render_reason_chips($reasons) {
    if (empty($reasons)) {
        return '<span class="admin-mfl-empty-cell">—</span>';
    }
    $html = '<ul class="admin-mfl-chips">';
    foreach ($reasons as $label) {
        $html .= '<li class="admin-mfl-chip">' . esc_html((string) $label) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * CSV 行出力用
 *
 * @param array<int, WP_Post> $posts
 */
function aidunite_admin_match_feedback_list_export_csv(array $posts) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="match-feedback-' . gmdate('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', '回答日', '回答時刻', 'マッチID',
        '回答チームID', '回答チーム名', '相手チームID', '相手チーム名',
        '回答者ID', '回答者名',
        '満足度', '良かった点1', '良かった点2', '良かった点3', '良かった点4',
        '再戦希望', '再戦理由', '自由記述',
        '相手評価', '会場評価', '会場改善', '投稿日',
    ]);
    foreach ($posts as $post) {
        $row = aidunite_admin_match_feedback_list_row_data($post->ID);
        if (empty($row)) {
            continue;
        }
        $dt = aidunite_admin_match_feedback_list_split_datetime($row['created_at']);
        $reason_cols = array_pad($row['satisfaction_reasons'], 4, '');
        fputcsv($out, [
            $row['id'],
            $dt['date'],
            $dt['time'],
            $row['match_id'],
            $row['team_id'],
            $row['team_name'],
            $row['opponent_team_id'],
            $row['opponent_team_name'],
            $row['user_id'],
            $row['user_display'],
            $row['satisfaction'],
            $reason_cols[0],
            $reason_cols[1],
            $reason_cols[2],
            $reason_cols[3],
            $row['rematch_interest'],
            $row['rematch_reason'],
            $row['comment'],
            $row['opponent_rating'],
            $row['venue_rating'],
            $row['venue_improvement'],
            $row['post_date'],
        ]);
    }
    fclose($out);
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (!is_page_template('page-admin-match-feedback-list.php')) {
        return;
    }
    $theme_uri = get_stylesheet_directory_uri();
    $css_path = get_stylesheet_directory() . '/assets/css/pages/admin-match-feedback-list.css';
    $ver = is_readable($css_path) ? (string) filemtime($css_path) : '1.0.0';
    wp_enqueue_style(
        'aidunite-admin-match-feedback-list',
        $theme_uri . '/assets/css/pages/admin-match-feedback-list.css',
        ['aidunite-style', 'aidunite-admin-filters', 'aidunite-admin-list-table'],
        $ver
    );
}, 20);
