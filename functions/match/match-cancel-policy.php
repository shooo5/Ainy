<?php
/**
 * 試合キャンセルポリシー（match-request.md §17.7 時間軸）と
 * マッチ申請の返答期限・至急判定の共通ロジック。
 *
 * 至急（is_urgent）は「試合日が7日以上先」かつ「返答期限24時間以内」のときのみ true。
 * 試合7日未満の申請は至急表示しない（返答期限は試合3日前23:59で統一）。
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_match_policy_last_minute_hours')) {
    /**
     * 直前キャンセル境界（時間）。§17.7: 48時間以内。
     */
    function aidunite_match_policy_last_minute_hours() {
        return (int) apply_filters('aidunite_match_policy_last_minute_hours', 48);
    }
}

if (!function_exists('aidunite_match_policy_response_deadline_days')) {
    /**
     * 返答期限 = 試合日の N 日前 23:59（キャンセル猶予と整合）。
     */
    function aidunite_match_policy_response_deadline_days() {
        return (int) apply_filters('aidunite_match_policy_response_deadline_days', 3);
    }
}

if (!function_exists('aidunite_match_policy_urgent_min_days_before_match')) {
    /**
     * 至急表示の最低条件: 試合日まであと N 日以上（当日〜6日先は至急にしない）。
     */
    function aidunite_match_policy_urgent_min_days_before_match() {
        return (int) apply_filters('aidunite_match_policy_urgent_min_days_before_match', 7);
    }
}

if (!function_exists('aidunite_match_policy_match_timestamp')) {
    /**
     * @param string $match_date Y-m-d
     * @return int|null
     */
    function aidunite_match_policy_match_timestamp($match_date) {
        $match_date = trim((string) $match_date);
        if ($match_date === '') {
            return null;
        }
        $ts = strtotime($match_date . ' 00:00:00');
        return $ts !== false ? (int) $ts : null;
    }
}

if (!function_exists('aidunite_match_policy_days_until_match')) {
    /**
     * 試合日までの日数（試合日当日=0、過去は負）。
     *
     * @param string $match_date Y-m-d
     * @return int|null
     */
    function aidunite_match_policy_days_until_match($match_date) {
        $match_ts = aidunite_match_policy_match_timestamp($match_date);
        if ($match_ts === null) {
            return null;
        }
        $today_ts = strtotime(wp_date('Y-m-d') . ' 00:00:00');
        return (int) floor(($match_ts - $today_ts) / DAY_IN_SECONDS);
    }
}

if (!function_exists('aidunite_match_policy_get_cancel_tier')) {
    /**
     * §17.7 キャンセル区分（時間軸）。
     *
     * @param string $match_date Y-m-d
     * @return string normal|last_minute|same_day|unknown
     */
    function aidunite_match_policy_get_cancel_tier($match_date) {
        $match_ts = aidunite_match_policy_match_timestamp($match_date);
        if ($match_ts === null) {
            return 'unknown';
        }

        $now = time();
        $today = wp_date('Y-m-d');
        if ($match_date === $today) {
            return 'same_day';
        }

        $hours_until = ($match_ts - $now) / HOUR_IN_SECONDS;
        if ($hours_until <= aidunite_match_policy_last_minute_hours()) {
            return 'last_minute';
        }

        return 'normal';
    }
}

if (!function_exists('aidunite_match_policy_get_response_deadline')) {
    /**
     * マッチ申請への返答期限（試合日の3日前 23:59:59）。
     *
     * @param string $match_date Y-m-d
     * @return string|null datetime
     */
    function aidunite_match_policy_get_response_deadline($match_date) {
        $days = aidunite_match_policy_response_deadline_days();
        if ($days < 1) {
            return null;
        }
        $match_ts = aidunite_match_policy_match_timestamp($match_date);
        if ($match_ts === null) {
            return null;
        }

        return wp_date(
            'Y-m-d 23:59:59',
            strtotime('-' . $days . ' days', $match_ts)
        );
    }
}

if (!function_exists('aidunite_match_policy_can_show_urgent')) {
    /**
     * 至急UIを出してよい試合か（試合日が7日以上先）。
     *
     * @param string $match_date Y-m-d
     */
    function aidunite_match_policy_can_show_urgent($match_date) {
        $days_until = aidunite_match_policy_days_until_match($match_date);
        if ($days_until === null) {
            return false;
        }

        return $days_until >= aidunite_match_policy_urgent_min_days_before_match();
    }
}

if (!function_exists('aidunite_match_policy_compute_action_urgency')) {
    /**
     * やることアイテム用の緊急度（URGENT / SOON / NORMAL）。
     *
     * @param string      $match_date Y-m-d（match_request 用。それ以外は空）
     * @param string|null $deadline   datetime
     * @return array{urgency_level:string,is_urgent:bool,remaining_hours?:int,remaining_days?:int,cancel_tier?:string,days_until_match?:int}
     */
    function aidunite_match_policy_compute_action_urgency($match_date = '', $deadline = null) {
        $empty = [
            'urgency_level' => 'NONE',
            'is_urgent'     => false,
        ];

        if (!$deadline) {
            return $empty;
        }

        $deadline_ts = strtotime((string) $deadline);
        if ($deadline_ts === false) {
            return $empty;
        }

        $now = time();
        $seconds_until_deadline = $deadline_ts - $now;
        $hours_until_deadline = $seconds_until_deadline / HOUR_IN_SECONDS;
        $days_until_deadline = $seconds_until_deadline / DAY_IN_SECONDS;

        $days_until_match = aidunite_match_policy_days_until_match($match_date);
        $cancel_tier = aidunite_match_policy_get_cancel_tier($match_date);
        $can_urgent = aidunite_match_policy_can_show_urgent($match_date);

        $result = [
            'urgency_level'    => 'NORMAL',
            'is_urgent'        => false,
            'cancel_tier'      => $cancel_tier,
            'days_until_match' => $days_until_match,
        ];

        // 至急: 試合7日以上先 かつ 返答期限まで24時間以内
        if ($can_urgent && $hours_until_deadline <= 24 && $days_until_deadline < 1) {
            $result['urgency_level'] = 'URGENT';
            $result['is_urgent'] = true;
            $result['remaining_hours'] = max(0, (int) floor($hours_until_deadline));
        } elseif ($can_urgent && $days_until_deadline <= 3) {
            $result['urgency_level'] = 'SOON';
            $result['remaining_days'] = max(0, (int) floor($days_until_deadline));
        } elseif ($hours_until_deadline <= 24) {
            $result['remaining_hours'] = max(0, (int) floor($hours_until_deadline));
        } else {
            $result['remaining_days'] = max(0, (int) floor($days_until_deadline));
        }

        return $result;
    }
}
