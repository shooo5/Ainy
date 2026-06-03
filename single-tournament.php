<?php
/**
 * 大会詳細（single tournament）
 * 仕様: docs/specs/tournament-feature-spec.md
 */

// 申込処理（POST）
$apply_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tournament_apply_nonce']) && wp_verify_nonce($_POST['tournament_apply_nonce'], 'tournament_apply')) {
    $tournament_id = isset($_POST['tournament_id']) ? (int) $_POST['tournament_id'] : 0;
    $team_id = isset($_POST['team_id']) ? (int) $_POST['team_id'] : 0;
    if ($tournament_id && $team_id) {
        $tour = get_post($tournament_id);
        if ($tour && $tour->post_type === 'tournament') {
            $status = get_post_meta($tournament_id, 'tournament_status', true);
            $closed = aidunite_tournament_is_recruitment_closed($tournament_id);
            $existing = aidunite_tournament_participant_status_for_team($tournament_id, $team_id);
            if ($status !== 'recruiting' || $closed) {
                $apply_message = '<p class="tournament-apply-message error">この大会は現在申込を受け付けていません。</p>';
            } elseif ($existing) {
                $apply_message = '<p class="tournament-apply-message error">すでに申込済みです。</p>';
            } else {
                $approval_mode = get_post_meta($tournament_id, 'approval_mode', true);
                $participant_status = ($approval_mode === 'auto') ? 'accepted' : 'applied';
                $title = get_the_title($tournament_id) . ' - 参加申込';
                $post_id = wp_insert_post(array(
                    'post_type'   => 'tournament_participant',
                    'post_title'  => $title,
                    'post_status' => 'publish',
                    'post_author' => get_current_user_id(),
                ));
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'tournament_id', $tournament_id);
                    update_post_meta($post_id, 'team_id', $team_id);
                    update_post_meta($post_id, 'participant_status', $participant_status);
                    update_post_meta($post_id, 'applied_at', current_time('mysql'));
                    if ($participant_status === 'accepted') {
                        update_post_meta($post_id, 'accepted_at', current_time('mysql'));
                        if (function_exists('aidunite_ensure_tournament_schedule_for_participant')) {
                            aidunite_ensure_tournament_schedule_for_participant($post_id);
                        }
                    }
                    $apply_message = $participant_status === 'accepted'
                        ? '<p class="tournament-apply-message success">参加が確定しました。</p>'
                        : '<p class="tournament-apply-message success">申し込みを受け付けました。主催者の承認をお待ちください。</p>';
                } else {
                    $apply_message = '<p class="tournament-apply-message error">申込に失敗しました。</p>';
                }
            }
        }
    }
}

get_header();

while (have_posts()) {
    the_post();
    $tournament_id = get_the_ID();
    $meta = aidunite_get_tournament_display_meta($tournament_id);
    $status = $meta['tournament_status'];
    $status_labels = array('draft' => '下書き', 'recruiting' => '募集中', 'confirmed' => '開催確定', 'finished' => '終了');
    $status_label = $status_labels[ $status ] ?? $status;

    // draft は非公開のため、一覧に出ない。直接URLで来た場合は 404 扱い
    if ($status === 'draft') {
        if (!current_user_can('edit_post', $tournament_id)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            include(get_query_template('404'));
            exit;
        }
    }

    $accepted_count = aidunite_tournament_accepted_count($tournament_id);
    $can_apply = ($status === 'recruiting' && !aidunite_tournament_is_recruitment_closed($tournament_id));

    $current_user_id = get_current_user_id();
    $user_team_id = $current_user_id ? (int) get_user_meta($current_user_id, 'team_id', true) : 0;
    $my_participant_status = $user_team_id ? aidunite_tournament_participant_status_for_team($tournament_id, $user_team_id) : null;

    $is_organizer = false;
    $organizer_user_id = (int) get_post_meta($tournament_id, 'organizer_user_id', true);
    $organizer_team_id = (int) get_post_meta($tournament_id, 'organizer_team_id', true);
    if ($current_user_id && ($organizer_user_id === $current_user_id || ($organizer_team_id && $user_team_id === $organizer_team_id))) {
        $is_organizer = true;
    }
    if (current_user_can('edit_post', $tournament_id)) {
        $is_organizer = true;
    }

    $accepted_teams = aidunite_tournament_accepted_teams($tournament_id);
?>

<main id="main" class="site-main single-tournament">
    <div class="container" style="max-width: 800px; margin: 0 auto; padding: var(--spacing-base, 1rem);">
        <?php echo $apply_message; ?>

        <p style="margin-bottom: 0.5rem;"><a href="<?php echo esc_url(get_permalink(get_page_by_path('tournaments')) ?: home_url('/tournaments/')); ?>">&larr; 大会一覧へ</a></p>

        <?php
        $event_type_options = aidunite_get_tournament_event_type_options();
        $event_type_label = isset($event_type_options[ $meta['event_type'] ]) ? $event_type_options[ $meta['event_type'] ] : $meta['event_type'];
        ?>
        <h1 class="tournament-title"><?php the_title(); ?></h1>
        <p class="tournament-status">
            <span class="tournament-event-type"><?php echo esc_html($event_type_label); ?></span>
            <strong><?php echo esc_html($status_label); ?></strong>
        </p>

        <section class="tournament-meta" style="margin: 1rem 0;">
            <dl style="margin: 0;">
                <dt>開催日</dt>
                <dd><?php echo esc_html($meta['event_date_start']); ?>
                    <?php if ($meta['event_date_end'] && $meta['event_date_end'] !== $meta['event_date_start']) : ?>
                        ～ <?php echo esc_html($meta['event_date_end']); ?>
                    <?php endif; ?>
                </dd>
                <dt>会場</dt>
                <dd><?php echo esc_html($meta['venue_name']); ?>
                    <?php if (!empty($meta['venue_address'])) : ?>
                        （<?php echo esc_html($meta['venue_address']); ?>）
                    <?php endif; ?>
                </dd>
                <dt>参加定員</dt>
                <dd><?php echo (int) $accepted_count; ?> / <?php echo (int) $meta['capacity']; ?> チーム</dd>
                <dt>申込締切</dt>
                <dd><?php echo $meta['application_deadline'] ? esc_html(date('Y年n月j日 H:i', strtotime($meta['application_deadline']))) : '—'; ?></dd>
                <dt>参加費</dt>
                <dd>
                    <?php
                    if ($meta['entry_fee_type'] === 'amount' && $meta['entry_fee_amount'] !== '') {
                        echo esc_html(number_format((int) $meta['entry_fee_amount']) . '円');
                    } else {
                        echo '主催者と直接調整';
                    }
                    ?>
                </dd>
                <dt>主催者</dt>
                <dd><?php echo esc_html($meta['organizer_name']); ?>
                    <span style="font-size: var(--font-size-sm, 0.875rem); color: var(--text-muted, #666);">（連絡は大会用チャットで）</span>
                </dd>
                <?php
                $level_opts = aidunite_get_tournament_target_level_options();
                $region_opts = aidunite_get_tournament_participation_region_options();
                $age_opts = aidunite_get_tournament_participation_age_options();
                $level_label = !empty($meta['target_level']) && isset($level_opts[ $meta['target_level'] ]) ? $level_opts[ $meta['target_level'] ] : '';
                $region_label = !empty($meta['participation_region']) && isset($region_opts[ $meta['participation_region'] ]) ? $region_opts[ $meta['participation_region'] ] : '';
                $age_label = !empty($meta['participation_age_group']) && isset($age_opts[ $meta['participation_age_group'] ]) ? $age_opts[ $meta['participation_age_group'] ] : '';
                if ($level_label || $region_label || $age_label || !empty($meta['participation_conditions'])) :
                ?>
                <dt>対象・参加条件</dt>
                <dd>
                    <?php if ($level_label) : ?>対象レベル: <?php echo esc_html($level_label); ?><br><?php endif; ?>
                    <?php if ($region_label) : ?>地域: <?php echo esc_html($region_label); ?><br><?php endif; ?>
                    <?php if ($age_label) : ?>学年・年代: <?php echo esc_html($age_label); ?><br><?php endif; ?>
                    <?php if (!empty($meta['participation_conditions'])) : ?><?php echo nl2br(esc_html($meta['participation_conditions'])); ?><?php endif; ?>
                </dd>
                <?php endif; ?>
            </dl>
        </section>

        <?php if (!empty($meta['tournament_description'])) : ?>
            <section class="tournament-description" style="margin: 1rem 0;">
                <h2>大会説明</h2>
                <div class="tournament-description-body"><?php echo nl2br(esc_html($meta['tournament_description'])); ?></div>
            </section>
        <?php endif; ?>

        <?php if (is_user_logged_in() && $user_team_id && $my_participant_status) : ?>
            <section class="tournament-my-status" style="margin: 1rem 0; padding: 1rem; background: var(--bg-muted, #f5f5f5); border-radius: var(--radius-base, 4px);">
                <h2>申込状況</h2>
                <p>
                    <?php
                    $status_text = array('applied' => '申込済み（承認待ち）', 'accepted' => '参加確定', 'canceled' => '辞退')[ $my_participant_status ] ?? $my_participant_status;
                    echo esc_html($status_text);
                    ?>
                </p>
            </section>
        <?php endif; ?>

        <section class="tournament-accepted-teams" style="margin: 1rem 0;">
            <h2>参加確定チーム</h2>
            <?php if (empty($accepted_teams)) : ?>
                <p>まだいません。</p>
            <?php else : ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($accepted_teams as $t) : ?>
                        <li><?php echo esc_html($t['team_name'] ?: 'ID:' . $t['team_id']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($is_organizer) :
            $all_participants = get_posts(array(
                'post_type'      => 'tournament_participant',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => array(array('key' => 'tournament_id', 'value' => $tournament_id)),
                'orderby'        => 'date',
                'order'          => 'ASC',
            ));
        ?>
            <section class="tournament-organizer" style="margin: 1rem 0; padding: 1rem; border: 1px solid var(--border-color, #ddd); border-radius: var(--radius-base, 4px);">
                <h2>主催者用：参加一覧</h2>
                <p>残り枠: <?php echo max(0, (int) $meta['capacity'] - $accepted_count); ?> チーム</p>
                <?php if (empty($all_participants)) : ?>
                    <p>申込はまだありません。</p>
                <?php else : ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($all_participants as $p) :
                            $tid = (int) get_post_meta($p->ID, 'team_id', true);
                            $pstatus = get_post_meta($p->ID, 'participant_status', true);
                            $team = $tid ? get_post($tid) : null;
                            $team_name = $team ? $team->post_title : 'ID:' . $tid;
                            $pstatus_label = array('applied' => '申込済み', 'accepted' => '参加確定', 'canceled' => '辞退')[ $pstatus ] ?? $pstatus;
                        ?>
                            <li style="margin-bottom: 0.5rem;"><?php echo esc_html($team_name); ?> — <?php echo esc_html($pstatus_label); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p><a href="<?php echo esc_url(admin_url('edit.php?post_type=tournament')); ?>" class="btn btn-sm">管理画面で編集</a></p>
            </section>
        <?php endif; ?>

        <?php if ($can_apply && is_user_logged_in() && $user_team_id && !$my_participant_status) : ?>
            <section class="tournament-apply" style="margin: 1rem 0;">
                <h2>申し込む</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('tournament_apply', 'tournament_apply_nonce'); ?>
                    <input type="hidden" name="tournament_id" value="<?php echo (int) $tournament_id; ?>">
                    <input type="hidden" name="team_id" value="<?php echo (int) $user_team_id; ?>">
                    <p>チーム「<?php echo esc_html(get_the_title($user_team_id)); ?>」で申し込みます。</p>
                    <button type="submit" class="btn btn-primary">申し込む</button>
                </form>
            </section>
        <?php elseif ($can_apply && !is_user_logged_in()) : ?>
            <p><a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="btn">ログインして申し込む</a></p>
        <?php endif; ?>
    </div>
</main>

<?php
}
get_footer();
