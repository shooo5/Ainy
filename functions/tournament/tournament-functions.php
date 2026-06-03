<?php
/**
 * 大会機能 共通関数
 * 仕様: docs/specs/tournament-feature-spec.md
 */

if (!defined('ABSPATH')) {
    exit;
}

/** 大会説明・備考のデフォルト文（カスタマイズ可能） */
function aidunite_get_default_tournament_description() {
    return "【持ち物】\n・ユニフォーム\n・その他必要なもの\n\n【注意事項】\n・当日の遅刻・欠席は大会用チャットでご連絡ください。\n\n【その他】\n・追加の案内があれば記入してください。";
}

/** 対象レベル選択肢（プルダウン用） */
function aidunite_get_tournament_target_level_options() {
    return array(
        ''       => '指定なし',
        'beginner' => '初心者',
        'intermediate' => '中級',
        'advanced' => '上級',
        'competitive' => '強豪',
        'any'     => '問わない',
    );
}

/** 参加条件：地域（プルダウン用） */
function aidunite_get_tournament_participation_region_options() {
    return array(
        '' => '指定なし',
        'kanto' => '関東',
        'kansai' => '関西',
        'tokai' => '東海',
        'kyushu' => '九州',
        'nationwide' => '全国',
        'other' => 'その他',
    );
}

/** 参加条件：学年・年代（プルダウン用） */
function aidunite_get_tournament_participation_age_options() {
    return array(
        '' => '指定なし',
        'u12' => 'U12',
        'u15' => 'U15',
        'u18' => 'U18',
        'high_school' => '高校生',
        'university' => '大学生',
        'adult' => '一般',
        'other' => 'その他',
    );
}

/** イベント種別（大会／クリニック／練習会／交流会）プルダウン用 */
function aidunite_get_tournament_event_type_options() {
    return array(
        'tournament' => '大会',
        'clinic'     => 'クリニック',
        'practice'   => '練習会',
        'exchange'   => '交流会',
    );
}

/**
 * 公開中の大会一覧を取得（draft 以外）
 *
 * @param string|null $status_filter tournament_status: recruiting | confirmed | finished | null=すべて
 * @param array $args get_posts に渡す追加引数
 * @return WP_Post[]
 */
function aidunite_get_public_tournaments($status_filter = null, $args = array()) {
    $meta_query = array(
        array(
            'key'     => 'tournament_status',
            'value'   => 'draft',
            'compare' => '!=',
        ),
    );
    if ($status_filter) {
        $meta_query[] = array(
            'key'   => 'tournament_status',
            'value' => $status_filter,
        );
    }

    $defaults = array(
        'post_type'      => 'tournament',
        'post_status'     => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'meta_value',
        'meta_key'       => 'event_date_start',
        'order'          => 'ASC',
        'meta_query'     => $meta_query,
    );

    return get_posts(array_merge($defaults, $args));
}

/**
 * 大会の参加確定数を返す
 *
 * @param int $tournament_id
 * @return int
 */
function aidunite_tournament_accepted_count($tournament_id) {
    $participants = get_posts(array(
        'post_type'      => 'tournament_entry',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array('key' => 'tournament_id', 'value' => (int) $tournament_id),
            array('key' => 'participant_status', 'value' => 'accepted'),
        ),
    ));
    return is_array($participants) ? count($participants) : 0;
}

/**
 * 募集終了しているか（締切過ぎ or 定員到達）
 *
 * @param int $tournament_id
 * @return bool
 */
function aidunite_tournament_is_recruitment_closed($tournament_id) {
    $status = get_post_meta($tournament_id, 'tournament_status', true);
    if ($status !== 'recruiting') {
        return true;
    }
    $deadline = get_post_meta($tournament_id, 'application_deadline', true);
    if ($deadline && strtotime($deadline) < time()) {
        return true;
    }
    $capacity = (int) get_post_meta($tournament_id, 'capacity', true);
    if ($capacity && aidunite_tournament_accepted_count($tournament_id) >= $capacity) {
        return true;
    }
    return false;
}

/**
 * チームがこの大会に申込済み／参加確定か
 *
 * @param int $tournament_id
 * @param int $team_id
 * @return string|null applied | accepted | canceled または null（未申込）
 */
function aidunite_tournament_participant_status_for_team($tournament_id, $team_id) {
    $posts = get_posts(array(
        'post_type'      => 'tournament_entry',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array('key' => 'tournament_id', 'value' => (int) $tournament_id),
            array('key' => 'team_id', 'value' => (int) $team_id),
        ),
    ));
    if (empty($posts)) {
        return null;
    }
    return get_post_meta($posts[0], 'participant_status', true) ?: null;
}

/**
 * 参加確定チーム一覧（accepted のみ）
 *
 * @param int $tournament_id
 * @return array [ ['team_id' => id, 'team_name' => name ], ... ]
 */
function aidunite_tournament_accepted_teams($tournament_id) {
    $participants = get_posts(array(
        'post_type'      => 'tournament_entry',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array('key' => 'tournament_id', 'value' => (int) $tournament_id),
            array('key' => 'participant_status', 'value' => 'accepted'),
        ),
        'orderby'        => 'date',
        'order'          => 'ASC',
    ));
    $out = array();
    foreach ($participants as $p) {
        $team_id = (int) get_post_meta($p->ID, 'team_id', true);
        $team = $team_id ? get_post($team_id) : null;
        $out[] = array(
            'team_id'   => $team_id,
            'team_name' => $team ? $team->post_title : '',
        );
    }
    return $out;
}

/**
 * 大会のメタをまとめて取得（表示用）
 *
 * @param int $tournament_id
 * @return array
 */
function aidunite_get_tournament_display_meta($tournament_id) {
    $organizer_team_id = (int) get_post_meta($tournament_id, 'organizer_team_id', true);
    $organizer_team = $organizer_team_id ? get_post($organizer_team_id) : null;
    $organizer_name = get_post_meta($tournament_id, 'organizer_name', true);
    if (empty($organizer_name) && $organizer_team) {
        $organizer_name = $organizer_team->post_title;
    }
    return array(
        'tournament_status'          => get_post_meta($tournament_id, 'tournament_status', true),
        'event_type'                 => get_post_meta($tournament_id, 'event_type', true) ?: 'tournament',
        'event_date_start'           => get_post_meta($tournament_id, 'event_date_start', true),
        'event_date_end'             => get_post_meta($tournament_id, 'event_date_end', true),
        'venue_name'                 => get_post_meta($tournament_id, 'venue_name', true),
        'venue_address'              => get_post_meta($tournament_id, 'venue_address', true),
        'capacity'                   => (int) get_post_meta($tournament_id, 'capacity', true),
        'application_deadline'       => get_post_meta($tournament_id, 'application_deadline', true),
        'organizer_team_id'          => $organizer_team_id,
        'organizer_name'             => $organizer_name,
        'tournament_description'     => get_post_meta($tournament_id, 'tournament_description', true),
        'match_format'               => get_post_meta($tournament_id, 'match_format', true),
        'target_level'               => get_post_meta($tournament_id, 'target_level', true),
        'participation_region'       => get_post_meta($tournament_id, 'participation_region', true),
        'participation_age_group'    => get_post_meta($tournament_id, 'participation_age_group', true),
        'participation_conditions'   => get_post_meta($tournament_id, 'participation_conditions', true),
        'entry_fee_type'             => get_post_meta($tournament_id, 'entry_fee_type', true),
        'entry_fee_amount'           => get_post_meta($tournament_id, 'entry_fee_amount', true),
    );
}

/**
 * 参加確定時にチームのカレンダーへ仮スケジュールを自動登録する（既存 schedule 連携）
 * 大会詳細リンク・日時・会場を schedule_note に記載する。
 *
 * @param int $participant_id tournament_entry の post ID
 * @return int|null 作成した schedule の post ID。既に登録済み・未確定の場合は null
 */
function aidunite_ensure_tournament_schedule_for_participant($participant_id) {
    $participant = get_post($participant_id);
    if (!$participant || $participant->post_type !== 'tournament_entry') {
        return null;
    }
    $status = get_post_meta($participant_id, 'participant_status', true);
    if ($status !== 'accepted') {
        return null;
    }
    $existing_schedule_id = (int) get_post_meta($participant_id, 'tournament_schedule_id', true);
    if ($existing_schedule_id) {
        $existing = get_post($existing_schedule_id);
        if ($existing && $existing->post_type === 'schedule') {
            return null;
        }
    }

    $tournament_id = (int) get_post_meta($participant_id, 'tournament_id', true);
    $team_id = (int) get_post_meta($participant_id, 'team_id', true);
    if (!$tournament_id || !$team_id) {
        return null;
    }

    $tour = get_post($tournament_id);
    if (!$tour || $tour->post_type !== 'tournament') {
        return null;
    }

    $event_date_start = get_post_meta($tournament_id, 'event_date_start', true);
    $event_date_end = get_post_meta($tournament_id, 'event_date_end', true) ?: $event_date_start;
    $venue_name = get_post_meta($tournament_id, 'venue_name', true);
    $venue_address = get_post_meta($tournament_id, 'venue_address', true);
    $event_type = get_post_meta($tournament_id, 'event_type', true) ?: 'tournament';
    $event_type_labels = aidunite_get_tournament_event_type_options();
    $event_type_label = isset($event_type_labels[ $event_type ]) ? $event_type_labels[ $event_type ] : '大会';

    $tournament_url = get_permalink($tournament_id);
    $title = $tour->post_title . '（' . $event_type_label . '）';
    $note_parts = array();
    $note_parts[] = '大会詳細: ' . $tournament_url;
    $note_parts[] = '日時: ' . $event_date_start . (($event_date_end !== $event_date_start) ? ' ～ ' . $event_date_end : '');
    $note_parts[] = '会場: ' . $venue_name . ($venue_address ? '（' . $venue_address . '）' : '');
    $schedule_note = implode("\n", $note_parts);

    $post_author = (int) $participant->post_author;
    if (!$post_author) {
        $team = get_post($team_id);
        $post_author = $team ? (int) $team->post_author : get_current_user_id();
    }
    if (!$post_author) {
        $post_author = get_current_user_id();
    }

    $schedule_id = wp_insert_post(array(
        'post_type'   => 'schedule',
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_author' => $post_author,
    ));

    if (!$schedule_id || is_wp_error($schedule_id)) {
        return null;
    }

    update_post_meta($schedule_id, 'schedule_date', $event_date_start);
    update_post_meta($schedule_id, 'schedule_end_date', $event_date_end);
    update_post_meta($schedule_id, 'schedule_start_time', '09:00');
    update_post_meta($schedule_id, 'schedule_end_time', '17:00');
    update_post_meta($schedule_id, 'schedule_type', '大会');
    update_post_meta($schedule_id, 'schedule_place', $venue_name);
    update_post_meta($schedule_id, 'schedule_note', $schedule_note);
    update_post_meta($schedule_id, 'team_id', $team_id);
    update_post_meta($schedule_id, 'certainty', 'tentative');
    update_post_meta($schedule_id, 'intent', 'tournament');
    update_post_meta($schedule_id, 'tournament_id', $tournament_id);
    update_post_meta($schedule_id, 'tournament_participant_id', $participant_id);

    update_post_meta($participant_id, 'tournament_schedule_id', $schedule_id);

    return $schedule_id;
}

/**
 * チームの大会参加状況一覧（応募中・参加確定）を取得（マイページ表示用）
 *
 * @param int $team_id
 * @param int $limit 取得件数上限
 * @return array [ ['tournament_id'=>int, 'title'=>string, 'url'=>string, 'status'=>string, 'status_label'=>string], ... ]
 */
function aidunite_get_team_tournament_participations($team_id, $limit = 20) {
    if (!$team_id) {
        return array();
    }
    $posts = get_posts(array(
        'post_type'      => 'tournament_entry',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => array(
            array('key' => 'team_id', 'value' => (int) $team_id),
        ),
    ));

    $status_labels = array(
        'applied'  => '応募中',
        'accepted' => '参加確定',
        'canceled' => '辞退',
    );

    $result = array();
    foreach ($posts as $p) {
        $tid = (int) get_post_meta($p->ID, 'tournament_id', true);
        $status = get_post_meta($p->ID, 'participant_status', true) ?: 'applied';
        $tour = $tid ? get_post($tid) : null;
        if (!$tour || $tour->post_type !== 'tournament') {
            continue;
        }
        $result[] = array(
            'tournament_id'  => $tid,
            'participant_id' => $p->ID,
            'title'         => $tour->post_title,
            'url'           => get_permalink($tid),
            'status'        => $status,
            'status_label'  => isset($status_labels[ $status ]) ? $status_labels[ $status ] : $status,
        );
    }
    return $result;
}
