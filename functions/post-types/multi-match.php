<?php
/**
 * 複数チームマッチ投稿タイプ定義
 */

// 複数チームマッチ投稿タイプ登録
function register_multi_match_post_type() {
    $labels = array(
        'name' => '複数チームマッチ',
        'singular_name' => '複数チームマッチ',
        'menu_name' => '複数チームマッチ',
        'name_admin_bar' => '複数チームマッチを追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しい複数チームマッチを追加',
        'new_item' => '新規複数チームマッチ',
        'edit_item' => '複数チームマッチを編集',
        'view_item' => '複数チームマッチを表示',
        'all_items' => '全複数チームマッチ',
        'search_items' => '複数チームマッチを検索',
        'not_found' => '複数チームマッチが見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に複数チームマッチはいません',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'multi-match'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
        'show_in_rest' => true,
    );

    register_post_type('multi_match', $args);
}
add_action('init', 'register_multi_match_post_type');

// 複数チームマッチ参加者投稿タイプ登録
function register_mm_participant_post_type() {
    $labels = array(
        'name' => '複数チームマッチ参加者',
        'singular_name' => '複数チームマッチ参加者',
        'menu_name' => '複数チームマッチ参加者',
        'name_admin_bar' => '複数チームマッチ参加者を追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しい参加者を追加',
        'new_item' => '新規参加者',
        'edit_item' => '参加者を編集',
        'view_item' => '参加者を表示',
        'all_items' => '全参加者',
        'search_items' => '参加者を検索',
        'not_found' => '参加者が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に参加者はいません',
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

    register_post_type('mm_participant', $args);
}
add_action('init', 'register_mm_participant_post_type');

// 複数チームマッチ結果投稿タイプ登録
function register_multi_match_result_post_type() {
    $labels = array(
        'name' => '複数チームマッチ結果',
        'singular_name' => '複数チームマッチ結果',
        'menu_name' => '複数チームマッチ結果',
        'name_admin_bar' => '複数チームマッチ結果を追加',
        'add_new' => '新規追加',
        'add_new_item' => '新しい結果を追加',
        'new_item' => '新規結果',
        'edit_item' => '結果を編集',
        'view_item' => '結果を表示',
        'all_items' => '全結果',
        'search_items' => '結果を検索',
        'not_found' => '結果が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に結果はいません',
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

    register_post_type('multi_match_result', $args);
}
add_action('init', 'register_multi_match_result_post_type');

// 複数チームマッチメタボックス追加
function add_multi_match_metaboxes() {
    add_meta_box(
        'multi_match_details',
        '複数チームマッチ詳細',
        'render_multi_match_metabox',
        'multi_match',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_multi_match_metaboxes');

// メタボックス表示
function render_multi_match_metabox($post) {
    wp_nonce_field('save_multi_match_metabox', 'multi_match_metabox_nonce');

    // 既存の値を取得
    $match_type = get_post_meta($post->ID, 'match_type', true);
    $match_date = get_post_meta($post->ID, 'match_date', true);
    $match_start_time = get_post_meta($post->ID, 'match_start_time', true);
    $match_end_time = get_post_meta($post->ID, 'match_end_time', true);
    $match_place = get_post_meta($post->ID, 'match_place', true);
    $max_teams = get_post_meta($post->ID, 'max_teams', true);
    $min_teams = get_post_meta($post->ID, 'min_teams', true);
    $organizer_team_id = get_post_meta($post->ID, 'organizer_team_id', true);
    $match_status = get_post_meta($post->ID, 'match_status', true);
    $match_format = get_post_meta($post->ID, 'match_format', true);
    $entry_deadline = get_post_meta($post->ID, 'entry_deadline', true);
    $match_description = get_post_meta($post->ID, 'match_description', true);
    $match_rules = get_post_meta($post->ID, 'match_rules', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="match_type">マッチタイプ</label></th>
            <td>
                <select id="match_type" name="match_type">
                    <option value="tournament" <?php selected($match_type, 'tournament'); ?>>トーナメント</option>
                    <option value="league" <?php selected($match_type, 'league'); ?>>リーグ戦</option>
                    <option value="practice_session" <?php selected($match_type, 'practice_session'); ?>>練習会</option>
                    <option value="friendly" <?php selected($match_type, 'friendly'); ?>>親善試合</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="match_format">試合形式</label></th>
            <td>
                <select id="match_format" name="match_format">
                    <option value="sequential_matches" <?php selected($match_format, 'sequential_matches'); ?>>順番対戦</option>
                    <option value="round_robin" <?php selected($match_format, 'round_robin'); ?>>総当たり戦</option>
                    <option value="single_elimination" <?php selected($match_format, 'single_elimination'); ?>>トーナメント戦（勝ち抜き）</option>
                    <option value="double_elimination" <?php selected($match_format, 'double_elimination'); ?>>トーナメント戦（敗者復活）</option>
                    <option value="group_stage" <?php selected($match_format, 'group_stage'); ?>>グループリーグ</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="match_date">開催日</label></th>
            <td>
                <input type="date" id="match_date" name="match_date"
                       value="<?php echo esc_attr($match_date); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="match_start_time">開始時間</label></th>
            <td>
                <input type="time" id="match_start_time" name="match_start_time"
                       value="<?php echo esc_attr($match_start_time); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="match_end_time">終了時間</label></th>
            <td>
                <input type="time" id="match_end_time" name="match_end_time"
                       value="<?php echo esc_attr($match_end_time); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="match_place">開催場所</label></th>
            <td>
                <input type="text" id="match_place" name="match_place"
                       value="<?php echo esc_attr($match_place); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="max_teams">最大参加チーム数</label></th>
            <td>
                <input type="number" id="max_teams" name="max_teams"
                       value="<?php echo esc_attr($max_teams); ?>" min="3" max="20" required>
            </td>
        </tr>
        <tr>
            <th><label for="min_teams">最小参加チーム数</label></th>
            <td>
                <input type="number" id="min_teams" name="min_teams"
                       value="<?php echo esc_attr($min_teams); ?>" min="3" max="20" required>
            </td>
        </tr>
        <tr>
            <th><label for="organizer_team_id">主催チーム</label></th>
            <td>
                <select id="organizer_team_id" name="organizer_team_id" required>
                    <option value="">主催チームを選択</option>
                    <?php
                    $teams = get_posts([
                        'post_type' => 'team',
                        'post_status' => 'publish',
                        'numberposts' => -1
                    ]);
                    foreach ($teams as $team) {
                        $selected = ($organizer_team_id == $team->ID) ? 'selected' : '';
                        echo "<option value='{$team->ID}' {$selected}>" . esc_html($team->post_title) . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="match_status">マッチステータス</label></th>
            <td>
                <select id="match_status" name="match_status">
                    <option value="planning" <?php selected($match_status, 'planning'); ?>>企画中</option>
                    <option value="recruiting" <?php selected($match_status, 'recruiting'); ?>>参加者募集中</option>
                    <option value="confirmed" <?php selected($match_status, 'confirmed'); ?>>確定</option>
                    <option value="completed" <?php selected($match_status, 'completed'); ?>>完了</option>
                    <option value="cancelled" <?php selected($match_status, 'cancelled'); ?>>中止</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="entry_deadline">参加申込締切</label></th>
            <td>
                <input type="datetime-local" id="entry_deadline" name="entry_deadline"
                       value="<?php echo esc_attr($entry_deadline); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="match_description">マッチ詳細</label></th>
            <td>
                <textarea id="match_description" name="match_description" rows="4" cols="50"><?php echo esc_textarea($match_description); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="match_rules">試合ルール</label></th>
            <td>
                <textarea id="match_rules" name="match_rules" rows="6" cols="50"><?php echo esc_textarea($match_rules); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// メタボックス保存
function save_multi_match_metabox($post_id) {
    if (!isset($_POST['multi_match_metabox_nonce']) ||
        !wp_verify_nonce($_POST['multi_match_metabox_nonce'], 'save_multi_match_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // メタデータ保存
    $fields = [
        'match_type', 'match_format', 'match_date', 'match_start_time', 'match_end_time',
        'match_place', 'max_teams', 'min_teams', 'organizer_team_id', 'match_status',
        'entry_deadline', 'match_description', 'match_rules'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('save_post', 'save_multi_match_metabox');

// 複数チームマッチ参加者メタボックス追加
function add_mm_participant_metaboxes() {
    add_meta_box(
        'mm_participant_details',
        '参加者詳細',
        'render_mm_participant_metabox',
        'mm_participant',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_mm_participant_metaboxes');

// 参加者メタボックス表示
function render_mm_participant_metabox($post) {
    wp_nonce_field('save_multi_match_participant_metabox', 'mm_participant_metabox_nonce');

    // 既存の値を取得
    $multi_match_id = get_post_meta($post->ID, 'multi_match_id', true);
    $team_id = get_post_meta($post->ID, 'team_id', true);
    $participant_status = get_post_meta($post->ID, 'participant_status', true);
    $application_date = get_post_meta($post->ID, 'application_date', true);
    $approval_date = get_post_meta($post->ID, 'approval_date', true);
    $withdrawal_date = get_post_meta($post->ID, 'withdrawal_date', true);
    $application_message = get_post_meta($post->ID, 'application_message', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="multi_match_id">複数チームマッチID</label></th>
            <td>
                <input type="number" id="multi_match_id" name="multi_match_id"
                       value="<?php echo esc_attr($multi_match_id); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="team_id">参加チーム</label></th>
            <td>
                <select id="team_id" name="team_id" required>
                    <option value="">チームを選択</option>
                    <?php
                    $teams = get_posts([
                        'post_type' => 'team',
                        'post_status' => 'publish',
                        'numberposts' => -1
                    ]);
                    foreach ($teams as $team) {
                        $selected = ($team_id == $team->ID) ? 'selected' : '';
                        echo "<option value='{$team->ID}' {$selected}>" . esc_html($team->post_title) . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="participant_status">参加ステータス</label></th>
            <td>
                <select id="participant_status" name="participant_status">
                    <option value="pending" <?php selected($participant_status, 'pending'); ?>>承認待ち</option>
                    <option value="approved" <?php selected($participant_status, 'approved'); ?>>承認済み</option>
                    <option value="rejected" <?php selected($participant_status, 'rejected'); ?>>拒否</option>
                    <option value="withdrawn" <?php selected($participant_status, 'withdrawn'); ?>>辞退</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="application_date">申込日</label></th>
            <td>
                <input type="datetime-local" id="application_date" name="application_date"
                       value="<?php echo esc_attr($application_date); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="approval_date">承認日</label></th>
            <td>
                <input type="datetime-local" id="approval_date" name="approval_date"
                       value="<?php echo esc_attr($approval_date); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="withdrawal_date">辞退日</label></th>
            <td>
                <input type="datetime-local" id="withdrawal_date" name="withdrawal_date"
                       value="<?php echo esc_attr($withdrawal_date); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="application_message">申込メッセージ</label></th>
            <td>
                <textarea id="application_message" name="application_message" rows="4" cols="50"><?php echo esc_textarea($application_message); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// 参加者メタボックス保存
function save_mm_participant_metabox($post_id) {
    if (!isset($_POST['mm_participant_metabox_nonce']) ||
        !wp_verify_nonce($_POST['mm_participant_metabox_nonce'], 'save_multi_match_participant_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // メタデータ保存
    $fields = [
        'multi_match_id', 'team_id', 'participant_status', 'application_date',
        'approval_date', 'withdrawal_date', 'application_message'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'application_message') {
                update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
            } else {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}
add_action('save_post', 'save_mm_participant_metabox');

// 複数チームマッチ結果メタボックス追加
function add_multi_match_result_metaboxes() {
    add_meta_box(
        'multi_match_result_details',
        '試合結果詳細',
        'render_multi_match_result_metabox',
        'multi_match_result',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_multi_match_result_metaboxes');

// 結果メタボックス表示
function render_multi_match_result_metabox($post) {
    wp_nonce_field('save_multi_match_result_metabox', 'multi_match_result_metabox_nonce');

    // 既存の値を取得
    $multi_match_id = get_post_meta($post->ID, 'multi_match_id', true);
    $match_number = get_post_meta($post->ID, 'match_number', true);
    $team1_id = get_post_meta($post->ID, 'team1_id', true);
    $team2_id = get_post_meta($post->ID, 'team2_id', true);
    $official_team_id = get_post_meta($post->ID, 'official_team_id', true);
    $team1_score = get_post_meta($post->ID, 'team1_score', true);
    $team2_score = get_post_meta($post->ID, 'team2_score', true);
    $match_type = get_post_meta($post->ID, 'match_type', true);
    $match_round = get_post_meta($post->ID, 'match_round', true);
    $result_status = get_post_meta($post->ID, 'result_status', true);
    $match_date = get_post_meta($post->ID, 'match_date', true);
    $match_duration = get_post_meta($post->ID, 'match_duration', true);
    $match_notes = get_post_meta($post->ID, 'match_notes', true);

    ?>
    <table class="form-table">
        <tr>
            <th><label for="multi_match_id">複数チームマッチID</label></th>
            <td>
                <input type="number" id="multi_match_id" name="multi_match_id"
                       value="<?php echo esc_attr($multi_match_id); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="match_number">試合番号</label></th>
            <td>
                <input type="number" id="match_number" name="match_number"
                       value="<?php echo esc_attr($match_number); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="team1_id">チーム1</label></th>
            <td>
                <select id="team1_id" name="team1_id" required>
                    <option value="">チームを選択</option>
                    <?php
                    $teams = get_posts([
                        'post_type' => 'team',
                        'post_status' => 'publish',
                        'numberposts' => -1
                    ]);
                    foreach ($teams as $team) {
                        $selected = ($team1_id == $team->ID) ? 'selected' : '';
                        echo "<option value='{$team->ID}' {$selected}>" . esc_html($team->post_title) . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team2_id">チーム2</label></th>
            <td>
                <select id="team2_id" name="team2_id" required>
                    <option value="">チームを選択</option>
                    <?php
                    foreach ($teams as $team) {
                        $selected = ($team2_id == $team->ID) ? 'selected' : '';
                        echo "<option value='{$team->ID}' {$selected}>" . esc_html($team->post_title) . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="official_team_id">オフィシャルチーム</label></th>
            <td>
                <select id="official_team_id" name="official_team_id">
                    <option value="">オフィシャルチームを選択</option>
                    <?php
                    foreach ($teams as $team) {
                        $selected = ($official_team_id == $team->ID) ? 'selected' : '';
                        echo "<option value='{$team->ID}' {$selected}>" . esc_html($team->post_title) . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team1_score">チーム1スコア</label></th>
            <td>
                <input type="number" id="team1_score" name="team1_score"
                       value="<?php echo esc_attr($team1_score); ?>" min="0">
            </td>
        </tr>
        <tr>
            <th><label for="team2_score">チーム2スコア</label></th>
            <td>
                <input type="number" id="team2_score" name="team2_score"
                       value="<?php echo esc_attr($team2_score); ?>" min="0">
            </td>
        </tr>
        <tr>
            <th><label for="match_type">試合タイプ</label></th>
            <td>
                <select id="match_type" name="match_type">
                    <option value="sequential" <?php selected($match_type, 'sequential'); ?>>順番対戦</option>
                    <option value="round_robin" <?php selected($match_type, 'round_robin'); ?>>総当たり戦</option>
                    <option value="single_elimination" <?php selected($match_type, 'single_elimination'); ?>>トーナメント戦</option>
                    <option value="group_stage" <?php selected($match_type, 'group_stage'); ?>>グループリーグ</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="match_round">ラウンド</label></th>
            <td>
                <input type="number" id="match_round" name="match_round"
                       value="<?php echo esc_attr($match_round); ?>" min="1">
            </td>
        </tr>
        <tr>
            <th><label for="result_status">結果ステータス</label></th>
            <td>
                <select id="result_status" name="result_status">
                    <option value="scheduled" <?php selected($result_status, 'scheduled'); ?>>予定</option>
                    <option value="in_progress" <?php selected($result_status, 'in_progress'); ?>>進行中</option>
                    <option value="completed" <?php selected($result_status, 'completed'); ?>>完了</option>
                    <option value="cancelled" <?php selected($result_status, 'cancelled'); ?>>中止</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="match_date">試合日</label></th>
            <td>
                <input type="datetime-local" id="match_date" name="match_date"
                       value="<?php echo esc_attr($match_date); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="match_duration">試合時間（分）</label></th>
            <td>
                <input type="number" id="match_duration" name="match_duration"
                       value="<?php echo esc_attr($match_duration); ?>" min="0">
            </td>
        </tr>
        <tr>
            <th><label for="match_notes">試合メモ</label></th>
            <td>
                <textarea id="match_notes" name="match_notes" rows="4" cols="50"><?php echo esc_textarea($match_notes); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

// 結果メタボックス保存
function save_multi_match_result_metabox($post_id) {
    if (!isset($_POST['multi_match_result_metabox_nonce']) ||
        !wp_verify_nonce($_POST['multi_match_result_metabox_nonce'], 'save_multi_match_result_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // メタデータ保存
    $fields = [
        'multi_match_id', 'match_number', 'team1_id', 'team2_id', 'official_team_id',
        'team1_score', 'team2_score', 'match_type', 'match_round', 'result_status',
        'match_date', 'match_duration', 'match_notes'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'match_notes') {
                update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
            } else {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}
add_action('save_post', 'save_multi_match_result_metabox');
