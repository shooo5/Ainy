<?php
/**
 * マイページ v2 UI ヘルパー（パターンC：タイトルバー型セクション）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * マイページ Joy 用: マッチ申請 canonical（persist-read 経由）
 *
 * @param int $request_id
 * @return array<string, mixed>
 */
function aidunite_mypage_read_match_request_fields($request_id) {
    return aidunite_mypage_read_match_request_snapshot($request_id);
}

/**
 * マイページ Joy 用: マッチ申請スナップショット（persist-read 経由）
 *
 * @param int $request_id
 * @return array<string, mixed>
 */
function aidunite_mypage_read_match_request_snapshot($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return [];
    }
    if (function_exists('aidunite_match_request_read_canonical_meta')) {
        $canonical = aidunite_match_request_read_canonical_meta($request_id);
        if ($canonical !== []) {
            return $canonical;
        }
    }

    return [
        'from_team_id' => (int) get_post_meta($request_id, 'from_team_id', true),
        'to_team_id' => (int) get_post_meta($request_id, 'to_team_id', true),
        'to_schedule_id' => (int) get_post_meta($request_id, 'to_schedule_id', true),
        'status' => (string) get_post_meta($request_id, 'status', true),
        'match_date' => (string) get_post_meta($request_id, 'match_date', true),
    ];
}

/**
 * マイページ v2 アイコン（assets/images/icons/chat/）
 */
function aidunite_get_mypage_icon_base_url() {
    return get_stylesheet_directory_uri() . '/assets/images/icons/chat/';
}

/**
 * セクションタイトルバー用アイコン名
 *
 * @param string $section todo|today-schedules|event|upcoming
 */
function aidunite_get_mypage_section_icon_name($section) {
    $map = [
        'todo'            => 'check_circle',
        'today-schedules' => 'today',
        'event'           => 'trophy',
        'upcoming'        => 'schedule',
        'happy-feed'      => 'celebration',
        'week-schedule'   => 'schedule',
        'secondary-todo'  => 'check_circle',
    ];

    return isset($map[$section]) ? $map[$section] : 'check_circle';
}

/**
 * クイックアクション用アイコン名
 *
 * @param string $key schedule|chat|match|recruit
 */
function aidunite_get_mypage_quick_icon_name($key) {
    $map = [
        'schedule'   => 'calendar_month',
        'chat'       => 'chat',
        'match'      => 'trophy',
        'recruit'    => 'campaign',
        'attendance' => 'check_circle',
        'stats'      => 'bar_chart_4_bars',
        'children'   => 'family_group',
    ];

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * パターンC：アイコン付きタイトルバーを出力
 *
 * @param array<string, mixed> $args {
 *   @type string $section   セクションキー（todo|today-schedules|event|upcoming）
 *   @type string $title    見出し
 *   @type string $subtitle 補足（任意）
 *   @type string $heading_id h3 id（任意）
 *   @type string $link_url 「すべて見る」URL（空なら非表示）
 *   @type string $link_label リンク文言（既定: すべて見る）
 *   @type string $link_id    リンク要素 id（任意）
 *   @type bool   $link_hidden リンクを hidden で出力（任意）
 *   @type bool   $priority  やること等の強調バー
 * }
 */
function aidunite_render_mypage_section_bar(array $args = []) {
    $args = wp_parse_args($args, [
        'section'     => '',
        'title'       => '',
        'subtitle'    => '',
        'heading_id'  => '',
        'link_url'    => '',
        'link_label'  => 'すべて見る',
        'link_id'     => '',
        'link_hidden' => false,
        'priority'    => false,
    ]);

    $section = (string) $args['section'];
    $icon_name = aidunite_get_mypage_section_icon_name($section);
    $bar_base = 'mypage-joy-section-bar';

    $bar_class = $bar_base;
    if ($section !== '') {
        $bar_class .= ' ' . $bar_base . '--' . sanitize_html_class($section);
    }
    if (!empty($args['priority'])) {
        $bar_class .= ' ' . $bar_base . '--priority';
    }

    $heading_id = (string) $args['heading_id'];
    $heading_attr = $heading_id !== '' ? ' id="' . esc_attr($heading_id) . '"' : '';
    ?>
    <div class="<?php echo esc_attr($bar_class); ?>">
        <div class="<?php echo esc_attr($bar_base); ?>__main">
            <span class="<?php echo esc_attr($bar_base); ?>__icon" aria-hidden="true">
                <?php echo aidunite_get_theme_icon_svg($icon_name, ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <h3 class="<?php echo esc_attr($bar_base); ?>__title"<?php echo $heading_attr; ?>>
                <?php echo esc_html((string) $args['title']); ?>
                <?php if ((string) $args['subtitle'] !== '') : ?>
                    <span class="<?php echo esc_attr($bar_base); ?>__subtitle"><?php echo esc_html((string) $args['subtitle']); ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if ((string) $args['link_url'] !== '') : ?>
            <?php
            $link_id = (string) $args['link_id'];
            $link_id_attr = $link_id !== '' ? ' id="' . esc_attr($link_id) . '"' : '';
            $link_hidden_attr = !empty($args['link_hidden']) ? ' hidden' : '';
            ?>
            <a href="<?php echo esc_url((string) $args['link_url']); ?>" class="<?php echo esc_attr($bar_base); ?>__link"<?php echo $link_id_attr . $link_hidden_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php echo esc_html((string) $args['link_label']); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Joy マイページを表示するチーム所属ロールか
 *
 * @param string $effective_role
 */
function aidunite_mypage_is_joy_eligible_role($effective_role) {
    return in_array((string) $effective_role, ['team_leader', 'parent', 'player', 'administrator'], true);
}

/**
 * Joy UI 文案の対象者キー（将来 team_category で分岐）
 *
 * @param int $team_id
 * @return string children|general
 */
function aidunite_mypage_joy_get_audience_key($team_id = 0) {
    $team_id = (int) $team_id;
    /**
     * 将来: 高校生以下 → children、それ以外 → general
     *
     * @param string $default
     * @param int    $team_id
     */
    return (string) apply_filters('aidunite_mypage_joy_audience_key', 'children', $team_id);
}

/**
 * Joy UI 文案（1箇所集約・将来年代分岐用）
 *
 * @param string $key
 * @param int    $team_id
 */
function aidunite_mypage_joy_copy($key, $team_id = 0) {
    $audience = aidunite_mypage_joy_get_audience_key($team_id);
    $bundles = [
        'praise_title' => [
            'children' => '今月も子どもたちの試合機会が増えています。',
            'general'  => '今月も試合の機会が増えています。',
        ],
        'praise_sub' => [
            'children' => 'うちのチーム、順調に試合機会を作れています。この調子でいきましょう。',
            'general'  => 'うちのチーム、順調に試合機会を作れています。この調子でいきましょう。',
        ],
        'calm_sub' => [
            'children' => '試合調整は順調です。このまま練習に集中できます。',
            'general'  => '試合調整は順調です。このまま練習に集中できます。',
        ],
        'discover_title' => [
            'children' => '試合できそうなチームがあります。',
            'general'  => '試合できそうなチームがあります。',
        ],
        'discover_kicker' => [
            'children' => '候補',
            'general'  => '候補',
        ],
        'happy_feed_empty' => [
            'children' => "まだお知らせはありません。\n試合が決まるとここに表示されます。",
            'general'  => "まだお知らせはありません。\n試合が決まるとここに表示されます。",
        ],
        'connection_feed' => [
            'children' => '新しいチームとつながりました。',
            'general'  => '新しいチームとつながりました。',
        ],
    ];

    if (!isset($bundles[$key])) {
        return '';
    }

    $row = $bundles[$key];
    if (isset($row[$audience])) {
        return (string) $row[$audience];
    }

    return (string) ($row['children'] ?? '');
}

/**
 * Joy UI 文案バンドル（REST / JS 用）
 *
 * @param int $team_id
 * @return array<string, string>
 */
function aidunite_mypage_joy_get_copy_bundle($team_id = 0) {
    $keys = [
        'praise_title',
        'praise_sub',
        'calm_sub',
        'discover_title',
        'discover_kicker',
        'happy_feed_empty',
        'connection_feed',
    ];
    $bundle = [];
    foreach ($keys as $key) {
        $bundle[$key] = aidunite_mypage_joy_copy($key, $team_id);
    }

    return $bundle;
}

/**
 * celebration をヒーローに出すか（48h 以内）
 *
 * @param array<string, mixed>|null $celebration
 */
function aidunite_mypage_joy_is_celebration_hero_eligible($celebration) {
    if ($celebration === null || !is_array($celebration)) {
        return false;
    }

    $modified_at = (string) ($celebration['modified_at'] ?? '');
    if ($modified_at === '') {
        return true;
    }

    $modified_ts = strtotime($modified_at);
    if ($modified_ts === false) {
        return true;
    }

    $ttl = (int) apply_filters('aidunite_mypage_joy_celebration_hero_ttl', 48 * HOUR_IN_SECONDS);

    return (time() - $modified_ts) <= $ttl;
}

/**
 * Joy マイページ用：自チーム募集中スケジュール件数
 *
 * @param int $team_id
 */
function aidunite_mypage_joy_count_recruiting($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $today = wp_date('Y-m-d');
    $posts = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'team_id',
                'value'   => (string) $team_id,
                'compare' => '=',
            ],
            [
                'key'     => 'schedule_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ],
    ]);

    $count = 0;
    foreach ($posts as $schedule_id) {
        $schedule_id = (int) $schedule_id;
        $intent = function_exists('aidunite_schedule_read_intent')
            ? (string) aidunite_schedule_read_intent($schedule_id)
            : '';
        $matching = function_exists('aidunite_schedule_get_canonical_meta')
            ? (string) (aidunite_schedule_get_canonical_meta($schedule_id)['matching'] ?? '0')
            : '0';
        $is_recruit = ($intent === 'recruit')
            || in_array($matching, [1, '1', true, 'yes'], true);
        if (!$is_recruit) {
            continue;
        }
        if (function_exists('aidunite_schedule_guest_remaining')
            && aidunite_schedule_guest_remaining($schedule_id) < 1) {
            continue;
        }
        $count++;
    }

    return $count;
}

/**
 * Joy マイページ用：送付申請の返答待ち件数
 *
 * @param int $team_id
 */
function aidunite_mypage_joy_count_pending_sent($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $requests = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'from_team_id',
                'value'   => (string) $team_id,
                'compare' => '=',
            ],
        ],
    ]);

    $count = 0;
    foreach ($requests as $request_id) {
        $request_id = (int) $request_id;
        $mr = aidunite_mypage_read_match_request_fields($request_id);
        $raw = (string) ($mr['status'] ?? '');
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, 'publish')
            : $raw;
        $established = ['established', 'accepted', 'canceled', 'rejected', '試合確定', '承認済み'];
        if (in_array($norm, $established, true) || in_array($raw, $established, true)) {
            continue;
        }
        if (in_array($norm, ['pending', 'publish', '申請中', ''], true)
            || in_array($raw, ['pending', 'publish', '申請中', ''], true)) {
            $count++;
        }
    }

    return $count;
}

/**
 * Joy マイページ用：次の成立済み試合
 *
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_mypage_joy_get_next_match($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    $today = wp_date('Y-m-d');
    $requests = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 30,
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
        ],
    ]);

    $candidates = [];
    foreach ($requests as $request) {
        $request_id = (int) $request->ID;
        $mr = aidunite_mypage_read_match_request_fields($request_id);
        $raw = (string) ($mr['status'] ?? '');
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, (string) $request->post_status)
            : $raw;
        if (!in_array($norm, ['established', 'accepted', '試合確定', '承認済み'], true)
            && !in_array($raw, ['established', 'accepted', '試合確定', '承認済み'], true)) {
            continue;
        }

        $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
        if ($to_schedule_id <= 0) {
            continue;
        }

        $match_date = function_exists('aidunite_schedule_read_normalized_date')
            ? (string) aidunite_schedule_read_normalized_date($to_schedule_id)
            : '';
        if ($match_date === '' || $match_date < $today) {
            continue;
        }

        $from_team_id = (int) ($mr['from_team_id'] ?? 0);
        $to_team_id_meta = (int) ($mr['to_team_id'] ?? 0);
        $opponent_team_id = ($from_team_id === $team_id) ? $to_team_id_meta : $from_team_id;
        if ($opponent_team_id <= 0) {
            $schedule_team = function_exists('aidunite_schedule_read_team_id')
                ? (int) aidunite_schedule_read_team_id($to_schedule_id)
                : 0;
            $opponent_team_id = ($schedule_team === $team_id) ? $from_team_id : $schedule_team;
        }

        $disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($to_schedule_id)
            : [];
        $start = (string) ($disp['start_time'] ?? '');
        $end = (string) ($disp['end_time'] ?? '');
        $type = (string) ($disp['schedule_type'] ?? 'practice_match');
        $place = (string) ($disp['place'] ?? '');

        $candidates[] = [
            'request_id'    => $request_id,
            'schedule_id'   => $to_schedule_id,
            'date'          => $match_date,
            'start_time'    => $start,
            'end_time'      => $end,
            'type'          => $type,
            'place'         => $place,
            'opponent_name' => ($opponent_team_id > 0 && function_exists('aidunite_get_team_name'))
                ? (string) aidunite_get_team_name($opponent_team_id)
                : '相手チーム',
            'detail_url'    => home_url('/match-board-own?market_tab=my'),
        ];
    }

    if ($candidates === []) {
        return null;
    }

    usort($candidates, static function ($a, $b) {
        return strcmp((string) $a['date'], (string) $b['date']);
    });

    return $candidates[0];
}

/**
 * Joy マイページ用：直近の成立（祝福バナー用・7日以内）
 *
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_mypage_joy_get_recent_celebration($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    $since = wp_date('Y-m-d H:i:s', strtotime('-7 days'));
    $requests = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'date_query'     => [
            ['after' => $since, 'inclusive' => true],
        ],
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
        ],
    ]);

    foreach ($requests as $request) {
        $request_id = (int) $request->ID;
        $mr = aidunite_mypage_read_match_request_fields($request_id);
        $raw = (string) ($mr['status'] ?? '');
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, (string) $request->post_status)
            : $raw;
        if (!in_array($norm, ['established', 'accepted', '試合確定', '承認済み'], true)
            && !in_array($raw, ['established', 'accepted', '試合確定', '承認済み'], true)) {
            continue;
        }

        $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
        if ($to_schedule_id <= 0) {
            continue;
        }

        $match_date = function_exists('aidunite_schedule_read_normalized_date')
            ? (string) aidunite_schedule_read_normalized_date($to_schedule_id)
            : '';
        if ($match_date === '' || $match_date < wp_date('Y-m-d')) {
            continue;
        }

        $from_team_id = (int) ($mr['from_team_id'] ?? 0);
        $opponent_team_id = ($from_team_id === $team_id)
            ? (int) ($mr['to_team_id'] ?? 0)
            : $from_team_id;
        $disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($to_schedule_id)
            : [];

        return [
            'request_id'    => $request_id,
            'schedule_id'   => $to_schedule_id,
            'date'          => $match_date,
            'start_time'    => (string) ($disp['start_time'] ?? ''),
            'end_time'      => (string) ($disp['end_time'] ?? ''),
            'type'          => (string) ($disp['schedule_type'] ?? 'practice_match'),
            'place'         => (string) ($disp['place'] ?? ''),
            'opponent_name' => ($opponent_team_id > 0 && function_exists('aidunite_get_team_name'))
                ? (string) aidunite_get_team_name($opponent_team_id)
                : '相手チーム',
            'detail_url'    => home_url('/match-board-own?market_tab=my'),
            'modified_at'   => get_post_modified_time('Y-m-d H:i:s', true, $request),
        ];
    }

    return null;
}

/**
 * Joy: 今月の試合進捗（成立・申請・募集中）
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_mypage_joy_get_month_progress($team_id) {
    $team_id = (int) $team_id;
    $month_start = wp_date('Y-m-01');
    $month_end = wp_date('Y-m-t');
    $established = 0;
    $applications = 0;
    $teams_connected = [];

    if ($team_id > 0) {
        $requests = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
                ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ],
        ]);

        foreach ($requests as $request) {
            $request_id = (int) $request->ID;
            if (aidunite_mypage_joy_is_bot_match_request_for_feed($request_id, $team_id)) {
                continue;
            }

            $created = strtotime((string) $request->post_date);
            $in_month = ($created >= strtotime($month_start . ' 00:00:00')
                && $created <= strtotime($month_end . ' 23:59:59'));

            $mr = aidunite_mypage_read_match_request_fields($request_id);
            $from_team_id = (int) ($mr['from_team_id'] ?? 0);
            if ($from_team_id === $team_id && $in_month) {
                $applications++;
            }

            $raw = (string) ($mr['status'] ?? '');
            $norm = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($raw, (string) $request->post_status)
                : $raw;
            $is_established = in_array($norm, ['established', 'accepted', '試合確定', '承認済み'], true)
                || in_array($raw, ['established', 'accepted', '試合確定', '承認済み'], true);
            if (!$is_established) {
                continue;
            }

            $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
            $match_date = $to_schedule_id > 0 && function_exists('aidunite_schedule_read_normalized_date')
                ? (string) aidunite_schedule_read_normalized_date($to_schedule_id)
                : (string) ($mr['match_date'] ?? '');
            if ($match_date === '' || $match_date < $month_start || $match_date > $month_end) {
                continue;
            }

            $established++;
            $opponent_id = ($from_team_id === $team_id)
                ? (int) ($mr['to_team_id'] ?? 0)
                : $from_team_id;
            if ($opponent_id <= 0 && $to_schedule_id > 0) {
                $schedule_team = function_exists('aidunite_schedule_read_team_id')
                    ? (int) aidunite_schedule_read_team_id($to_schedule_id)
                    : 0;
                $opponent_id = ($schedule_team === $team_id) ? $from_team_id : $schedule_team;
            }
            if ($opponent_id > 0
                && !(function_exists('aidunite_is_onboarding_bot_team')
                    && aidunite_is_onboarding_bot_team($opponent_id))) {
                $teams_connected[$opponent_id] = true;
            }
        }
    }

    $recruiting = function_exists('aidunite_mypage_joy_count_recruiting')
        ? aidunite_mypage_joy_count_recruiting($team_id)
        : 0;
    $praise_threshold = (int) apply_filters('aidunite_mypage_joy_praise_established_threshold', 3);

    return [
        'month_label'        => wp_date('n月'),
        'established'        => $established,
        'applications'       => $applications,
        'recruiting'         => $recruiting,
        'teams_connected'    => count($teams_connected),
        'praise_threshold'   => $praise_threshold,
        'is_praise_worthy'   => $established >= $praise_threshold,
    ];
}

/**
 * Joy: 申請候補（新しい相手が見つかりました）
 *
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_mypage_joy_get_discover_candidate($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0
        || !function_exists('aidunite_market_get_my_matching_schedules')
        || !function_exists('aidunite_market_get_all_recruitments')
        || !function_exists('aidunite_market_get_row_state')) {
        return null;
    }

    $my_schedules = aidunite_market_get_my_matching_schedules($team_id);
    if ($my_schedules === []) {
        return null;
    }

    $range = function_exists('aidunite_market_board_default_date_range')
        ? aidunite_market_board_default_date_range()
        : ['from' => wp_date('Y-m-d', strtotime('+1 day')), 'to' => wp_date('Y-m-d', strtotime('+60 days'))];
    $recruitments = aidunite_market_get_all_recruitments($range['from'], $range['to'], $team_id);
    if ($recruitments === []) {
        return null;
    }

    foreach ($recruitments as $recruit_post) {
        if (!$recruit_post instanceof WP_Post) {
            continue;
        }
        $recruit_id = (int) $recruit_post->ID;
        $recruit_team = function_exists('aidunite_schedule_read_team_id')
            ? (int) aidunite_schedule_read_team_id($recruit_id)
            : 0;
        if ($recruit_team === $team_id) {
            continue;
        }

        $row = aidunite_market_get_row_state($recruit_post, $my_schedules, $team_id);
        $state = (string) ($row['state'] ?? '');
        if (!in_array($state, ['green', 'yellow'], true)) {
            continue;
        }

        $match_date = function_exists('aidunite_schedule_read_normalized_date')
            ? (string) aidunite_schedule_read_normalized_date($recruit_id)
            : '';
        $disp = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($recruit_id)
            : [];
        $team_name = ($recruit_team > 0 && function_exists('aidunite_get_team_name'))
            ? (string) aidunite_get_team_name($recruit_team)
            : '相手チーム';
        $board = home_url('/match-board-own');
        $apply_url = $board . (strpos($board, '?') === false ? '?' : '&') . 'market_tab=recruit';

        return [
            'recruit_schedule_id' => $recruit_id,
            'my_schedule_id'      => (int) ($row['best_my_schedule_id'] ?? 0),
            'team_name'           => $team_name,
            'match_date'          => $match_date,
            'start_time'          => (string) ($disp['start_time'] ?? ''),
            'end_time'            => (string) ($disp['end_time'] ?? ''),
            'row_state'           => $state,
            'apply_url'           => $apply_url,
        ];
    }

    return null;
}

/**
 * Joy フィード集計から除外するオンボーディング・ボット MR か
 *
 * @param int $request_id
 * @param int $team_id 自チーム（相手がボットチームかの判定用）
 */
function aidunite_mypage_joy_is_bot_match_request_for_feed($request_id, $team_id = 0) {
    $request_id = (int) $request_id;
    $team_id = (int) $team_id;
    if ($request_id <= 0) {
        return false;
    }
    if (function_exists('aidunite_match_request_is_onboarding_bot')
        && aidunite_match_request_is_onboarding_bot($request_id)) {
        return true;
    }
    if ($team_id <= 0) {
        return false;
    }

    $snapshot = aidunite_mypage_read_match_request_snapshot($request_id);
    $from_team_id = (int) ($snapshot['from_team_id'] ?? 0);
    $to_team_id = (int) ($snapshot['to_team_id'] ?? 0);
    $to_schedule_id = (int) ($snapshot['to_schedule_id'] ?? 0);
    $opponent_id = ($from_team_id === $team_id) ? $to_team_id : $from_team_id;
    if ($opponent_id <= 0 && $to_schedule_id > 0) {
        $schedule_team = function_exists('aidunite_schedule_read_team_id')
            ? (int) aidunite_schedule_read_team_id($to_schedule_id)
            : 0;
        $opponent_id = ($schedule_team === $team_id) ? $from_team_id : $schedule_team;
    }

    return $opponent_id > 0
        && function_exists('aidunite_is_onboarding_bot_team')
        && aidunite_is_onboarding_bot_team($opponent_id);
}

/**
 * Joy: うれしいお知らせ（処理済みログ。ヒーロー未処理分は除外）
 *
 * @param int                $team_id
 * @param array<string, mixed> $exclude {
 *   @type int|null $hero_request_id
 *   @type int|null $celebration_request_id
 * }
 * @return array<int, array<string, mixed>>
 */
function aidunite_mypage_joy_build_happy_feed($team_id, array $exclude = []) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $hero_request_id = isset($exclude['hero_request_id']) ? (int) $exclude['hero_request_id'] : 0;
    $celebration_request_id = isset($exclude['celebration_request_id']) ? (int) $exclude['celebration_request_id'] : 0;
    $since = wp_date('Y-m-d H:i:s', strtotime('-30 days'));
    $items = [];

    $requests = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 30,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'date_query'     => [
            ['after' => $since, 'inclusive' => true],
        ],
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
        ],
    ]);

    $board_my = home_url('/match-board-own?market_tab=my');
    $board_recruit = home_url('/match-board-own?market_tab=recruit');

    foreach ($requests as $request) {
        $request_id = (int) $request->ID;
        if ($request_id === $hero_request_id || $request_id === $celebration_request_id) {
            continue;
        }
        if (aidunite_mypage_joy_is_bot_match_request_for_feed($request_id, $team_id)) {
            continue;
        }

        $mr = aidunite_mypage_read_match_request_fields($request_id);
        $raw = (string) ($mr['status'] ?? '');
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, (string) $request->post_status)
            : $raw;
        $from_team_id = (int) ($mr['from_team_id'] ?? 0);
        $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
        $schedule_team = $to_schedule_id > 0 && function_exists('aidunite_schedule_read_team_id')
            ? (int) aidunite_schedule_read_team_id($to_schedule_id)
            : 0;
        $opponent_id = ($from_team_id === $team_id)
            ? (int) ($mr['to_team_id'] ?? 0)
            : $from_team_id;
        if ($opponent_id <= 0 && $schedule_team > 0) {
            $opponent_id = ($schedule_team === $team_id) ? $from_team_id : $schedule_team;
        }
        $opponent_name = ($opponent_id > 0 && function_exists('aidunite_get_team_name'))
            ? (string) aidunite_get_team_name($opponent_id)
            : '相手チーム';

        $is_established = in_array($norm, ['established', 'accepted', '試合確定', '承認済み'], true)
            || in_array($raw, ['established', 'accepted', '試合確定', '承認済み'], true);
        $is_pending_in = in_array($norm, ['pending', 'publish', '申請中', ''], true)
            || in_array($raw, ['pending', 'publish', '申請中', ''], true);

        if ($is_established) {
            $items[] = [
                'id'          => 'established_' . $request_id,
                'type'        => 'established',
                'icon'        => 'celebration',
                'message'     => $opponent_name . 'との試合が決まりました。',
                'occurred_at' => get_post_modified_time('c', true, $request),
                'url'         => $board_my,
            ];
            continue;
        }

        if ($is_pending_in && $schedule_team === $team_id) {
            continue;
        }

        if ($from_team_id === $team_id && in_array($norm, ['pending', 'publish', '申請中', ''], true)) {
            $items[] = [
                'id'          => 'sent_' . $request_id,
                'type'        => 'application_sent',
                'icon'        => 'send',
                'message'     => $opponent_name . 'へ試合を申請しました。',
                'occurred_at' => get_post_time('c', true, $request),
                'url'         => $board_my,
            ];
        }
    }

    $progress = aidunite_mypage_joy_get_month_progress($team_id);
    if (($progress['teams_connected'] ?? 0) > 0) {
        $items[] = [
            'id'          => 'connected_' . wp_date('Y-m'),
            'type'        => 'connection',
            'icon'        => 'groups',
            'message'     => aidunite_mypage_joy_copy('connection_feed', $team_id),
            'occurred_at' => wp_date('c'),
            'url'         => $board_my,
        ];
    }

    usort($items, static function ($a, $b) {
        return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
    });

    return $items;
}

/**
 * Joy マイページ REST ペイロード
 *
 * @return array<string, mixed>
 */
function aidunite_mypage_joy_build_payload() {
    $user_id = get_current_user_id();
    list($effective_role,) = aidunite_get_effective_user_role($user_id);
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    $action_items = function_exists('aidunite_get_action_required_items_for_display')
        ? aidunite_get_action_required_items_for_display()
        : [];

    $match_requests = array_values(array_filter($action_items, static function ($item) {
        return ($item['type'] ?? '') === 'match_request';
    }));

    $recruiting = aidunite_mypage_joy_count_recruiting($team_id);
    $pending_sent = aidunite_mypage_joy_count_pending_sent($team_id);
    $pending_received = count($match_requests);
    $action_count = count(array_filter($action_items, static function ($item) {
        return in_array($item['type'] ?? '', ['match_request', 'attendance', 'match_feedback', 'payment', 'tuition_payment', 'funnel_onboarding', 'funnel_reuse'], true);
    }));

    $priority = $match_requests !== [] ? $match_requests[0] : null;
    if ($priority !== null && ($priority['type'] ?? '') === 'match_request') {
        $req_id = (int) ($priority['id'] ?? 0);
        if ($req_id > 0) {
            $mr = aidunite_mypage_read_match_request_fields($req_id);
            $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
            $from_team_id = (int) ($mr['from_team_id'] ?? 0);
            if ($from_team_id > 0 && function_exists('aidunite_get_team_name')) {
                $priority['opponent_name'] = (string) aidunite_get_team_name($from_team_id);
            }
            if ($to_schedule_id > 0 && function_exists('aidunite_schedule_get_display_bundle')) {
                $disp = aidunite_schedule_get_display_bundle($to_schedule_id);
                $priority['start_time'] = (string) ($disp['start_time'] ?? '');
                $priority['end_time'] = (string) ($disp['end_time'] ?? '');
            }
        }
    }

    $secondary = array_values(array_filter($action_items, static function ($item) use ($priority) {
        if (($item['type'] ?? '') === 'match_request' && $priority !== null && ($item['id'] ?? '') === ($priority['id'] ?? '')) {
            return false;
        }
        return in_array($item['type'] ?? '', ['match_request', 'attendance', 'match_feedback', 'payment', 'tuition_payment', 'funnel_onboarding', 'funnel_reuse'], true);
    }));
    $secondary = array_slice($secondary, 0, 4);

    $next_match = aidunite_mypage_joy_get_next_match($team_id);
    $celebration = aidunite_mypage_joy_get_recent_celebration($team_id);
    $discover = aidunite_mypage_joy_get_discover_candidate($team_id);
    $month_progress = aidunite_mypage_joy_get_month_progress($team_id);

    $today = wp_date('Y-m-d');
    $has_match_today = ($next_match && ($next_match['date'] ?? '') === $today);

    $hero_request_id = ($priority && ($priority['type'] ?? '') === 'match_request')
        ? (int) ($priority['id'] ?? 0)
        : 0;
    $celebration_hero_eligible = aidunite_mypage_joy_is_celebration_hero_eligible($celebration);
    $celebration_request_id = ($celebration_hero_eligible && $celebration && isset($celebration['request_id']))
        ? (int) $celebration['request_id']
        : 0;

    $display_state = 'calm';
    if ($priority && ($priority['type'] ?? '') === 'match_request') {
        $display_state = !empty($priority['is_urgent']) ? 'reply_received' : 'reply_received';
    } elseif ($has_match_today) {
        $display_state = 'match_day';
    } elseif ($celebration_hero_eligible && $celebration !== null && $pending_received === 0 && $hero_request_id === 0) {
        $display_state = 'celebration';
    } elseif ($pending_sent > 0 && $pending_received === 0 && $action_count === 0) {
        $display_state = 'waiting';
    } elseif ($discover !== null && $pending_received === 0 && $action_count === 0) {
        $display_state = 'discover';
    } elseif (!empty($month_progress['is_praise_worthy']) && $pending_received === 0 && $action_count === 0) {
        $display_state = 'praise';
    } elseif ($action_count > 0) {
        $display_state = 'action_other';
    }

    $happy_feed_all = aidunite_mypage_joy_build_happy_feed($team_id, [
        'hero_request_id'        => $hero_request_id,
        'celebration_request_id' => $display_state === 'celebration' ? $celebration_request_id : 0,
    ]);
    $happy_feed = array_slice($happy_feed_all, 0, 3);

    $match_board = home_url('/match-board-own');
    $match_recruit_url = $match_board . (strpos($match_board, '?') === false ? '?' : '&') . 'market_tab=recruit';
    $match_my_url = $match_board . (strpos($match_board, '?') === false ? '?' : '&') . 'market_tab=my';

    return [
        'display_state'              => $display_state,
        'celebration_hero_eligible'  => $celebration_hero_eligible,
        'copy'                       => aidunite_mypage_joy_get_copy_bundle($team_id),
        'summary'           => [
            'recruiting'       => $recruiting,
            'pending_sent'     => $pending_sent,
            'pending_received' => $pending_received,
            'action_count'     => $action_count,
        ],
        'month_progress'    => $month_progress,
        'priority_action'   => $priority,
        'secondary_actions' => $secondary,
        'discover'          => $discover,
        'happy_feed'        => $happy_feed,
        'happy_feed_total'  => count($happy_feed_all),
        'next_match'        => $next_match,
        'celebration'       => $celebration,
        'urls'              => [
            'match_recruit' => $match_recruit_url,
            'match_my'      => $match_my_url,
            'match_board'   => $match_board,
            'schedule'      => home_url('/schedule-management'),
        ],
        'effective_role'    => $effective_role,
    ];
}

/**
 * REST: Joy マイページコンテキスト
 */
function aidunite_rest_get_mypage_joy_context() {
    return rest_ensure_response([
        'success' => true,
        'data'    => aidunite_mypage_joy_build_payload(),
    ]);
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/mypage-joy-context', [
        'methods'             => 'GET',
        'callback'            => 'aidunite_rest_get_mypage_joy_context',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
    ]);
});
