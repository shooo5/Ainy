<?php
/**
 * 管理者決済一覧 — Stripe連携サマリータブ
 *
 * @var array<string, mixed> $stripe_vm
 */

if (!defined('ABSPATH')) {
    exit;
}

$tpl_args = is_array($args ?? null) ? $args : [];
$stripe_vm = is_array($tpl_args['stripe_vm'] ?? null)
    ? $tpl_args['stripe_vm']
    : (is_array($stripe_vm ?? null) ? $stripe_vm : []);
$summary = is_array($stripe_vm['summary'] ?? null) ? $stripe_vm['summary'] : [];
$attention = is_array($stripe_vm['attention'] ?? null) ? $stripe_vm['attention'] : [];
$is_test = !empty($summary['is_test_mode']);
?>

<div class="admin-payment-stripe-summary">
    <p class="admin-payment-list-note">
        Stripe モード:
        <strong><?php echo $is_test ? 'テスト（サンドボックス）' : '本番'; ?></strong>
        — システム料は Ainy 本体アカウント、月謝は各チームの Connect アカウントで管理されます。
    </p>

    <div class="admin-payment-summary-cards" role="list">
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">チーム数</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['team_total'] ?? 0); ?></p>
        </article>
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">システム料 Sub</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['system_subscription_count'] ?? 0); ?></p>
            <p class="admin-payment-summary-card__meta">有料 <?php echo (int) ($summary['system_paid_count'] ?? 0); ?> / トライアル <?php echo (int) ($summary['system_trial_count'] ?? 0); ?></p>
        </article>
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">本体 Customer</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['system_customer_count'] ?? 0); ?></p>
        </article>
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">Connect 連携</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['connect_linked_count'] ?? 0); ?></p>
        </article>
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">月謝 開放チーム</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['tuition_open_count'] ?? 0); ?></p>
        </article>
        <article class="admin-payment-summary-card" role="listitem">
            <h3 class="admin-payment-summary-card__title">月謝 登録保護者</h3>
            <p class="admin-payment-summary-card__value"><?php echo (int) ($summary['tuition_parent_registered_count'] ?? 0); ?></p>
        </article>
    </div>

    <?php
    $attention_sections = [
        'club_no_connect' => [
            'title' => 'Clubプランだが Connect 未連携',
            'description' => '月謝徴収の前提（Connect）が未設定のチームです。',
        ],
        'tuition_enabled_no_connect' => [
            'title' => '月謝 ON だが Connect 未連携',
            'description' => '月謝設定は有効ですが、Connect アカウントがありません。',
        ],
        'subscription_no_customer' => [
            'title' => 'Sub あり・Customer なし',
            'description' => '代表者の Stripe Customer ID が未保存の可能性があります。',
        ],
    ];
    ?>

    <?php foreach ($attention_sections as $key => $section) :
        $rows = is_array($attention[$key] ?? null) ? $attention[$key] : [];
        if ($rows === []) {
            continue;
        }
        ?>
    <section class="admin-payment-attention-section" aria-labelledby="apl-attention-<?php echo esc_attr($key); ?>">
        <h2 id="apl-attention-<?php echo esc_attr($key); ?>" class="admin-payment-attention-section__title"><?php echo esc_html((string) $section['title']); ?></h2>
        <p class="admin-payment-list-note"><?php echo esc_html((string) $section['description']); ?></p>
        <div class="admin-payment-list-table-wrap">
            <table class="admin-payment-table wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>チーム</th>
                        <th>代表者</th>
                        <th>プラン</th>
                        <th>契約状態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url((string) ($row['team_settings_url'] ?? '')); ?>"><?php echo esc_html((string) ($row['team_name'] ?? '')); ?></a>
                        </td>
                        <td><?php echo esc_html((string) ($row['leader_name'] ?? '—')); ?></td>
                        <td><?php echo esc_html((string) ($row['plan_label'] ?? '—')); ?></td>
                        <td>
                            <span class="admin-payment-status-badge admin-payment-status-badge--<?php echo esc_attr((string) ($row['contract_modifier'] ?? 'inactive')); ?>">
                                <?php echo esc_html((string) ($row['contract_label'] ?? '—')); ?>
                            </span>
                        </td>
                        <td class="admin-payment-list-actions">
                            <a href="<?php echo esc_url((string) ($row['team_payment_url'] ?? home_url('/team-payment-management'))); ?>">月謝設定</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <?php
    $has_attention = false;
    foreach ($attention_sections as $key => $_section) {
        if (!empty($attention[$key])) {
            $has_attention = true;
            break;
        }
    }
    if (!$has_attention) :
        ?>
    <p class="admin-payment-list-empty-note">要確認の連携不整合はありません。</p>
    <?php endif; ?>
</div>
