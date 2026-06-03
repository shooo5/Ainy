<?php
/**
 * Template Name: 通知設定ページ
 *
 * ユーザー向け: アプリ内（常時）・メール ON/OFF のみ操作可能。
 * priority / channels / 通知タイプ一覧など内部設計は画面に出さない（仕様: docs/spec/notification.md 第4節）。
 */
if (!class_exists('AidUniteNotificationConfig')) {
    require_once get_stylesheet_directory() . '/functions/notify/notification-config.php';
}

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
$settings = aidunite_get_user_notification_settings($current_user_id);

// 保存: メールのみユーザー操作。他キーは既存 user_meta を維持（内部の notification_types / quiet_hours / batch_digest / push 等は UI から変更しない）。
if (
    isset($_POST['notification_settings_nonce'], $_POST['save_user_notification_prefs'])
    && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['notification_settings_nonce'])), 'save_notification_settings')
) {
    $defaults_full = AidUniteNotificationConfig::get_user_default_settings();
    $merged = wp_parse_args(is_array($settings) ? $settings : [], $defaults_full);
    $merged['email_notifications'] = !empty($_POST['email_notifications']);
    $merged['line_notifications'] = false;
    update_user_meta($current_user_id, 'notification_settings', $merged);
    $settings = aidunite_get_user_notification_settings($current_user_id);

    echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
}

$email_on = !empty($settings['email_notifications']);

$notif_settings_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-notification-settings notification-settings-page ainy-notification-settings',
        'title' => '通知設定',
        'subtitle' => 'お知らせの受け取り方',
        'actions' => [
            [
                'type' => 'link',
                'href' => home_url('/notifications'),
                'icon' => 'chevron_left',
                'aria' => 'お知らせ一覧へ',
            ],
        ],
    ]);
    $notif_settings_shell_opened = true;
} else {
    echo '<div class="container notification-settings-page ainy-notification-settings">';
    echo '<h1 class="ainy-notification-settings__title">通知設定</h1>';
    echo '<p class="ainy-notification-settings__nav-top"><a href="' . esc_url(home_url('/notifications')) . '">お知らせ一覧へ</a></p>';
}
?>
    <p class="ainy-notification-settings__lead">
        お知らせの受け取り方のうち、変更できる項目だけを表示しています。
    </p>

    <form method="post" action="" class="notification-settings-form ainy-notification-settings__form">
        <?php wp_nonce_field('save_notification_settings', 'notification_settings_nonce'); ?>
        <input type="hidden" name="save_user_notification_prefs" value="1" />

        <ul class="ainy-notification-settings__list" role="list">
            <li class="ainy-notification-settings__card">
                <div class="ainy-notification-settings__row-head">
                    <span class="ainy-notification-settings__label">アプリ内通知</span>
                    <span class="ainy-notification-settings__badge ainy-notification-settings__badge--on" aria-label="常にオン">常にON</span>
                </div>
                <p class="ainy-notification-settings__desc">
                    Ainy内のお知らせ一覧に表示されます。重要なお知らせのためOFFにはできません。
                </p>
            </li>

            <li class="ainy-notification-settings__card">
                <div class="ainy-notification-settings__row-head">
                    <span class="ainy-notification-settings__label" id="label-email-notifications">メール通知</span>
                    <div class="ainy-notification-settings__switch-cell">
                        <label class="toggle-switch" for="email_notifications">
                            <input
                                type="checkbox"
                                name="email_notifications"
                                id="email_notifications"
                                value="1"
                                role="switch"
                                aria-checked="<?php echo $email_on ? 'true' : 'false'; ?>"
                                aria-labelledby="label-email-notifications"
                                <?php checked($email_on); ?>
                            />
                            <span class="toggle-slider" aria-hidden="true"></span>
                        </label>
                        <span class="toggle-switch__sr" id="email-notifications-state-sr"><?php echo $email_on ? 'オン' : 'オフ'; ?></span>
                    </div>
                </div>
                <p class="ainy-notification-settings__desc">
                    登録メールアドレスに通知を送ります。
                </p>
            </li>

            <li class="ainy-notification-settings__card" aria-disabled="true">
                <div class="ainy-notification-settings__row-head">
                    <span class="ainy-notification-settings__label">LINE通知</span>
                    <span class="ainy-notification-settings__badge ainy-notification-settings__badge--soon">準備中</span>
                </div>
                <p class="ainy-notification-settings__desc">
                    今後対応予定です。
                </p>
            </li>

            <li class="ainy-notification-settings__card" aria-disabled="true">
                <div class="ainy-notification-settings__row-head">
                    <span class="ainy-notification-settings__label">プッシュ通知</span>
                    <span class="ainy-notification-settings__badge ainy-notification-settings__badge--soon">準備中</span>
                </div>
                <p class="ainy-notification-settings__desc">
                    今後対応予定です。
                </p>
            </li>
        </ul>

        <div class="ainy-notification-settings__actions">
            <button type="submit" class="button button-primary button-large ainy-notification-settings__submit">設定を保存</button>
        </div>
    </form>

    <?php if (current_user_can('manage_options') && function_exists('aidunite_notification_send')) : ?>
    <details class="ainy-notification-settings__admin">
        <summary class="ainy-notification-settings__admin-summary">サイト管理者向け（テスト送信）</summary>
        <div class="ainy-notification-settings__admin-body">
            <p class="ainy-notification-settings__desc">
                自分宛にテスト通知を1件送り、お知らせ一覧で確認できます。メールの宛先は既定で <strong>サイトの管理者メール</strong>（設定 → 一般のメールアドレス。チーム申請通知と同じ HTML 形式）です。別アドレスに送る場合は REST の JSON で <code>test_mail_recipient</code> を指定し、許可リスト（フィルター <code>aidunite_notification_test_mail_allowlist</code>）に追加してください。To と管理者メールが異なるときは診断用に管理者メールへ Bcc も送ります。ログインユーザーのメール通知 ON/OFF にかかわらず送信を試みます。
            </p>
            <button type="button" id="notification-test-send-btn" class="button button-secondary">テスト通知を送信する</button>
            <span id="notification-test-result" class="ainy-notification-settings__test-result" aria-live="polite"></span>
        </div>
    </details>
    <?php endif; ?>

<?php
if ($notif_settings_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<?php get_footer(); ?>

<?php if (current_user_can('manage_options') && function_exists('aidunite_notification_send')) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var testSendBtn = document.getElementById('notification-test-send-btn');
    var testResultEl = document.getElementById('notification-test-result');

    function setTestResultWithIcon(el, basename, message) {
        if (typeof AidUniteThemeIcons !== 'undefined') {
            el.innerHTML = AidUniteThemeIcons.html(basename, 16) + ' ' + message;
        } else {
            el.textContent = message;
        }
    }

    if (!testSendBtn || !testResultEl) return;
    testSendBtn.addEventListener('click', function() {
        testSendBtn.disabled = true;
        testResultEl.textContent = '送信中...';
        testResultEl.style.color = '';
        fetch('<?php echo esc_url(rest_url('aidunite/v1/notification-test')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
            },
            body: JSON.stringify({})
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            testSendBtn.disabled = false;
            if (data.success) {
                var mail = data.mail || {};
                var mailFailed = mail.attempted && mail.ok === false;
                setTestResultWithIcon(testResultEl, 'check', data.message || '送信しました');
                testResultEl.style.color = mailFailed ? 'var(--danger-color, #dc3545)' : 'var(--success-color, #28a745)';
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(data.message || 'テスト通知を送信しました', mailFailed ? 'error' : 'success');
                }
            } else {
                setTestResultWithIcon(testResultEl, 'brightness_alert', data.message || '失敗');
                testResultEl.style.color = 'var(--danger-color, #dc3545)';
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(data.message || '送信に失敗しました', 'error');
                }
            }
        })
        .catch(function() {
            testSendBtn.disabled = false;
            setTestResultWithIcon(testResultEl, 'brightness_alert', 'エラー');
            testResultEl.style.color = 'var(--danger-color, #dc3545)';
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('通信エラーが発生しました', 'error');
            } else {
                alert('通信エラーが発生しました');
            }
        });
    });
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var cb = document.getElementById('email_notifications');
    if (!cb) return;
    var sr = document.getElementById('email-notifications-state-sr');
    function sync() {
        cb.setAttribute('aria-checked', cb.checked ? 'true' : 'false');
        if (sr) {
            sr.textContent = cb.checked ? 'オン' : 'オフ';
        }
    }
    cb.addEventListener('change', sync);
    sync();
});
</script>

<style>
.ainy-notification-settings {
    max-width: 40rem;
    margin-left: auto;
    margin-right: auto;
    padding: var(--spacing-base, 1rem) var(--spacing-sm, 0.75rem) var(--spacing-lg, 2rem);
}
.ainy-notification-settings__title {
    font-size: var(--font-size-xl, 1.5rem);
    font-weight: 700;
    margin: 0 0 var(--spacing-sm, 0.5rem);
    color: var(--text-primary, #1a1a1a);
}
.ainy-notification-settings__nav-top {
    margin: 0 0 var(--spacing-base, 1rem);
    font-size: var(--font-size-sm, 0.9rem);
}
.ainy-notification-settings__nav-top a {
    color: var(--primary-color);
    text-decoration: underline;
}
.ainy-notification-settings__nav-top a:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
.ainy-notification-settings__lead {
    margin: 0 0 var(--spacing-base, 1rem);
    font-size: var(--font-size-sm, 0.9rem);
    color: var(--text-secondary, #555);
    line-height: 1.5;
}
.ainy-notification-settings__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-base, 1rem);
}
.ainy-notification-settings__card {
    margin: 0;
    padding: var(--spacing-base, 1rem);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: var(--radius-base, 8px);
    background: var(--bg-primary, #fff);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}
.ainy-notification-settings__row-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-sm, 0.75rem);
    flex-wrap: wrap;
}
.ainy-notification-settings__label {
    font-weight: 600;
    color: var(--text-primary, #1a1a1a);
}
.ainy-notification-settings__desc {
    margin: var(--spacing-sm, 0.5rem) 0 0;
    font-size: var(--font-size-sm, 0.9rem);
    color: var(--text-secondary, #555);
    line-height: 1.55;
}
.ainy-notification-settings__switch-cell {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.ainy-notification-settings__badge {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    border-radius: var(--radius-small, 4px);
    font-size: var(--font-size-xs, 0.8rem);
    font-weight: 600;
}
.ainy-notification-settings__badge--on {
    background: rgba(40, 167, 69, 0.12);
    color: var(--success-color, #28a745);
    border: 1px solid rgba(40, 167, 69, 0.35);
}
.ainy-notification-settings__badge--soon {
    background: var(--bg-secondary, #f1f3f5);
    color: var(--text-secondary, #555);
    border: 1px solid var(--border-color, #dee2e6);
}
.ainy-notification-settings__actions {
    margin-top: var(--spacing-lg, 1.5rem);
    text-align: center;
}
.ainy-notification-settings__submit:focus-visible {
    outline: 2px solid var(--primary-color, #2271b1);
    outline-offset: 2px;
}
.ainy-notification-settings__admin {
    margin-top: var(--spacing-lg, 2rem);
    padding: var(--spacing-base, 1rem);
    border: 1px dashed var(--border-color, #ccc);
    border-radius: var(--radius-base, 8px);
    background: var(--bg-secondary, #f8f9fa);
}
.ainy-notification-settings__admin-summary {
    cursor: pointer;
    font-weight: 600;
    color: var(--text-secondary, #555);
}
.ainy-notification-settings__admin-summary:focus-visible {
    outline: 2px solid var(--primary-color, #2271b1);
    outline-offset: 2px;
}
.ainy-notification-settings__admin-body {
    margin-top: var(--spacing-sm, 0.75rem);
}
.ainy-notification-settings__test-result {
    margin-left: var(--spacing-sm, 0.75rem);
    font-size: var(--font-size-sm, 0.9rem);
}
@media (max-width: 480px) {
    .ainy-notification-settings {
        padding-left: var(--spacing-xs, 0.5rem);
        padding-right: var(--spacing-xs, 0.5rem);
    }
}
</style>
