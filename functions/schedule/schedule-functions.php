<?php
/**
 * スケジュール関連の共通関数
 */

/**
 * チームの team_gender_option を募集の gender_condition 初期値へ（MVP: male|female のみ）。
 *
 * @param string $team_gender team メタ team_gender_option の生値
 * @return string 空文字 または male|female
 */
function aidunite_team_gender_option_to_schedule_gender_condition($team_gender) {
    if (function_exists('aidunite_normalize_team_gender_option')) {
        return aidunite_normalize_team_gender_option($team_gender);
    }
    $g = is_string($team_gender) ? trim($team_gender) : '';
    if (in_array($g, ['male', 'female'], true)) {
        return $g;
    }
    return '';
}

/**
 * 全スケジュールを取得する共通関数
 *
 * @param array $args 追加のクエリ引数
 * @return array スケジュールの投稿配列
 */
function get_all_schedules($args = []) {
    $default_args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_key' => 'schedule_date',
        'orderby' => 'meta_value',
        'order' => 'ASC'
    ];

    return get_posts(array_merge($default_args, $args));
}

/**
 * スケジュールの基本情報を取得
 *
 * @param int $schedule_id スケジュールID
 * @return array スケジュール情報
 */
function get_schedule_basic_info($schedule_id) {
    $author_id = get_post_field('post_author', $schedule_id);
    $team_name = get_the_author_meta('display_name', $author_id);

    return [
        'id' => $schedule_id,
        'team_name' => $team_name,
        'team_id' => get_post_meta($schedule_id, 'team_id', true),
        'date' => get_post_meta($schedule_id, 'schedule_date', true),
        'start_time' => get_post_meta($schedule_id, 'schedule_start_time', true),
        'end_time' => get_post_meta($schedule_id, 'schedule_end_time', true),
        'time' => get_post_meta($schedule_id, 'schedule_time', true),
        // 旧キーに統一
        'place' => get_post_meta($schedule_id, 'schedule_place', true),
        'type' => get_post_meta($schedule_id, 'schedule_type', true),
        'note' => get_post_meta($schedule_id, 'schedule_note', true),
        'matching_request' => get_post_meta($schedule_id, 'matching', true), // 統一されたキー名を使用
        // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
        'place_option' => get_post_meta($schedule_id, 'schedule_place', true) ?: get_post_meta($schedule_id, 'schedule_place_option', true),
        'gender_condition' => get_post_meta($schedule_id, 'schedule_gender', true) ?: get_post_meta($schedule_id, 'matching_gender_condition', true),
        'title' => get_post_meta($schedule_id, 'schedule_title', true) ?: 'スケジュール'
    ];
}

/**
 * スケジュール一覧テーブルのヘッダーを出力
 *
 * @param bool $is_admin 管理者用かどうか
 */
function output_schedule_table_header($is_admin = false) {
    ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: var(--bg-secondary);">
                <?php if ($is_admin): ?>
                    <th><input type="checkbox" onclick="toggleAll(this)"></th>
                    <th>投稿ID</th>
                    <th>チームID</th>
                <?php endif; ?>
                <th>スケジュール名</th>
                <th>チーム名</th>
                <th>日付</th>
                <th>時間</th>
                <th>会場</th>
                <?php if ($is_admin): ?>
                    <th>種別</th>
                    <th>備考</th>
                    <th>会場条件</th>
                    <th>性別条件</th>
                <?php endif; ?>
                <th>募集中</th>
                <?php if (!$is_admin): ?>
                    <th>編集</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
    <?php
}

/**
 * スケジュール一覧テーブルのフッターを出力
 */
function output_schedule_table_footer() {
    ?>
        </tbody>
    </table>
    <?php
}

/**
 * スケジュール一覧テーブルの行を出力
 *
 * @param array $schedule_info スケジュール情報
 * @param bool $is_admin 管理者用かどうか
 */
function output_schedule_table_row($schedule_info, $is_admin = false) {
    ?>
    <tr style="border-bottom: 1px solid #ddd;">
        <?php if ($is_admin): ?>
            <td><input type="checkbox" name="schedule_ids[]" value="<?= esc_attr($schedule_info['id']); ?>"></td>
            <td><?= esc_html($schedule_info['id']); ?></td>
            <td><?= esc_html($schedule_info['team_id']); ?></td>
        <?php endif; ?>
        <td style="padding:8px;"><?= esc_html($schedule_info['title']); ?></td>
        <td><?= esc_html($schedule_info['team_name']); ?></td>
        <td><?= esc_html($schedule_info['date']); ?></td>
        <td><?= esc_html($schedule_info['start_time'] && $schedule_info['end_time'] ?
            $schedule_info['start_time'] . '〜' . $schedule_info['end_time'] :
            $schedule_info['time']); ?></td>
        <td><?= esc_html($schedule_info['place_option'] ?: $schedule_info['place']); ?></td>
        <?php if ($is_admin): ?>
            <td><?= esc_html($schedule_info['type']); ?></td>
            <td><?= esc_html($schedule_info['note']); ?></td>
            <td><?= esc_html($schedule_info['place_option']); ?></td>
            <td><?= esc_html($schedule_info['gender_condition']); ?></td>
        <?php endif; ?>
        <td><?= $schedule_info['matching_request'] ? '✅' : '❌'; ?></td>
        <?php if (!$is_admin): ?>
            <td>
                <a href="<?= get_edit_post_link($schedule_info['id']); ?>" class="btn btn-sm btn-primary">編集</a>
            </td>
        <?php endif; ?>
    </tr>
    <?php
}

/**
 * スケジュールを検索・フィルタリング
 *
 * @param array $search_params 検索パラメータ
 * @param int $user_id ユーザーID（権限チェック用）
 * @return array フィルタリングされたスケジュール
 */
function search_schedules($search_params = [], $user_id = null) {
    $args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [],
        'date_query' => []
    ];

    // 検索クエリ
    if (!empty($search_params['search'])) {
        $args['s'] = sanitize_text_field($search_params['search']);
    }

    // 種別フィルター
    if (!empty($search_params['type'])) {
        $args['meta_query'][] = [
            'key' => 'schedule_type',
            'value' => sanitize_text_field($search_params['type']),
            'compare' => '='
        ];
    }

    // 日付フィルター
    if (!empty($search_params['year']) && !empty($search_params['month'])) {
        $args['date_query'][] = [
            'year' => intval($search_params['year']),
            'month' => intval($search_params['month'])
        ];
    }

    // チームフィルター（チーム代表者の場合）
    if ($user_id) {
        $user_role = aidunite_get_effective_user_role($user_id)[0];
        if ($user_role === 'team_leader') {
            $user_teams = get_posts([
                'post_type' => 'team',
                'author' => $user_id,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);

            if (!empty($user_teams)) {
                $args['meta_query'][] = [
                    'key' => 'team_id',
                    'value' => $user_teams,
                    'compare' => 'IN'
                ];
            }
        }
    }

    // 並び順
    if (!empty($search_params['orderby'])) {
        $args['orderby'] = $search_params['orderby'];
        $args['order'] = $search_params['order'] ?? 'ASC';
    } else {
        $args['meta_key'] = 'schedule_date';
        $args['orderby'] = 'meta_value';
        $args['order'] = 'ASC';
    }

    return get_posts($args);
}

/**
 * カレンダー用のスケジュールデータを取得
 *
 * @param int $year 年
 * @param int $month 月
 * @param int $user_id ユーザーID
 * @return array カレンダーデータ
 */
function get_calendar_schedule_data($year, $month, $user_id = null) {
    $search_params = [
        'year' => $year,
        'month' => $month
    ];

    $schedules = search_schedules($search_params, $user_id);
    $calendar_data = [];

    foreach ($schedules as $schedule) {
        $schedule_info = get_schedule_basic_info($schedule->ID);
        $date = $schedule_info['date'];

        if ($date) {
            $day = date('j', strtotime($date));
            if (!isset($calendar_data[$day])) {
                $calendar_data[$day] = [];
            }

            $calendar_data[$day][] = [
                'id' => $schedule->ID,
                'title' => $schedule_info['title'],
                'type' => $schedule_info['type'],
                'time' => $schedule_info['start_time'] . ' - ' . $schedule_info['end_time'],
                'place' => $schedule_info['place_option'] ?: $schedule_info['place'],
                'note' => $schedule_info['note']
            ];
        }
    }

    return $calendar_data;
}

/**
 * スケジュール統計情報を取得
 *
 * @param int $user_id ユーザーID
 * @param array $period 期間（開始日、終了日）
 * @return array 統計情報
 */
function get_schedule_statistics($user_id = null, $period = []) {
    $args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ];

    // 期間フィルター
    if (!empty($period['start']) && !empty($period['end'])) {
        $args['date_query'] = [
            [
                'after' => $period['start'],
                'before' => $period['end'],
                'inclusive' => true
            ]
        ];
    }

    // ユーザー権限フィルター
    if ($user_id) {
        $user_role = aidunite_get_effective_user_role($user_id)[0];
        if ($user_role === 'team_leader') {
            $user_teams = get_posts([
                'post_type' => 'team',
                'author' => $user_id,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);

            if (!empty($user_teams)) {
                $args['meta_query'][] = [
                    'key' => 'team_id',
                    'value' => $user_teams,
                    'compare' => 'IN'
                ];
            }
        }
    }

    $schedules = get_posts($args);

    $stats = [
        'total' => count($schedules),
        'by_type' => [],
        'by_month' => [],
        'matching_requests' => 0
    ];

    foreach ($schedules as $schedule) {
        $schedule_info = get_schedule_basic_info($schedule->ID);

        // 種別別統計
        $type = $schedule_info['type'] ?: 'その他';
        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = 0;
        }
        $stats['by_type'][$type]++;

        // 月別統計
        if ($schedule_info['date']) {
            $month = date('Y-m', strtotime($schedule_info['date']));
            if (!isset($stats['by_month'][$month])) {
                $stats['by_month'][$month] = 0;
            }
            $stats['by_month'][$month]++;
        }

        // マッチング希望数
        if ($schedule_info['matching_request']) {
            $stats['matching_requests']++;
        }
    }

    return $stats;
}

/**
 * スケジュール登録時の通知送信
 *
 * @param int $schedule_id スケジュールID
 * @param array $notification_settings 通知設定
 * @return bool 通知送信成功時true
 */
function aidunite_notify_schedule_registration($schedule_id, $notification_settings = []) {
    $schedule_info = get_schedule_basic_info($schedule_id);
    $team_id = $schedule_info['team_id'];

    if (!$team_id) {
        error_log("❌ スケジュール通知失敗：team_idが未設定 schedule_id={$schedule_id}");
        return false;
    }

    // 通知設定のデフォルト値
    $notify_parents = $notification_settings['notify_parents'] ?? true;
    $notify_players = $notification_settings['notify_players'] ?? true;

    $success_count = 0;
    $total_count = 0;

    // 保護者への通知
    if ($notify_parents) {
        $parent_users = aidunite_get_team_parents($team_id);
        foreach ($parent_users as $parent_user) {
            $result = aidunite_notify_schedule_to_user($parent_user->ID, $schedule_info, 'parent');
            if ($result) $success_count++;
            $total_count++;
        }
    }

    // 選手への通知
    if ($notify_players) {
        $player_users = aidunite_get_team_players($team_id);
        foreach ($player_users as $player_user) {
            $result = aidunite_notify_schedule_to_user($player_user->ID, $schedule_info, 'player');
            if ($result) $success_count++;
            $total_count++;
        }
    }

    error_log("📅 スケジュール通知完了：schedule_id={$schedule_id}, 成功={$success_count}/{$total_count}");
    return $success_count > 0;
}

/**
 * 個別ユーザーへのスケジュール通知
 *
 * @param int $user_id ユーザーID
 * @param array $schedule_info スケジュール情報
 * @param string $user_type ユーザータイプ ('parent' | 'player')
 * @return bool 通知成功時true
 */
function aidunite_notify_schedule_to_user($user_id, $schedule_info, $user_type) {
    $user = get_userdata($user_id);
    if (!$user) {
        error_log("❌ スケジュール通知失敗：無効なユーザーID {$user_id}");
        return false;
    }

    $date = $schedule_info['date'];
    $type = $schedule_info['type'];
    $place = $schedule_info['place_option'] ?: $schedule_info['place'];
    $note = $schedule_info['note'];
    $title = $schedule_info['title'];
    $date_label = function_exists('aidunite_format_notification_date')
        ? aidunite_format_notification_date($date)
        : $date;

    // 通知メッセージの作成
    $message = "📅 新しいスケジュールが登録されました\n\n";
    $message .= "【{$title}】\n";
    $message .= '日付: ' . ($date_label !== '' ? $date_label : '—') . "\n";
    $message .= "種別: {$type}\n";
    if ($place) {
        $message .= "場所: {$place}\n";
    }
    if ($note) {
        $message .= "備考: {$note}\n";
    }

    // 通知の送信
    $notification_data = [
        'user_id' => $user_id,
        'type' => 'schedule_registration',
        'title' => '新しいスケジュールが登録されました',
        'message' => $message,
        'data' => [
            'schedule_id' => $schedule_info['id'],
            'schedule_title' => $title,
            'schedule_date' => $date
        ]
    ];

    return aidunite_create_notification($notification_data);
}

/**
 * チームの保護者一覧を取得
 *
 * @param int $team_id チームID
 * @return array 保護者ユーザー配列
 */
function aidunite_get_team_parents($team_id) {
    $players = get_posts([
        'post_type' => 'player',
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1
    ]);

    $parent_ids = [];
    foreach ($players as $player) {
        $parent_id = get_post_meta($player->ID, 'parent_user_id', true);
        if ($parent_id && !in_array($parent_id, $parent_ids)) {
            $parent_ids[] = $parent_id;
        }
    }

    if (empty($parent_ids)) {
        return [];
    }

    return get_users([
        'include' => $parent_ids,
        'orderby' => 'display_name'
    ]);
}

/**
 * チームの選手一覧を取得
 *
 * @param int $team_id チームID
 * @return array 選手ユーザー配列
 */
function aidunite_get_team_players($team_id) {
    $players = get_posts([
        'post_type' => 'player',
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1
    ]);

    $player_ids = [];
    foreach ($players as $player) {
        $player_user_id = get_post_meta($player->ID, 'user_id', true);
        if ($player_user_id && !in_array($player_user_id, $player_ids)) {
            $player_ids[] = $player_user_id;
        }
    }

    if (empty($player_ids)) {
        return [];
    }

    return get_users([
        'include' => $player_ids,
        'orderby' => 'display_name'
    ]);
}

/**
 * スケジュールテンプレートを保存
 *
 * @param array $template_data テンプレートデータ
 * @param int $user_id ユーザーID
 * @return int|false 保存成功時テンプレートID、失敗時false
 */
function aidunite_save_schedule_template($template_data, $user_id) {
    $template_name = sanitize_text_field($template_data['name']);
    $template_content = wp_kses_post($template_data['content']);

    $post_data = [
        'post_title' => $template_name,
        'post_content' => $template_content,
        'post_type' => 'schedule_template',
        'post_status' => 'publish',
        'post_author' => $user_id
    ];

    $template_id = wp_insert_post($post_data);

    if ($template_id && !is_wp_error($template_id)) {
        // テンプレートメタデータを保存
        update_post_meta($template_id, 'template_type', $template_data['type'] ?? 'custom');
        update_post_meta($template_id, 'template_category', $template_data['category'] ?? 'general');

        return $template_id;
    }

    return false;
}

/**
 * ユーザーのスケジュールテンプレート一覧を取得
 *
 * @param int $user_id ユーザーID
 * @return array テンプレート配列
 */
function aidunite_get_user_schedule_templates($user_id) {
    $templates = get_posts([
        'post_type' => 'schedule_template',
        'author' => $user_id,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    $template_list = [];
    foreach ($templates as $template) {
        $template_list[] = [
            'id' => $template->ID,
            'name' => $template->post_title,
            'content' => $template->post_content,
            'type' => get_post_meta($template->ID, 'template_type', true),
            'category' => get_post_meta($template->ID, 'template_category', true),
            'created' => $template->post_date
        ];
    }

    return $template_list;
}

/**
 * スケジュールをCSV形式でエクスポート
 *
 * @param array $schedules スケジュール配列
 * @return string CSVデータ
 */
function aidunite_export_schedules_to_csv($schedules) {
    $csv_data = [];

    // ヘッダー行
    $csv_data[] = [
        'ID',
        'スケジュール名',
        'チーム名',
        '日付',
        '開始時間',
        '終了時間',
        '種別',
        '場所',
        '場所オプション',
        '備考',
        'マッチング希望',
        '性別条件',
        '作成日'
    ];

    // データ行
    foreach ($schedules as $schedule) {
        $schedule_info = get_schedule_basic_info($schedule->ID);

        $csv_data[] = [
            $schedule->ID,
            $schedule_info['title'],
            $schedule_info['team_name'],
            $schedule_info['date'],
            $schedule_info['start_time'],
            $schedule_info['end_time'],
            $schedule_info['type'],
            $schedule_info['place'],
            $schedule_info['place_option'],
            $schedule_info['note'],
            $schedule_info['matching_request'] ? 'はい' : 'いいえ',
            $schedule_info['gender_condition'],
            $schedule->post_date
        ];
    }

    // CSV文字列を生成
    $csv_string = '';
    foreach ($csv_data as $row) {
        $csv_string .= implode(',', array_map(function($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $row)) . "\n";
    }

    return $csv_string;
}

/**
 * 古いスケジュールをアーカイブ
 *
 * @param int $days_ago 何日前からアーカイブするか
 * @return int アーカイブされたスケジュール数
 */
function aidunite_archive_old_schedules($days_ago = 30) {
    $cutoff_date = date('Y-m-d', strtotime("-{$days_ago} days"));

    $old_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'schedule_date',
                'value' => $cutoff_date,
                'compare' => '<',
                'type' => 'DATE'
            ]
        ],
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $archived_count = 0;
    foreach ($old_schedules as $schedule_id) {
        $result = wp_update_post([
            'ID' => $schedule_id,
            'post_status' => 'private'
        ]);

        if ($result && !is_wp_error($result)) {
            update_post_meta($schedule_id, 'archived_date', current_time('mysql'));
            $archived_count++;
        }
    }

    error_log("📦 スケジュールアーカイブ完了：{$archived_count}件をアーカイブ");
    return $archived_count;
}

// 出欠関連の旧スタブは削除済み。実装は functions/attendance/（attendance-functions.php・attendance-notification.php）を参照。

/**
 * スケジュール保存時の自動昇格ロジック（試合系の状態管理）
 *
 * @param int $post_id 投稿ID
 * @param WP_Post $post 投稿オブジェクト
 * @param bool $update 更新かどうか
 */
function aidunite_schedule_auto_status_update($post_id, $post, $update) {
    // スケジュール投稿タイプのみ処理
    if ($post->post_type !== 'schedule') {
        return;
    }

    // 自動保存は除外
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // 試合系の種別を取得
    $schedule_type = get_post_meta($post_id, 'schedule_type', true);

    // 試合系（practice_match, joint_practice）のみ処理
    if (!in_array($schedule_type, ['practice_match', 'joint_practice'])) {
        return;
    }

    // 相手情報の確認
    $has_opponent = false;
    $opponent_team_id = get_post_meta($post_id, 'match_opponent_team_id', true);
    $opponent_name = get_post_meta($post_id, 'match_opponent_name', true);

    if (!empty($opponent_team_id) || !empty($opponent_name)) {
        $has_opponent = true;
    }

    // 会場情報の確認
    $has_place = !empty(get_post_meta($post_id, 'schedule_place', true));

    // 時間情報の確認
    $start_time = get_post_meta($post_id, 'schedule_start_time', true);
    $end_time = get_post_meta($post_id, 'schedule_end_time', true);
    $has_time = !empty($start_time) && !empty($end_time);

    // 状態の自動決定
    $new_status = 'planned'; // デフォルト

    if ($has_opponent && $has_place && $has_time) {
        $new_status = 'confirmed';
    } elseif ($has_opponent) {
        $new_status = 'matched';
    }

    // 現在の状態を取得
    $current_status = get_post_meta($post_id, 'match_status', true);

    // 状態が変更された場合のみ更新
    if ($current_status !== $new_status) {
        update_post_meta($post_id, 'match_status', $new_status);

        // ログ出力
        error_log("🔄 スケジュール状態自動更新：schedule_id={$post_id}, {$current_status} → {$new_status}");

        // 状態変更フック（通知等で使用）
        do_action('aidunite_schedule_status_changed', $post_id, $current_status, $new_status);
    }
}
add_action('save_post', 'aidunite_schedule_auto_status_update', 10, 3);

/**
 * ユーザーのスケジュールを期間指定で取得（REST API用）
 *
 * @param string $start_date 開始日（YYYY-MM-DD）
 * @param string $end_date 終了日（YYYY-MM-DD）
 * @param int   $user_id ユーザーID
 * @param array $fetch_args scope: operating|managed, team_id: int（managed 時の絞り込み）
 * @return array スケジュール配列（UI用DTO）
 */
function aidunite_get_user_schedules_by_date_range($start_date, $end_date, $user_id = null, $fetch_args = []) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return [];
    }

    // 日付形式の検証
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        return [];
    }

    $team_clause = aidunite_resolve_schedule_fetch_team_meta_query($user_id, $fetch_args);
    if ($team_clause === null) {
        return [];
    }

    // スケジュールを取得（操作中チーム分のみ）
    $args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            $team_clause,
            [
                'key' => 'schedule_date',
                'value' => [$start_date, $end_date],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ];

    $schedules = get_posts($args);
    $formatted_schedules = [];

    foreach ($schedules as $schedule) {
        // プライベート予定は登録者本人にのみ表示
        $is_personal = get_post_meta($schedule->ID, 'is_personal', true);
        if ($is_personal === '1' && (int) $schedule->post_author !== (int) $user_id) {
            continue;
        }
        $schedule_data = aidunite_format_schedule_for_ui($schedule);
        if ($schedule_data) {
            $formatted_schedules[] = $schedule_data;
        }
    }

    return $formatted_schedules;
}

/**
 * スケジュール取得用 team_id meta_query（operating / managed / 単一チーム絞り込み）
 *
 * @param int   $user_id
 * @param array $fetch_args scope, team_id
 * @return array|null
 */
function aidunite_resolve_schedule_fetch_team_meta_query($user_id, $fetch_args = []) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    $scope = isset($fetch_args['scope']) ? strtolower(trim((string) $fetch_args['scope'])) : 'operating';
    $team_id_filter = isset($fetch_args['team_id']) ? (int) $fetch_args['team_id'] : 0;

    if ($team_id_filter > 0) {
        if (function_exists('aidunite_user_has_managed_team_access')
            && !aidunite_user_has_managed_team_access($user_id, $team_id_filter)) {
            return null;
        }
        return [
            'key'     => 'team_id',
            'value'   => $team_id_filter,
            'compare' => '=',
        ];
    }

    if ($scope === 'managed' && function_exists('aidunite_schedule_team_meta_query_for_user')) {
        return aidunite_schedule_team_meta_query_for_user($user_id);
    }

    if (function_exists('aidunite_schedule_team_meta_query_for_operating_team')) {
        return aidunite_schedule_team_meta_query_for_operating_team($user_id);
    }

    return null;
}

/**
 * カレンダー／リスト用の相手表示（複数参加時は「先頭名＋他N」）
 *
 * @param int $schedule_id
 * @return string
 */
function aidunite_schedule_opponent_display_for_ui($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return '';
    }

    $labels = [];

    $csv = get_post_meta($schedule_id, 'participants', true);
    if (is_string($csv) && trim($csv) !== '') {
        $parts = preg_split('/\s*,\s*/', trim($csv));
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (is_numeric($part)) {
                $tid = (int) $part;
                $title = $tid > 0 ? get_the_title($tid) : '';
                if ($title !== '') {
                    $labels[] = $title;
                }
            } else {
                $labels[] = $part;
            }
        }
    }

    if (empty($labels)) {
        $opponent_team_id = (int) get_post_meta($schedule_id, 'match_opponent_team_id', true);
        $opponent_name = trim((string) get_post_meta($schedule_id, 'match_opponent_name', true));
        if ($opponent_name !== '') {
            $labels[] = $opponent_name;
        } elseif ($opponent_team_id > 0) {
            $title = get_the_title($opponent_team_id);
            if ($title !== '') {
                $labels[] = $title;
            }
        }
    }

    $labels = array_values(array_unique(array_filter($labels)));
    if (count($labels) === 0) {
        return '';
    }
    if (count($labels) === 1) {
        return $labels[0];
    }

    return $labels[0] . '他' . (count($labels) - 1);
}

/**
 * スケジュール UI 用：管理チーム一覧（フィルタチップ用）
 *
 * @param int $user_id
 * @return array<int, array{id:int, name:string, gender_label:string}>
 */
function aidunite_get_managed_teams_for_schedule_ui($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !function_exists('aidunite_get_managed_team_ids')) {
        return [];
    }
    $ids = aidunite_get_managed_team_ids($user_id);
    if (empty($ids)) {
        return [];
    }
    $teams = [];
    foreach ($ids as $tid) {
        $tid = (int) $tid;
        if ($tid <= 0) {
            continue;
        }
        $post = get_post($tid);
        if (!$post || $post->post_type !== 'team') {
            continue;
        }
        $gender_raw = get_post_meta($tid, 'team_gender_option', true);
        $gender_label = function_exists('aidunite_team_gender_label')
            ? aidunite_team_gender_label($gender_raw)
            : '';
        if ($gender_label === '—') {
            $gender_label = '';
        }
        $teams[] = [
            'id'            => $tid,
            'name'          => $post->post_title,
            'gender_label'  => $gender_label,
        ];
    }
    usort($teams, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $teams;
}

/**
 * スケジュール目的（intent）を正規化（confirmed / recruit / tentative のみ）
 *
 * @param string $intent_raw post_meta intent
 * @param string $certainty_raw post_meta certainty（intent 未設定の極古データ用）
 * @return string confirmed|recruit|tentative
 */
function aidunite_normalize_schedule_intent($intent_raw, $certainty_raw = '') {
    $intent = strtolower(trim((string) $intent_raw));
    if (in_array($intent, ['confirmed', 'recruit', 'tentative'], true)) {
        return $intent;
    }
    if (strtolower(trim((string) $certainty_raw)) === 'tentative') {
        return 'tentative';
    }
    return 'confirmed';
}

/**
 * intent の UI ラベル（登録 Step1 と管理カレンダーで共通）
 *
 * @param string $intent
 * @return string
 */
function aidunite_schedule_intent_label($intent) {
    $labels = [
        'confirmed' => '確定の予定',
        'recruit'   => '試合を募集',
        'tentative' => '仮押さえ',
    ];
    $key = aidunite_normalize_schedule_intent($intent);
    return $labels[$key] ?? '—';
}

/**
 * スケジュールをUI表示用にフォーマット
 *
 * @param WP_Post $schedule スケジュール投稿
 * @return array|null フォーマット済みデータ
 */
function aidunite_format_schedule_for_ui($schedule) {
    $schedule_type = get_post_meta($schedule->ID, 'schedule_type', true);
    $intent = aidunite_normalize_schedule_intent(
        get_post_meta($schedule->ID, 'intent', true),
        get_post_meta($schedule->ID, 'certainty', true)
    );
    $match_status = get_post_meta($schedule->ID, 'match_status', true);

    // 試合系でmatch_statusが未設定の場合、自動判定
    if (in_array($schedule_type, ['practice_match', 'joint_practice']) && empty($match_status)) {
        $opponent_team_id = get_post_meta($schedule->ID, 'match_opponent_team_id', true);
        $opponent_name = get_post_meta($schedule->ID, 'match_opponent_name', true);
        $place = get_post_meta($schedule->ID, 'schedule_place', true);
        $start_time = get_post_meta($schedule->ID, 'schedule_start_time', true);
        $end_time = get_post_meta($schedule->ID, 'schedule_end_time', true);

        $has_opponent = !empty($opponent_team_id) || !empty($opponent_name);
        $has_place = !empty($place);
        $has_time = !empty($start_time) && !empty($end_time);

        if ($has_opponent && $has_place && $has_time) {
            $match_status = 'confirmed';
        } elseif ($has_opponent) {
            $match_status = 'matched';
        } else {
            $match_status = 'planned';
        }
    }

    // display_typeとstatus_badgeの決定
    $display_type = $schedule_type;
    $status_badge = '';

    if (in_array($schedule_type, ['practice_match', 'joint_practice'])) {
        switch ($match_status) {
            case 'planned':
                $display_type = 'practice';
                $status_badge = '予定';
                break;
            case 'matched':
                $display_type = $schedule_type === 'practice_match' ? 'practice_match_matched' : 'joint_practice_matched';
                $status_badge = '成立';
                break;
            case 'confirmed':
                $display_type = $schedule_type === 'practice_match' ? 'practice_match_confirmed' : 'joint_practice_confirmed';
                $status_badge = '確定';
                break;
        }
    }

    // 確定済みは表示名から募集サフィックスを除去（旧データ互換）
    $display_schedule_type = $schedule_type;
    if ($intent === 'confirmed' || $match_status === 'confirmed') {
        $display_schedule_type = preg_replace('/（募集）|\(募集\)|（募）|\(募\)/u', '', (string) $schedule_type);
        $display_schedule_type = trim((string) $display_schedule_type);
        if ($display_schedule_type === '') {
            $display_schedule_type = $schedule_type;
        }
    }

    // 相手情報の取得
    $opponent_team_id = get_post_meta($schedule->ID, 'match_opponent_team_id', true);
    $opponent_name = get_post_meta($schedule->ID, 'match_opponent_name', true);

    // チーム名の取得
    $team_name = '';
    if ($opponent_team_id) {
        $team_post = get_post($opponent_team_id);
        if ($team_post && $team_post->post_type === 'team') {
            $team_name = $team_post->post_title;
        }
    }

    // 会場情報の取得（会場名を優先、なければschedule_place）
    $schedule_place = get_post_meta($schedule->ID, 'schedule_place', true);
    $venue_name = get_post_meta($schedule->ID, 'venue_name', true);
    $place_display = !empty($venue_name) ? $venue_name : $schedule_place;
    $start_time = get_post_meta($schedule->ID, 'schedule_start_time', true);
    $end_time = get_post_meta($schedule->ID, 'schedule_end_time', true);
    $gender_value = get_post_meta($schedule->ID, 'schedule_gender', true);

    // 試合確定済みなら、match_request の確定値（selected_*）を優先表示
    if (in_array($match_status, ['confirmed'], true) || $intent === 'confirmed') {
        $established_requests = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => ['established', '試合確定'], 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'my_schedule_id', 'value' => (string) $schedule->ID, 'compare' => '='],
                    ['key' => 'to_schedule_id', 'value' => (string) $schedule->ID, 'compare' => '='],
                ],
            ],
        ]);

        if (!empty($established_requests)) {
            $req_id = (int) $established_requests[0]->ID;
            $selected_start_time = (string) get_post_meta($req_id, 'selected_start_time', true);
            $selected_end_time = (string) get_post_meta($req_id, 'selected_end_time', true);
            $selected_gender = (string) get_post_meta($req_id, 'selected_gender', true);
            $selected_place = (string) get_post_meta($req_id, 'selected_place', true);
            if ($selected_place === 'both') {
                $selected_place = 'either';
            }

            if ($selected_start_time !== '') {
                $start_time = $selected_start_time;
            }
            if ($selected_end_time !== '') {
                $end_time = $selected_end_time;
            }
            if (in_array($selected_gender, ['male', 'female', 'both'], true)) {
                $gender_value = $selected_gender;
            }

            // selected_place は申請者（from_team / my_schedule）視点で保存されるため、閲覧チーム視点で変換
            if (in_array($selected_place, ['home', 'away', 'either'], true)) {
                $from_team_id = (int) get_post_meta($req_id, 'from_team_id', true);
                $to_team_id = (int) get_post_meta($req_id, 'to_team_id', true);
                $viewer_team_id = function_exists('aidunite_get_current_team_id')
                    ? (int) aidunite_get_current_team_id(get_current_user_id())
                    : (int) get_user_meta(get_current_user_id(), 'team_id', true);
                $place_for_viewer = function_exists('aidunite_resolve_place_for_viewer')
                    ? aidunite_resolve_place_for_viewer($selected_place, $from_team_id, $to_team_id, $viewer_team_id)
                    : $selected_place;
                $place_display = $place_for_viewer;
            }
        }
    }

    $schedule_place_for_ui = $schedule_place;
    if (in_array($place_display, ['home', 'away', 'either'], true)) {
        $schedule_place_for_ui = $place_display;
        $venue_name = '';
    }

    $owner_team_id = (int) get_post_meta($schedule->ID, 'team_id', true);
    $owner_team_name = '';
    $owner_team_gender = '';
    $owner_team_gender_label = '';
    if ($owner_team_id > 0) {
        $owner_team_post = get_post($owner_team_id);
        if ($owner_team_post && $owner_team_post->post_type === 'team') {
            $owner_team_name = $owner_team_post->post_title;
        }
        $owner_team_gender_option = get_post_meta($owner_team_id, 'team_gender_option', true);
        if (in_array($owner_team_gender_option, ['male', 'female'], true)) {
            $owner_team_gender = $owner_team_gender_option;
        }
        if (function_exists('aidunite_team_gender_label')) {
            $owner_team_gender_label = aidunite_team_gender_label($owner_team_gender_option);
            if ($owner_team_gender_label === '—') {
                $owner_team_gender_label = '';
            }
        }
    }

    $opponent_display = function_exists('aidunite_schedule_opponent_display_for_ui')
        ? aidunite_schedule_opponent_display_for_ui((int) $schedule->ID)
        : ($opponent_name ?: $team_name);

    return [
        'id' => $schedule->ID,
        'team_id' => $owner_team_id,
        'team_name' => $owner_team_name,
        'team_gender' => $owner_team_gender,
        'team_gender_label' => $owner_team_gender_label,
        'team_color_index' => $owner_team_id > 0 ? ($owner_team_id % 6) : 0,
        'opponent_display' => $opponent_display,
        'date' => get_post_meta($schedule->ID, 'schedule_date', true),
        'start_time' => $start_time,
        'end_time' => $end_time,
        'place' => aidunite_format_venue_display($place_display),
        'schedule_place' => $schedule_place_for_ui,
        'venue_name' => $venue_name,
        'type' => $display_schedule_type, // フロントエンド表示用（確定時は募集サフィックス除去）
        'schedule_type' => $schedule_type, // 後方互換性のため残す
        'intent' => $intent,
        'intent_label' => aidunite_schedule_intent_label($intent),
        'match_status' => $match_status,
        'opponent' => [
            'team_id' => $opponent_team_id,
            'name' => $opponent_name ?: $team_name
        ],
        'match_request' => get_post_meta($schedule->ID, 'match_request', true),
        'venue_condition' => get_post_meta($schedule->ID, 'venue_condition', true) ?: get_post_meta($schedule->ID, 'schedule_place_option', true),
        'schedule_place_option' => get_post_meta($schedule->ID, 'schedule_place_option', true),
        'gender_condition' => get_post_meta($schedule->ID, 'gender_condition', true),
        'capacity' => [
            'total' => get_post_meta($schedule->ID, 'capacity', true),
            'male' => get_post_meta($schedule->ID, 'male_capacity', true),
            'female' => get_post_meta($schedule->ID, 'female_capacity', true)
        ],
        'note' => get_post_meta($schedule->ID, 'schedule_note', true) ?: get_post_meta($schedule->ID, 'note', true),
        'display_type' => $display_type,
        'status_badge' => $status_badge,
        // 新しいフィールド
        'gender' => $gender_value,
        'memo' => get_post_meta($schedule->ID, 'schedule_quick_memo', true), // BugFix: schedule_memo -> schedule_quick_memo
        'matching' => get_post_meta($schedule->ID, 'matching', true), // マッチング希望フラグ
        'quick_memo' => get_post_meta($schedule->ID, 'schedule_quick_memo', true), // 後方互換性のため
        'is_personal' => get_post_meta($schedule->ID, 'is_personal', true) === '1'
    ];
}

/**
 * 指定日のスケジュールを取得（REST API用）
 *
 * @param string $date 日付（YYYY-MM-DD）
 * @param int $user_id ユーザーID
 * @return array スケジュール配列
 */
function aidunite_get_schedules_by_date($date, $user_id = null) {
    return aidunite_get_user_schedules_by_date_range($date, $date, $user_id);
}

/**
 * ユーザーの全スケジュールを取得（REST API用）
 *
 * @param int $user_id ユーザーID
 * @return array スケジュール配列（UI用DTO）
 */
function aidunite_get_all_user_schedules($user_id = null, $fetch_args = []) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return [];
    }

    $teams = function_exists('aidunite_get_managed_teams_for_schedule_ui')
        ? aidunite_get_managed_teams_for_schedule_ui($user_id)
        : [];
    if (count($teams) > 1 && empty($fetch_args['scope'])) {
        $fetch_args['scope'] = 'managed';
    }

    $team_clause = aidunite_resolve_schedule_fetch_team_meta_query($user_id, $fetch_args);
    if ($team_clause === null) {
        return [];
    }

    $args = [
        'post_type' => 'schedule',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            $team_clause,
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ];

    $schedules = get_posts($args);
    $formatted_schedules = [];

    foreach ($schedules as $schedule) {
        $is_personal = get_post_meta($schedule->ID, 'is_personal', true);
        if ($is_personal === '1' && (int) $schedule->post_author !== (int) $user_id) {
            continue;
        }
        $schedule_data = aidunite_format_schedule_for_ui($schedule);
        if ($schedule_data) {
            $formatted_schedules[] = $schedule_data;
        }
    }

    return $formatted_schedules;
}

/**
 * 会場条件を日本語表示に変換
 */
function aidunite_format_venue_display($venue_condition) {
    if (empty($venue_condition)) {
        return '未設定';
    }

    switch ($venue_condition) {
        case 'home':
            return 'ホーム';
        case 'away':
            return 'アウェイ';
        case 'either':
            return 'どちらでも';
        default:
            return $venue_condition; // その他の場合はそのまま表示
    }
}

/**
 * 一度限りの移行：intent=confirmed なのに schedule_type が「（募集）」付きの旧データを補正
 */
function aidunite_migrate_confirmed_schedule_type_suffix() {
    if (get_option('aidunite_migrated_confirmed_schedule_type_suffix', false)) {
        return;
    }

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'intent',
                'value' => 'confirmed',
                'compare' => '='
            ]
        ],
        'fields' => 'ids',
    ]);

    $updated = 0;
    foreach ($schedules as $schedule_id) {
        $schedule_type = (string) get_post_meta($schedule_id, 'schedule_type', true);
        if ($schedule_type === '') {
            continue;
        }
        $normalized = preg_replace('/（募集）|\(募集\)|（募）|\(募\)/u', '', $schedule_type);
        $normalized = trim((string) $normalized);
        if ($normalized !== '' && $normalized !== $schedule_type) {
            update_post_meta($schedule_id, 'schedule_type', $normalized);
            $date = get_post_meta($schedule_id, 'schedule_date', true);
            wp_update_post([
                'ID' => $schedule_id,
                'post_title' => ($date ? ($date . ' ') : '') . $normalized,
            ]);
            $updated++;
        }
    }

    update_option('aidunite_migrated_confirmed_schedule_type_suffix', true);
    if ($updated > 0) {
        error_log('aidunite_migrate_confirmed_schedule_type_suffix: ' . $updated . ' 件を補正しました');
    }
}
add_action('init', 'aidunite_migrate_confirmed_schedule_type_suffix', 22);
