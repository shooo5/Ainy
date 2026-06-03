<?php
/**
 * Webアプリ：ヒーロー内一体型ヘッダー（Ainyロゴ・チーム切替・通知・マイページ・管理・メニュー）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_nav           = isset($args['active_nav']) ? (string) $args['active_nav'] : 'none';
$notification_count   = isset($args['notification_count']) ? (int) $args['notification_count'] : 0;
if ($notification_count <= 0 && function_exists('aidunite_get_notification_count')) {
    $notification_count = (int) aidunite_get_notification_count(get_current_user_id());
}

$notif_active  = $active_nav === 'notifications' ? ' is-active' : '';
?>

<div class="ainy-webapp-hero-bar mypage-v2-hero-bar">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="ainy-webapp-hero-logo mypage-v2-hero-logo" aria-label="Ainy ホーム">
        <span class="ainy-webapp-hero-logo-icon mypage-v2-hero-logo-icon" aria-hidden="true">A</span>
        <span class="ainy-webapp-hero-logo-text mypage-v2-hero-logo-text">Ainy</span>
    </a>

    <div class="ainy-webapp-hero-actions mypage-v2-hero-actions">
        <?php
        if (function_exists('aidunite_render_operating_team_header_control')) {
            echo '<div class="ainy-webapp-hero-operating-team mypage-v2-hero-operating-team">';
            aidunite_render_operating_team_header_control();
            echo '</div>';
        }
        ?>
        <span class="ainy-header-notification-wrap">
            <a href="<?php echo esc_url(home_url('/notifications')); ?>" class="ainy-header-icon-link ainy-header-notification-link ainy-webapp-hero-icon-link mypage-v2-hero-icon-link<?php echo $notif_active; ?>" aria-label="<?php echo $notification_count > 0 ? esc_attr('お知らせ（未読' . $notification_count . '件）') : 'お知らせ'; ?>"<?php echo $notif_active ? ' aria-current="page"' : ''; ?>>
                <svg width="24" height="24" viewBox="0 -960 960 960" fill="#EAC452" aria-hidden="true"><path d="M160-200v-80h80v-280q0-83 50-147.5T420-792v-28q0-25 17.5-42.5T480-880q25 0 42.5 17.5T540-820v28q80 20 130 84.5T720-560v280h80v80H160Zm320-300Zm0 420q-33 0-56.5-23.5T400-160h160q0 33-23.5 56.5T480-80ZM320-280h320v-280q0-66-47-113t-113-47q-66 0-113 47t-47 113v280Z"/></svg>
                <?php if ($notification_count > 0) : ?>
                    <span class="ainy-notification-dot" aria-hidden="true"></span>
                <?php endif; ?>
            </a>
        </span>
        <button type="button" class="ainy-hamburger ainy-webapp-hero-hamburger mypage-v2-hero-hamburger" title="メニュー" aria-label="メニューを開く">
            <span class="ainy-hamburger-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </button>
    </div>
</div>
