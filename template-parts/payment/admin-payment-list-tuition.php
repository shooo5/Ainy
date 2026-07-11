<?php
/**
 * 管理者決済一覧 — 保護者（月謝）タブ
 *
 * @var array<int, array<string, mixed>> $tuition_page
 * @var int $offset
 * @var string $tuition_status_filter
 * @var string $team_q
 * @var string $month_label
 */

if (!defined('ABSPATH')) {
    exit;
}

$tpl_args = is_array($args ?? null) ? $args : [];
$tuition_page = is_array($tpl_args['tuition_page'] ?? null)
    ? $tpl_args['tuition_page']
    : (is_array($tuition_page ?? null) ? $tuition_page : []);
$offset = (int) ($tpl_args['offset'] ?? $offset ?? 0);
$tuition_status_filter = (string) ($tpl_args['tuition_status_filter'] ?? $tuition_status_filter ?? '');
$team_q = (string) ($tpl_args['team_q'] ?? $team_q ?? '');
$month_label = (string) ($tpl_args['month_label'] ?? $month_label ?? '');
?>

<div class="search-filter-section admin-schedule-filters admin-payment-list-filters">
    <h3 class="admin-payment-list-filters__title">フィルター（保護者・月謝）</h3>
    <form method="get" class="admin-schedule-filters__form">
        <input type="hidden" name="apl_tab" value="tuition">
        <div class="admin-schedule-filters__field">
            <label for="apl_tuition_status_filter">今月の状態</label>
            <select id="apl_tuition_status_filter" name="tuition_status_filter" class="admin-schedule-filters__input">
                <option value="">すべて</option>
                <option value="paid" <?php selected($tuition_status_filter, 'paid'); ?>>入金済</option>
                <option value="pending_billing" <?php selected($tuition_status_filter, 'pending_billing'); ?>>請求前</option>
                <option value="overdue" <?php selected($tuition_status_filter, 'overdue'); ?>>未払い</option>
                <option value="failed" <?php selected($tuition_status_filter, 'failed'); ?>>決済失敗</option>
                <option value="not_registered" <?php selected($tuition_status_filter, 'not_registered'); ?>>未登録</option>
                <option value="disabled" <?php selected($tuition_status_filter, 'disabled'); ?>>月謝未開放</option>
            </select>
        </div>
        <div class="admin-schedule-filters__field">
            <label for="apl_tuition_team_q">チーム名</label>
            <input type="search" id="apl_tuition_team_q" name="team_q" class="admin-schedule-filters__input" value="<?php echo esc_attr($team_q); ?>" placeholder="部分一致">
        </div>
        <div class="admin-schedule-filters__actions">
            <button type="submit" class="button button-primary">絞り込む</button>
            <a href="<?php echo esc_url(aidunite_admin_payment_list_tab_url('tuition')); ?>" class="button">リセット</a>
        </div>
    </form>
    <?php if ($month_label !== '') : ?>
    <p class="admin-payment-list-note">対象月: <strong><?php echo esc_html($month_label); ?></strong>（月末自動決済。請求前は未払いではありません）</p>
    <?php endif; ?>
</div>

<div class="admin-payment-list-table-wrap">
    <table class="admin-payment-table wp-list-table widefat striped">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>チーム</th>
                <th>保護者</th>
                <th>月謝額</th>
                <th>カード登録</th>
                <th>今月の状態</th>
                <th>直近入金</th>
                <th>Connect Sub</th>
                <th>Connect顧客</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($tuition_page === []) : ?>
            <tr>
                <td colspan="10" class="admin-payment-list-empty">該当する保護者がありません。</td>
            </tr>
            <?php endif; ?>
            <?php foreach ($tuition_page as $idx => $row) :
                $row_no = $offset + $idx + 1;
                $status_key = (string) ($row['month_status'] ?? '');
                ?>
            <tr>
                <td class="col-no"><?php echo (int) $row_no; ?></td>
                <td>
                    <a href="<?php echo esc_url((string) $row['team_settings_url']); ?>"><?php echo esc_html((string) $row['team_name']); ?></a>
                    <span class="admin-payment-list-subid">ID <?php echo (int) $row['team_id']; ?></span>
                </td>
                <td>
                    <?php echo esc_html((string) $row['parent_name']); ?>
                    <?php if (!empty($row['parent_email'])) : ?>
                    <span class="admin-payment-list-subid"><?php echo esc_html((string) $row['parent_email']); ?></span>
                    <?php endif; ?>
                    <span class="admin-payment-list-subid">ユーザーID <?php echo (int) $row['parent_user_id']; ?></span>
                </td>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['monthly_fee']); ?>
                <td>
                    <span class="admin-payment-link-badge admin-payment-link-badge--<?php echo !empty($row['has_subscription']) ? 'ok' : 'none'; ?>">
                        <?php echo esc_html((string) $row['registration_label']); ?>
                    </span>
                </td>
                <td>
                    <span class="admin-payment-status-badge admin-payment-status-badge--tuition-<?php echo esc_attr($status_key !== '' ? $status_key : 'disabled'); ?>">
                        <?php echo esc_html((string) $row['month_status_label']); ?>
                    </span>
                </td>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['latest_payment_label']); ?>
                <td>
                    <?php if ((string) ($row['stripe_subscription_id'] ?? '') !== '') : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--ok">あり</span>
                    <span class="admin-payment-list-stripe-id" title="<?php echo esc_attr((string) $row['stripe_subscription_id']); ?>">
                        <?php echo esc_html((string) $row['stripe_subscription_short']); ?>
                    </span>
                    <?php else : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--none">なし</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((string) ($row['connect_customer_id'] ?? '') !== '') : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--ok">あり</span>
                    <span class="admin-payment-list-stripe-id" title="<?php echo esc_attr((string) $row['connect_customer_id']); ?>">
                        <?php echo esc_html((string) $row['connect_customer_short']); ?>
                    </span>
                    <?php else : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--none">なし</span>
                    <?php endif; ?>
                </td>
                <td class="admin-payment-list-actions">
                    <a href="<?php echo esc_url((string) $row['user_edit_url']); ?>">ユーザー</a>
                    <a href="<?php echo esc_url((string) $row['team_settings_url']); ?>">チーム</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
