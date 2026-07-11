<?php
/**
 * 管理者決済一覧 — チーム（システム料）タブ
 *
 * @var array<int, array<string, mixed>> $teams_page
 * @var int $offset
 * @var string $contract_filter
 * @var string $team_q
 */

if (!defined('ABSPATH')) {
    exit;
}

$tpl_args = is_array($args ?? null) ? $args : [];
$teams_page = is_array($tpl_args['teams_page'] ?? null)
    ? $tpl_args['teams_page']
    : (is_array($teams_page ?? null) ? $teams_page : []);
$offset = (int) ($tpl_args['offset'] ?? $offset ?? 0);
$contract_filter = (string) ($tpl_args['contract_filter'] ?? $contract_filter ?? '');
$team_q = (string) ($tpl_args['team_q'] ?? $team_q ?? '');
?>

<div class="search-filter-section admin-schedule-filters admin-payment-list-filters">
    <h3 class="admin-payment-list-filters__title">フィルター（チーム・システム料）</h3>
    <form method="get" class="admin-schedule-filters__form">
        <input type="hidden" name="apl_tab" value="teams">
        <div class="admin-schedule-filters__field">
            <label for="apl_contract_filter">契約状態</label>
            <select id="apl_contract_filter" name="contract_filter" class="admin-schedule-filters__input">
                <option value="">すべて</option>
                <option value="trial" <?php selected($contract_filter, 'trial'); ?>>トライアル</option>
                <option value="paid" <?php selected($contract_filter, 'paid'); ?>>有料契約</option>
                <option value="active" <?php selected($contract_filter, 'active'); ?>>サブスク連携済</option>
                <option value="unpaid" <?php selected($contract_filter, 'unpaid'); ?>>未払い</option>
                <option value="cancelling" <?php selected($contract_filter, 'cancelling'); ?>>解約手続き中</option>
                <option value="cancelled" <?php selected($contract_filter, 'cancelled'); ?>>解約済</option>
                <option value="inactive" <?php selected($contract_filter, 'inactive'); ?>>未開始</option>
            </select>
        </div>
        <div class="admin-schedule-filters__field">
            <label for="apl_team_q">チーム名</label>
            <input type="search" id="apl_team_q" name="team_q" class="admin-schedule-filters__input" value="<?php echo esc_attr($team_q); ?>" placeholder="部分一致">
        </div>
        <div class="admin-schedule-filters__actions">
            <button type="submit" class="button button-primary">絞り込む</button>
            <a href="<?php echo esc_url(aidunite_admin_payment_list_tab_url('teams')); ?>" class="button">リセット</a>
        </div>
    </form>
</div>

<div class="admin-payment-list-table-wrap">
    <table class="admin-payment-table wp-list-table widefat striped">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>チーム</th>
                <th>代表者</th>
                <th>プラン</th>
                <th>契約形態</th>
                <th>契約状態</th>
                <th>月額</th>
                <th>次回請求</th>
                <th>Stripe Sub</th>
                <th>Connect</th>
                <th>Founding</th>
                <th>更新日時</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($teams_page === []) : ?>
            <tr>
                <td colspan="13" class="admin-payment-list-empty">該当するチームがありません。</td>
            </tr>
            <?php endif; ?>
            <?php foreach ($teams_page as $idx => $row) :
                $row_no = $offset + $idx + 1;
                $modifier = (string) ($row['contract_modifier'] ?? 'inactive');
                ?>
            <tr>
                <td class="col-no"><?php echo (int) $row_no; ?></td>
                <td>
                    <a href="<?php echo esc_url((string) $row['team_settings_url']); ?>"><?php echo esc_html((string) $row['team_name']); ?></a>
                    <span class="admin-payment-list-subid">ID <?php echo (int) $row['team_id']; ?></span>
                </td>
                <td>
                    <?php echo esc_html((string) $row['leader_name']); ?>
                    <?php if (!empty($row['leader_email'])) : ?>
                    <span class="admin-payment-list-subid"><?php echo esc_html((string) $row['leader_email']); ?></span>
                    <?php endif; ?>
                </td>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['plan_label']); ?>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['contract_mode_label']); ?>
                <td>
                    <span class="admin-payment-status-badge admin-payment-status-badge--<?php echo esc_attr($modifier); ?>">
                        <?php echo esc_html((string) $row['contract_label']); ?>
                    </span>
                </td>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['monthly_fee']); ?>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['next_billing_label']); ?>
                <td>
                    <?php if (!empty($row['stripe_subscription_linked'])) : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--ok">連携済</span>
                    <?php if (!empty($row['stripe_subscription_url'])) : ?>
                    <a href="<?php echo esc_url((string) $row['stripe_subscription_url']); ?>" target="_blank" rel="noopener noreferrer" class="admin-payment-list-stripe-id" title="<?php echo esc_attr((string) $row['stripe_subscription_id']); ?>">
                        <?php echo esc_html((string) $row['stripe_subscription_short']); ?>
                    </a>
                    <?php else : ?>
                    <span class="admin-payment-list-stripe-id"><?php echo esc_html((string) $row['stripe_subscription_short']); ?></span>
                    <?php endif; ?>
                    <?php else : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--none">未連携</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($row['connect_linked'])) : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--ok">連携済</span>
                    <?php if (!empty($row['stripe_connect_url'])) : ?>
                    <a href="<?php echo esc_url((string) $row['stripe_connect_url']); ?>" target="_blank" rel="noopener noreferrer" class="admin-payment-list-stripe-id" title="<?php echo esc_attr((string) $row['connect_account_id']); ?>">
                        <?php echo esc_html((string) $row['connect_account_short']); ?>
                    </a>
                    <?php endif; ?>
                    <?php else : ?>
                    <span class="admin-payment-link-badge admin-payment-link-badge--none">未連携</span>
                    <?php endif; ?>
                </td>
                <?php aidunite_admin_list_echo_cell('text', (string) $row['founding_label']); ?>
                <?php aidunite_admin_list_echo_cell('datetime', (string) $row['status_updated']); ?>
                <td class="admin-payment-list-actions">
                    <a href="<?php echo esc_url((string) $row['team_settings_url']); ?>">チーム設定</a>
                    <?php if (!empty($row['can_assign_founding'])) : ?>
                    <button type="button"
                        class="aidunite-assign-founding-btn"
                        data-team-id="<?php echo (int) $row['team_id']; ?>">
                        Founding割当
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
