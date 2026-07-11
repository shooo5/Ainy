<?php
/**
 * 通知一覧ページ用ヘルパー（page-notifications.php）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * マッチ関連通知タイプ（一覧フィルタ・表示用）
 *
 * @return string[]
 */
function ainy_notification_match_types() {
    return [
        'match',
        'match_request',
        'match_established',
        'match_accepted', // legacy DB 読取互換（新規送信は match_established）
        'match_rejected',
        'match_cancelled',
        'match_canceled',
        'match_request_received',
        'match_updated',
        'match_established',
        'match_game_dissolved',
        'match_participant_withdrawn',
        'match_request_superseded',
        'match_request_reconfirm_required',
        'match_request_reconfirm_proposed',
        'match_request_reconfirm_accepted',
        'match_feedback_survey',
    ];
}

/**
 * 通知アイコン（SVG basename）とカラー variant
 *
 * @return array{icon_svg: string, variant: string}
 */
function ainy_notification_icon_meta($type, $is_match) {
    $type = (string) $type;

    if ($type === 'team_application_approved') {
        return ['icon_svg' => 'check_circle', 'variant' => 'green'];
    }
    if ($type === 'team_application_rejected') {
        return ['icon_svg' => 'brightness_alert', 'variant' => 'orange'];
    }
    if ($type === 'match_feedback_survey') {
        return ['icon_svg' => 'list_alt_add', 'variant' => 'gray'];
    }
    if ($is_match) {
        if (stripos($type, 'accepted') !== false || stripos($type, 'established') !== false) {
            return ['icon_svg' => 'check_circle', 'variant' => 'green'];
        }
        if (stripos($type, 'rejected') !== false || stripos($type, 'cancelled') !== false || stripos($type, 'dissolved') !== false) {
            return ['icon_svg' => 'close', 'variant' => 'red'];
        }
        if (stripos($type, 'request') !== false || stripos($type, 'reconfirm') !== false) {
            return ['icon_svg' => 'contact_mail', 'variant' => 'purple'];
        }
        return ['icon_svg' => 'basketball', 'variant' => 'orange'];
    }
    if (stripos($type, 'schedule') !== false || stripos($type, 'attendance') !== false) {
        return ['icon_svg' => 'calendar_month', 'variant' => 'purple'];
    }
    if (stripos($type, 'message') !== false || stripos($type, 'chat') !== false) {
        return ['icon_svg' => 'chat', 'variant' => 'purple'];
    }
    if (stripos($type, 'team') !== false || stripos($type, 'admin_team') !== false) {
        return ['icon_svg' => 'group', 'variant' => 'blue'];
    }
    if (stripos($type, 'payment') !== false) {
        return ['icon_svg' => 'currency_yen', 'variant' => 'orange'];
    }
    if (stripos($type, 'tournament') !== false) {
        return ['icon_svg' => 'trophy', 'variant' => 'orange'];
    }
    if (stripos($type, 'welcome') !== false) {
        return ['icon_svg' => 'redeem', 'variant' => 'red'];
    }

    return ['icon_svg' => 'notification_add', 'variant' => 'gray'];
}

/**
 * 相対時刻表示（日本語）
 */
function ainy_notification_relative_time($post_id) {
    $ts = (int) get_post_time('U', true, $post_id);
    if ($ts <= 0) {
        return '';
    }
    $now = (int) current_time('timestamp');
    $diff = max(0, $now - $ts);

    if ($diff < 60) {
        return 'たった今';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . '分前';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . '時間前';
    }

    $today_start = strtotime('today', $now);
    $yesterday_start = strtotime('yesterday', $now);

    if ($ts >= $today_start) {
        return wp_date('G:i', $ts);
    }
    if ($ts >= $yesterday_start) {
        return '昨日 ' . wp_date('G:i', $ts);
    }

    return wp_date('n/j G:i', $ts);
}

/**
 * 日付グループキー（today / yesterday / earlier）
 */
function ainy_notification_group_key($post_id) {
    $ts = (int) get_post_time('U', true, $post_id);
    $now = (int) current_time('timestamp');
    $today_start = strtotime('today', $now);
    $yesterday_start = strtotime('yesterday', $now);

    if ($ts >= $today_start) {
        return 'today';
    }
    if ($ts >= $yesterday_start) {
        return 'yesterday';
    }
    return 'earlier';
}

/**
 * @return array<string, string>
 */
function ainy_notification_group_labels() {
    return [
        'today'    => '今日',
        'yesterday' => '昨日',
        'earlier'  => 'それより前',
    ];
}

/**
 * 通知1件分の表示用データ
 *
 * @param WP_Post $post
 * @param string[] $match_types
 * @return array<string, mixed>
 */
function ainy_notification_prepare_item($post, array $match_types) {
    $post_id = (int) $post->ID;
    $is_read = get_post_meta($post_id, 'is_read', true);
    $is_read = ($is_read === '1' || $is_read === 1 || $is_read === true);
    $type = get_post_meta($post_id, 'type', true) ?: get_post_meta($post_id, 'notification_type', true);
    $type = (string) $type;
    $title = get_the_title($post_id);
    $link_url = get_post_meta($post_id, 'link_url', true);
    $related_id = (int) get_post_meta($post_id, 'related_id', true);

    if (empty($link_url) && $related_id > 0) {
        if ($type === 'match_game_dissolved') {
            $link_url = home_url('/match-board-own');
        } elseif ($type === 'match_feedback_survey' && function_exists('aidunite_get_match_feedback_survey_url')) {
            $link_url = aidunite_get_match_feedback_survey_url($related_id);
        } elseif (
            (function_exists('aidunite_notification_should_link_to_match_detail')
                ? aidunite_notification_should_link_to_match_detail($type)
                : (in_array($type, $match_types, true) || stripos($type, 'match') !== false))
            && (in_array($type, $match_types, true) || stripos($type, 'match') !== false)
        ) {
            $link_url = home_url('/match-detail/?id=' . $related_id);
        } elseif (stripos($type, 'schedule') !== false) {
            $link_url = function_exists('aidunite_get_schedule_edit_url')
                ? aidunite_get_schedule_edit_url((int) $related_id)
                : home_url('/schedule-management/?edit_schedule=' . (int) $related_id);
        } elseif ($type === 'team_application_approved' || $type === 'team_application_rejected') {
            $link_url = home_url('/mypage/');
        } elseif ($type === 'team_approval' || stripos($type, 'team') !== false) {
            $link_url = home_url('/team-detail/?id=' . $related_id);
        }
    }

    $is_match = in_array($type, $match_types, true) || stripos($type, 'match') !== false;
    $icon_meta = ainy_notification_icon_meta($type, $is_match);

    $raw_message = get_post_field('post_content', $post_id);
    $message = '';
    $excerpt = '';
    if (is_string($raw_message) && trim($raw_message) !== '') {
        $message = trim(wp_strip_all_tags($raw_message));
        $excerpt = wp_trim_words($message, 28, '…');
    }

    return [
        'id'           => $post_id,
        'is_read'      => $is_read,
        'type'         => $type,
        'related_id'   => $related_id,
        'title'        => $title,
        'message'      => $message,
        'excerpt'      => $excerpt,
        'link_url'     => $link_url ? esc_url($link_url) : '',
        'is_match'     => $is_match,
        'icon_svg'     => $icon_meta['icon_svg'],
        'icon_variant' => $icon_meta['variant'],
        'time_label'   => ainy_notification_relative_time($post_id),
        'group_key'    => ainy_notification_group_key($post_id),
    ];
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<string, array<int, array<string, mixed>>>
 */
function ainy_notification_group_items(array $items) {
    $grouped = [
        'today'     => [],
        'yesterday' => [],
        'earlier'   => [],
    ];
    foreach ($items as $item) {
        $key = $item['group_key'] ?? 'earlier';
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $item;
    }
    return $grouped;
}
