<?php
/**
 * 大会（tournament）投稿タイプ定義
 * 仕様: docs/specs/tournament-feature-spec.md
 */

if (!defined('ABSPATH')) {
    exit;
}

// 大会 CPT 登録
function aidunite_register_tournament_post_type() {
    $labels = array(
        'name'               => '大会',
        'singular_name'      => '大会',
        'menu_name'          => '大会',
        'name_admin_bar'     => '大会を追加',
        'add_new'            => '新規追加',
        'add_new_item'       => '新しい大会を追加',
        'new_item'            => '新規大会',
        'edit_item'          => '大会を編集',
        'view_item'          => '大会を表示',
        'all_items'          => '全大会',
        'search_items'       => '大会を検索',
        'not_found'          => '大会が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に大会はありません',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'   => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'tournament'),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-awards',
        'supports'            => array('title', 'editor', 'author', 'excerpt', 'thumbnail', 'custom-fields'),
        'show_in_rest'        => true,
    );

    register_post_type('tournament', $args);
}
add_action('init', 'aidunite_register_tournament_post_type');

// 大会参加 CPT 登録（投稿タイプ名は WordPress 制限で 1〜20 文字）
function aidunite_register_tournament_participant_post_type() {
    $labels = array(
        'name'               => '大会参加',
        'singular_name'      => '大会参加',
        'menu_name'          => '大会参加',
        'name_admin_bar'     => '大会参加を追加',
        'add_new'            => '新規追加',
        'add_new_item'       => '新しい参加を追加',
        'new_item'            => '新規参加',
        'edit_item'          => '参加を編集',
        'view_item'          => '参加を表示',
        'all_items'          => '全参加',
        'search_items'       => '参加を検索',
        'not_found'          => '参加が見つかりませんでした',
        'not_found_in_trash' => 'ゴミ箱に参加はありません',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=tournament',
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array('title', 'author'),
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
    );

    register_post_type('tournament_entry', $args);
}
add_action('init', 'aidunite_register_tournament_participant_post_type');

// 大会メタボックス追加
function aidunite_add_tournament_metaboxes() {
    add_meta_box(
        'tournament_details',
        '大会詳細',
        'aidunite_render_tournament_metabox',
        'tournament',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'aidunite_add_tournament_metaboxes');

function aidunite_render_tournament_metabox($post) {
    wp_nonce_field('save_tournament_metabox', 'tournament_metabox_nonce');

    $status               = get_post_meta($post->ID, 'tournament_status', true) ?: 'draft';
    $event_date_start     = get_post_meta($post->ID, 'event_date_start', true);
    $event_date_end       = get_post_meta($post->ID, 'event_date_end', true);
    $venue_name          = get_post_meta($post->ID, 'venue_name', true);
    $venue_address       = get_post_meta($post->ID, 'venue_address', true);
    $capacity            = get_post_meta($post->ID, 'capacity', true) ?: 8;
    $application_deadline = get_post_meta($post->ID, 'application_deadline', true);
    $organizer_team_id   = get_post_meta($post->ID, 'organizer_team_id', true);
    $organizer_name      = get_post_meta($post->ID, 'organizer_name', true);
    $tournament_description = get_post_meta($post->ID, 'tournament_description', true);
    if (empty($tournament_description)) {
        $tournament_description = aidunite_get_default_tournament_description();
    }
    $match_format        = get_post_meta($post->ID, 'match_format', true);
    $target_level        = get_post_meta($post->ID, 'target_level', true);
    $participation_region = get_post_meta($post->ID, 'participation_region', true);
    $participation_age_group = get_post_meta($post->ID, 'participation_age_group', true);
    $participation_conditions = get_post_meta($post->ID, 'participation_conditions', true);
    $entry_fee_type       = get_post_meta($post->ID, 'entry_fee_type', true) ?: 'contact_organizer';
    $entry_fee_amount    = get_post_meta($post->ID, 'entry_fee_amount', true);
    $approval_mode       = get_post_meta($post->ID, 'approval_mode', true) ?: 'manual';
    $organizer_user_id   = get_post_meta($post->ID, 'organizer_user_id', true);
    $event_type         = get_post_meta($post->ID, 'event_type', true) ?: 'tournament';

    // application_deadline を datetime-local 用に変換（Y-m-d\TH:i）
    $deadline_local = '';
    if (!empty($application_deadline)) {
        $ts = is_numeric($application_deadline) ? (int) $application_deadline : strtotime($application_deadline);
        if ($ts) {
            $deadline_local = date('Y-m-d\TH:i', $ts);
        } else {
            $deadline_local = $application_deadline;
        }
    }
    ?>
    <table class="form-table">
        <tr>
            <th><label for="tournament_status">大会ステータス</label></th>
            <td>
                <select id="tournament_status" name="tournament_status">
                    <option value="draft" <?php selected($status, 'draft'); ?>>下書き</option>
                    <option value="recruiting" <?php selected($status, 'recruiting'); ?>>募集中</option>
                    <option value="confirmed" <?php selected($status, 'confirmed'); ?>>開催確定</option>
                    <option value="finished" <?php selected($status, 'finished'); ?>>終了</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="event_type">イベント種別</label></th>
            <td>
                <select id="event_type" name="event_type">
                    <?php foreach (aidunite_get_tournament_event_type_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($event_type, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">大会／クリニック／練習会／交流会を同一仕組みで扱います。一覧・詳細で種別を表示し分けます。</p>
            </td>
        </tr>
        <tr>
            <th><label for="event_date_start">開催日（開始）</label></th>
            <td>
                <input type="date" id="event_date_start" name="event_date_start" value="<?php echo esc_attr($event_date_start); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="event_date_end">開催日（終了）</label></th>
            <td>
                <input type="date" id="event_date_end" name="event_date_end" value="<?php echo esc_attr($event_date_end ?: $event_date_start); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="venue_name">会場名</label></th>
            <td>
                <input type="text" id="venue_name" name="venue_name" value="<?php echo esc_attr($venue_name); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="venue_address">会場住所</label></th>
            <td>
                <input type="text" id="venue_address" name="venue_address" value="<?php echo esc_attr($venue_address); ?>" class="large-text">
            </td>
        </tr>
        <tr>
            <th><label for="capacity">参加チーム定員</label></th>
            <td>
                <input type="number" id="capacity" name="capacity" value="<?php echo esc_attr($capacity); ?>" min="1" max="999" required>
            </td>
        </tr>
        <tr>
            <th><label for="application_deadline">申込締切</label></th>
            <td>
                <input type="datetime-local" id="application_deadline" name="application_deadline" value="<?php echo esc_attr($deadline_local); ?>" required>
            </td>
        </tr>
        <tr>
            <th><label for="organizer_team_id">主催チーム（チーム代表者＝主催者）</label></th>
            <td>
                <select id="organizer_team_id" name="organizer_team_id" required>
                    <option value="">— 選択 —</option>
                    <?php
                    $teams = get_posts(array('post_type' => 'team', 'post_status' => 'publish', 'numberposts' => -1));
                    foreach ($teams as $team) {
                        echo '<option value="' . (int) $team->ID . '" ' . selected($organizer_team_id, $team->ID, false) . '>' . esc_html($team->post_title) . '</option>';
                    }
                    ?>
                </select>
                <p class="description">主催者＝選択したチームの代表者。連絡は大会用チャットで行います。</p>
            </td>
        </tr>
        <tr>
            <th><label for="organizer_name">主催者表示名（任意）</label></th>
            <td>
                <input type="text" id="organizer_name" name="organizer_name" value="<?php echo esc_attr($organizer_name); ?>" class="regular-text" placeholder="空欄ならチーム名を表示">
            </td>
        </tr>
        <tr>
            <th><label for="tournament_description">大会説明・備考</label></th>
            <td>
                <textarea id="tournament_description" name="tournament_description" rows="6" class="large-text"><?php echo esc_textarea($tournament_description); ?></textarea>
                <p class="description">デフォルト文を編集して利用できます。</p>
            </td>
        </tr>
        <tr>
            <th><label for="match_format">試合形式</label></th>
            <td>
                <select id="match_format" name="match_format">
                    <option value="">未設定</option>
                    <option value="round_robin" <?php selected($match_format, 'round_robin'); ?>>総当たり</option>
                    <option value="league" <?php selected($match_format, 'league'); ?>>リーグ戦</option>
                    <option value="tournament" <?php selected($match_format, 'tournament'); ?>>トーナメント</option>
                    <option value="other" <?php selected($match_format, 'other'); ?>>その他</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="target_level">対象レベル</label></th>
            <td>
                <select id="target_level" name="target_level">
                    <?php foreach (aidunite_get_tournament_target_level_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($target_level, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="participation_region">参加条件：地域</label></th>
            <td>
                <select id="participation_region" name="participation_region">
                    <?php foreach (aidunite_get_tournament_participation_region_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($participation_region, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="participation_age_group">参加条件：学年・年代</label></th>
            <td>
                <select id="participation_age_group" name="participation_age_group">
                    <?php foreach (aidunite_get_tournament_participation_age_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($participation_age_group, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="participation_conditions">参加条件：その他</label></th>
            <td>
                <textarea id="participation_conditions" name="participation_conditions" rows="2" class="large-text"><?php echo esc_textarea($participation_conditions); ?></textarea>
            </td>
        </tr>
        <tr>
            <th><label for="entry_fee_type">参加費</label></th>
            <td>
                <select id="entry_fee_type" name="entry_fee_type">
                    <option value="amount" <?php selected($entry_fee_type, 'amount'); ?>>金額を設定</option>
                    <option value="contact_organizer" <?php selected($entry_fee_type, 'contact_organizer'); ?>>主催者と直接調整</option>
                </select>
                <span id="entry_fee_amount_wrap" style="<?php echo $entry_fee_type !== 'amount' ? 'display:none' : ''; ?>">
                    <input type="number" id="entry_fee_amount" name="entry_fee_amount" value="<?php echo esc_attr($entry_fee_amount); ?>" min="0" placeholder="円">
                    円
                </span>
            </td>
        </tr>
        <tr>
            <th><label for="approval_mode">申込承認</label></th>
            <td>
                <select id="approval_mode" name="approval_mode">
                    <option value="manual" <?php selected($approval_mode, 'manual'); ?>>主催者が承認</option>
                    <option value="auto" <?php selected($approval_mode, 'auto'); ?>>自動承認</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="organizer_user_id">主催者ユーザーID（任意）</label></th>
            <td>
                <input type="number" id="organizer_user_id" name="organizer_user_id" value="<?php echo esc_attr($organizer_user_id); ?>" min="0">
            </td>
        </tr>
    </table>
    <script>
    document.getElementById('entry_fee_type').addEventListener('change', function() {
        document.getElementById('entry_fee_amount_wrap').style.display = this.value === 'amount' ? '' : 'none';
    });
    </script>
    <?php
}

function aidunite_save_tournament_metabox($post_id) {
    if (!isset($_POST['tournament_metabox_nonce']) || !wp_verify_nonce($_POST['tournament_metabox_nonce'], 'save_tournament_metabox')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (get_post_type($post_id) !== 'tournament') {
        return;
    }

    $fields = array(
        'tournament_status', 'event_type', 'event_date_start', 'event_date_end', 'venue_name', 'venue_address',
        'capacity', 'application_deadline', 'organizer_team_id', 'organizer_name',
        'tournament_description', 'match_format', 'target_level',
        'participation_region', 'participation_age_group', 'participation_conditions',
        'entry_fee_type', 'entry_fee_amount', 'approval_mode', 'organizer_user_id',
    );

    foreach ($fields as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $val = $_POST[$key];
        if (in_array($key, array('capacity', 'entry_fee_amount', 'organizer_user_id', 'organizer_team_id'), true)) {
            $val = absint($val);
        } else {
            $val = sanitize_text_field($val);
        }
        update_post_meta($post_id, $key, $val);
    }

    // application_deadline を Y-m-d H:i:s で保存（datetime-local は Y-m-d\TH:i で送られる）
    if (!empty($_POST['application_deadline'])) {
        $dl = sanitize_text_field($_POST['application_deadline']);
        $ts = strtotime(str_replace('T', ' ', $dl));
        if ($ts) {
            update_post_meta($post_id, 'application_deadline', date('Y-m-d H:i:s', $ts));
        }
    }
    // 主催チームのみ設定されている場合は表示名をチーム名で補完
    $oid = (int) get_post_meta($post_id, 'organizer_team_id', true);
    $oname = get_post_meta($post_id, 'organizer_name', true);
    if ($oid && trim($oname) === '') {
        $team = get_post($oid);
        if ($team) {
            update_post_meta($post_id, 'organizer_name', $team->post_title);
        }
    }
}
add_action('save_post_tournament', 'aidunite_save_tournament_metabox');

// 大会参加メタボックス
function aidunite_add_tournament_participant_metaboxes() {
    add_meta_box(
        'tournament_participant_details',
        '参加詳細',
        'aidunite_render_tournament_participant_metabox',
        'tournament_entry',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'aidunite_add_tournament_participant_metaboxes');

function aidunite_render_tournament_participant_metabox($post) {
    wp_nonce_field('save_tournament_participant_metabox', 'tournament_participant_metabox_nonce');

    $tournament_id    = get_post_meta($post->ID, 'tournament_id', true);
    $team_id         = get_post_meta($post->ID, 'team_id', true);
    $participant_status = get_post_meta($post->ID, 'participant_status', true) ?: 'applied';
    $applied_at      = get_post_meta($post->ID, 'applied_at', true);
    $accepted_at     = get_post_meta($post->ID, 'accepted_at', true);
    $canceled_at     = get_post_meta($post->ID, 'canceled_at', true);
    $application_note = get_post_meta($post->ID, 'application_note', true);

    $tournaments = get_posts(array('post_type' => 'tournament', 'post_status' => array('publish', 'draft'), 'numberposts' => -1));
    $teams = get_posts(array('post_type' => 'team', 'post_status' => 'publish', 'numberposts' => -1));
    ?>
    <table class="form-table">
        <tr>
            <th><label for="tournament_id">大会</label></th>
            <td>
                <select id="tournament_id" name="tournament_id" required>
                    <option value="">— 選択 —</option>
                    <?php foreach ($tournaments as $t) : ?>
                        <option value="<?php echo (int) $t->ID; ?>" <?php selected($tournament_id, $t->ID); ?>><?php echo esc_html($t->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="team_id">チーム</label></th>
            <td>
                <select id="team_id" name="team_id" required>
                    <option value="">— 選択 —</option>
                    <?php foreach ($teams as $t) : ?>
                        <option value="<?php echo (int) $t->ID; ?>" <?php selected($team_id, $t->ID); ?>><?php echo esc_html($t->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="participant_status">参加ステータス</label></th>
            <td>
                <select id="participant_status" name="participant_status">
                    <option value="applied" <?php selected($participant_status, 'applied'); ?>>申込済み</option>
                    <option value="accepted" <?php selected($participant_status, 'accepted'); ?>>参加確定</option>
                    <option value="canceled" <?php selected($participant_status, 'canceled'); ?>>辞退</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="applied_at">申込日時</label></th>
            <td>
                <input type="datetime-local" id="applied_at" name="applied_at" value="<?php echo esc_attr(aidunite_format_datetime_local($applied_at)); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="accepted_at">参加確定日時</label></th>
            <td>
                <input type="datetime-local" id="accepted_at" name="accepted_at" value="<?php echo esc_attr(aidunite_format_datetime_local($accepted_at)); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="canceled_at">辞退日時</label></th>
            <td>
                <input type="datetime-local" id="canceled_at" name="canceled_at" value="<?php echo esc_attr(aidunite_format_datetime_local($canceled_at)); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="application_note">申込メッセージ</label></th>
            <td>
                <textarea id="application_note" name="application_note" rows="2" class="large-text"><?php echo esc_textarea($application_note); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

function aidunite_format_datetime_local($value) {
    if (empty($value)) {
        return '';
    }
    $ts = is_numeric($value) ? (int) $value : strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

function aidunite_save_tournament_participant_metabox($post_id) {
    if (!isset($_POST['tournament_participant_metabox_nonce']) || !wp_verify_nonce($_POST['tournament_participant_metabox_nonce'], 'save_tournament_participant_metabox')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (get_post_type($post_id) !== 'tournament_entry') {
        return;
    }

    $fields = array('tournament_id', 'team_id', 'participant_status', 'applied_at', 'accepted_at', 'canceled_at', 'application_note');
    foreach ($fields as $key) {
        if (!isset($_POST[$key])) {
            continue;
        }
        $val = sanitize_text_field($_POST[$key]);
        if ($key === 'tournament_id' || $key === 'team_id') {
            $val = absint($val);
        }
        if (($key === 'applied_at' || $key === 'accepted_at' || $key === 'canceled_at') && $val !== '') {
            $ts = strtotime(str_replace('T', ' ', $val));
            if ($ts) {
                $val = date('Y-m-d H:i:s', $ts);
            }
        }
        update_post_meta($post_id, $key, $val);
    }

    // 参加確定時にチームのカレンダーへ仮スケジュールを自動登録
    $new_status = isset($_POST['participant_status']) ? sanitize_text_field($_POST['participant_status']) : '';
    if ($new_status === 'accepted' && function_exists('aidunite_ensure_tournament_schedule_for_participant')) {
        aidunite_ensure_tournament_schedule_for_participant($post_id);
    }
}
add_action('save_post_tournament_entry', 'aidunite_save_tournament_participant_metabox');
