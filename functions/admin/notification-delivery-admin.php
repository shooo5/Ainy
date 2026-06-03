<?php
/**
 * 管理画面: 通知配信ログ・ユーザー通知状態（docs/spec/notification.md 第24節）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * メニュー登録
 */
function aidunite_notification_delivery_admin_menu() {
    add_menu_page(
        '通知送信ログ',
        '通知送信ログ',
        'manage_options',
        'aidunite-notification-delivery-log',
        'aidunite_render_notification_delivery_admin_page',
        'dashicons-chart-area',
        32
    );
}
add_action('admin_menu', 'aidunite_notification_delivery_admin_menu');

/**
 * 管理画面レンダリング
 */
function aidunite_render_notification_delivery_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('権限がありません。', 'aidunite'));
    }

    if (!function_exists('aidunite_notification_delivery_maybe_install_tables')) {
        wp_die(esc_html__('通知ログモジュールが読み込まれていません。', 'aidunite'));
    }

    aidunite_notification_delivery_maybe_install_tables();

    global $wpdb;
    $events_table = aidunite_notification_delivery_events_table();
    $channels_table = aidunite_notification_delivery_channels_table();

    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash((string) $_GET['filter_type'])) : '';
    $filter_user = isset($_GET['filter_user']) ? (int) $_GET['filter_user'] : 0;
    $lookup_user = isset($_GET['lookup_user']) ? (int) $_GET['lookup_user'] : 0;

    $where = ['1=1'];
    $params = [];
    if ($filter_type !== '') {
        $where[] = 'e.notification_type = %s';
        $params[] = $filter_type;
    }
    if ($filter_user > 0) {
        $where[] = 'e.user_id = %d';
        $params[] = $filter_user;
    }
    $where_sql = implode(' AND ', $where);

    $sql = "SELECT e.* FROM {$events_table} e WHERE {$where_sql} ORDER BY e.id DESC LIMIT 150";
    if (!empty($params)) {
        $events = $wpdb->get_results($wpdb->prepare($sql, $params));
    } else {
        $events = $wpdb->get_results($sql);
    }
    if (!is_array($events)) {
        $events = [];
    }

    $channels_by_event = [];
    if (!empty($events)) {
        $ids = array_map('intval', wp_list_pluck($events, 'id'));
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            $in_list = implode(',', $ids);
            $rows = $wpdb->get_results(
                "SELECT * FROM {$channels_table} WHERE event_id IN ({$in_list}) ORDER BY id ASC"
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $eid = (int) $r->event_id;
                    if (!isset($channels_by_event[$eid])) {
                        $channels_by_event[$eid] = [];
                    }
                    $channels_by_event[$eid][] = $r;
                }
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('通知送信ログ（管理者）', 'aidunite'); ?></h1>
        <p class="description">
            <?php echo esc_html__('仕様: docs/spec/notification.md 第24節。本文・メールアドレスはログに保存しません。', 'aidunite'); ?>
        </p>

        <h2 class="title"><?php echo esc_html__('フィルター', 'aidunite'); ?></h2>
        <form method="get" action="" class="aidunite-delivery-filters" style="margin-bottom:1.5em;">
            <input type="hidden" name="page" value="aidunite-notification-delivery-log" />
            <label>
                <?php echo esc_html__('通知タイプ', 'aidunite'); ?>
                <input type="text" name="filter_type" value="<?php echo esc_attr($filter_type); ?>" placeholder="match_request" />
            </label>
            <label style="margin-left:1em;">
                <?php echo esc_html__('宛先 user_id', 'aidunite'); ?>
                <input type="number" name="filter_user" value="<?php echo $filter_user ? (int) $filter_user : ''; ?>" min="1" step="1" />
            </label>
            <button type="submit" class="button"><?php echo esc_html__('絞り込み', 'aidunite'); ?></button>
        </form>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('日時(UTC)', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('環境', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('type', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('user', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('related', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('CPT', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('correlation', 'aidunite'); ?></th>
                    <th><?php echo esc_html__('チャンネル', 'aidunite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)) : ?>
                    <tr><td colspan="9"><?php echo esc_html__('ログがありません。', 'aidunite'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($events as $ev) : ?>
                        <?php
                        $eid = (int) $ev->id;
                        $ch_rows = $channels_by_event[$eid] ?? [];
                        ?>
                        <tr>
                            <td><?php echo (int) $ev->id; ?></td>
                            <td><?php echo esc_html((string) $ev->created_at); ?></td>
                            <td><?php echo esc_html((string) $ev->environment); ?></td>
                            <td><code><?php echo esc_html((string) $ev->notification_type); ?></code></td>
                            <td><?php echo (int) $ev->user_id; ?></td>
                            <td><?php echo $ev->related_id !== null ? (int) $ev->related_id : '—'; ?></td>
                            <td><?php echo $ev->notification_post_id ? (int) $ev->notification_post_id : '—'; ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html((string) $ev->correlation_id); ?></code></td>
                            <td><?php
                            foreach ($ch_rows as $c) {
                                echo esc_html((string) $c->channel) . '=' . esc_html((string) $c->result);
                                if (!empty($c->skip_reason)) {
                                    echo ' <small>(' . esc_html((string) $c->skip_reason) . ')</small>';
                                }
                                if (!empty($c->detail)) {
                                    echo ' <small>' . esc_html((string) $c->detail) . '</small>';
                                }
                                echo '<br />';
                            }
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <hr />

        <h2 class="title"><?php echo esc_html__('ユーザー通知状態（問い合わせ用）', 'aidunite'); ?></h2>
        <form method="get" action="" style="margin-bottom:1em;">
            <input type="hidden" name="page" value="aidunite-notification-delivery-log" />
            <?php if ($filter_type !== '') : ?>
                <input type="hidden" name="filter_type" value="<?php echo esc_attr($filter_type); ?>" />
            <?php endif; ?>
            <?php if ($filter_user > 0) : ?>
                <input type="hidden" name="filter_user" value="<?php echo (int) $filter_user; ?>" />
            <?php endif; ?>
            <label>
                <?php echo esc_html__('user_id', 'aidunite'); ?>
                <input type="number" name="lookup_user" value="<?php echo $lookup_user ? (int) $lookup_user : ''; ?>" min="1" step="1" required />
            </label>
            <button type="submit" class="button"><?php echo esc_html__('表示', 'aidunite'); ?></button>
        </form>

        <?php
        if ($lookup_user > 0) {
            $u = get_userdata($lookup_user);
            if (!$u) {
                echo '<p>' . esc_html__('ユーザーが見つかりません。', 'aidunite') . '</p>';
            } else {
                $settings = aidunite_notification_get_settings($lookup_user);
                $raw = get_user_meta($lookup_user, 'notification_settings', true);
                $raw_line = is_array($raw) && array_key_exists('line_notifications', $raw)
                    ? ($raw['line_notifications'] ? 'true' : 'false')
                    : '—';

                $last = get_posts([
                    'post_type' => 'notification',
                    'post_status' => 'publish',
                    'author' => $lookup_user,
                    'posts_per_page' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids',
                ]);
                $last_id = !empty($last) ? (int) $last[0] : 0;
                $last_date = $last_id ? get_post_field('post_date', $last_id) : '';

                $since_ts = (int) current_time('timestamp') - 30 * DAY_IN_SECONDS;
                $since_local = wp_date('Y-m-d H:i:s', $since_ts);
                $recent_count = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_author = %d AND post_date >= %s",
                        'notification',
                        'publish',
                        $lookup_user,
                        $since_local
                    )
                );

                echo '<table class="widefat"><tbody>';
                echo '<tr><th>display_name</th><td>' . esc_html($u->display_name) . '</td></tr>';
                echo '<tr><th>email_notifications（送信判定）</th><td>' . (!empty($settings['email_notifications']) ? 'ON' : 'OFF') . '</td></tr>';
                echo '<tr><th>line_notifications（get_settings 返却）</th><td>' . (!empty($settings['line_notifications']) ? 'true' : 'false') . '（常に false 仕様）</td></tr>';
                echo '<tr><th>生 user_meta line_notifications</th><td>' . esc_html($raw_line) . '</td></tr>';
                echo '<tr><th>直近の通知 CPT（1件）</th><td>' . ($last_id ? '#' . (int) $last_id . ' ' . esc_html((string) $last_date) : '—') . '</td></tr>';
                echo '<tr><th>過去30日の通知 CPT 件数（概算）</th><td>' . (int) $recent_count . '</td></tr>';
                echo '</tbody></table>';
            }
        }
        ?>
    </div>
    <?php
}
