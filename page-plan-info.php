<?php
/**
 * Template Name: プラン情報ページ
 */

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';

get_header();

$config = aidunite_get_payment_config();
$plan_display_mode = function_exists('aidunite_get_plan_display_mode') ? aidunite_get_plan_display_mode() : 'coming_soon';
$school_plans = $config['school']['plans'];
$club_plans = $config['club']['plans'];
?>

<div class="page-container">
    <div class="plan-info-container">
        <div class="plan-info-header">
            <h1>プラン情報</h1>
            <p>Aniyシステムのプランをご確認ください</p>
        </div>

        <!-- 学校チーム向けプラン -->
        <section class="plan-section">
            <h2>🏫 学校チーム向けプラン</h2>
            <div class="plan-grid <?php echo $plan_display_mode === 'coming_soon' ? 'plan-grid--coming-soon' : ''; ?>">
                <?php foreach ($school_plans as $plan): ?>
                    <div class="plan-card <?php echo $plan_display_mode === 'coming_soon' ? 'plan-card--coming-soon' : ''; ?>">
                        <?php if ($plan_display_mode === 'coming_soon'): ?>
                        <div class="plan-card-coming-soon-overlay" aria-hidden="true">
                            <span class="plan-card-coming-soon-text">Coming Soon</span>
                            <span class="plan-card-coming-soon-sub">準備中です</span>
                        </div>
                        <?php endif; ?>
                        <h3><?php echo esc_html($plan['name']); ?></h3>
                        <div class="plan-description">
                            <?php echo esc_html($plan['description']); ?>
                        </div>
                        <div class="plan-trial">
                            <?php if ($plan['trial_type'] === 'days'): ?>
                                <span class="trial-badge">トライアル: <?php echo esc_html($plan['trial_value']); ?>日間</span>
                            <?php elseif ($plan['trial_type'] === 'free_months'): ?>
                                <span class="trial-badge">特典: <?php echo esc_html($plan['trial_value']); ?>か月分無料</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($plan['is_default'])): ?>
                            <div class="default-badge">デフォルトプラン</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- クラブチーム向けプラン -->
        <section class="plan-section">
            <h2>🏆 クラブチーム向けプラン</h2>
            <div class="plan-grid <?php echo $plan_display_mode === 'coming_soon' ? 'plan-grid--coming-soon' : ''; ?>">
                <?php foreach ($club_plans as $plan): ?>
                    <div class="plan-card <?php echo $plan_display_mode === 'coming_soon' ? 'plan-card--coming-soon' : ''; ?>">
                        <?php if ($plan_display_mode === 'coming_soon'): ?>
                        <div class="plan-card-coming-soon-overlay" aria-hidden="true">
                            <span class="plan-card-coming-soon-text">Coming Soon</span>
                            <span class="plan-card-coming-soon-sub">準備中です</span>
                        </div>
                        <?php endif; ?>
                        <h3><?php echo esc_html($plan['name']); ?></h3>
                        <div class="plan-description">
                            <?php echo esc_html($plan['description']); ?>
                        </div>
                        <div class="plan-trial">
                            <?php if ($plan['trial_type'] === 'days'): ?>
                                <span class="trial-badge">トライアル: <?php echo esc_html($plan['trial_value']); ?>日間</span>
                            <?php elseif ($plan['trial_type'] === 'free_months'): ?>
                                <span class="trial-badge">特典: <?php echo esc_html($plan['trial_value']); ?>か月分無料</span>
                            <?php endif; ?>
                        </div>
                        <div class="plan-note">
                            <p>※ クラブチームは登録選手数 × 1人あたり金額で計算されます</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 金額情報 -->
        <section class="price-section">
            <h2>💰 料金について</h2>
            <div class="price-info">
                <div class="price-item">
                    <h3>学校チーム</h3>
                    <ul>
                        <li>教育委員会契約: ¥<?php echo number_format($config['school']['board_amount']); ?>/月</li>
                        <li>学校契約: ¥<?php echo number_format($config['school']['school_amount']); ?>/月</li>
                        <li>個人契約: ¥<?php echo number_format($config['school']['personal_amount']); ?>/月</li>
                    </ul>
                </div>
                <div class="price-item">
                    <h3>クラブチーム</h3>
                    <ul>
                        <li>1人あたり: ¥<?php echo number_format($config['club']['base_amount']); ?>/月</li>
                        <li>※ 登録選手数 × 1人あたり金額</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <div class="plan-info-cta">
            <a href="<?php echo home_url('/team-registration'); ?>" class="btn btn-primary btn-large">
                チーム登録を始める
            </a>
        </div>
    </div>
</div>

<style>
.plan-info-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.plan-info-header {
    text-align: center;
    margin-bottom: 3rem;
}

.plan-info-header h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.plan-section {
    margin-bottom: 3rem;
}

.plan-section h2 {
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 0.5rem;
}

.plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.plan-card {
    background: white;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s;
    position: relative;
}

.plan-card:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
    transform: translateY(-4px);
}

/* カミングスーン表示 */
.plan-grid--coming-soon .plan-card {
    pointer-events: none;
}
.plan-card--coming-soon {
    overflow: hidden;
}
.plan-card-coming-soon-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.92);
    z-index: 2;
    border-radius: 10px;
}
.plan-card-coming-soon-text {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
    letter-spacing: 0.05em;
}
.plan-card-coming-soon-sub {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.plan-card h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.plan-description {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.plan-trial {
    margin-bottom: 1rem;
}

.trial-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.9rem;
}

.default-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    background: var(--primary-color);
    color: white;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
}

.plan-note {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.price-section {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 3rem;
}

.price-section h2 {
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.price-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.price-item {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
}

.price-item h3 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.price-item ul {
    list-style: none;
    padding: 0;
}

.price-item li {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-light);
}

.plan-info-cta {
    text-align: center;
    margin-top: 3rem;
}

.btn-large {
    padding: 1rem 3rem;
    font-size: 1.2rem;
}

/* モバイル対応 */
@media (max-width: 768px) {
    .plan-info-container {
        padding: 1rem;
    }

    .plan-info-header h1 {
        font-size: 2rem;
    }

    .plan-grid {
        grid-template-columns: 1fr;
    }

    .price-info {
        grid-template-columns: 1fr;
    }
}
</style>

<?php get_footer(); ?>
