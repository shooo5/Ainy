<?php
/**
 * スケジュール投稿タイプ定義
 * ACFから独立したカスタムフィールド実装
 */

// スケジュール投稿タイプ登録
function register_schedule_post_type() {
    $labels = array(
        'name' => 'スケジュール',
        'singular_name' => 'スケジュール',
        'menu_name' => 'スケジュール',
        'name_admin_bar' => 'スケジュールを追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しいスケジュールを追加',
        'new_item' => '新規スケジュール',
        'edit_item' => 'スケジュールを編集',
        'view_item' => 'スケジュールを表示',
        'all_items' => '全スケジュール',
        'search_items' => 'スケジュールを検索',
        'not_found' => 'スケジュールが見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱にスケジュールはいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => false,
        'menu_position' => 6,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => array('title', 'author'),
        'capability_type' => 'post',
        'show_in_rest' => true,
    );

    register_post_type('schedule', $args);
}
add_action('init', 'register_schedule_post_type');

// スケジュールメタボックス追加
function add_schedule_metaboxes() {
    add_meta_box(
        'schedule_details',
        'スケジュール詳細',
        'render_schedule_metabox',
        'schedule',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_schedule_metaboxes');

// メタボックス表示
function render_schedule_metabox($post) {
    wp_nonce_field('save_schedule_metabox', 'schedule_metabox_nonce');

    // 既存の値を取得（表示は get_schedule_meta / display_bundle 経由）
    $meta = function_exists('get_schedule_meta') ? get_schedule_meta($post->ID) : [];
    $schedule_date = (string) ($meta['date'] ?? '');
    $schedule_start_time = (string) ($meta['start_time'] ?? '');
    $schedule_end_time = (string) ($meta['end_time'] ?? '');
    $schedule_place = (string) ($meta['place'] ?? '');
    $schedule_note = (string) ($meta['note'] ?? '');
    $schedule_type = (string) ($meta['type'] ?? '');
    $matching = (string) ($meta['matching'] ?? get_post_meta($post->ID, 'matching', true));
    $matching_gender_condition = (string) ($meta['gender_condition'] ?? '');
    $schedule_place_option = (string) ($meta['place_option'] ?? $schedule_place);

    // 時間オプション生成
    $time_options = '';
    for ($hour = 6; $hour <= 23; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            $time = sprintf('%02d:%02d', $hour, $minute);
            $time_options .= "<option value='{$time}'" .
                (($schedule_start_time === $time) ? ' selected' : '') .
                ">{$time}</option>";
        }
    }

    ?>
    <table class="form-table">
        <tr>
            <th><label for="schedule_date">日付</label></th>
            <td>
                <input type="date" id="schedule_date" name="schedule_date"
                       value="<?php echo esc_attr($schedule_date); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="schedule_start_time">開始時間</label></th>
            <td>
                <select id="schedule_start_time" name="schedule_start_time" required>
                    <option value="">選択してください</option>
                    <?php echo $time_options; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="schedule_end_time">終了時間</label></th>
            <td>
                <select id="schedule_end_time" name="schedule_end_time" required>
                    <option value="">選択してください</option>
                    <?php echo $time_options; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="schedule_type">種別</label></th>
            <td>
                <select id="schedule_type" name="schedule_type" required>
                    <option value="">選択してください</option>
                    <option value="練習" <?php selected($schedule_type, '練習'); ?>>練習</option>
                    <option value="試合" <?php selected($schedule_type, '試合'); ?>>試合</option>
                    <option value="大会" <?php selected($schedule_type, '大会'); ?>>大会</option>
                    <option value="その他" <?php selected($schedule_type, 'その他'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="schedule_place">会場</label></th>
            <td>
                <input type="text" id="schedule_place" name="schedule_place"
                       value="<?php echo esc_attr($schedule_place); ?>"
                       placeholder="例：○○体育館">
            </td>
        </tr>
        <tr>
            <th><label for="schedule_place_option">会場オプション</label></th>
            <td>
                <select id="schedule_place_option" name="schedule_place_option">
                    <option value="">選択してください</option>
                    <option value="自宅" <?php selected($schedule_place_option, '自宅'); ?>>自宅</option>
                    <option value="学校" <?php selected($schedule_place_option, '学校'); ?>>学校</option>
                    <option value="体育館" <?php selected($schedule_place_option, '体育館'); ?>>体育館</option>
                    <option value="公園" <?php selected($schedule_place_option, '公園'); ?>>公園</option>
                    <option value="その他" <?php selected($schedule_place_option, 'その他'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="matching">マッチング希望</label></th>
            <td>
                <input type="checkbox" id="matching" name="matching" value="1"
                       <?php checked($matching, '1'); ?>>
                <label for="matching">練習試合の相手を探す</label>
            </td>
        </tr>
        <tr>
            <th><label for="matching_gender_condition">性別条件</label></th>
            <td>
                <select id="matching_gender_condition" name="matching_gender_condition">
                    <option value="">指定なし</option>
                    <option value="男子" <?php selected($matching_gender_condition, '男子'); ?>>男子</option>
                    <option value="女子" <?php selected($matching_gender_condition, '女子'); ?>>女子</option>
                    <option value="男女" <?php selected($matching_gender_condition, '男女'); ?>>男女</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="schedule_note">備考</label></th>
            <td>
                <textarea id="schedule_note" name="schedule_note" rows="4" cols="50"
                          placeholder="詳細な情報があれば記入してください"><?php echo esc_textarea($schedule_note); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// メタボックス保存処理
function save_schedule_metabox($post_id) {
    if (function_exists('aidunite_schedule_is_persist_write_in_progress')
        && aidunite_schedule_is_persist_write_in_progress()) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    // セキュリティチェック
    if (!isset($_POST['schedule_metabox_nonce']) ||
        !wp_verify_nonce($_POST['schedule_metabox_nonce'], 'save_schedule_metabox')) {
        return;
    }

    // 自動保存チェック
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // 投稿タイプチェック
    if (get_post_type($post_id) !== 'schedule') {
        return;
    }

    if (function_exists('aidunite_schedule_merge_legacy_params_for_persist')
        && function_exists('aidunite_schedule_update_published_post')) {
        $params = [];
        foreach ([
            'schedule_date',
            'schedule_start_time',
            'schedule_end_time',
            'schedule_place',
            'schedule_note',
            'schedule_type',
            'matching_gender_condition',
            'schedule_place_option',
        ] as $field) {
            if (isset($_POST[$field])) {
                $params[$field] = wp_unslash($_POST[$field]);
            }
        }

        if (isset($_POST['matching'])) {
            $params['intent'] = 'recruit';
        } else {
            $existing_intent = (string) get_post_meta($post_id, 'intent', true);
            $params['intent'] = ($existing_intent === 'tentative') ? 'tentative' : 'confirmed';
        }

        $persist = aidunite_schedule_merge_legacy_params_for_persist($params, (int) $post_id);
        aidunite_schedule_update_published_post((int) $post_id, $persist);

        if (isset($_POST['matching']) && $_POST['matching'] && function_exists('aidunite_schedule_finalize_new_recruit')) {
            $author_id = (int) get_post_field('post_author', $post_id);
            $team_id = (int) ($persist['team_id'] ?? 0);
            aidunite_schedule_finalize_new_recruit((int) $post_id, $author_id, $team_id);
        }

        return;
    }
}
add_action('save_post', 'save_schedule_metabox');

// スケジュール取得ヘルパー関数（読取は aidunite_schedule_get_canonical_meta を優先）
function get_schedule_meta($post_id, $key = null) {
    if (function_exists('aidunite_schedule_get_canonical_meta')) {
        $canonical = aidunite_schedule_get_canonical_meta((int) $post_id);
        if ($key) {
            $map = [
                'date' => 'date',
                'start_time' => 'start_time',
                'end_time' => 'end_time',
                'place' => 'schedule_place',
                'note' => 'schedule_quick_memo',
                'type' => 'schedule_type',
                'matching' => 'matching',
                'gender_condition' => 'gender_condition',
                'place_option' => 'venue_condition',
                'team_id' => 'team_id',
            ];
            $canonical_key = $map[$key] ?? $key;

            return $canonical[$canonical_key] ?? get_post_meta($post_id, $key, true);
        }

        return [
            'date' => $canonical['date'] ?? '',
            'start_time' => $canonical['start_time'] ?? '',
            'end_time' => $canonical['end_time'] ?? '',
            'place' => $canonical['schedule_place'] ?? '',
            'note' => $canonical['schedule_quick_memo'] ?? '',
            'type' => $canonical['schedule_type'] ?? '',
            'matching' => $canonical['matching'] ?? '',
            'gender_condition' => $canonical['gender_condition'] ?? '',
            'place_option' => $canonical['venue_condition'] ?? '',
            'team_id' => $canonical['team_id'] ?? 0,
            'match_board_id' => $canonical['match_board_id'] ?? 0,
        ];
    }

    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle((int) $post_id);
        if ($bundle !== []) {
            if ($key) {
                $map = [
                    'date' => 'date',
                    'start_time' => 'start_time',
                    'end_time' => 'end_time',
                    'place' => 'place',
                    'note' => 'memo',
                    'type' => 'schedule_type',
                    'matching' => 'matching',
                    'gender_condition' => 'gender',
                    'place_option' => 'place',
                    'team_id' => 'team_id',
                ];
                $bundle_key = $map[$key] ?? $key;

                return $bundle[$bundle_key] ?? get_post_meta($post_id, $key, true);
            }

            return [
                'date' => (string) ($bundle['date'] ?? ''),
                'start_time' => (string) ($bundle['start_time'] ?? ''),
                'end_time' => (string) ($bundle['end_time'] ?? ''),
                'place' => (string) ($bundle['place'] ?? ''),
                'note' => (string) ($bundle['memo'] ?? ''),
                'type' => (string) ($bundle['schedule_type'] ?? ''),
                'matching' => (string) ($bundle['matching'] ?? ''),
                'gender_condition' => (string) ($bundle['gender'] ?? ''),
                'place_option' => (string) ($bundle['place'] ?? ''),
                'team_id' => (int) ($bundle['team_id'] ?? 0),
            ];
        }
    }

    if ($key) {
        return get_post_meta($post_id, $key, true);
    }

    return [
        'date' => function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date((int) $post_id)
            : '',
        'start_time' => '',
        'end_time' => '',
        'place' => function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw((int) $post_id)
            : '',
        'note' => '',
        'type' => '',
        'matching' => (string) get_post_meta($post_id, 'matching', true),
        'gender_condition' => function_exists('aidunite_schedule_read_gender_raw')
            ? aidunite_schedule_read_gender_raw((int) $post_id)
            : '',
        'place_option' => function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw((int) $post_id)
            : '',
        'team_id' => function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id((int) $post_id)
            : 0,
    ];
}

// 新しいメタフィールドの宣言（REST API公開用）
function register_schedule_meta_fields() {
    // 主要メタキー（enumは文字列）
    register_post_meta('schedule', 'schedule_type', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'match_status', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'match_opponent_team_id', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'match_opponent_name', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'schedule_date', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'schedule_start_time', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'schedule_end_time', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'schedule_place', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'match_request', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'venue_condition', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'gender_condition', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'capacity', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'male_capacity', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'female_capacity', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'note', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    // 新機能用のメタフィールド
    register_post_meta('schedule', 'certainty', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'min_required_teams', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'participants', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'accepted_count', [
        'type' => 'number',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'include_self_count', [
        'type' => 'boolean',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'auto_confirm', [
        'type' => 'boolean',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'notify_on_confirm', [
        'type' => 'boolean',
        'single' => true,
        'show_in_rest' => true,
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    // 出欠管理メタフィールド
    register_post_meta('schedule', 'attendance_required', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'default' => '0',
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);

    register_post_meta('schedule', 'attendance_data', [
        'type' => 'string',  // WordPressでは配列はJSON文字列として保存
        'single' => true,
        'show_in_rest' => false,  // セキュリティのためREST APIでは公開しない
        'auth_callback' => function() {
            return current_user_can('edit_posts') || current_user_can('administrator');
        }
    ]);
}
add_action('init', 'register_schedule_meta_fields');
