<?php
/**
 * 複数チームマッチ用チャット投稿タイプ定義
 */

// 複数チームマッチチャット投稿タイプ登録
function register_multi_match_chat_post_type() {
    $labels = array(
        'name' => '複数チームマッチチャット',
        'singular_name' => '複数チームマッチチャット',
        'menu_name' => '複数チームマッチチャット',
        'name_admin_bar' => '複数チームマッチチャットを追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しいチャットを追加',
        'new_item' => '新規チャット',
        'edit_item' => 'チャットを編集',
        'view_item' => 'チャットを表示',
        'all_items' => '全チャット',
        'search_items' => 'チャットを検索',
        'not_found' => 'チャットが見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱にチャットはいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'capability_type' => 'post',
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'author'),
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
    );

    register_post_type('multi_match_chat', $args);
}
add_action('init', 'register_multi_match_chat_post_type');

// 複数チームマッチチャットメッセージ投稿タイプ登録
function register_mm_chat_message_post_type() {
    $labels = array(
        'name' => '複数チームマッチチャットメッセージ',
        'singular_name' => '複数チームマッチチャットメッセージ',
        'menu_name' => '複数チームマッチチャットメッセージ',
        'name_admin_bar' => '複数チームマッチチャットメッセージを追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しいメッセージを追加',
        'new_item' => '新規メッセージ',
        'edit_item' => 'メッセージを編集',
        'view_item' => 'メッセージを表示',
        'all_items' => '全メッセージ',
        'search_items' => 'メッセージを検索',
        'not_found' => 'メッセージが見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱にメッセージはいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'capability_type' => 'post',
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'author'),
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
    );

    register_post_type('mm_chat_message', $args);
}
add_action('init', 'register_mm_chat_message_post_type');

// 複数チームマッチチャットメタボックス追加
function add_multi_match_chat_metaboxes() {
    add_meta_box(
        'multi_match_chat_details',
        '複数チームマッチチャット詳細',
        'render_multi_match_chat_metabox',
        'multi_match_chat',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_multi_match_chat_metaboxes');

// メタボックス表示
function render_multi_match_chat_metabox($post) {
    wp_nonce_field('save_multi_match_chat_metabox', 'multi_match_chat_metabox_nonce');

    // 既存の値を取得
    $multi_match_id = get_post_meta($post->ID, 'multi_match_id', true);
    $chat_status = get_post_meta($post->ID, 'chat_status', true);
    $created_date = get_post_meta($post->ID, 'created_date', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="multi_match_id">複数チームマッチID</label></th>
            <td>
                <input type="number" id="multi_match_id" name="multi_match_id"
                       value="<?php echo esc_attr($multi_match_id); ?>" required>
                <p class="description">このチャットが紐づく複数チームマッチのID</p>
            </td>
        </tr>
        <tr>
            <th><label for="chat_status">チャットステータス</label></th>
            <td>
                <select id="chat_status" name="chat_status">
                    <option value="active" <?php selected($chat_status, 'active'); ?>>アクティブ</option>
                    <option value="archived" <?php selected($chat_status, 'archived'); ?>>アーカイブ</option>
                    <option value="closed" <?php selected($chat_status, 'closed'); ?>>閉鎖</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="created_date">作成日</label></th>
            <td>
                <input type="datetime-local" id="created_date" name="created_date"
                       value="<?php echo esc_attr($created_date); ?>">
            </td>
        </tr>
    </table>
    <?php
}

// メタボックス保存
function save_multi_match_chat_metabox($post_id) {
    if (!isset($_POST['multi_match_chat_metabox_nonce']) ||
        !wp_verify_nonce($_POST['multi_match_chat_metabox_nonce'], 'save_multi_match_chat_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // メタデータ保存
    if (isset($_POST['multi_match_id'])) {
        update_post_meta($post_id, 'multi_match_id', sanitize_text_field($_POST['multi_match_id']));
    }
    if (isset($_POST['chat_status'])) {
        update_post_meta($post_id, 'chat_status', sanitize_text_field($_POST['chat_status']));
    }
    if (isset($_POST['created_date'])) {
        update_post_meta($post_id, 'created_date', sanitize_text_field($_POST['created_date']));
    }
}
add_action('save_post', 'save_multi_match_chat_metabox');

// 複数チームマッチチャットメッセージメタボックス追加
function add_mm_chat_message_metaboxes() {
    add_meta_box(
        'mm_chat_message_details',
        '複数チームマッチチャットメッセージ詳細',
        'render_mm_chat_message_metabox',
        'mm_chat_message',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_mm_chat_message_metaboxes');

// メッセージメタボックス表示
function render_mm_chat_message_metabox($post) {
    wp_nonce_field('save_multi_match_chat_message_metabox', 'mm_chat_message_metabox_nonce');

    // 既存の値を取得
    $chat_id = get_post_meta($post->ID, 'chat_id', true);
    $team_id = get_post_meta($post->ID, 'team_id', true);
    $message_content = get_post_meta($post->ID, 'message_content', true);
    $message_type = get_post_meta($post->ID, 'message_type', true);
    $is_system_message = get_post_meta($post->ID, 'is_system_message', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="chat_id">チャットID</label></th>
            <td>
                <input type="number" id="chat_id" name="chat_id"
                       value="<?php echo esc_attr($chat_id); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="team_id">送信チームID</label></th>
            <td>
                <input type="number" id="team_id" name="team_id"
                       value="<?php echo esc_attr($team_id); ?>">
                <p class="description">システムメッセージの場合は空</p>
            </td>
        </tr>
        <tr>
            <th><label for="message_content">メッセージ内容</label></th>
            <td>
                <textarea id="message_content" name="message_content" rows="4" cols="50"><?php echo esc_textarea($message_content); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="message_type">メッセージタイプ</label></th>
            <td>
                <select id="message_type" name="message_type">
                    <option value="text" <?php selected($message_type, 'text'); ?>>テキスト</option>
                    <option value="image" <?php selected($message_type, 'image'); ?>>画像</option>
                    <option value="file" <?php selected($message_type, 'file'); ?>>ファイル</option>
                    <option value="system" <?php selected($message_type, 'system'); ?>>システム</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="is_system_message">システムメッセージ</label></th>
            <td>
                <input type="checkbox" id="is_system_message" name="is_system_message"
                       value="1" <?php checked($is_system_message, '1'); ?>>
                <label for="is_system_message">システムメッセージとして表示</label>
            </td>
        </tr>
    </table>
    <?php
}

// メッセージメタボックス保存
function save_mm_chat_message_metabox($post_id) {
    if (!isset($_POST['mm_chat_message_metabox_nonce']) ||
        !wp_verify_nonce($_POST['mm_chat_message_metabox_nonce'], 'save_multi_match_chat_message_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // メタデータ保存
    if (isset($_POST['chat_id'])) {
        update_post_meta($post_id, 'chat_id', sanitize_text_field($_POST['chat_id']));
    }
    if (isset($_POST['team_id'])) {
        update_post_meta($post_id, 'team_id', sanitize_text_field($_POST['team_id']));
    }
    if (isset($_POST['message_content'])) {
        update_post_meta($post_id, 'message_content', sanitize_textarea_field($_POST['message_content']));
    }
    if (isset($_POST['message_type'])) {
        update_post_meta($post_id, 'message_type', sanitize_text_field($_POST['message_type']));
    }
    update_post_meta($post_id, 'is_system_message', isset($_POST['is_system_message']) ? '1' : '0');
}
add_action('save_post', 'save_mm_chat_message_metabox');
