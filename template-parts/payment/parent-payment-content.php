<?php
/**
 * 保護者月謝支払いページ本体
 *
 * @var array<string, mixed> $vm
 * @var array<string, mixed>|null $payment_flash
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = is_array($args ?? null) ? $args : [];
$vm = is_array($args['vm'] ?? null) ? $args['vm'] : [];
$payment_flash = $args['payment_flash'] ?? null;

$team_logo = (string) ($vm['team_logo'] ?? '');
$team_name = (string) ($vm['team_name'] ?? '');
$monthly_fee = (int) ($vm['monthly_fee'] ?? 0);
$tuition_open = !empty($vm['tuition_open']);
$has_subscription = !empty($vm['has_subscription']);
$tuition_registration_pending = !empty($vm['tuition_registration_pending']);
$checkout_button_label = (string) ($vm['checkout_button_label'] ?? '支払い方法を登録する');
?>

<?php if (is_array($payment_flash) && !empty($payment_flash['message'])) : ?>
<div class="payment-setup-section payment-setup-flash payment-setup-flash--<?php echo esc_attr((string) ($payment_flash['type'] ?? 'info')); ?>" role="status">
    <p><?php echo esc_html((string) $payment_flash['message']); ?></p>
</div>
<?php endif; ?>

<?php if (!$tuition_open) : ?>
<div class="payment-setup-section payment-setup-alert" role="status">
    <p>このチームでは月謝のお支払い設定はまだ利用できません。チーム代表者にお問い合わせください。</p>
</div>
<?php else : ?>

<section class="payment-setup-section parent-payment-info" aria-labelledby="parent-payment-info-heading">
    <h2 id="parent-payment-info-heading" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true">
            <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        お支払い情報
    </h2>
    <div class="parent-payment-info-cards">
        <article class="parent-payment-info-card">
            <p class="parent-payment-info-card__label">チーム名</p>
            <div class="parent-payment-info-card__value parent-payment-info-card__value--team">
                <?php if ($team_logo !== '' && function_exists('aidunite_team_logo_is_displayable') && aidunite_team_logo_is_displayable($team_logo)) : ?>
                    <img class="parent-payment-info-card__logo" src="<?php echo esc_url($team_logo); ?>" alt="" width="64" height="64" loading="lazy">
                <?php else : ?>
                    <div class="parent-payment-info-card__logo parent-payment-info-card__logo--placeholder" aria-hidden="true">
                        <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('groups', ['width' => '32', 'height' => '32'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
                <p class="parent-payment-info-card__team-name"><?php echo esc_html($team_name); ?></p>
            </div>
        </article>

        <article class="parent-payment-info-card">
            <p class="parent-payment-info-card__label">月謝金額</p>
            <p class="parent-payment-info-card__value">
                <span class="parent-payment-info-card__value-icon" aria-hidden="true">
                    <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('payments', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                ¥<?php echo esc_html(number_format($monthly_fee)); ?>/月
            </p>
        </article>

        <article class="parent-payment-info-card">
            <p class="parent-payment-info-card__label">お支払いサイクル</p>
            <p class="parent-payment-info-card__value">
                <span class="parent-payment-info-card__value-icon" aria-hidden="true">
                    <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('calendar_month', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
                月末自動カード支払い
            </p>
        </article>
    </div>
</section>

<section class="payment-setup-section" aria-labelledby="parent-payment-method-heading">
    <h2 id="parent-payment-method-heading" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true">
            <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('payments', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        支払い方法
    </h2>
    <div class="parent-payment-method">
        <p class="parent-payment-method__lead">クレジットカードで月謝をお支払いいただけます。カード情報は安全に処理されます。</p>
        <button
            type="button"
            id="start-stripe-connect-checkout"
            class="btn btn-primary parent-payment-method__cta<?php echo $tuition_registration_pending ? ' parent-payment-method__cta--pending' : ''; ?>"
            <?php echo $tuition_registration_pending ? ' disabled aria-disabled="true"' : ''; ?>
        >
            <span class="parent-payment-method__cta-icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('payments', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <?php echo esc_html($checkout_button_label); ?>
        </button>
        <?php if ($has_subscription) : ?>
        <button type="button" id="cancel-stripe-connect-subscription" class="btn btn-secondary parent-payment-method__cancel">
            月謝を停止する
        </button>
        <?php endif; ?>
        <p class="parent-payment-method__note">
            <span aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('lock', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            カード情報は Stripe により安全に処理されます
        </p>
    </div>
</section>

<section class="payment-setup-section parent-payment-section--history" aria-labelledby="parent-payment-history-heading">
    <h2 id="parent-payment-history-heading" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true">
            <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('save', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        お支払い履歴
    </h2>
    <div id="payment-history-list" class="parent-payment-history">
        <p class="parent-payment-history__loading">履歴を読み込み中...</p>
    </div>
</section>

<?php endif; ?>
