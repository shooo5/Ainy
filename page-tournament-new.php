<?php
/**
 * Template Name: 大会作成
 * 固定ページスラッグ: tournament-new で使用
 * 仕様: docs/specs/tournament-feature-spec.md
 */

// ログイン必須
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = $auth_result->user_id;
$user_team_id = (int) get_user_meta($current_user_id, 'team_id', true);

// 開発の管理者以外は作成フォームを非表示（Coming Soon）
$can_create_tournament = current_user_can('manage_options');

$created_message = '';
$created_tournament_id = 0;

// POST: 大会作成（管理者のみ処理）
if ($can_create_tournament && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tournament_new_nonce']) && wp_verify_nonce($_POST['tournament_new_nonce'], 'tournament_new')) {
    $title = isset($_POST['tournament_title']) ? sanitize_text_field($_POST['tournament_title']) : '';
    if (empty($title)) {
        $created_message = '<p class="tournament-form-message error">大会名を入力してください。</p>';
    } else {
        $event_date_start = isset($_POST['event_date_start']) ? sanitize_text_field($_POST['event_date_start']) : '';
        $event_date_end = isset($_POST['event_date_end']) ? sanitize_text_field($_POST['event_date_end']) : $event_date_start;
        $venue_name = isset($_POST['venue_name']) ? sanitize_text_field($_POST['venue_name']) : '';
        $venue_address = isset($_POST['venue_address']) ? sanitize_text_field($_POST['venue_address']) : '';
        $capacity = isset($_POST['capacity']) ? absint($_POST['capacity']) : 8;
        $application_deadline = isset($_POST['application_deadline']) ? sanitize_text_field($_POST['application_deadline']) : '';
        $organizer_team_id = isset($_POST['organizer_team_id']) ? absint($_POST['organizer_team_id']) : 0;
        $organizer_name = isset($_POST['organizer_name']) ? sanitize_text_field($_POST['organizer_name']) : '';
        $tournament_description = isset($_POST['tournament_description']) ? sanitize_textarea_field($_POST['tournament_description']) : '';
        if (empty($tournament_description)) {
            $tournament_description = aidunite_get_default_tournament_description();
        }
        $match_format = isset($_POST['match_format']) ? sanitize_text_field($_POST['match_format']) : '';
        $target_level = isset($_POST['target_level']) ? sanitize_text_field($_POST['target_level']) : '';
        $participation_region = isset($_POST['participation_region']) ? sanitize_text_field($_POST['participation_region']) : '';
        $participation_age_group = isset($_POST['participation_age_group']) ? sanitize_text_field($_POST['participation_age_group']) : '';
        $participation_conditions = isset($_POST['participation_conditions']) ? sanitize_textarea_field($_POST['participation_conditions']) : '';
        $entry_fee_type = isset($_POST['entry_fee_type']) ? sanitize_text_field($_POST['entry_fee_type']) : 'contact_organizer';
        $entry_fee_amount = isset($_POST['entry_fee_amount']) ? absint($_POST['entry_fee_amount']) : 0;
        $approval_mode = isset($_POST['approval_mode']) ? sanitize_text_field($_POST['approval_mode']) : 'manual';
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'tournament';
        $event_type = in_array($event_type, array_keys(aidunite_get_tournament_event_type_options()), true) ? $event_type : 'tournament';

        if (empty($event_date_start) || empty($venue_name) || empty($application_deadline) || empty($organizer_team_id)) {
            $created_message = '<p class="tournament-form-message error">必須項目（開催日・会場・申込締切・主催チーム）を入力してください。</p>';
        } else {
            // 締切期限: 開催日（開始）より前であること
            $deadline_ts = strtotime(str_replace('T', ' ', $application_deadline));
            $event_start_ts = strtotime($event_date_start . ' 00:00:00');
            if ($deadline_ts && $event_start_ts && $deadline_ts >= $event_start_ts) {
                $created_message = '<p class="tournament-form-message error">申込締切は開催日（開始）より前の日時を指定してください。</p>';
            } else {
            $post_id = wp_insert_post(array(
                'post_type'   => 'tournament',
                'post_title'  => $title,
                'post_status' => 'publish',
                'post_author' => $current_user_id,
            ));
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, 'tournament_status', 'draft');
                update_post_meta($post_id, 'event_date_start', $event_date_start);
                update_post_meta($post_id, 'event_date_end', $event_date_end);
                update_post_meta($post_id, 'venue_name', $venue_name);
                update_post_meta($post_id, 'venue_address', $venue_address);
                update_post_meta($post_id, 'capacity', $capacity);
                if (!empty($application_deadline)) {
                    $ts = strtotime(str_replace('T', ' ', $application_deadline));
                    if ($ts) {
                        update_post_meta($post_id, 'application_deadline', date('Y-m-d H:i:s', $ts));
                    } else {
                        update_post_meta($post_id, 'application_deadline', $application_deadline);
                    }
                }
                update_post_meta($post_id, 'organizer_team_id', $organizer_team_id);
                update_post_meta($post_id, 'organizer_name', $organizer_name);
                update_post_meta($post_id, 'tournament_description', $tournament_description);
                update_post_meta($post_id, 'match_format', $match_format);
                update_post_meta($post_id, 'target_level', $target_level);
                update_post_meta($post_id, 'participation_region', $participation_region);
                update_post_meta($post_id, 'participation_age_group', $participation_age_group);
                update_post_meta($post_id, 'participation_conditions', $participation_conditions);
                update_post_meta($post_id, 'entry_fee_type', $entry_fee_type);
                update_post_meta($post_id, 'entry_fee_amount', $entry_fee_amount);
                update_post_meta($post_id, 'approval_mode', $approval_mode);
                update_post_meta($post_id, 'event_type', $event_type);
                update_post_meta($post_id, 'organizer_user_id', $current_user_id);
                if (!$organizer_name && $organizer_team_id) {
                    $team = get_post($organizer_team_id);
                    if ($team) {
                        update_post_meta($post_id, 'organizer_name', $team->post_title);
                    }
                }

                $created_tournament_id = $post_id;
                $created_message = '<p class="tournament-form-message success">下書きを保存しました。募集を開始するには「大会ページ」で内容を確認し、管理画面からステータスを「募集中」に変更してください。</p>';
            } else {
                $created_message = '<p class="tournament-form-message error">保存に失敗しました。</p>';
            }
            }
        }
    }
}

get_header();
?>

<main id="main" class="site-main tournament-new-page">
    <div class="container" style="max-width: 720px; margin: 0 auto; padding: var(--spacing-base, 1rem);">
        <p style="margin-bottom: 1rem;">
            <a href="<?php echo esc_url(get_permalink(get_page_by_path('tournaments')) ?: home_url('/tournaments/')); ?>">&larr; 大会一覧へ</a>
        </p>

        <h1 class="page-title">大会を作成</h1>

        <?php if (!$can_create_tournament) : ?>
            <div class="tournament-coming-soon" style="text-align: center; padding: var(--spacing-xl, 2rem) var(--spacing-base, 1rem); background: var(--bg-secondary, #f8f9fa); border-radius: var(--radius-medium, 8px); margin: var(--spacing-lg, 1.5rem) 0;">
                <p class="tournament-coming-soon-label" style="font-size: var(--font-size-lg, 1.125rem); font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-sm, 0.5rem);">Coming Soon</p>
                <p class="tournament-coming-soon-desc" style="color: var(--text-secondary, #666); font-size: var(--font-size-base, 1rem); margin: 0;">大会・クリニック・練習会の作成機能は準備中です。もうしばらくお待ちください。</p>
            </div>
        <?php else : ?>
        <?php echo $created_message; ?>

        <?php if ($created_tournament_id) : ?>
            <p>
                <a href="<?php echo esc_url(get_permalink($created_tournament_id)); ?>" class="btn btn-primary">作成した大会を見る</a>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $created_tournament_id . '&action=edit')); ?>" class="btn">管理画面で編集・募集開始</a>
            </p>
        <?php else : ?>
            <form method="post" action="" class="tournament-new-form" style="margin-top: 1rem;">
                <?php wp_nonce_field('tournament_new', 'tournament_new_nonce'); ?>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="tournament_title">大会名 <span class="required">*</span></label>
                    <input type="text" id="tournament_title" name="tournament_title" class="form-control" value="<?php echo esc_attr(isset($_POST['tournament_title']) ? $_POST['tournament_title'] : ''); ?>" required placeholder="例: A〇〇カップ">
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="event_type">イベント種別</label>
                    <select id="event_type" name="event_type" class="form-control">
                        <?php
                        $event_type_options = aidunite_get_tournament_event_type_options();
                        $selected_event_type = isset($_POST['event_type']) ? $_POST['event_type'] : 'tournament';
                        foreach ($event_type_options as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($selected_event_type, $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="margin-top: 0.25rem; font-size: var(--font-size-sm, 0.875rem); color: var(--text-muted, #666);">大会／クリニック／練習会／交流会から選択。一覧・詳細で種別を表示し分けます。</p>
                </div>

                <div class="form-row" style="display: flex; gap: 1rem; margin-bottom: var(--spacing-base, 1rem);">
                    <div class="form-group" style="flex: 1;">
                        <label for="event_date_start">開催日（開始） <span class="required">*</span></label>
                        <input type="date" id="event_date_start" name="event_date_start" class="form-control" value="<?php echo esc_attr(isset($_POST['event_date_start']) ? $_POST['event_date_start'] : ''); ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="event_date_end">開催日（終了） <span class="required">*</span></label>
                        <input type="date" id="event_date_end" name="event_date_end" class="form-control" value="<?php echo esc_attr(isset($_POST['event_date_end']) ? $_POST['event_date_end'] : (isset($_POST['event_date_start']) ? $_POST['event_date_start'] : '')); ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="venue_name">会場名 <span class="required">*</span></label>
                    <input type="text" id="venue_name" name="venue_name" class="form-control" value="<?php echo esc_attr(isset($_POST['venue_name']) ? $_POST['venue_name'] : ''); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="venue_address">会場住所</label>
                    <input type="text" id="venue_address" name="venue_address" class="form-control" value="<?php echo esc_attr(isset($_POST['venue_address']) ? $_POST['venue_address'] : ''); ?>">
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="capacity">参加チーム定員 <span class="required">*</span></label>
                    <input type="number" id="capacity" name="capacity" class="form-control" value="<?php echo esc_attr(isset($_POST['capacity']) ? (int) $_POST['capacity'] : 8); ?>" min="1" max="999" required>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="application_deadline">申込締切 <span class="required">*</span></label>
                    <input type="datetime-local" id="application_deadline" name="application_deadline" class="form-control" value="<?php echo esc_attr(isset($_POST['application_deadline']) ? $_POST['application_deadline'] : ''); ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="organizer_team_id">主催チーム（チーム代表者＝主催者） <span class="required">*</span></label>
                    <select id="organizer_team_id" name="organizer_team_id" class="form-control" required>
                        <option value="">— 選択 —</option>
                        <?php
                        $teams = get_posts(array('post_type' => 'team', 'post_status' => 'publish', 'numberposts' => -1));
                        foreach ($teams as $t) :
                            $sel = (isset($_POST['organizer_team_id']) && (int) $_POST['organizer_team_id'] === (int) $t->ID) ? ' selected' : '';
                        ?>
                            <option value="<?php echo (int) $t->ID; ?>"<?php echo $sel; ?>><?php echo esc_html($t->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="margin-top: 0.25rem; font-size: var(--font-size-sm, 0.875rem); color: var(--text-muted, #666);">連絡は大会用チャットで行います。</p>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="organizer_name">主催者表示名（任意）</label>
                    <input type="text" id="organizer_name" name="organizer_name" class="form-control" value="<?php echo esc_attr(isset($_POST['organizer_name']) ? $_POST['organizer_name'] : ''); ?>" placeholder="空欄ならチーム名を表示">
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="tournament_description">大会説明・備考</label>
                    <textarea id="tournament_description" name="tournament_description" class="form-control" rows="6"><?php echo esc_textarea(isset($_POST['tournament_description']) ? $_POST['tournament_description'] : aidunite_get_default_tournament_description()); ?></textarea>
                    <p class="description" style="margin-top: 0.25rem; font-size: var(--font-size-sm, 0.875rem); color: var(--text-muted, #666);">デフォルト文を編集して利用できます。</p>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="match_format">試合形式</label>
                    <select id="match_format" name="match_format" class="form-control">
                        <option value="">未設定</option>
                        <option value="round_robin" <?php echo (isset($_POST['match_format']) && $_POST['match_format'] === 'round_robin') ? 'selected' : ''; ?>>総当たり</option>
                        <option value="league" <?php echo (isset($_POST['match_format']) && $_POST['match_format'] === 'league') ? 'selected' : ''; ?>>リーグ戦</option>
                        <option value="tournament" <?php echo (isset($_POST['match_format']) && $_POST['match_format'] === 'tournament') ? 'selected' : ''; ?>>トーナメント</option>
                        <option value="other" <?php echo (isset($_POST['match_format']) && $_POST['match_format'] === 'other') ? 'selected' : ''; ?>>その他</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="target_level">対象レベル</label>
                    <select id="target_level" name="target_level" class="form-control">
                        <?php foreach (aidunite_get_tournament_target_level_options() as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php echo (isset($_POST['target_level']) && $_POST['target_level'] === $val) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="participation_region">参加条件：地域</label>
                    <select id="participation_region" name="participation_region" class="form-control">
                        <?php foreach (aidunite_get_tournament_participation_region_options() as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php echo (isset($_POST['participation_region']) && $_POST['participation_region'] === $val) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="participation_age_group">参加条件：学年・年代</label>
                    <select id="participation_age_group" name="participation_age_group" class="form-control">
                        <?php foreach (aidunite_get_tournament_participation_age_options() as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php echo (isset($_POST['participation_age_group']) && $_POST['participation_age_group'] === $val) ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="participation_conditions">参加条件：その他</label>
                    <textarea id="participation_conditions" name="participation_conditions" class="form-control" rows="2"><?php echo esc_textarea(isset($_POST['participation_conditions']) ? $_POST['participation_conditions'] : ''); ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label>参加費</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <select id="entry_fee_type" name="entry_fee_type" class="form-control" style="width: auto;">
                            <option value="amount" <?php echo (isset($_POST['entry_fee_type']) && $_POST['entry_fee_type'] === 'amount') ? 'selected' : ''; ?>>金額を設定</option>
                            <option value="contact_organizer" <?php echo (!isset($_POST['entry_fee_type']) || $_POST['entry_fee_type'] === 'contact_organizer') ? 'selected' : ''; ?>>主催者と直接調整</option>
                        </select>
                        <span id="entry_fee_amount_wrap" style="<?php echo (isset($_POST['entry_fee_type']) && $_POST['entry_fee_type'] === 'amount') ? '' : 'display:none'; ?>">
                            <input type="number" id="entry_fee_amount" name="entry_fee_amount" class="form-control" style="width: 120px;" value="<?php echo esc_attr(isset($_POST['entry_fee_amount']) ? (int) $_POST['entry_fee_amount'] : ''); ?>" min="0" placeholder="円"> 円
                        </span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: var(--spacing-base, 1rem);">
                    <label for="approval_mode">申込承認</label>
                    <select id="approval_mode" name="approval_mode" class="form-control">
                        <option value="manual" <?php echo (!isset($_POST['approval_mode']) || $_POST['approval_mode'] === 'manual') ? 'selected' : ''; ?>>主催者が承認</option>
                        <option value="auto" <?php echo (isset($_POST['approval_mode']) && $_POST['approval_mode'] === 'auto') ? 'selected' : ''; ?>>自動承認</option>
                    </select>
                </div>

                <p class="form-actions" style="margin-top: var(--spacing-lg, 1.5rem);">
                    <button type="submit" class="btn btn-primary">下書きで保存</button>
                    <a href="<?php echo esc_url(get_permalink(get_page_by_path('tournaments')) ?: home_url('/tournaments/')); ?>" class="btn">キャンセル</a>
                </p>
            </form>

            <script>
            document.getElementById('entry_fee_type').addEventListener('change', function() {
                document.getElementById('entry_fee_amount_wrap').style.display = this.value === 'amount' ? '' : 'none';
            });
            document.getElementById('event_date_start').addEventListener('change', function() {
                var end = document.getElementById('event_date_end');
                if (!end.value || end.value < this.value) {
                    end.value = this.value;
                }
            });
            </script>
        <?php endif; ?>
        <?php endif; // $can_create_tournament ?>
    </div>
</main>

<?php get_footer(); ?>
