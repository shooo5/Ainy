<?php
/*
Template Name: 管理用決済一覧
 *
 * 使い方: 固定ページを新規作成し、スラッグを「admin-payment-list」に、
 * テンプレートで「管理用決済一覧」を選択してください。
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/admin-payment-list.php';

$list_page_var = 'apl_paged';
$per_page = 25;
$payment_status_filter = isset($_GET['payment_status_filter']) ? sanitize_text_field(wp_unslash($_GET['payment_status_filter'])) : '';
$allowed_status = ['', 'paid', 'trial', 'unpaid', 'cancelled'];
if (!in_array($payment_status_filter, $allowed_status, true)) {
    $payment_status_filter = '';
}

$all_users = aidunite_admin_payment_list_collect_users();
if ($payment_status_filter !== '') {
    $all_users = array_values(array_filter($all_users, static function ($user) use ($payment_status_filter) {
        $status = function_exists('aidunite_get_payment_status')
            ? aidunite_get_payment_status($user->ID)
            : get_user_meta($user->ID, 'payment_status', true);
        return (string) $status === $payment_status_filter;
    }));
}

$total_count = count($all_users);
$total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
$requested_page = isset($_GET[$list_page_var]) ? (int) $_GET[$list_page_var] : (isset($_GET['paged']) ? (int) $_GET['paged'] : 0);
$current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
$offset = ($current_page - 1) * $per_page;
$users_page = array_slice($all_users, $offset, $per_page);

$pagination_base = add_query_arg(
    array_filter(['payment_status_filter' => $payment_status_filter]),
    get_permalink()
);
$reset_url = remove_query_arg(['payment_status_filter', $list_page_var, 'paged'], get_permalink());

get_header();
?>

<div class="wrap page-admin-payment-list-wrap">
    <h1>決済一覧（管理者用）</h1>
    <p style="color:var(--text-secondary); margin:0 0 var(--spacing-lg);">
        チーム所属ユーザーまたは決済ステータスが登録されているユーザーを表示します。
        金額の変更は <a href="<?php echo esc_url(home_url('/admin-payment-management')); ?>">決済管理</a> から行ってください。
    </p>

    <div class="search-filter-section admin-schedule-filters" style="background:var(--bg-secondary); padding:var(--spacing-lg); margin:var(--spacing-lg) 0; border-radius:var(--radius-base);">
        <h3>🔍 フィルター</h3>
        <form method="get" class="admin-schedule-filters__form">
            <div class="admin-schedule-filters__field">
                <label for="payment_status_filter">決済ステータス</label>
                <select id="payment_status_filter" name="payment_status_filter" class="admin-schedule-filters__input">
                    <option value="">すべて</option>
                    <option value="paid" <?php selected($payment_status_filter, 'paid'); ?>>有料（paid）</option>
                    <option value="trial" <?php selected($payment_status_filter, 'trial'); ?>>トライアル</option>
                    <option value="unpaid" <?php selected($payment_status_filter, 'unpaid'); ?>>未払い</option>
                    <option value="cancelled" <?php selected($payment_status_filter, 'cancelled'); ?>>解約</option>
                </select>
            </div>
            <div class="admin-schedule-filters__actions">
                <button type="submit" class="button button-primary">絞り込む</button>
                <a href="<?php echo esc_url($reset_url); ?>" class="button">リセット</a>
            </div>
        </form>
        <p style="margin:var(--spacing-base) 0 0;">
            全 <?php echo (int) $total_count; ?> 件
            <?php if ($total_pages > 1) : ?>
                （<?php echo (int) ($offset + 1); ?> - <?php echo (int) min($offset + $per_page, $total_count); ?> 件目）
            <?php endif; ?>
        </p>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav style="margin-bottom:var(--spacing-base);" aria-label="ページ送り">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
        ]);
        ?>
    </nav>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="admin-payment-table wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="col-checkbox">
                        <label class="aidunite-admin-checkbox" title="このページをすべて選択（将来用）">
                            <input type="checkbox" class="aidunite-admin-checkbox__input" disabled aria-label="すべて選択">
                            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                        </label>
                    </th>
                    <th class="col-no">No</th>
                    <th>ユーザー名</th>
                    <th>ユーザーID</th>
                    <th>チーム名</th>
                    <th>チームID</th>
                    <th>金額タイプ</th>
                    <th>月額目安</th>
                    <th>決済ステータス</th>
                    <th>決済有無</th>
                    <th>更新日時</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users_page)) : ?>
                <tr>
                    <td colspan="12" style="text-align:center; padding:var(--spacing-xl);">該当するユーザーがありません。</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($users_page as $idx => $user) :
                    $row = aidunite_admin_payment_list_row_data($user);
                    $row_no = $offset + $idx + 1;
                    $uid = (int) $row['user_id'];
                    ?>
                <tr>
                    <td class="col-checkbox">
                        <label class="aidunite-admin-checkbox">
                            <input type="checkbox" class="payment-row-checkbox aidunite-admin-checkbox__input" value="<?php echo (int) $uid; ?>" aria-label="<?php echo esc_attr('ユーザーID ' . $uid); ?>">
                            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                        </label>
                    </td>
                    <td class="col-no"><?php echo (int) $row_no; ?></td>
                    <?php aidunite_admin_list_echo_cell('text', $row['user_name']); ?>
                    <?php aidunite_admin_list_echo_cell('id', (string) $uid); ?>
                    <?php
                    if ($row['team_settings_url'] !== '') {
                        $team_link = '<a href="' . esc_url($row['team_settings_url']) . '">' . esc_html($row['team_name']) . '</a>';
                        aidunite_admin_list_echo_cell_html('text', $team_link);
                    } else {
                        aidunite_admin_list_echo_cell('text', $row['team_name']);
                    }
                    ?>
                    <?php aidunite_admin_list_echo_cell('id', $row['team_id'] !== '—' ? (string) $row['team_id'] : '—'); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['amount_type_label']); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['monthly_fee']); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['payment_status_label']); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['paid_flag_label']); ?>
                    <?php aidunite_admin_list_echo_cell('datetime', $row['status_updated']); ?>
                    <td>
                        <a href="<?php echo esc_url($row['user_edit_url']); ?>">ユーザー一覧</a>
                        <?php if ($row['team_settings_url'] !== '') : ?>
                        | <a href="<?php echo esc_url($row['team_settings_url']); ?>">チーム設定</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav style="margin-top:var(--spacing-xl); display:flex; justify-content:center;" aria-label="ページ送り（下）">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
        ]);
        ?>
    </nav>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
