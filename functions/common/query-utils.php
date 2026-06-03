<?php
/**
 * 統一クエリユーティリティ
 * AidUnite Theme - Query Utils
 *
 * WP_Query/meta_queryの書き方を統一するためのユーティリティクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class AidUniteScheduleQuery {
    /**
     * スケジュールを取得
     *
     * @param array $args クエリ引数
     * @return WP_Post[] スケジュール投稿の配列
     */
    public static function get($args = []) {
        $defaults = [
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'schedule_date',
            'order' => 'ASC'
        ];

        $query_args = wp_parse_args($args, $defaults);
        return get_posts($query_args);
    }

    /**
     * チームのスケジュールを取得
     *
     * @param int $team_id チームID
     * @param array $args 追加のクエリ引数
     * @return WP_Post[] スケジュール投稿の配列
     */
    public static function getByTeam($team_id, $args = []) {
        $meta_query = [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ];

        if (!empty($args['meta_query'])) {
            $meta_query = array_merge($meta_query, $args['meta_query']);
        }

        $args['meta_query'] = $meta_query;
        return self::get($args);
    }

    /**
     * 日付範囲でスケジュールを取得
     *
     * @param string $start_date 開始日
     * @param string $end_date 終了日
     * @param array $args 追加のクエリ引数
     * @return WP_Post[] スケジュール投稿の配列
     */
    public static function getByDateRange($start_date, $end_date, $args = []) {
        $meta_query = [
            [
                'key' => 'schedule_date',
                'value' => [$start_date, $end_date],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]
        ];

        if (!empty($args['meta_query'])) {
            $meta_query = array_merge($meta_query, $args['meta_query']);
        }

        $args['meta_query'] = $meta_query;
        return self::get($args);
    }
}

class AidUniteMatchQuery {
    /**
     * マッチを取得
     *
     * @param array $args クエリ引数
     * @return WP_Post[] マッチ投稿の配列
     */
    public static function get($args = []) {
        $defaults = [
            'post_type' => 'match_request',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query_args = wp_parse_args($args, $defaults);
        return get_posts($query_args);
    }

    /**
     * チームのマッチを取得
     *
     * @param int $team_id チームID
     * @param array $args 追加のクエリ引数
     * @return WP_Post[] マッチ投稿の配列
     */
    public static function getByTeam($team_id, $args = []) {
        $meta_query = [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ];

        if (!empty($args['meta_query'])) {
            $meta_query = array_merge($meta_query, $args['meta_query']);
        }

        $args['meta_query'] = $meta_query;
        return self::get($args);
    }

    /**
     * ステータスでマッチを取得
     *
     * @param string $status ステータス
     * @param array $args 追加のクエリ引数
     * @return WP_Post[] マッチ投稿の配列
     */
    public static function getByStatus($status, $args = []) {
        $meta_query = [
            [
                'key' => 'match_board_status',
                'value' => $status,
                'compare' => '='
            ]
        ];

        if (!empty($args['meta_query'])) {
            $meta_query = array_merge($meta_query, $args['meta_query']);
        }

        $args['meta_query'] = $meta_query;
        return self::get($args);
    }
}

class AidUniteTeamQuery {
    /**
     * チームを取得
     *
     * @param array $args クエリ引数
     * @return WP_Post[] チーム投稿の配列
     */
    public static function get($args = []) {
        $defaults = [
            'post_type' => 'team',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        $query_args = wp_parse_args($args, $defaults);
        return get_posts($query_args);
    }

    /**
     * ステータスでチームを取得
     *
     * @param string $status ステータス
     * @param array $args 追加のクエリ引数
     * @return WP_Post[] チーム投稿の配列
     */
    public static function getByStatus($status, $args = []) {
        $meta_query = [
            [
                'key' => 'team_status',
                'value' => $status,
                'compare' => '='
            ]
        ];

        if (!empty($args['meta_query'])) {
            $meta_query = array_merge($meta_query, $args['meta_query']);
        }

        $args['meta_query'] = $meta_query;
        return self::get($args);
    }
}
