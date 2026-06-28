<?php
/**
 * チーム月謝管理ページ本体
 *
 * @var string $message
 * @var string $message_type
 * @var int    $team_id
 * @var WP_Post $team
 * @var bool   $tuition_enabled
 * @var int    $monthly_fee
 * @var string $connect_acct
 * @var string $team_fee_note
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = is_array($args ?? null) ? $args : [];
$message = (string) ($args['message'] ?? '');
$message_type = (string) ($args['message_type'] ?? 'success');
$team_id = (int) ($args['team_id'] ?? 0);
$team = $args['team'] ?? null;
$tuition_enabled = !empty($args['tuition_enabled']);
$monthly_fee = (int) ($args['monthly_fee'] ?? 0);
$connect_acct = (string) ($args['connect_acct'] ?? '');
$team_fee_note = (string) ($args['team_fee_note'] ?? '');
$team_title = ($team instanceof WP_Post) ? (string) $team->post_title : '';
?>

<?php if ($message !== '') : ?>
<div class="payment-setup-section payment-setup-flash payment-setup-flash--<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" role="status">
    <p><?php echo esc_html($message); ?></p>
</div>
<?php endif; ?>

<div class="payment-setup-section payment-setup-section--inline-link">
    <p class="help-text">
        保護者の今月の支払い状況は
        <a href="<?php echo esc_url(home_url('/team-tuition-collections')); ?>">月謝の徴収状況</a>
        から確認できます。
    </p>
</div>

<div class="team-payment-management-grid">
    <section class="payment-setup-section" aria-labelledby="team-tuition-settings-heading">
        <h2 id="team-tuition-settings-heading" class="payment-setup-section__title payment-setup-section__title--icon">
            <span class="payment-setup-section__title-icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('payments', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            月謝設定
        </h2>
        <form method="post">
            <?php wp_nonce_field('aidunite_save_tuition_settings_' . $team_id); ?>
            <div class="form-group current-team-group">
                <p class="current-team-text">現在のチーム: <strong><?php echo esc_html($team_title); ?></strong></p>
            </div>

            <div class="form-group tuition-toggle-row">
                <span class="tuition-label">月謝機能:</span>
                <span class="tuition-status-text" id="tuition-status-text"><?php echo $tuition_enabled ? '有効' : '無効'; ?></span>
                <label class="toggle-switch">
                    <input type="checkbox" id="team_tuition_enabled_toggle" name="team_tuition_enabled" value="1" <?php checked($tuition_enabled); ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <p class="help-text">有効にすると、保護者マイページから月謝支払いページ（/parent-payment）へ誘導されます。</p>

            <div class="form-group" id="tuition-amount-section">
                <div class="current-setting-display">
                    <strong>現在の設定金額:</strong>
                    <span class="current-amount-value">
                        <?php if ($monthly_fee > 0) : ?>
                            <?php echo esc_html(number_format($monthly_fee)); ?>円
                        <?php else : ?>
                            未設定
                        <?php endif; ?>
                    </span>
                </div>
                <label for="team_monthly_fee">月謝金額（円）</label>
                <input
                    type="number"
                    id="team_monthly_fee"
                    name="team_monthly_fee"
                    min="0"
                    step="100"
                    value="<?php echo esc_attr($monthly_fee); ?>"
                    placeholder="例: 5000"
                >
                <p class="help-text">0または未入力の場合は「未設定」となり、月謝のCheckoutはエラーになります（誤課金防止）。</p>
                <?php if ($team_fee_note !== '') : ?>
                <p class="help-text"><?php echo esc_html($team_fee_note); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" name="aidunite_save_tuition_settings" value="1" class="btn btn-primary">
                    設定を保存
                </button>
            </div>
        </form>
    </section>

    <section class="payment-setup-section" aria-labelledby="team-connect-heading">
        <h2 id="team-connect-heading" class="payment-setup-section__title payment-setup-section__title--icon">
            <span class="payment-setup-section__title-icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('settings', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            Stripe Connect 連携
        </h2>
        <div class="form-group">
            <p><strong>現在の連携状態:</strong></p>
            <?php if ($connect_acct !== '') : ?>
                <p class="status-badge status-connected">
                    連携済み（アカウントID: <?php echo esc_html($connect_acct); ?>）
                </p>
            <?php else : ?>
                <p class="status-badge status-not-connected">
                    未連携（Stripe連携を開始してください）
                </p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <p class="help-text">
                「Stripe連携を開始」ボタンを押すと、Stripeの画面に移動します。<br>
                口座情報や本人確認を完了すると、このチームの月謝を直接受け取れるようになります。
            </p>
            <div class="form-actions">
                <button type="button" id="aidunite-start-connect-onboarding" class="btn btn-secondary">
                    Stripe連携を開始
                </button>
            </div>
        </div>

        <div id="aidunite-connect-onboarding-message" class="inline-message"></div>
    </section>
</div>
