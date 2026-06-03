<?php
/**
 * 管理者用決済一覧
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 決済ステータスの表示ラベル
 *
 * @param string|null $status
 * @return string
 */
function aidunite_admin_payment_list_status_label($status) {
    $status = (string) $status;
    $labels = [
        'paid' => '有料（paid）',
        'trial' => 'トライアル',
        'unpaid' => '未払い',
        'cancelled' => '解約',
    ];

    return $labels[$status] ?? ($status !== '' ? $status : '未設定');
}

/**
 * 決済有無（一覧用）
 *
 * @param string|null $status
 * @return string
 */
function aidunite_admin_payment_list_paid_flag_label($status) {
    return (string) $status === 'paid' ? 'あり' : 'なし';
}

/**
 * 金額タイプ（チームの payment_mode + 種別）
 *
 * @param int $team_id
 * @return string
 */
function aidunite_admin_payment_list_amount_type_label($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '—';
    }

    $team_type = function_exists('aidunite_get_team_type') ? aidunite_get_team_type($team_id) : '';
    $payment_mode = function_exists('aidunite_get_team_payment_mode')
        ? (string) aidunite_get_team_payment_mode($team_id)
        : '';

    if ($team_type === 'club') {
        return 'クラブ（Stripe・人数課金）';
    }

    $mode_labels = [
        'board' => '学校・教育委員会契約',
        'school' => '学校・学校契約',
        'personal' => '学校・個人契約',
    ];

    if (isset($mode_labels[$payment_mode])) {
        return $mode_labels[$payment_mode];
    }

    $type_label = function_exists('aidunite_team_type_label')
        ? aidunite_team_type_label($team_type)
        : $team_type;
    return $type_label !== '' ? ($type_label . ' / モード未設定') : '—';
}

/**
 * 一覧行データ
 *
 * @param WP_User $user
 * @return array<string, mixed>
 */
function aidunite_admin_payment_list_row_data(WP_User $user) {
    $user_id = (int) $user->ID;
    $team_id = (int) get_user_meta($user_id, 'team_id', true);
    $team_name = '—';
    if ($team_id > 0) {
        $team_post = get_post($team_id);
        $team_name = $team_post ? $team_post->post_title : ('ID:' . $team_id);
    }

    $payment_status = function_exists('aidunite_get_payment_status')
        ? aidunite_get_payment_status($user_id)
        : get_user_meta($user_id, 'payment_status', true);

    $status_updated = (string) get_user_meta($user_id, 'payment_status_updated', true);
    $monthly_fee = ($team_id > 0 && function_exists('aidunite_calculate_monthly_fee'))
        ? (int) aidunite_calculate_monthly_fee($team_id)
        : 0;

    $roles = is_array($user->roles) ? implode(', ', $user->roles) : '';

    return [
        'user_id' => $user_id,
        'user_name' => $user->display_name ?: $user->user_login,
        'user_email' => $user->user_email,
        'team_id' => $team_id > 0 ? $team_id : '—',
        'team_name' => $team_name,
        'amount_type_label' => aidunite_admin_payment_list_amount_type_label($team_id),
        'payment_status' => $payment_status,
        'payment_status_label' => aidunite_admin_payment_list_status_label($payment_status),
        'paid_flag_label' => aidunite_admin_payment_list_paid_flag_label($payment_status),
        'monthly_fee' => $monthly_fee > 0 ? number_format($monthly_fee) . '円' : '—',
        'status_updated' => $status_updated !== '' ? $status_updated : '—',
        'roles' => $roles !== '' ? $roles : '—',
        'user_edit_url' => add_query_arg(['user_id' => $user_id], home_url('/admin-user-list')),
        'team_settings_url' => $team_id > 0
            ? add_query_arg('team_id', $team_id, home_url('/team-settings'))
            : '',
    ];
}

/**
 * 決済一覧の対象ユーザー（チーム所属または決済ステータスあり）
 *
 * @return WP_User[]
 */
function aidunite_admin_payment_list_collect_users() {
    $by_id = [];

    $with_team = get_users([
        'meta_key' => 'team_id',
        'meta_compare' => 'EXISTS',
        'number' => -1,
        'orderby' => 'ID',
        'order' => 'DESC',
    ]);
    foreach ($with_team as $user) {
        $tid = (int) get_user_meta($user->ID, 'team_id', true);
        if ($tid > 0) {
            $by_id[(int) $user->ID] = $user;
        }
    }

    $with_payment = get_users([
        'meta_key' => 'payment_status',
        'meta_compare' => 'EXISTS',
        'number' => -1,
        'orderby' => 'ID',
        'order' => 'DESC',
    ]);
    foreach ($with_payment as $user) {
        $by_id[(int) $user->ID] = $user;
    }

    $users = array_values($by_id);
    usort($users, static function ($a, $b) {
        return (int) $b->ID <=> (int) $a->ID;
    });

    return $users;
}
