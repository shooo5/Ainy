<?php
/**
 * Template Name: 大会・イベント公開 LP
 *
 * URL 例: /competition-event/?slug=ainy-cup-2026
 *
 * @package AidUnite
 */

$slug = isset($_GET['slug']) ? sanitize_title((string) wp_unslash($_GET['slug'])) : '';
$event_id = 0;
$payload = [];
$error_message = '';

if ($slug !== '') {
    $event_id = aidunite_competition_persist_find_event_id_by_slug($slug);
    if ($event_id > 0) {
        $payload = aidunite_competition_read_public_lp_payload($event_id);
        if (isset($payload['error'])) {
            if ($payload['error'] === 'forbidden') {
                $error_message = 'このイベントは公開されていません。';
            } elseif ($payload['error'] === 'not_available') {
                $error_message = 'このイベントは現在公開されていません。';
            }
            $payload = [];
        }
    }
}

if ($slug === '') {
    $error_message = 'イベント slug が指定されていません（<code>?slug=</code> を付けてください）。';
} elseif ($event_id < 1) {
    $error_message = '指定されたイベントが見つかりません。';
}

if ($event_id > 0 && !empty($payload) && function_exists('aidunite_competition_render_public_lp_og_tags')) {
    aidunite_competition_render_public_lp_og_tags($event_id, is_array($payload['og'] ?? null) ? $payload['og'] : []);
}

if (function_exists('aidunite_competition_public_lp_enqueue_assets')) {
    aidunite_competition_public_lp_enqueue_assets($event_id);
}

get_header();

$date_label = '';
if (!empty($payload['date_start'])) {
    $date_label = date('Y年n月j日', strtotime((string) $payload['date_start']));
    if (!empty($payload['date_end']) && $payload['date_end'] !== $payload['date_start']) {
        $date_label .= ' 〜 ' . date('n月j日', strtotime((string) $payload['date_end']));
    }
}

$venue = is_array($payload['venue'] ?? null) ? $payload['venue'] : [];
$capacity = is_array($payload['capacity'] ?? null) ? $payload['capacity'] : [];
$entry_fee = is_array($payload['entry_fee'] ?? null) ? $payload['entry_fee'] : [];
$marketing = is_array($payload['marketing'] ?? null) ? $payload['marketing'] : [];
$refund_policy = is_array($payload['refund_policy'] ?? null) ? $payload['refund_policy'] : [];
$cta = is_array($payload['cta'] ?? null) ? $payload['cta'] : [];
$tags = is_array($marketing['tags'] ?? null) ? $marketing['tags'] : [];
$refund_lines = is_array($refund_policy['summary_lines'] ?? null) ? $refund_policy['summary_lines'] : [];
?>

<main class="competition-event-public" role="main">
    <?php if ($error_message !== '') : ?>
        <div class="competition-event-public__container">
            <p class="competition-event-public__empty"><?php echo wp_kses_post($error_message); ?></p>
        </div>
    <?php else : ?>
        <?php if (!empty($marketing['cover_image'])) : ?>
            <div class="competition-event-public__hero" style="background-image: url('<?php echo esc_url((string) $marketing['cover_image']); ?>');">
                <div class="competition-event-public__hero-overlay"></div>
            </div>
        <?php endif; ?>

        <div class="competition-event-public__container">
            <header class="competition-event-public__header">
                <?php if (!empty($payload['event_kind_label'])) : ?>
                    <p class="competition-event-public__kind"><?php echo esc_html((string) $payload['event_kind_label']); ?></p>
                <?php endif; ?>
                <h1 class="competition-event-public__title"><?php echo esc_html((string) ($payload['title'] ?? '')); ?></h1>
                <?php if (!empty($marketing['summary'])) : ?>
                    <p class="competition-event-public__summary"><?php echo esc_html((string) $marketing['summary']); ?></p>
                <?php endif; ?>
                <?php if ($tags !== []) : ?>
                    <ul class="competition-event-public__tags" aria-label="タグ">
                        <?php foreach ($tags as $tag) : ?>
                            <li class="competition-event-public__tag"><?php echo esc_html((string) $tag); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </header>

            <section class="competition-event-public__facts" aria-label="イベント概要">
                <?php if ($date_label !== '') : ?>
                    <div class="competition-event-public__fact">
                        <span class="competition-event-public__fact-label">開催日</span>
                        <span class="competition-event-public__fact-value"><?php echo esc_html($date_label); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($venue['name'])) : ?>
                    <div class="competition-event-public__fact">
                        <span class="competition-event-public__fact-label">会場</span>
                        <span class="competition-event-public__fact-value">
                            <?php echo esc_html((string) $venue['name']); ?>
                            <?php if (!empty($venue['address'])) : ?>
                                <span class="competition-event-public__fact-sub"><?php echo esc_html((string) $venue['address']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payload['application_deadline'])) : ?>
                    <div class="competition-event-public__fact">
                        <span class="competition-event-public__fact-label">申込締切</span>
                        <span class="competition-event-public__fact-value"><?php echo esc_html(date('Y年n月j日', strtotime((string) $payload['application_deadline']))); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($capacity['remaining'])) : ?>
                    <div class="competition-event-public__fact">
                        <span class="competition-event-public__fact-label">定員残</span>
                        <span class="competition-event-public__fact-value">
                            <?php
                            $remaining = (int) $capacity['remaining'];
                            echo esc_html($remaining > 0 ? '残り ' . $remaining . ' チーム' : '満員');
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($entry_fee['required'])) : ?>
                    <div class="competition-event-public__fact">
                        <span class="competition-event-public__fact-label">参加費</span>
                        <span class="competition-event-public__fact-value">
                            ¥<?php echo esc_html(number_format((int) ($entry_fee['amount'] ?? 0))); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($refund_lines !== []) : ?>
                <section class="competition-event-public__refund" aria-label="返金ポリシー">
                    <h2 class="competition-event-public__section-title">返金ポリシー</h2>
                    <ul class="competition-event-public__refund-list">
                        <?php foreach ($refund_lines as $line) : ?>
                            <li><?php echo esc_html((string) $line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($refund_policy['notes'])) : ?>
                        <p class="competition-event-public__refund-notes"><?php echo esc_html((string) $refund_policy['notes']); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="competition-event-public__cta" aria-label="参加案内">
                <?php if (empty($payload['recruitment_open'])) : ?>
                    <p class="competition-event-public__cta-closed">現在、新規のお申し込みは受け付けておりません。</p>
                <?php else : ?>
                    <p class="competition-event-public__cta-lead">参加には Ainy アカウントとチーム代表権限が必要です。</p>
                    <div class="competition-event-public__cta-actions">
                        <a class="competition-event-public__btn competition-event-public__btn--primary"
                           href="<?php echo esc_url((string) ($cta['mypage_url'] ?? home_url('/mypage/'))); ?>">
                            マイページで参加する
                        </a>
                        <?php if (!is_user_logged_in() && !empty($cta['login_url'])) : ?>
                            <a class="competition-event-public__btn competition-event-public__btn--secondary"
                               href="<?php echo esc_url((string) $cta['login_url']); ?>">
                                ログイン / 新規登録
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();
