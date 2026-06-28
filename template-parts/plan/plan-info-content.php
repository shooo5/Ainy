<?php
/**
 * 料金プラン LP 本体
 *
 * @var array<string, mixed> $vm
 */

if (!defined('ABSPATH')) {
    exit;
}

$vm = is_array($args['vm'] ?? null) ? $args['vm'] : [];
$match = is_array($vm['match_plan'] ?? null) ? $vm['match_plan'] : [];
$club = is_array($vm['club_plan'] ?? null) ? $vm['club_plan'] : [];
$amounts = is_array($vm['amounts'] ?? null) ? $vm['amounts'] : [];
$cta_url = (string) ($vm['payment_setup_url'] ?? home_url('/payment-setup'));
?>

<section class="plan-info-pricing" aria-label="料金プラン比較">
    <div class="plan-info-pricing__grid">
        <article class="plan-info-card plan-info-card--match">
            <p class="plan-info-card__tagline"><?php echo esc_html((string) ($match['tagline'] ?? '')); ?></p>
            <h2 class="plan-info-card__name"><?php echo esc_html((string) ($match['name'] ?? 'Matchプラン')); ?></h2>
            <p class="plan-info-card__description"><?php echo esc_html((string) ($match['description'] ?? '')); ?></p>

            <div class="plan-info-card__price-block">
                <p class="plan-info-card__price">
                    <span class="plan-info-card__price-label">月額</span>
                    <strong>¥<?php echo number_format((int) ($match['price_amount'] ?? 0)); ?></strong>
                    <span class="plan-info-card__price-tax">（税込）</span>
                </p>
                <p class="plan-info-card__trial"><?php echo esc_html((string) ($match['trial_label'] ?? '')); ?></p>
            </div>

            <ul class="plan-info-card__features" role="list">
                <?php foreach ((array) ($match['features'] ?? []) as $feature) : ?>
                <li class="plan-info-card__feature">
                    <span class="plan-info-card__feature-icon" aria-hidden="true">
                        <?php echo aidunite_render_theme_icon((string) ($feature['icon'] ?? 'check_circle'), ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="plan-info-card__feature-body">
                        <strong><?php echo esc_html((string) ($feature['title'] ?? '')); ?></strong>
                        <?php if (!empty($feature['text'])) : ?>
                        <span><?php echo esc_html((string) $feature['text']); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>

            <a class="btn btn-outline plan-info-card__cta" href="<?php echo esc_url($cta_url); ?>">
                <?php echo esc_html((string) ($match['cta_label'] ?? '2ヶ月無料で始める')); ?>
                <?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>
        </article>

        <article class="plan-info-card plan-info-card--club is-recommended">
            <span class="plan-info-card__badge">
                <?php echo aidunite_render_theme_icon('star', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                おすすめ
            </span>
            <p class="plan-info-card__tagline"><?php echo esc_html((string) ($club['tagline'] ?? '')); ?></p>
            <h2 class="plan-info-card__name"><?php echo esc_html((string) ($club['name'] ?? 'Clubプラン')); ?></h2>
            <p class="plan-info-card__description"><?php echo esc_html((string) ($club['description'] ?? '')); ?></p>

            <div class="plan-info-card__price-block plan-info-card__price-block--club">
                <div class="plan-info-card__price-row">
                    <span>月額基本料</span>
                    <strong>¥<?php echo number_format((int) ($club['price_base'] ?? 0)); ?></strong>
                    <span class="plan-info-card__price-tax">（税込）</span>
                </div>
                <div class="plan-info-card__price-plus" aria-hidden="true">＋</div>
                <div class="plan-info-card__price-row">
                    <span>選手1人あたり</span>
                    <strong>¥<?php echo number_format((int) ($club['price_per_player'] ?? 0)); ?></strong>
                    <span class="plan-info-card__price-tax">/人（税込）</span>
                </div>
                <div class="plan-info-card__example">
                    <p class="plan-info-card__example-label"><?php echo esc_html((string) ($club['example_label'] ?? '')); ?></p>
                    <p class="plan-info-card__example-price">
                        月額 <strong>¥<?php echo number_format((int) ($club['example_fee'] ?? 0)); ?></strong>
                        <span class="plan-info-card__price-tax">（税込）</span>
                    </p>
                    <p class="plan-info-card__example-breakdown">
                        内訳: ¥<?php echo number_format((int) ($club['price_base'] ?? 0)); ?>
                        ＋ ¥<?php echo number_format((int) ($club['price_per_player'] ?? 0)); ?>
                        × <?php echo (int) ($club['example_players'] ?? 20); ?>名
                    </p>
                </div>
                <?php if (!empty($club['minimum_note'])) : ?>
                <p class="plan-info-card__minimum"><?php echo esc_html((string) $club['minimum_note']); ?></p>
                <?php endif; ?>
                <p class="plan-info-card__trial"><?php echo esc_html((string) ($club['trial_label'] ?? '')); ?></p>
            </div>

            <ul class="plan-info-card__features plan-info-card__features--grid" role="list">
                <?php foreach ((array) ($club['features'] ?? []) as $feature) : ?>
                <li class="plan-info-card__feature">
                    <span class="plan-info-card__feature-icon" aria-hidden="true">
                        <?php echo aidunite_render_theme_icon((string) ($feature['icon'] ?? 'check_circle'), ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="plan-info-card__feature-body">
                        <strong><?php echo esc_html((string) ($feature['title'] ?? '')); ?></strong>
                        <?php if (!empty($feature['text'])) : ?>
                        <span><?php echo esc_html((string) $feature['text']); ?></span>
                        <?php endif; ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>

            <a class="btn btn-primary plan-info-card__cta plan-info-card__cta--primary" href="<?php echo esc_url($cta_url); ?>">
                <?php echo esc_html((string) ($club['cta_label'] ?? '2ヶ月無料で始める')); ?>
                <?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>
        </article>
    </div>
</section>

<section class="plan-info-upgrade" aria-labelledby="plan-info-upgrade-title">
    <div class="plan-info-upgrade__content">
        <div class="plan-info-upgrade__copy">
            <h2 id="plan-info-upgrade-title" class="plan-info-upgrade__title">
                <?php echo aidunite_render_theme_icon('trophy', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                まずはMatchプランからスタート！
            </h2>
            <p>チームの成長に合わせていつでもClubプランにアップグレードできます。データはそのまま引き継がれます。</p>
        </div>
        <div class="plan-info-upgrade__flow" aria-hidden="true">
            <div class="plan-info-upgrade__step">
                <span class="plan-info-upgrade__step-name">Matchプラン</span>
                <span class="plan-info-upgrade__step-price">¥<?php echo number_format((int) ($amounts['match_amount'] ?? 2000)); ?></span>
                <span class="plan-info-upgrade__step-note">試合マッチングに集中</span>
            </div>
            <span class="plan-info-upgrade__arrow"><?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div class="plan-info-upgrade__step plan-info-upgrade__step--club">
                <span class="plan-info-upgrade__step-name">Clubプラン</span>
                <span class="plan-info-upgrade__step-price">¥<?php echo number_format((int) ($amounts['match_amount'] ?? 2000)); ?> + ¥<?php echo number_format((int) ($amounts['club_per_player'] ?? 500)); ?>/人</span>
                <span class="plan-info-upgrade__step-note">チーム運営を効率化</span>
            </div>
        </div>
    </div>
</section>

<section class="plan-info-security" aria-label="セキュリティについて">
    <p>
        <?php echo aidunite_render_theme_icon('lock', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        すべてのプランでセキュリティは万全です。安心してご利用いただけます。
    </p>
</section>
