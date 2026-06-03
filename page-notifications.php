<?php
/**
 * Template Name: 通知一覧ページ
 */

require_once get_stylesheet_directory() . '/functions/notifications/notifications-page-helpers.php';

get_header();

if (!is_user_logged_in()) {
    echo '<div class="container">';
    echo '<p>このページを表示するにはログインが必要です。</p>';
    echo '<p><a href="' . esc_url(home_url('/login')) . '" class="button">ログインページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}

$current_user_id = get_current_user_id();
$match_types = ainy_notification_match_types();
$group_labels = ainy_notification_group_labels();
wp_localize_script('ainy-notifications-list', 'ainyNotificationsList', array(
    'restMarkRead' => esc_url_raw(rest_url('aidunite/v1/mark-read')),
    'restDelete'   => esc_url_raw(rest_url('aidunite/v1/notifications/delete')),
    'restNonce'    => wp_create_nonce('wp_rest'),
    'i18n'         => array(
        'noUnread'        => '既読にする通知がありません。',
        'markedAll'       => 'すべて既読にしました。',
        'error'           => 'エラーが発生しました。',
        'confirmDelete'   => '既読の通知をすべて削除しますか？',
        'noReadToDelete'  => '削除する既読通知がありません。',
        'deleted'         => '既読通知を削除しました。',
        'deleteFailed'    => '削除に失敗しました。',
        'closeDetail'     => '閉じる',
        'detailAria'      => '通知の詳細',
    ),
));

$raw_posts = function_exists('aidunite_notification_list')
    ? aidunite_notification_list($current_user_id)
    : get_posts([
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'author'         => $current_user_id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

$items = [];
foreach ($raw_posts as $post) {
    $items[] = ainy_notification_prepare_item($post, $match_types);
}

$count_unread = 0;
foreach ($items as $item) {
    if (!$item['is_read']) {
        $count_unread++;
    }
}

$grouped = ainy_notification_group_items($items);
$settings_url = home_url('/notification-settings');
?>

<div class="team-dashboard-container page-notifications ainy-notifications-page ainy-webapp-page filter-unread">

    <?php
    if (function_exists('aidunite_render_web_app_page_hero')) {
        aidunite_render_web_app_page_hero([
            'size'       => 'md',
            'title'      => '通知一覧',
            'back'       => true,
            'active_nav' => 'notifications',
            'actions'    => [
                [
                    'type'  => 'link',
                    'href'  => $settings_url,
                    'icon_svg' => 'settings',
                    'aria'  => '通知設定',
                ],
            ],
        ]);
        aidunite_render_web_app_content_open('ainy-webapp-content--flush');
    }
    ?>

    <?php if ($items) : ?>
        <nav class="ainy-notifications-filter-tabs" role="tablist" aria-label="通知の絞り込み">
            <button type="button" class="active" role="tab" aria-selected="true" data-filter="unread">
                未読
                <span class="ainy-notifications-tab-count" data-tab-count="unread" data-count="<?php echo (int) $count_unread; ?>" aria-label="<?php echo $count_unread > 0 ? '未読' . (int) $count_unread . '件' : ''; ?>"><?php echo $count_unread > 0 ? (int) $count_unread : ''; ?></span>
            </button>
            <button type="button" role="tab" aria-selected="false" data-filter="read">
                既読
            </button>
        </nav>

        <main class="ainy-notifications-main">
            <?php foreach (['today', 'yesterday', 'earlier'] as $group_key) : ?>
                <?php if (empty($grouped[$group_key])) {
                    continue;
                } ?>
                <section class="ainy-notifications-group" data-group="<?php echo esc_attr($group_key); ?>">
                    <h2><?php echo esc_html($group_labels[$group_key]); ?></h2>
                    <ul class="ainy-notifications-list" role="list">
                        <?php foreach ($grouped[$group_key] as $item) : ?>
                            <?php
                            $card_classes = ['ainy-notification-card'];
                            if (!$item['is_read']) {
                                $card_classes[] = 'unread';
                            } else {
                                $card_classes[] = 'read';
                            }
                            ?>
                            <li
                                class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
                                role="listitem"
                                tabindex="0"
                                data-id="<?php echo (int) $item['id']; ?>"
                                data-link="<?php echo esc_attr($item['link_url']); ?>"
                                data-type="<?php echo esc_attr($item['type']); ?>"
                                data-filter-unread="<?php echo $item['is_read'] ? '0' : '1'; ?>"
                            >
                                <span class="ainy-notification-card__dot" aria-hidden="true"></span>
                                <div class="ainy-notification-card__icon ainy-notification-card__icon--<?php echo esc_attr($item['icon_variant']); ?>" aria-hidden="true">
                                    <?php echo aidunite_render_theme_icon($item['icon_svg'], ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                                <div class="ainy-notification-card__body">
                                    <div class="ainy-notification-card__meta">
                                        <h3><?php echo esc_html($item['title']); ?></h3>
                                        <time datetime="<?php echo esc_attr(get_post_time('c', true, $item['id'])); ?>"><?php echo esc_html($item['time_label']); ?></time>
                                    </div>
                                    <?php if ($item['excerpt'] !== '') : ?>
                                        <p><?php echo esc_html($item['excerpt']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['message'] !== '') : ?>
                                        <span class="ainy-notification-card__message-source" hidden><?php echo esc_html($item['message']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="ainy-notification-card__arrow" aria-hidden="true"><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </main>

        <div class="ainy-notifications-actions">
            <div class="ainy-notifications-actions-panel ainy-notifications-actions-panel--unread" id="ainy-notifications-actions-unread">
                <button type="button" class="ainy-notifications-mark-read" id="ainy-mark-all-read">すべて既読にする</button>
            </div>
            <div class="ainy-notifications-actions-panel ainy-notifications-actions-panel--read is-hidden" id="ainy-notifications-actions-read" hidden>
                <button type="button" class="btn btn-sm btn-danger ainy-notifications-delete-read" id="ainy-delete-read">既読を削除</button>
            </div>
        </div>

        <div
            class="ainy-notification-detail-modal"
            id="ainy-notification-detail-modal"
            hidden
            aria-hidden="true"
        >
            <div class="ainy-notification-detail-modal__backdrop" data-close-detail tabindex="-1"></div>
            <div
                class="ainy-notification-detail-modal__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="ainy-notification-detail-title"
            >
                <header class="ainy-notification-detail-modal__header">
                    <div class="ainy-notification-detail-modal__heading">
                        <h2 id="ainy-notification-detail-title"></h2>
                        <time id="ainy-notification-detail-time" datetime=""></time>
                    </div>
                    <button
                        type="button"
                        class="ainy-notification-detail-modal__close"
                        data-close-detail
                        aria-label="閉じる"
                    >&times;</button>
                </header>
                <div class="ainy-notification-detail-modal__body" id="ainy-notification-detail-body"></div>
                <footer class="ainy-notification-detail-modal__footer">
                    <button type="button" class="btn btn-primary ainy-notification-detail-modal__done" data-close-detail>閉じる</button>
                </footer>
            </div>
        </div>
    <?php else : ?>
        <main class="ainy-notifications-main">
            <div class="ainy-notifications-empty" role="status">
                <div class="ainy-notifications-empty__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('forward_to_inbox', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <h3>通知はまだありません</h3>
                <p>新しい通知が届くと、ここに表示されます。</p>
                <p><a href="<?php echo esc_url($settings_url); ?>">メール通知などの設定</a></p>
            </div>
        </main>
    <?php endif; ?>

    <?php
    if (function_exists('aidunite_render_web_app_content_close')) {
        aidunite_render_web_app_content_close();
    }
    ?>

</div>

<?php get_footer(); ?>
