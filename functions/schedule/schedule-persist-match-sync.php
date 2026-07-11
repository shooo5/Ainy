<?php
/**
 * マッチ成立・キャンセル時の schedule メタ更新（match-state-sync から呼ぶ正本）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 成立前の schedule フィールドを1回だけバックアップ
 *
 * @param int $schedule_id
 * @return bool 今回バックアップしたら true
 */
function aidunite_schedule_backup_pre_established_once($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_meta($schedule_id, 'pre_established_saved', true) === '1') {
        return false;
    }

    update_post_meta($schedule_id, 'pre_established_start_time', get_post_meta($schedule_id, 'schedule_start_time', true));
    update_post_meta($schedule_id, 'pre_established_end_time', get_post_meta($schedule_id, 'schedule_end_time', true));
    update_post_meta($schedule_id, 'pre_established_place', get_post_meta($schedule_id, 'schedule_place', true));
    update_post_meta($schedule_id, 'pre_established_place_option', get_post_meta($schedule_id, 'schedule_place_option', true));
    update_post_meta($schedule_id, 'pre_established_gender', get_post_meta($schedule_id, 'schedule_gender', true));
    update_post_meta($schedule_id, 'pre_established_male_slots', get_post_meta($schedule_id, 'male_slots', true));
    update_post_meta($schedule_id, 'pre_established_female_slots', get_post_meta($schedule_id, 'female_slots', true));
    update_post_meta($schedule_id, 'pre_established_place_lock', get_post_meta($schedule_id, 'place_lock', true));
    update_post_meta($schedule_id, 'pre_established_match_status', get_post_meta($schedule_id, 'match_status', true));
    update_post_meta($schedule_id, 'pre_established_match_opponent_team_id', get_post_meta($schedule_id, 'match_opponent_team_id', true));
    update_post_meta($schedule_id, 'pre_established_match_opponent_name', get_post_meta($schedule_id, 'match_opponent_name', true));
    update_post_meta($schedule_id, 'pre_established_saved', '1');

    return true;
}

/**
 * pre_established_* から schedule フィールドを復元しバックアップキーを削除
 *
 * @param int $schedule_id
 * @return bool バックアップありで復元したら true
 */
function aidunite_schedule_restore_pre_established_fields($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_meta($schedule_id, 'pre_established_saved', true) !== '1') {
        return false;
    }

    update_post_meta($schedule_id, 'schedule_start_time', get_post_meta($schedule_id, 'pre_established_start_time', true));
    update_post_meta($schedule_id, 'schedule_end_time', get_post_meta($schedule_id, 'pre_established_end_time', true));

    $restore_place = (string) (get_post_meta($schedule_id, 'pre_established_place', true)
        ?: get_post_meta($schedule_id, 'pre_established_place_option', true));
    if (function_exists('aidunite_schedule_write_place_meta')) {
        aidunite_schedule_write_place_meta($schedule_id, $restore_place);
    } else {
        update_post_meta($schedule_id, 'schedule_place', $restore_place);
    }

    if (function_exists('aidunite_schedule_write_gender_meta')) {
        aidunite_schedule_write_gender_meta($schedule_id, (string) get_post_meta($schedule_id, 'pre_established_gender', true));
    } else {
        update_post_meta($schedule_id, 'schedule_gender', get_post_meta($schedule_id, 'pre_established_gender', true));
    }

    $pre_male = get_post_meta($schedule_id, 'pre_established_male_slots', true);
    $pre_female = get_post_meta($schedule_id, 'pre_established_female_slots', true);
    if (function_exists('aidunite_schedule_write_recruit_slot_meta')) {
        aidunite_schedule_write_recruit_slot_meta($schedule_id, (int) $pre_male, (int) $pre_female);
    } else {
        update_post_meta($schedule_id, 'male_slots', $pre_male);
        update_post_meta($schedule_id, 'female_slots', $pre_female);
    }

    $pre_match_status = get_post_meta($schedule_id, 'pre_established_match_status', true);
    if ($pre_match_status === '' || $pre_match_status === null) {
        update_post_meta($schedule_id, 'match_status', 'planned');
    } else {
        update_post_meta($schedule_id, 'match_status', $pre_match_status);
    }

    $pre_opponent_team_id = get_post_meta($schedule_id, 'pre_established_match_opponent_team_id', true);
    $pre_opponent_name = get_post_meta($schedule_id, 'pre_established_match_opponent_name', true);
    if ($pre_opponent_team_id === '' || $pre_opponent_team_id === null) {
        delete_post_meta($schedule_id, 'match_opponent_team_id');
    } else {
        update_post_meta($schedule_id, 'match_opponent_team_id', $pre_opponent_team_id);
    }
    if ($pre_opponent_name === '' || $pre_opponent_name === null) {
        delete_post_meta($schedule_id, 'match_opponent_name');
    } else {
        update_post_meta($schedule_id, 'match_opponent_name', $pre_opponent_name);
    }

    $pre_lock = get_post_meta($schedule_id, 'pre_established_place_lock', true);
    if ($pre_lock === '' || $pre_lock === null) {
        delete_post_meta($schedule_id, 'place_lock');
    } else {
        update_post_meta($schedule_id, 'place_lock', $pre_lock);
    }

    delete_post_meta($schedule_id, 'pre_established_start_time');
    delete_post_meta($schedule_id, 'pre_established_end_time');
    delete_post_meta($schedule_id, 'pre_established_place');
    delete_post_meta($schedule_id, 'pre_established_place_option');
    delete_post_meta($schedule_id, 'pre_established_gender');
    delete_post_meta($schedule_id, 'pre_established_male_slots');
    delete_post_meta($schedule_id, 'pre_established_female_slots');
    delete_post_meta($schedule_id, 'pre_established_place_lock');
    delete_post_meta($schedule_id, 'pre_established_match_status');
    delete_post_meta($schedule_id, 'pre_established_match_opponent_team_id');
    delete_post_meta($schedule_id, 'pre_established_match_opponent_name');
    delete_post_meta($schedule_id, 'pre_established_saved');

    return true;
}

/**
 * バックアップなし時の match 関連メタを募集表示へ戻す
 *
 * @param int $schedule_id
 */
function aidunite_schedule_clear_established_match_display_meta($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return;
    }
    update_post_meta($schedule_id, 'match_status', 'planned');
    delete_post_meta($schedule_id, 'match_opponent_team_id');
    delete_post_meta($schedule_id, 'match_opponent_name');
    delete_post_meta($schedule_id, 'place_lock');
}

/**
 * pre_established_* から復元（§17.5.1 / §17.6）。参加側を recruit+matching に戻すオプション付き。
 *
 * @param int                  $schedule_id
 * @param array<string, mixed> $options set_recruit_matching
 * @return bool バックアップありで復元したら true
 */
function aidunite_schedule_restore_from_pre_established_backup($schedule_id, array $options = []) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return false;
    }

    $set_recruit_matching = !empty($options['set_recruit_matching']);
    $had_backup = aidunite_schedule_restore_pre_established_fields($schedule_id);

    if (!$had_backup) {
        aidunite_schedule_clear_established_match_display_meta($schedule_id);
    }

    if ($set_recruit_matching) {
        update_post_meta($schedule_id, 'intent', 'recruit');
        update_post_meta($schedule_id, 'matching', '1');
        update_post_meta($schedule_id, 'is_match_requested', '1');
        $my_date = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date((int) $schedule_id)
            : '';
        if ($my_date !== '') {
            wp_update_post([
                'ID' => $schedule_id,
                'post_title' => $my_date . ' 練習試合',
            ]);
        }
    }

    return $had_backup;
}

/**
 * 成立時: 申請側 my_schedule を確定練習試合表示へ
 *
 * @param int $my_schedule_id
 */
function aidunite_schedule_finalize_applicant_on_established($my_schedule_id) {
    $my_schedule_id = (int) $my_schedule_id;
    if ($my_schedule_id < 1) {
        return;
    }

    update_post_meta($my_schedule_id, 'intent', 'confirmed');
    update_post_meta($my_schedule_id, 'matching', '0');
    update_post_meta($my_schedule_id, 'is_match_requested', '0');
    update_post_meta($my_schedule_id, 'certainty', 'firm');

    $my_bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($my_schedule_id)
        : [];
    $my_date = (string) ($my_bundle['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date((int) $my_schedule_id)
        : ''));
    wp_update_post([
        'ID' => $my_schedule_id,
        'post_title' => $my_date !== '' ? ($my_date . ' 練習試合') : '練習試合',
    ]);
}

/**
 * 成立時: 確定した時間・会場・性別を schedule に反映
 *
 * @param int                  $schedule_id
 * @param array<string, mixed> $args selected_start, selected_end, selected_place, selected_gender,
 *                                 apply_gender (bool), my_schedule_id (申請側のみ性別反映用)
 */
function aidunite_schedule_apply_established_selection($schedule_id, array $args) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return;
    }

    $selected_start = (string) ($args['selected_start'] ?? '');
    $selected_end = (string) ($args['selected_end'] ?? '');
    $selected_place = (string) ($args['selected_place'] ?? '');
    $selected_gender = (string) ($args['selected_gender'] ?? '');
    $apply_gender = !empty($args['apply_gender']);
    $my_schedule_id = (int) ($args['my_schedule_id'] ?? 0);

    if ($selected_start !== '') {
        update_post_meta($schedule_id, 'schedule_start_time', $selected_start);
    }
    if ($selected_end !== '') {
        update_post_meta($schedule_id, 'schedule_end_time', $selected_end);
    }

    if ($apply_gender && $my_schedule_id > 0 && $schedule_id === $my_schedule_id
        && in_array($selected_gender, ['male', 'female', 'both'], true)) {
        if (function_exists('aidunite_schedule_write_gender_meta')) {
            aidunite_schedule_write_gender_meta($schedule_id, $selected_gender);
        } else {
            update_post_meta($schedule_id, 'schedule_gender', $selected_gender);
        }
    }

    if (in_array($selected_place, ['home', 'away', 'either'], true)) {
        $place_for_schedule = (string) ($args['place_for_schedule'] ?? $selected_place);
        if (function_exists('aidunite_schedule_write_place_meta')) {
            aidunite_schedule_write_place_meta($schedule_id, $place_for_schedule);
        } else {
            update_post_meta($schedule_id, 'schedule_place', $place_for_schedule);
        }
        if (in_array($place_for_schedule, ['home', 'away', 'either'], true)) {
            update_post_meta($schedule_id, 'venue_name', '');
        }
    }
}

/**
 * 相手のみ申請で自動作成された schedule か（申請前に存在しなかった my_schedule）
 *
 * @param int $schedule_id
 * @return bool
 */
function aidunite_schedule_is_match_apply_tentative_origin($schedule_id) {
    $schedule_id = (int) $schedule_id;

    return $schedule_id > 0
        && (string) get_post_meta($schedule_id, 'aidunite_schedule_origin', true) === 'match_apply_tentative';
}

/**
 * 成立後キャンセル時: 相手のみ申請の仮 schedule を物理削除（my_schedule のみ）
 *
 * @param int $schedule_id     処理対象
 * @param int $my_schedule_id  当該 MR の申請側 schedule
 * @return bool 削除したら true
 */
/**
 * 申請失敗・未採用時: match_apply_tentative 由来の孤児 schedule を削除（MR 未紐づけのみ）
 *
 * @param int $schedule_id
 * @return bool 削除したら true
 */
function aidunite_schedule_rollback_orphan_match_apply_tentative($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || !function_exists('aidunite_schedule_is_match_apply_tentative_origin')
        || !aidunite_schedule_is_match_apply_tentative_origin($schedule_id)) {
        return false;
    }

    $linked_mr = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'my_schedule_id',
                'value'   => (string) $schedule_id,
                'compare' => '=',
            ],
        ],
    ]);
    if (!empty($linked_mr)) {
        return false;
    }

    $deleted = function_exists('aidunite_schedule_delete_match_apply_tentative_on_established_cancel')
        ? aidunite_schedule_delete_match_apply_tentative_on_established_cancel($schedule_id, $schedule_id)
        : (bool) wp_delete_post($schedule_id, true);

    if ($deleted && function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('rollback_orphan_match_apply_tentative', [
            'schedule_id' => $schedule_id,
        ]);
    }

    return $deleted;
}

function aidunite_schedule_delete_match_apply_tentative_on_established_cancel($schedule_id, $my_schedule_id) {
    $schedule_id = (int) $schedule_id;
    $my_schedule_id = (int) $my_schedule_id;
    if ($schedule_id < 1 || $my_schedule_id < 1 || $schedule_id !== $my_schedule_id) {
        return false;
    }
    if (!aidunite_schedule_is_match_apply_tentative_origin($schedule_id)) {
        return false;
    }

    if (function_exists('aidunite_perform_safe_schedule_deletion')) {
        $result = aidunite_perform_safe_schedule_deletion($schedule_id);
        if (!is_wp_error($result)) {
            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('delete_match_apply_tentative_schedule', [
                    'schedule_id' => $schedule_id,
                    'my_schedule_id' => $my_schedule_id,
                ]);
            }
            return true;
        }
    }

    if (function_exists('delete_schedule')) {
        $legacy = delete_schedule($schedule_id);
        return !empty($legacy['success']);
    }

    return (bool) wp_delete_post($schedule_id, true);
}

/**
 * ロールバック: intent / matching を募集へ戻す（仮 schedule 自動作成分は削除側で処理）
 *
 * @param int $schedule_id
 * @return string 復元後の intent
 */
function aidunite_schedule_rollback_intent_after_established_cancel($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return '';
    }

    if (aidunite_schedule_is_match_apply_tentative_origin($schedule_id)) {
        return 'tentative';
    }

    update_post_meta($schedule_id, 'intent', 'recruit');
    update_post_meta($schedule_id, 'certainty', 'firm');

    $intent = (string) get_post_meta($schedule_id, 'intent', true);
    if ($intent === 'recruit') {
        $slots_remain = true;
        if (function_exists('aidunite_get_remaining_gender_slots')) {
            $rem = aidunite_get_remaining_gender_slots($schedule_id);
            $canon = function_exists('aidunite_market_recruitment_gender_canonical')
                ? aidunite_market_recruitment_gender_canonical($schedule_id)
                : '';
            if ($canon === 'male') {
                $slots_remain = ((int) ($rem['male'] ?? 0)) > 0;
            } elseif ($canon === 'female') {
                $slots_remain = ((int) ($rem['female'] ?? 0)) > 0;
            } else {
                $slots_remain = ((int) ($rem['male'] ?? 0)) > 0 || ((int) ($rem['female'] ?? 0)) > 0;
            }
        }
        if ($slots_remain) {
            update_post_meta($schedule_id, 'matching', '1');
            update_post_meta($schedule_id, 'is_match_requested', '1');
        }
    }

    return $intent;
}

/**
 * match_apply_tentative 由来 schedule の matching フラグを維持
 *
 * @param int $schedule_id
 */
function aidunite_schedule_write_match_apply_tentative_flags($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return;
    }
    update_post_meta($schedule_id, 'matching', '1');
    update_post_meta($schedule_id, 'is_match_requested', '1');
}
