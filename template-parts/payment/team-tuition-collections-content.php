<?php
/**
 * チーム月謝徴収状況ページ本体
 *
 * @var array<string, mixed> $vm canonical page model
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = is_array($args ?? null) ? $args : [];
$vm = is_array($args['vm'] ?? null) ? $args['vm'] : [];

$month_label = (string) ($vm['month_label'] ?? '');
$tuition_open = !empty($vm['tuition_open']);
$connect_block_reason = (string) ($vm['connect_block_reason'] ?? '');
$monthly_fee = (int) ($vm['monthly_fee'] ?? 0);
$summary = is_array($vm['summary'] ?? null) ? $vm['summary'] : [];
$parents = is_array($vm['parents'] ?? null) ? $vm['parents'] : [];
$recent_events = is_array($vm['recent_events'] ?? null) ? $vm['recent_events'] : [];
$settings_url = (string) ($vm['settings_url'] ?? home_url('/team-payment-management'));
$billing_note = (string) ($vm['billing_note'] ?? '');
$followup_messages = is_array($vm['followup_messages'] ?? null) ? $vm['followup_messages'] : [];
$action_needed_count = (int) ($summary['action_needed_count'] ?? 0);
$collected_total = (int) ($summary['collected_total_yen'] ?? 0);
$expected_total = (int) ($summary['expected_total_yen'] ?? 0);
?>

<?php if (!$tuition_open) : ?>
<section class="payment-setup-section team-tuition-connect-block" role="status">
    <?php
    $block_message = function_exists('aidunite_payment_read_tuition_block_message')
        ? aidunite_payment_read_tuition_block_message($connect_block_reason, 'leader')
        : '月謝機能が有効になっていないか、Stripe Connect の連携が未完了です。';
    ?>
    <p class="team-tuition-collections-empty team-tuition-collections-empty--blocked">
        <?php echo esc_html($block_message); ?>
        <a href="<?php echo esc_url($settings_url); ?>">チーム月謝管理</a>で設定してください。
    </p>
    <?php if ($connect_block_reason === 'connect_incomplete') : ?>
    <div class="form-actions">
        <a class="btn btn-primary" href="<?php echo esc_url($settings_url); ?>#team-connect-heading">Stripe Connect 連携を完了する</a>
    </div>
    <?php endif; ?>
</section>
<?php else : ?>

<section class="payment-setup-section" aria-labelledby="team-tuition-summary-heading">
    <h2 id="team-tuition-summary-heading" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true">
            <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <?php echo esc_html($month_label !== '' ? $month_label . 'の徴収サマリー' : '今月の徴収サマリー'); ?>
    </h2>
    <?php if ($billing_note !== '') : ?>
    <p class="team-tuition-collections-billing-note" role="note"><?php echo esc_html($billing_note); ?></p>
    <?php endif; ?>

    <div class="team-tuition-collections-summary" role="group" aria-label="徴収サマリー（タップで絞り込み）">
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="all" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">保護者数</span>
            <span class="team-tuition-collections-summary__value"><?php echo esc_html((string) (int) ($summary['parent_count'] ?? 0)); ?>名</span>
        </button>
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="paid" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">支払い済み</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--paid"><?php echo esc_html((string) (int) ($summary['paid_count'] ?? 0)); ?>名</span>
        </button>
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="pending_billing" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">請求前</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--pending"><?php echo esc_html((string) (int) ($summary['pending_billing_count'] ?? 0)); ?>名</span>
        </button>
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="overdue" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">未払い</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--overdue"><?php echo esc_html((string) (int) ($summary['overdue_count'] ?? 0)); ?>名</span>
        </button>
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="failed" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">失敗</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--failed"><?php echo esc_html((string) (int) ($summary['failed_count'] ?? 0)); ?>名</span>
        </button>
        <button type="button" class="team-tuition-collections-summary__card team-tuition-collections-filter" data-filter="not_registered" aria-pressed="false">
            <span class="team-tuition-collections-summary__label">未登録</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--not-registered"><?php echo esc_html((string) (int) ($summary['not_registered_count'] ?? 0)); ?>名</span>
        </button>
        <div class="team-tuition-collections-summary__card team-tuition-collections-summary__card--wide team-tuition-collections-summary__card--amount" aria-live="polite">
            <span class="team-tuition-collections-summary__label">入金 / 見込み</span>
            <span class="team-tuition-collections-summary__value team-tuition-collections-summary__value--amount">
                ¥<?php echo esc_html(number_format($collected_total)); ?>
                <span class="team-tuition-collections-summary__amount-sep">/</span>
                ¥<?php echo esc_html(number_format($expected_total)); ?>
            </span>
            <?php if ($monthly_fee > 0) : ?>
            <span class="team-tuition-collections-summary__meta">
                カード登録済み <?php echo esc_html((string) (int) ($summary['registered_count'] ?? 0)); ?>名
                × ¥<?php echo esc_html(number_format($monthly_fee)); ?>
                <?php if ((int) ($summary['not_registered_count'] ?? 0) > 0) : ?>
                （未登録 <?php echo esc_html((string) (int) ($summary['not_registered_count'] ?? 0)); ?>名は見込みに未含む）
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="payment-setup-section" aria-labelledby="team-tuition-parents-heading">
    <div class="team-tuition-collections-list-head">
        <h2 id="team-tuition-parents-heading" class="payment-setup-section__title payment-setup-section__title--icon team-tuition-collections-list-head__title">
            <span class="payment-setup-section__title-icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('group', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span id="team-tuition-parents-list-title">要対応の保護者（<?php echo esc_html((string) $action_needed_count); ?>名）</span>
        </h2>
        <div class="team-tuition-collections-list-head__actions">
            <button type="button" class="btn btn-secondary btn-sm" id="team-tuition-show-all" aria-pressed="false">
                すべて表示
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="team-tuition-export-csv">
                CSV
            </button>
        </div>
    </div>

    <?php if ($parents === []) : ?>
        <p class="team-tuition-collections-empty">登録済みの保護者がいません。</p>
    <?php else : ?>
        <p id="team-tuition-filter-empty" class="team-tuition-collections-empty" hidden>該当する保護者はいません。</p>
        <div class="team-tuition-collections-table-wrap">
            <table class="team-tuition-collections-table" id="team-tuition-parents-table">
                <thead>
                    <tr>
                        <th scope="col">保護者</th>
                        <th scope="col">お子さま</th>
                        <th scope="col">今月</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parents as $parent_row) : ?>
                        <?php
                        $status_key = (string) ($parent_row['month_status'] ?? '');
                        $status_label = (string) ($parent_row['month_status_label'] ?? '');
                        $needs_action = !empty($parent_row['needs_action']);
                        ?>
                        <tr
                            class="team-tuition-collections-parent-row"
                            data-month-status="<?php echo esc_attr($status_key); ?>"
                            data-needs-action="<?php echo $needs_action ? '1' : '0'; ?>"
                            data-parent-name="<?php echo esc_attr((string) ($parent_row['parent_name'] ?? '')); ?>"
                            data-parent-email="<?php echo esc_attr((string) ($parent_row['parent_email'] ?? '')); ?>"
                            data-child-summary="<?php echo esc_attr((string) ($parent_row['linked_child_summary'] ?? '')); ?>"
                            data-status-label="<?php echo esc_attr($status_label); ?>"
                        >
                            <td>
                                <span class="team-tuition-collections-table__name"><?php echo esc_html((string) ($parent_row['parent_name'] ?? '')); ?></span>
                                <?php if (!empty($parent_row['parent_email'])) : ?>
                                <span class="team-tuition-collections-table__meta"><?php echo esc_html((string) $parent_row['parent_email']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) ($parent_row['linked_child_summary'] ?? '—')); ?></td>
                            <td>
                                <span class="team-tuition-collections-status team-tuition-collections-status--<?php echo esc_attr($status_key); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="payment-setup-section" aria-labelledby="team-tuition-events-heading">
    <h2 id="team-tuition-events-heading" class="payment-setup-section__title payment-setup-section__title--icon">
        <span class="payment-setup-section__title-icon" aria-hidden="true">
            <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('payments', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        直近の入金イベント（最大10件）
    </h2>
    <?php if ($recent_events === []) : ?>
        <p class="team-tuition-collections-empty">入金イベントはまだありません。</p>
    <?php else : ?>
        <div class="team-tuition-collections-table-wrap">
            <table class="team-tuition-collections-table team-tuition-collections-table--events">
                <thead>
                    <tr>
                        <th scope="col">日時</th>
                        <th scope="col">保護者</th>
                        <th scope="col">金額</th>
                        <th scope="col">状態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_events as $event_row) : ?>
                        <?php
                        $event_status = (string) ($event_row['status'] ?? '');
                        $event_status_label = (string) ($event_row['status_label'] ?? '');
                        $payment_date = (string) ($event_row['payment_date'] ?? '');
                        $payment_date_display = $payment_date !== '' ? wp_date('Y/m/d H:i', strtotime($payment_date)) : '—';
                        ?>
                        <tr>
                            <td><?php echo esc_html($payment_date_display); ?></td>
                            <td><?php echo esc_html((string) ($event_row['parent_display_name'] ?? '—')); ?></td>
                            <td>¥<?php echo esc_html(number_format((int) ($event_row['amount_yen'] ?? 0))); ?></td>
                            <td>
                                <span class="team-tuition-collections-status team-tuition-collections-status--<?php echo esc_attr($event_status); ?>">
                                    <?php echo esc_html($event_status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div id="team-tuition-followup-root">
    <?php
    $followup_defs = [
        'overdue' => ['title' => '未払いフォロー用の案内文', 'help' => '月末を過ぎてもお支払いが確認できない保護者へ送る文面です。'],
        'failed' => ['title' => '決済失敗フォロー用の案内文', 'help' => 'カード決済が失敗した保護者へ送る文面です。'],
        'not_registered' => ['title' => 'カード未登録フォロー用の案内文', 'help' => '月謝のカード登録がまだの保護者へ送る文面です。'],
    ];
    foreach ($followup_defs as $type => $def) :
        $message = (string) ($followup_messages[$type] ?? '');
        if ($message === '') {
            continue;
        }
        ?>
    <section
        class="payment-setup-section team-tuition-followup-block"
        data-followup-type="<?php echo esc_attr($type); ?>"
        aria-labelledby="team-tuition-followup-<?php echo esc_attr($type); ?>-heading"
        hidden
    >
        <h2 id="team-tuition-followup-<?php echo esc_attr($type); ?>-heading" class="payment-setup-section__title payment-setup-section__title--icon">
            <span class="payment-setup-section__title-icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('mail', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <?php echo esc_html($def['title']); ?>
        </h2>
        <p class="help-text"><?php echo esc_html($def['help']); ?></p>
        <textarea
            class="team-tuition-collections-followup team-tuition-followup-textarea"
            rows="8"
            readonly
            aria-label="<?php echo esc_attr($def['title']); ?>"
        ><?php echo esc_textarea($message); ?></textarea>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary team-tuition-copy-followup" data-followup-type="<?php echo esc_attr($type); ?>">
                案内文をコピー
            </button>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<p id="team-tuition-copy-feedback" class="team-tuition-collections-copy-feedback" role="status" aria-live="polite" hidden></p>

<?php endif; ?>
