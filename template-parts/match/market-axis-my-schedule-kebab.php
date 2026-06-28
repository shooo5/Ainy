<?php
/**
 * 申請状況タブ：自分の募集ヘッダー右上ケバブ（編集・招待コード・解散）
 *
 * @var array $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = isset($args) && is_array($args) ? $args : [];
$schedule_id = (int) ($args['schedule_id'] ?? 0);
$edit_url = (string) ($args['edit_url'] ?? '');
$show_invite_btn = !empty($args['show_invite_btn']);
$can_invite = !empty($args['can_invite']);
$is_full = !empty($args['is_full']);
$matching_on = !empty($args['matching_on']);
$m_place = (string) ($args['m_place'] ?? '');
$venue_name = (string) ($args['venue_name'] ?? '');
$host_dissolve_mr_id = (int) ($args['host_dissolve_mr_id'] ?? 0);
$is_anchor_host_block = !empty($args['is_anchor_host_block']);

$menu_id = 'market-axis-my-kebab-' . max(0, $schedule_id);
$invite_label = '招待コード';
$invite_disabled = true;
$invite_disabled_title = '';

if ($show_invite_btn) {
    $invite_disabled = false;
} elseif ($can_invite && $is_full) {
    $invite_disabled_title = '満員';
    $invite_label = '招待コード（満員）';
} elseif (!$matching_on) {
    $invite_disabled_title = '対戦募集をONにすると発行できます';
} elseif (in_array($m_place, ['home', 'ホーム'], true) && trim($venue_name) === '') {
    $invite_disabled_title = '会場名が未入力です（編集して入力してください）';
}

$show_dissolve = $is_anchor_host_block && $host_dissolve_mr_id > 0;
$show_edit_invite = !$show_dissolve && $schedule_id > 0 && $edit_url !== '';

if (!$show_dissolve && !$show_edit_invite) {
    return;
}
?>
<div class="market-axis-my-kebab" data-market-axis-my-kebab>
    <button
        type="button"
        class="market-axis-my-kebab__trigger"
        aria-label="メニュー"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($menu_id); ?>"
        data-market-axis-my-kebab-trigger
    ><span class="market-axis-my-kebab__icon" aria-hidden="true">⋮</span></button>
    <div
        class="market-axis-my-kebab__menu"
        id="<?php echo esc_attr($menu_id); ?>"
        role="menu"
        hidden
    >
        <?php if ($show_dissolve) : ?>
            <button
                type="button"
                class="market-axis-my-kebab__item market-axis-block-dissolve-btn au-open-cancel-modal"
                role="menuitem"
                data-request-id="<?php echo (int) $host_dissolve_mr_id; ?>"
                data-team-name=""
                data-cancel-type="established"
                data-cancel-scope="dissolve"
            >試合を解散</button>
        <?php else : ?>
            <a href="<?php echo esc_url($edit_url); ?>" class="market-axis-my-kebab__item" role="menuitem">編集</a>
            <button
                type="button"
                class="market-axis-my-kebab__item market-axis-invite-btn<?php echo $invite_disabled ? ' market-axis-my-kebab__item--disabled' : ''; ?>"
                role="menuitem"
                data-schedule-id="<?php echo (int) $schedule_id; ?>"
                <?php echo $invite_disabled ? ' disabled' : ''; ?>
                <?php echo $invite_disabled_title !== '' ? ' title="' . esc_attr($invite_disabled_title) . '"' : ''; ?>
            ><?php echo esc_html($invite_label); ?></button>
        <?php endif; ?>
    </div>
</div>
