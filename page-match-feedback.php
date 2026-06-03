<?php
/**
 * Template Name: マッチアンケート
 * マッチ成立後のアンケート回答ページ
 */

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
$managed_team_ids = function_exists('aidunite_get_managed_team_ids')
    ? aidunite_get_managed_team_ids((int) $current_user_id)
    : [];
if (empty($managed_team_ids)) {
    $lt = (int) get_user_meta($current_user_id, 'team_id', true);
    if ($lt > 0) {
        $managed_team_ids = [$lt];
    }
}
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

if (!$match_id) {
    echo '<div class="container">';
    echo '<p>マッチIDが指定されていません。</p>';
    echo '<p><a href="' . esc_url(home_url('/mypage')) . '" class="button">マイページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}

// マッチ情報を取得
$match_request = get_post($match_id);
if (!$match_request || $match_request->post_type !== 'match_request') {
    echo '<div class="container">';
    echo '<p>マッチ情報が見つかりません。</p>';
    echo '<p><a href="' . esc_url(home_url('/mypage')) . '" class="button">マイページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}

$from_team_id = (int) get_post_meta($match_id, 'from_team_id', true);
$to_team_id = (int) get_post_meta($match_id, 'to_team_id', true);
$status = get_post_meta($match_id, 'status', true);

$in_from = $from_team_id > 0 && in_array($from_team_id, $managed_team_ids, true);
$in_to = $to_team_id > 0 && in_array($to_team_id, $managed_team_ids, true);

// 自分の managed いずれかが参加しているマッチか確認
if (!$in_from && !$in_to) {
    echo '<div class="container">';
    echo '<p>このマッチにアクセスする権限がありません。</p>';
    echo '<p><a href="' . esc_url(home_url('/mypage')) . '" class="button">マイページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}

// 操作中チームがマッチ側にあれば優先、なければ from / to のどちらか（managed 側）
$current_user_team_id = 0;
if (function_exists('aidunite_get_current_team_id')) {
    $cur = (int) aidunite_get_current_team_id((int) $current_user_id);
    if ($cur && (($cur === $from_team_id && $in_from) || ($cur === $to_team_id && $in_to))) {
        $current_user_team_id = $cur;
    }
}
if ($current_user_team_id <= 0) {
    $current_user_team_id = $in_from ? $from_team_id : ($in_to ? $to_team_id : 0);
}

// 相手チーム情報を取得
$opponent_team_id = ($current_user_team_id === $from_team_id) ? $to_team_id : $from_team_id;
$opponent_team_name = $opponent_team_id ? get_the_title($opponent_team_id) : '（不明）';

// スケジュール情報を取得
$schedule_id = get_post_meta($match_id, 'to_schedule_id', true);
if (!$schedule_id) {
    $schedule_id = get_post_meta($match_id, 'from_schedule_id', true);
}

$schedule_date = $schedule_id ? get_post_meta($schedule_id, 'schedule_date', true) : '';
$schedule_start = $schedule_id ? get_post_meta($schedule_id, 'schedule_start_time', true) : '';
$schedule_end = $schedule_id ? get_post_meta($schedule_id, 'schedule_end_time', true) : '';
$venue_display = '';
if ($schedule_id) {
    $venue_name = trim((string) get_post_meta($schedule_id, 'venue_name', true));
    if ($venue_name !== '') {
        $venue_display = $venue_name;
    } else {
        $place_raw = get_post_meta($schedule_id, 'schedule_place', true) ?: get_post_meta($schedule_id, 'schedule_place_option', true);
        if ($place_raw && function_exists('aidunite_jp_place')) {
            $venue_display = aidunite_jp_place($place_raw);
        } elseif ($place_raw) {
            $venue_display = (string) $place_raw;
        }
    }
}

// 既に回答済みかチェック
$fb_team_clause = [
    'key' => 'team_id',
    'value' => $managed_team_ids,
    'compare' => 'IN',
];
if (count($managed_team_ids) === 1) {
    $fb_team_clause = [
        'key' => 'team_id',
        'value' => (int) $managed_team_ids[0],
        'compare' => '=',
    ];
} elseif (empty($managed_team_ids)) {
    $fb_team_clause = [
        'key' => 'team_id',
        'value' => 0,
        'compare' => '=',
    ];
}
$existing_feedback = get_posts([
    'post_type' => 'match_feedback',
    'posts_per_page' => 1,
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'match_id',
            'value' => $match_id,
            'compare' => '='
        ],
        $fb_team_clause,
    ]
]);

$is_already_answered = !empty($existing_feedback);
$show_submitted_thanks = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$show_completed_state = $is_already_answered || $show_submitted_thanks;

$post_match_module_context = function_exists('aidunite_build_post_match_module_context')
    ? aidunite_build_post_match_module_context($current_user_id, $current_user_team_id, $match_id)
    : [];
$slot_modules_unanswered = 'after_match_feedback';
$slot_modules_answered = 'after_match_feedback_answered';

$footer_module_slot = ($is_already_answered || $show_submitted_thanks)
    ? $slot_modules_answered
    : $slot_modules_unanswered;

add_action('wp_footer', static function () use ($current_user_team_id, $match_id, $footer_module_slot) {
    if (!wp_script_is('aidunite-post-match-modules', 'enqueued')) {
        return;
    }
    wp_add_inline_script(
        'aidunite-post-match-modules',
        'window.aidunitePostMatchModules=window.aidunitePostMatchModules||{};' .
        'aidunitePostMatchModules.teamId=' . (int) $current_user_team_id . ';' .
        'aidunitePostMatchModules.matchId=' . (int) $match_id . ';' .
        'aidunitePostMatchModules.slot=' . wp_json_encode($footer_module_slot) . ';',
        'before'
    );
}, 20);
?>

<div class="team-dashboard-container page-match-feedback mf-experience-page">
    <header class="mf-hero<?php echo $show_completed_state ? ' mf-hero--completed' : ''; ?>">
        <a href="<?php echo esc_url(home_url('/mypage')); ?>" class="mf-hero__back">マイページに戻る</a>
        <span class="mf-hero__decoration mf-hero__decoration--hoop" aria-hidden="true">🏀</span>
        <span class="mf-hero__decoration mf-hero__decoration--ball" aria-hidden="true">🏀</span>
        <div class="mf-hero__inner">
            <h1 class="mf-hero__title">試合おつかれさまでした！</h1>
            <?php if (!$show_completed_state) : ?>
            <p class="mf-hero__lead">3分で終わる簡単な振り返りに<br>ご協力をお願いします ✍️</p>
            <?php endif; ?>
            <?php
            if (function_exists('aidunite_render_match_feedback_match_summary')) {
                echo aidunite_render_match_feedback_match_summary([
                    'schedule_date'      => $schedule_date,
                    'schedule_start'     => $schedule_start,
                    'schedule_end'       => $schedule_end,
                    'opponent_team_name' => $opponent_team_name,
                    'venue_display'      => $venue_display,
                ]);
            }
            ?>
        </div>
    </header>

    <div class="content-section mf-content">
        <?php if ($is_already_answered || $show_submitted_thanks) : ?>
            <?php if ($show_submitted_thanks && $is_already_answered) : ?>
            <div class="post-match-feedback-thanks" data-testid="survey-submit-thanks" role="status">
                <p><strong>アンケートを送信しました。</strong></p>
                <p>いただいた内容は、今後の試合のマッチング改善に活用します。</p>
                <div class="post-match-feedback-thanks__actions">
                    <a href="<?php echo esc_url(home_url('/match-board-own')); ?>" class="btn btn-primary">試合掲示板へ</a>
                    <a href="<?php echo esc_url(home_url('/mypage')); ?>" class="btn btn-secondary">マイページへ</a>
                </div>
            </div>
            <?php elseif ($is_already_answered) : ?>
            <div class="alert alert-info" data-testid="survey-already-answered">
                <p><?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> このマッチのアンケートは既に回答済みです。</p>
            </div>
            <?php endif; ?>
            <?php if (!$show_submitted_thanks) : ?>
            <p><a href="<?php echo esc_url(home_url('/mypage')); ?>" class="btn btn-primary">マイページに戻る</a></p>
            <?php endif; ?>
            <?php
            if (!empty($post_match_module_context) && function_exists('aidunite_render_post_match_modules')) {
                aidunite_render_post_match_modules($slot_modules_answered, $post_match_module_context);
            }
            ?>
        <?php else : ?>
            <?php
            $redirect_success = add_query_arg(['match_id' => $match_id, 'submitted' => '1'], home_url('/match-feedback'));
            ?>
            <form
                id="match-feedback-form"
                class="feedback-form"
                data-testid="match-feedback-form"
                data-opponent-team-id="<?php echo $opponent_team_id && (int) $opponent_team_id !== (int) $current_user_team_id ? (int) $opponent_team_id : ''; ?>"
                data-redirect-success="<?php echo esc_url($redirect_success); ?>"
            >
                <?php
                if (function_exists('aidunite_render_match_feedback_survey_fields')) {
                    echo aidunite_render_match_feedback_survey_fields([
                        'mode'                    => 'live',
                        'match_id'                => $match_id,
                        'team_id'                 => $current_user_team_id,
                        'current_user_id'         => $current_user_id,
                        'opponent_team_id'        => $opponent_team_id,
                        'opponent_team_name'      => $opponent_team_name,
                        'schedule_date'           => $schedule_date,
                        'schedule_start'          => $schedule_start,
                        'schedule_end'            => $schedule_end,
                        'venue_display'           => $venue_display,
                        'favorite_checked'        => function_exists('aidunite_is_favorite_team')
                            && aidunite_is_favorite_team($current_user_id, $opponent_team_id),
                        'show_post_match_modules' => !empty($post_match_module_context),
                        'post_match_slot'         => $slot_modules_unanswered,
                        'post_match_context'      => $post_match_module_context,
                    ]);
                }
                ?>

                <div class="mf-submit-row">
                    <a href="<?php echo esc_url(home_url('/mypage')); ?>" class="mf-btn-later">あとで回答する</a>
                    <button type="submit" class="mf-btn-submit" data-testid="survey-submit-button">
                        <span class="mf-btn-submit__main"><?php echo aidunite_render_theme_icon('send', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 送信する</span>
                        <small class="mf-btn-submit__sub">ご協力ありがとうございました！</small>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
