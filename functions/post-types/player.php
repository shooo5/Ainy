<?php
/**
 * 選手投稿タイプ定義
 * ACFから独立したカスタムフィールド実装
 */

// 選手投稿タイプ登録
function register_player_post_type() {
    $labels = array(
        'name' => '選手',
        'singular_name' => '選手',
        'menu_name' => '選手',
        'name_admin_bar' => '選手を追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しい選手を追加',
        'new_item' => '新規選手',
        'edit_item' => '選手を編集',
        'view_item' => '選手を表示',
        'all_items' => '全選手',
        'search_items' => '選手を検索',
        'not_found' => '選手が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に選手はいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title', 'editor'),
        'capability_type' => 'post',
        'show_in_rest' => true,
    );

    register_post_type('player', $args);
}
add_action('init', 'register_player_post_type');

// 選手メタボックス追加
function add_player_metaboxes() {
    add_meta_box(
        'player_details',
        '選手詳細情報',
        'render_player_metabox',
        'player',
        'normal',
        'high'
    );

    add_meta_box(
        'player_contact',
        '連絡先・保護者情報',
        'render_player_contact_metabox',
        'player',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_player_metaboxes');

// 選手詳細メタボックス表示
function render_player_metabox($post) {
    wp_nonce_field('save_player_metabox', 'player_metabox_nonce');

    // 既存の値を取得
    $player_name = get_post_meta($post->ID, 'player_name', true);
    $player_name_kana = get_post_meta($post->ID, 'player_name_kana', true);
    $birth_date = get_post_meta($post->ID, 'birth_date', true);
    $age = get_post_meta($post->ID, 'age', true);
    $gender = get_post_meta($post->ID, 'gender', true);
    $position = get_post_meta($post->ID, 'position', true);
    $jersey_number = get_post_meta($post->ID, 'jersey_number', true);
    $height = get_post_meta($post->ID, 'height', true);
    $weight = get_post_meta($post->ID, 'weight', true);
    $team_post_id = get_post_meta($post->ID, 'team_post_id', true);
    $player_description = get_post_meta($post->ID, 'player_description', true);
    $player_achievements = get_post_meta($post->ID, 'player_achievements', true);

    // 年齢自動計算
    if (!empty($birth_date) && empty($age)) {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
    }

    ?>
    <table class="form-table">
        <tr>
            <th><label for="player_name">選手名</label></th>
            <td>
                <input type="text" id="player_name" name="player_name"
                       value="<?php echo esc_attr($player_name); ?>"
                       class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="player_name_kana">選手名（カナ）</label></th>
            <td>
                <input type="text" id="player_name_kana" name="player_name_kana"
                       value="<?php echo esc_attr($player_name_kana); ?>"
                       class="regular-text" placeholder="ヤマダ タロウ">
            </td>
        </tr>
        <tr>
            <th><label for="birth_date">生年月日</label></th>
            <td>
                <input type="date" id="birth_date" name="birth_date"
                       value="<?php echo esc_attr($birth_date); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="age">年齢</label></th>
            <td>
                <input type="number" id="age" name="age"
                       value="<?php echo esc_attr($age); ?>"
                       min="0" max="100" readonly>
                <span class="description">生年月日から自動計算されます</span>
            </td>
        </tr>
        <tr>
            <th><label for="gender">性別</label></th>
            <td>
                <select id="gender" name="gender" required>
                    <option value="">選択してください</option>
                    <option value="男子" <?php selected($gender, '男子'); ?>>男子</option>
                    <option value="女子" <?php selected($gender, '女子'); ?>>女子</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="position">ポジション</label></th>
            <td>
                <select id="position" name="position">
                    <option value="">選択してください</option>
                    <option value="PG" <?php selected($position, 'PG'); ?>>PG（ポイントガード）</option>
                    <option value="SG" <?php selected($position, 'SG'); ?>>SG（シューティングガード）</option>
                    <option value="SF" <?php selected($position, 'SF'); ?>>SF（スモールフォワード）</option>
                    <option value="PF" <?php selected($position, 'PF'); ?>>PF（パワーフォワード）</option>
                    <option value="C" <?php selected($position, 'C'); ?>>C（センター）</option>
                    <option value="その他" <?php selected($position, 'その他'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="jersey_number">背番号</label></th>
            <td>
                <input type="number" id="jersey_number" name="jersey_number"
                       value="<?php echo esc_attr($jersey_number); ?>"
                       min="0" max="99">
            </td>
        </tr>
        <tr>
            <th><label for="height">身長（cm）</label></th>
            <td>
                <input type="number" id="height" name="height"
                       value="<?php echo esc_attr($height); ?>"
                       min="100" max="250">
            </td>
        </tr>
        <tr>
            <th><label for="weight">体重（kg）</label></th>
            <td>
                <input type="number" id="weight" name="weight"
                       value="<?php echo esc_attr($weight); ?>"
                       min="20" max="150" step="0.1">
            </td>
        </tr>
        <tr>
            <th><label for="player_description">選手紹介</label></th>
            <td>
                <textarea id="player_description" name="player_description" rows="4" cols="50"
                          placeholder="選手の特徴や得意なプレーを記入してください"><?php echo esc_textarea($player_description); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="player_achievements">実績</label></th>
            <td>
                <textarea id="player_achievements" name="player_achievements" rows="3" cols="50"
                          placeholder="大会成績や表彰歴があれば記入してください"><?php echo esc_textarea($player_achievements); ?></textarea>
            </td>
        </tr>
    </table>

    <?php
    $age_js = get_stylesheet_directory() . '/assets/js/admin/player-metabox-age.js';
    if (is_readable($age_js)) {
        wp_enqueue_script(
            'aidunite-player-metabox-age',
            get_stylesheet_directory_uri() . '/assets/js/admin/player-metabox-age.js',
            [],
            (string) filemtime($age_js),
            true
        );
    }
    ?>
    <?php
}

// 連絡先メタボックス表示
function render_player_contact_metabox($post) {
    $guardian_name = get_post_meta($post->ID, 'guardian_name', true);
    $guardian_relationship = get_post_meta($post->ID, 'guardian_relationship', true);
    $guardian_phone = get_post_meta($post->ID, 'guardian_phone', true);
    $guardian_email = get_post_meta($post->ID, 'guardian_email', true);
    $emergency_contact = get_post_meta($post->ID, 'emergency_contact', true);
    $medical_info = get_post_meta($post->ID, 'medical_info', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="guardian_name">保護者名</label></th>
            <td>
                <input type="text" id="guardian_name" name="guardian_name"
                       value="<?php echo esc_attr($guardian_name); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="guardian_relationship">続柄</label></th>
            <td>
                <select id="guardian_relationship" name="guardian_relationship">
                    <option value="">選択してください</option>
                    <option value="父" <?php selected($guardian_relationship, '父'); ?>>父</option>
                    <option value="母" <?php selected($guardian_relationship, '母'); ?>>母</option>
                    <option value="祖父" <?php selected($guardian_relationship, '祖父'); ?>>祖父</option>
                    <option value="祖母" <?php selected($guardian_relationship, '祖母'); ?>>祖母</option>
                    <option value="その他" <?php selected($guardian_relationship, 'その他'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="guardian_phone">保護者電話番号</label></th>
            <td>
                <input type="tel" id="guardian_phone" name="guardian_phone"
                       value="<?php echo esc_attr($guardian_phone); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="guardian_email">保護者メール</label></th>
            <td>
                <input type="email" id="guardian_email" name="guardian_email"
                       value="<?php echo esc_attr($guardian_email); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="emergency_contact">緊急連絡先</label></th>
            <td>
                <input type="tel" id="emergency_contact" name="emergency_contact"
                       value="<?php echo esc_attr($emergency_contact); ?>"
                       class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="medical_info">健康情報</label></th>
            <td>
                <textarea id="medical_info" name="medical_info" rows="3" cols="30"
                          placeholder="アレルギーや持病があれば記入してください"><?php echo esc_textarea($medical_info); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// メタボックス保存処理
function save_player_metabox($post_id) {
    // セキュリティチェック
    if (!isset($_POST['player_metabox_nonce']) ||
        !wp_verify_nonce($_POST['player_metabox_nonce'], 'save_player_metabox')) {
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
    if (get_post_type($post_id) !== 'player') {
        return;
    }

    // フィールド保存
    $fields = [
        'player_name',
        'player_name_kana',
        'birth_date',
        'age',
        'gender',
        'position',
        'jersey_number',
        'height',
        'weight',
        'player_description',
        'player_achievements',
        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_email',
        'emergency_contact',
        'medical_info'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            update_post_meta($post_id, $field, $value);
        }
    }

    // 年齢自動計算
    $birth_date = get_post_meta($post_id, 'birth_date', true);
    if (!empty($birth_date)) {
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        update_post_meta($post_id, 'age', $age);
    }

    // 選手名をタイトルに自動設定
    $player_name = get_post_meta($post_id, 'player_name', true);
    if (!empty($player_name)) {
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $player_name . 'の登録'
        ]);
    }

    // 所属チームIDを自動設定
    $current_user = wp_get_current_user();
    $team_post = get_posts([
        'post_type' => 'team',
        'author' => $current_user->ID,
        'post_status' => ['publish', 'pending', 'draft'],
        'numberposts' => 1,
    ]);

    if ($team_post) {
        $team_id = $team_post[0]->ID;
        update_post_meta($post_id, 'team_post_id', $team_id);
    }
}
add_action('save_post', 'save_player_metabox');

// 選手取得ヘルパー関数
function get_player_meta($post_id, $key = null) {
    if ($key) {
        return get_post_meta($post_id, $key, true);
    }

    return [
        'name' => get_post_meta($post_id, 'player_name', true),
        'name_kana' => get_post_meta($post_id, 'player_name_kana', true),
        'birth_date' => get_post_meta($post_id, 'birth_date', true),
        'age' => get_post_meta($post_id, 'age', true),
        'gender' => get_post_meta($post_id, 'gender', true),
        'position' => get_post_meta($post_id, 'position', true),
        'jersey_number' => get_post_meta($post_id, 'jersey_number', true),
        'height' => get_post_meta($post_id, 'height', true),
        'weight' => get_post_meta($post_id, 'weight', true),
        'team_post_id' => get_post_meta($post_id, 'team_post_id', true),
        'description' => get_post_meta($post_id, 'player_description', true),
        'achievements' => get_post_meta($post_id, 'player_achievements', true),
        'guardian_name' => get_post_meta($post_id, 'guardian_name', true),
        'guardian_relationship' => get_post_meta($post_id, 'guardian_relationship', true),
        'guardian_phone' => get_post_meta($post_id, 'guardian_phone', true),
        'guardian_email' => get_post_meta($post_id, 'guardian_email', true),
        'emergency_contact' => get_post_meta($post_id, 'emergency_contact', true),
        'medical_info' => get_post_meta($post_id, 'medical_info', true)
    ];
}
