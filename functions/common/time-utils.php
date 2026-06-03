<?php
/**
 * 時間関連共通関数
 * 時間オプション生成と時間範囲処理の統合
 */

/**
 * 時間オプションリスト生成（15分刻み）
 *
 * @param int $step_minutes 刻み時間（分）
 * @param int $start_hour 開始時間（時）
 * @param int $end_hour 終了時間（時）
 * @param string $format 時間形式 ('H:i' | 'g:i A' | 'G:i')
 * @return array 時間オプション配列 ['value' => 'label']
 */
function aidunite_get_time_options($step_minutes = 15, $start_hour = 0, $end_hour = 24, $format = 'H:i') {
    $options = [];

    for ($hour = $start_hour; $hour < $end_hour; $hour++) {
        for ($minute = 0; $minute < 60; $minute += $step_minutes) {
            $time = sprintf('%02d:%02d', $hour, $minute);

            switch ($format) {
                case 'H:i':
                    $label = $time;
                    break;
                case 'g:i A':
                    $label = date('g:i A', strtotime($time));
                    break;
                case 'G:i':
                    $label = date('G:i', strtotime($time));
                    break;
                default:
                    $label = $time;
                    break;
            }

            $options[$time] = $label;
        }
    }

    return $options;
}

/**
 * 時間範囲オプション生成（00:00〜24:00の15分刻みペア）
 *
 * @param int $step_minutes 刻み時間（分）
 * @param string $format 時間形式 ('H:i' | 'g:i A' | 'G:i')
 * @param int $min_duration 最小継続時間（分）
 * @param int $max_duration 最大継続時間（分）
 * @return array 時間範囲オプション配列 ['start_time-end_time' => 'start_time - end_time']
 */
function aidunite_generate_time_range_options($step_minutes = 15, $format = 'H:i', $min_duration = 30, $max_duration = 240) {
    $options = [];
    $time_options = aidunite_get_time_options($step_minutes, 0, 24, $format);
    $time_keys = array_keys($time_options);

    for ($i = 0; $i < count($time_keys); $i++) {
        $start_time = $time_keys[$i];

        for ($j = $i + 1; $j < count($time_keys); $j++) {
            $end_time = $time_keys[$j];

            // 継続時間を計算
            $duration = aidunite_calculate_duration_minutes($start_time, $end_time);

            // 最小・最大継続時間の範囲内かチェック
            if ($duration >= $min_duration && $duration <= $max_duration) {
                $key = "{$start_time}-{$end_time}";
                $label = "{$time_options[$start_time]} - {$time_options[$end_time]}";
                $options[$key] = $label;
            }
        }
    }

    return $options;
}

/**
 * 時間範囲の重複チェック
 *
 * @param string $start1 開始時間1
 * @param string $end1 終了時間1
 * @param string $start2 開始時間2
 * @param string $end2 終了時間2
 * @return bool 重複している場合true
 */
function aidunite_check_time_overlap($start1, $end1, $start2, $end2) {
    $start1_minutes = aidunite_time_to_minutes($start1);
    $end1_minutes = aidunite_time_to_minutes($end1);
    $start2_minutes = aidunite_time_to_minutes($start2);
    $end2_minutes = aidunite_time_to_minutes($end2);

    return !($end1_minutes <= $start2_minutes || $end2_minutes <= $start1_minutes);
}

/**
 * 時間を分に変換
 *
 * @param string $time 時間文字列 ('H:i'形式)
 * @return int 分
 */
function aidunite_time_to_minutes($time) {
    $parts = explode(':', $time);
    return intval($parts[0]) * 60 + intval($parts[1]);
}

/**
 * 分を時間に変換
 *
 * @param int $minutes 分
 * @param string $format 出力形式 ('H:i' | 'g:i A')
 * @return string 時間文字列
 */
function aidunite_minutes_to_time($minutes, $format = 'H:i') {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;

    switch ($format) {
        case 'H:i':
            return sprintf('%02d:%02d', $hours, $mins);
        case 'g:i A':
            $time = sprintf('%02d:%02d', $hours, $mins);
            return date('g:i A', strtotime($time));
        default:
            return sprintf('%02d:%02d', $hours, $mins);
    }
}

/**
 * 2つの時間の間の継続時間を計算
 *
 * @param string $start_time 開始時間
 * @param string $end_time 終了時間
 * @return int 継続時間（分）
 */
function aidunite_calculate_duration_minutes($start_time, $end_time) {
    $start_minutes = aidunite_time_to_minutes($start_time);
    $end_minutes = aidunite_time_to_minutes($end_time);

    // 24時間を超える場合の処理
    if ($end_minutes < $start_minutes) {
        $end_minutes += 24 * 60; // 24時間を追加
    }

    return $end_minutes - $start_minutes;
}

/**
 * 時間範囲の調整オプション生成
 *
 * @param string $my_start 自分の開始時間
 * @param string $my_end 自分の終了時間
 * @param string $other_start 相手の開始時間
 * @param string $other_end 相手の終了時間
 * @return array 調整可能な時間範囲オプション
 */
function aidunite_get_adjustable_time_options($my_start, $my_end, $other_start, $other_end) {
    $options = [];

    // 重複時間帯を計算
    $overlap_start = max($my_start, $other_start);
    $overlap_end = min($my_end, $other_end);

    if (aidunite_time_to_minutes($overlap_start) < aidunite_time_to_minutes($overlap_end)) {
        // 15分刻みでオプション生成
        $step_minutes = 15;
        $current = $overlap_start;

        while (aidunite_time_to_minutes($current) < aidunite_time_to_minutes($overlap_end)) {
            $end_candidate = aidunite_minutes_to_time(
                aidunite_time_to_minutes($current) + $step_minutes
            );

            if (aidunite_time_to_minutes($end_candidate) <= aidunite_time_to_minutes($overlap_end)) {
                $key = "{$current}-{$end_candidate}";
                $label = "{$current} - {$end_candidate}";
                $options[$key] = $label;
            }

            $current = $end_candidate;
        }
    }

    return $options;
}
