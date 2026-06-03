<?php
/**
 * チーム投稿タイプ定義
 * ACFから独立したカスタムフィールド実装
 */

// チーム投稿タイプ登録
function register_team_post_type() {
    $labels = array(
        'name' => 'チーム',
        'singular_name' => 'チーム',
        'menu_name' => 'チーム',
        'name_admin_bar' => 'チームを追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しいチームを追加',
        'new_item' => '新規チーム',
        'edit_item' => 'チームを編集',
        'view_item' => 'チームを表示',
        'all_items' => '全チーム',
        'search_items' => 'チームを検索',
        'not_found' => 'チームが見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱にチームはいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post',
        'show_in_rest' => true,
    );

    register_post_type('team', $args);
}
add_action('init', 'register_team_post_type');

// チームメタボックス追加
function add_team_metaboxes() {
    add_meta_box(
        'team_details',
        'チーム詳細情報',
        'render_team_metabox',
        'team',
        'normal',
        'high'
    );

    add_meta_box(
        'team_contact',
        '連絡先情報',
        'render_team_contact_metabox',
        'team',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_team_metaboxes');

// チーム詳細メタボックス表示
function render_team_metabox($post) {
    wp_nonce_field('save_team_metabox', 'team_metabox_nonce');

    // 既存の値を取得
    $team_name = get_post_meta($post->ID, 'team_name', true);
    $team_name_kana = get_post_meta($post->ID, 'team_name_kana', true);
    $team_description = get_post_meta($post->ID, 'team_description', true);
    $team_achievements = get_post_meta($post->ID, 'team_achievements', true);
    $sport_type = get_post_meta($post->ID, 'sport_type', true);
    $team_category = get_post_meta($post->ID, 'team_category', true);
    $team_type = get_post_meta($post->ID, 'team_type', true);
    if (function_exists('aidunite_team_type_to_canonical')) {
        $team_type = aidunite_team_type_to_canonical($team_type);
    }
    $team_type_options = function_exists('aidunite_team_type_options_for_select')
        ? aidunite_team_type_options_for_select()
        : [
            'school' => '学校',
            'club' => 'クラブ',
            'community' => '地域',
            'corporate' => '企業',
            'other' => 'その他',
        ];
    $team_gender_option = get_post_meta($post->ID, 'team_gender_option', true);
    $region = get_post_meta($post->ID, 'region', true);
    $team_logo = get_post_meta($post->ID, 'team_logo', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="team_name">チーム名</label></th>
            <td>
                <input type="text" id="team_name" name="team_name"
                       value="<?php echo esc_attr($team_name); ?>"
                       class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="team_name_kana">フリガナ</label></th>
            <td>
                <input type="text" id="team_name_kana" name="team_name_kana"
                       value="<?php echo esc_attr($team_name_kana); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="sport_type">競技種目</label></th>
            <td>
                <select id="sport_type" name="sport_type" required>
                    <option value="">選択してください</option>
                    <option value="バスケットボール" <?php selected($sport_type, 'バスケットボール'); ?>>バスケットボール</option>
                    <option value="サッカー" <?php selected($sport_type, 'サッカー'); ?>>サッカー</option>
                    <option value="野球" <?php selected($sport_type, '野球'); ?>>野球</option>
                    <option value="テニス" <?php selected($sport_type, 'テニス'); ?>>テニス</option>
                    <option value="バレーボール" <?php selected($sport_type, 'バレーボール'); ?>>バレーボール</option>
                    <option value="その他" <?php selected($sport_type, 'その他'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team_category">年代カテゴリ</label></th>
            <td>
                <select id="team_category" name="team_category" required>
                    <option value="">選択してください</option>
                    <option value="小学生" <?php selected($team_category, '小学生'); ?>>小学生</option>
                    <option value="中学生" <?php selected($team_category, '中学生'); ?>>中学生</option>
                    <option value="高校生" <?php selected($team_category, '高校生'); ?>>高校生</option>
                    <option value="大学生" <?php selected($team_category, '大学生'); ?>>大学生</option>
                    <option value="社会人" <?php selected($team_category, '社会人'); ?>>社会人</option>
                    <option value="シニア" <?php selected($team_category, 'シニア'); ?>>シニア</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team_type">所属タイプ</label></th>
            <td>
                <select id="team_type" name="team_type" required>
                    <option value="">選択してください</option>
                    <?php foreach ($team_type_options as $opt_value => $opt_label) : ?>
                    <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($team_type, $opt_value); ?>><?php echo esc_html($opt_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team_gender_option">性別</label></th>
            <td>
                <select id="team_gender_option" name="team_gender_option" required>
                    <option value="">選択してください</option>
                    <option value="male" <?php selected($team_gender_option, 'male'); ?>>男子</option>
                    <option value="female" <?php selected($team_gender_option, 'female'); ?>>女子</option>
                    <option value="both" <?php selected($team_gender_option, 'both'); ?>>男子・女子可</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>活動地域</th>
            <td>
                <?php
                if (function_exists('aidunite_render_team_activity_fields')) {
                    aidunite_render_team_activity_fields((int) $post->ID, ['context' => 'wp_admin']);
                    aidunite_enqueue_team_activity_fields_script();
                }
                ?>
            </td>
        </tr>
        <tr>
            <th><label for="team_description">チーム紹介</label></th>
            <td>
                <textarea id="team_description" name="team_description" rows="5" cols="50"
                          placeholder="チームの特徴や活動内容を記入してください"><?php echo esc_textarea($team_description); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="team_achievements">実績</label></th>
            <td>
                <textarea id="team_achievements" name="team_achievements" rows="4" cols="50"
                          placeholder="大会成績や表彰歴があれば記入してください"><?php echo esc_textarea($team_achievements); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="team_logo">チームロゴURL</label></th>
            <td>
                <input type="url" id="team_logo" name="team_logo"
                       value="<?php echo esc_url($team_logo); ?>"
                       class="regular-text" placeholder="https://example.com/logo.jpg">
            </td>
        </tr>
    </table>
    <?php
}

// 連絡先メタボックス表示
function render_team_contact_metabox($post) {
    $registrant_name = get_post_meta($post->ID, 'registrant_name', true);
    $contact_mail = get_post_meta($post->ID, 'contact_mail', true);
    $contact_phone = get_post_meta($post->ID, 'contact_phone', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="registrant_name">代表者名</label></th>
            <td>
                <input type="text" id="registrant_name" name="registrant_name"
                       value="<?php echo esc_attr($registrant_name); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="contact_mail">連絡先メール</label></th>
            <td>
                <input type="email" id="contact_mail" name="contact_mail"
                       value="<?php echo esc_attr($contact_mail); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="contact_phone">連絡先電話番号</label></th>
            <td>
                <input type="tel" id="contact_phone" name="contact_phone"
                       value="<?php echo esc_attr($contact_phone); ?>"
                       class="regular-text">
            </td>
        </tr>
    </table>
    <?php
}

// メタボックス保存処理
function save_team_metabox($post_id) {
    // save_post の再入防止（同一 request 内で無限ループを避ける）
    static $in_progress = [];
    if (!empty($in_progress[$post_id])) return;
    $in_progress[$post_id] = true;

    try {
    // セキュリティチェック
    if (!isset($_POST['team_metabox_nonce']) ||
        !wp_verify_nonce($_POST['team_metabox_nonce'], 'save_team_metabox')) {
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
    if (get_post_type($post_id) !== 'team') {
        return;
    }

    // フィールド保存
    $fields = [
        'team_name',
        'team_name_kana',
        'team_description',
        'team_achievements',
        'sport_type',
        'team_category',
        'team_type',
        'team_gender_option',
        'team_logo',
        'registrant_name',
        'contact_mail',
        'contact_phone'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            if ($field === 'team_gender_option' && function_exists('aidunite_normalize_team_gender_option')) {
                $value = aidunite_normalize_team_gender_option($value);
                if ($value === '') {
                    $value = 'both';
                }
            }
            if ($field === 'team_type' && function_exists('aidunite_update_team_type_meta')) {
                $saved = aidunite_update_team_type_meta($post_id, $value);
                if ($saved !== '') {
                    continue;
                }
            }
            update_post_meta($post_id, $field, $value);
        }
    }

    if (function_exists('aidunite_team_activity_save_meta')) {
        aidunite_team_activity_save_meta((int) $post_id, [
            'activity_prefecture' => wp_unslash($_POST['activity_prefecture'] ?? ''),
            'activity_area_type'  => wp_unslash($_POST['activity_area_type'] ?? ''),
            'activity_area_ward'  => wp_unslash($_POST['activity_area_ward'] ?? ''),
            'activity_area_city'  => wp_unslash($_POST['activity_area_city'] ?? ''),
            'activity_area_sync'  => wp_unslash($_POST['activity_area_sync'] ?? ''),
            'activity_area'       => wp_unslash($_POST['activity_area'] ?? ''),
        ]);
    }

    // チーム名をタイトルに自動設定
    $team_name = get_post_meta($post_id, 'team_name', true);
    if (!empty($team_name)) {
        // 差分がある場合だけ更新（save_post 内 wp_update_post の無限再入抑止）
        $current_title = get_post_field('post_title', $post_id);
        if ($current_title !== $team_name) {
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $team_name
            ]);
        }
    }

    // 新規投稿時はステータスをpendingに設定
    if (get_post_status($post_id) === 'auto-draft') {
        wp_update_post([
            'ID' => $post_id,
            'post_status' => 'pending'
        ]);
    }
    } finally {
        unset($in_progress[$post_id]);
    }
}
add_action('save_post', 'save_team_metabox');

// チーム取得ヘルパー関数
function get_team_meta($post_id, $key = null) {
    if ($key) {
        return get_post_meta($post_id, $key, true);
    }

    return [
        'name' => get_post_meta($post_id, 'team_name', true),
        'description' => get_post_meta($post_id, 'team_description', true),
        'achievements' => get_post_meta($post_id, 'team_achievements', true),
        'sport_type' => get_post_meta($post_id, 'sport_type', true),
        'category' => get_post_meta($post_id, 'team_category', true),
        'type' => get_post_meta($post_id, 'team_type', true),
        'gender' => get_post_meta($post_id, 'team_gender_option', true),
        'region' => get_post_meta($post_id, 'region', true),
        'logo' => get_post_meta($post_id, 'team_logo', true),
        'registrant_name' => get_post_meta($post_id, 'registrant_name', true),
        'contact_mail' => get_post_meta($post_id, 'contact_mail', true),
        'contact_phone' => get_post_meta($post_id, 'contact_phone', true)
    ];
}
