<?php
/**
 * Template Name: 大会一覧
 * 固定ページスラッグ: tournaments で使用
 * 仕様: docs/specs/tournament-feature-spec.md
 */

get_header();

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$allowed_status = array('recruiting', 'confirmed', 'finished');
if ($status_filter && !in_array($status_filter, $allowed_status, true)) {
    $status_filter = '';
}

$tournaments = aidunite_get_public_tournaments($status_filter ?: null);
?>

<main id="main" class="site-main tournament-list-page">
    <div class="container" style="max-width: 900px; margin: 0 auto; padding: var(--spacing-base, 1rem);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: var(--spacing-base, 1rem);">
            <h1 class="page-title" style="margin: 0;">大会一覧</h1>
            <?php if (is_user_logged_in() && current_user_can('manage_options')) : ?>
                <?php
                $new_page = get_page_by_path('tournament-new');
                $new_url = $new_page ? get_permalink($new_page) : home_url('/tournament-new/');
                ?>
                <a href="<?php echo esc_url($new_url); ?>" class="btn btn-primary">大会を作成</a>
            <?php endif; ?>
        </div>

        <div class="tournament-filters" style="margin-bottom: var(--spacing-base, 1rem);">
            <a href="<?php echo esc_url(get_permalink()); ?>" class="btn <?php echo !$status_filter ? 'btn-primary' : 'btn-outline'; ?>">すべて</a>
            <a href="<?php echo esc_url(add_query_arg('status', 'recruiting', get_permalink())); ?>" class="btn <?php echo $status_filter === 'recruiting' ? 'btn-primary' : 'btn-outline'; ?>">募集中</a>
            <a href="<?php echo esc_url(add_query_arg('status', 'confirmed', get_permalink())); ?>" class="btn <?php echo $status_filter === 'confirmed' ? 'btn-primary' : 'btn-outline'; ?>">開催確定</a>
            <a href="<?php echo esc_url(add_query_arg('status', 'finished', get_permalink())); ?>" class="btn <?php echo $status_filter === 'finished' ? 'btn-primary' : 'btn-outline'; ?>">終了</a>
        </div>

        <?php if (empty($tournaments)) : ?>
            <p class="tournament-list-empty">該当する大会はありません。</p>
        <?php else : ?>
            <ul class="tournament-list" style="list-style: none; padding: 0;">
                <?php foreach ($tournaments as $tournament) :
                    $meta = aidunite_get_tournament_display_meta($tournament->ID);
                    $accepted_count = aidunite_tournament_accepted_count($tournament->ID);
                    $status = $meta['tournament_status'];
                    $status_label = array('recruiting' => '募集中', 'confirmed' => '開催確定', 'finished' => '終了')[ $status ] ?? $status;
                    $event_type_options = aidunite_get_tournament_event_type_options();
                    $event_type_label = isset($event_type_options[ $meta['event_type'] ]) ? $event_type_options[ $meta['event_type'] ] : '';
                    $url = get_permalink($tournament);
                ?>
                    <li class="tournament-card" style="border: 1px solid var(--border-color, #ddd); border-radius: var(--radius-base, 4px); padding: var(--spacing-base, 1rem); margin-bottom: var(--spacing-base, 1rem);">
                        <h2 style="font-size: var(--font-size-lg, 1.125rem); margin-top: 0;">
                            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($tournament->post_title); ?></a>
                        </h2>
                        <p style="margin: 0.25rem 0; color: var(--text-muted, #666); font-size: var(--font-size-sm, 0.875rem);">
                            <?php if ($event_type_label) : ?><span class="tournament-event-type-badge"><?php echo esc_html($event_type_label); ?></span> <?php endif; ?>
                            <span class="tournament-status-badge"><?php echo esc_html($status_label); ?></span>
                            <?php echo esc_html($meta['event_date_start']); ?>
                            <?php if ($meta['event_date_end'] && $meta['event_date_end'] !== $meta['event_date_start']) : ?>
                                ～<?php echo esc_html($meta['event_date_end']); ?>
                            <?php endif; ?>
                            ｜<?php echo esc_html($meta['venue_name']); ?>
                        </p>
                        <p style="margin: 0.25rem 0; font-size: var(--font-size-sm, 0.875rem);">
                            参加: <?php echo (int) $accepted_count; ?> / <?php echo (int) $meta['capacity']; ?> チーム
                            <?php if ($status === 'recruiting' && $meta['application_deadline']) : ?>
                                ｜締切 <?php echo esc_html(date('Y/m/d H:i', strtotime($meta['application_deadline']))); ?>
                            <?php endif; ?>
                        </p>
                        <p style="margin: 0.5rem 0 0;">
                            <a href="<?php echo esc_url($url); ?>" class="btn btn-sm">詳細を見る</a>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
