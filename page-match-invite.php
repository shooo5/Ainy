<?php
/**
 * Template Name: 試合招待確認ページ（ゲスト用）
 */

require_once get_template_directory() . '/functions/match/match-invite-functions.php';

// トークンの取得
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

// トークン検証
$token_data = null;
$schedule = null;
$error_message = '';

if ($token) {
    $token_data = aidunite_verify_match_invite_token($token);

    if ($token_data) {
        $schedule = get_post($token_data['schedule_id']);
        if (!$schedule || $schedule->post_type !== 'schedule') {
            $error_message = 'このスケジュールは存在しません';
            $token_data = null;
        }
    } else {
        $error_message = 'この招待リンクは無効または期限切れです（72時間有効）';
    }
}

// OGP（LINEなどのリンクプレビュー用）
$og_title = '試合招待の確認';
$og_description = '試合の招待が届いています。リンクをタップして内容を確認し、承認または拒否してください。';
if ($token_data && $schedule) {
    $sch_inv = function_exists('aidunite_schedule_get_api_display_fields')
        ? aidunite_schedule_get_api_display_fields((int) $schedule->ID)
        : [];
    $schedule_date = (string) ($sch_inv['date'] ?? '');
    $schedule_start = (string) ($sch_inv['start_time'] ?? '');
    $schedule_end = (string) ($sch_inv['end_time'] ?? '');
    $venue_name = (string) ($sch_inv['venue_name'] ?? '');
    $user_id = $token_data['user_id'];
    $team_id = aidunite_user_read_primary_team_id((int) $user_id);
    $team_name = $team_id ? get_the_title($team_id) : '';
    $date_ja = $schedule_date ? date('Y年n月j日', strtotime($schedule_date)) : '';
    $time_ja = ($schedule_start && $schedule_end) ? "{$schedule_start}〜{$schedule_end}" : '';
    $venue_ja = $venue_name ? 'アウェイ @' . $venue_name : '';
    $og_title = '～ 試合招待 ～- ' . ($team_name ? $team_name . 'から' : '');
    $og_description = $date_ja . ($time_ja ? ' ' . $time_ja : '') . 'の試合招待です。' . ($venue_ja ? $venue_ja . '。' : '') . '内容を確認し、承認または拒否をお願いします。';
}
add_action('wp_head', function () use ($og_title, $og_description) {
    $url = get_permalink();
    if (isset($_GET['token'])) {
        $url = home_url('/match-invite?token=' . sanitize_text_field($_GET['token']));
    }
    echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
}, 1);

// CSS読み込み
wp_enqueue_style(
    'match-invite-style',
    get_stylesheet_directory_uri() . '/assets/css/pages/match-invite.css',
    ['aidunite-style', 'button-style', 'form-style'],
    '1.0.0'
);

// JS読み込み
wp_enqueue_script('match-invite-js', get_stylesheet_directory_uri() . '/assets/js/match/match-invite.js', ['jquery'], '1.0.0', true);
wp_localize_script('match-invite-js', 'matchInviteSettings', [
    'nonce' => wp_create_nonce('wp_rest'),
    'root' => home_url('/wp-json/')
]);

get_header();
?>

<div class="match-invite-container">
    <?php if (!$token_data || !$schedule): ?>
        <!-- エラー表示 -->
        <div class="match-invite-card error-card">
            <div class="error-icon">⚠️</div>
            <h1>無効な招待リンク</h1>
            <p><?php echo esc_html($error_message ?: 'この招待リンクは無効または期限切れです'); ?></p>
            <p class="error-note">チーム代表者に新しい招待リンクを依頼してください。</p>
            <a href="<?php echo home_url('/'); ?>" class="btn btn-primary">ホームに戻る</a>
        </div>
    <?php else: ?>
        <!-- 試合情報表示 -->
        <?php
        $sch_inv = function_exists('aidunite_schedule_get_api_display_fields')
            ? aidunite_schedule_get_api_display_fields((int) $schedule->ID)
            : [];
        $schedule_date = (string) ($sch_inv['date'] ?? '');
        $schedule_start = (string) ($sch_inv['start_time'] ?? '');
        $schedule_end = (string) ($sch_inv['end_time'] ?? '');
        $schedule_gender = (string) ($sch_inv['gender'] ?? (function_exists('aidunite_schedule_read_gender_raw')
            ? aidunite_schedule_read_gender_raw((int) $schedule->ID)
            : ''));
        $venue_name = (string) ($sch_inv['venue_name'] ?? '');
        // 招待＝当方ホームなので、相手側にはアウェイ @会場名で表示
        $venue_display = !empty($venue_name) ? 'アウェイ @' . $venue_name : '会場名未設定';

        // 性別を日本語に変換
        $gender_label = '';
        if ($schedule_gender === 'male') {
            $gender_label = '男子';
        } elseif ($schedule_gender === 'female') {
            $gender_label = '女子';
        } elseif ($schedule_gender === 'both') {
            $gender_label = '男子・女子可';
        } else {
            $gender_label = $schedule_gender ?: '未設定';
        }

        // 相手チーム名を取得
        $user_id = $token_data['user_id'];
        $team_id = aidunite_user_read_primary_team_id((int) $user_id);
        $team_name = '';
        if ($team_id) {
            $team_post = get_post($team_id);
            if ($team_post) {
                $team_name = $team_post->post_title;
            }
        }
        ?>

        <div class="match-invite-card">
            <div class="invite-header">
                <h1>～ 試合招待 ～</h1>
                <p class="invite-description">以下の試合内容をご確認の上、<br>承認または拒否をしてください。</p>
            </div>

            <div class="match-info match-info--two-col">
                <div class="info-row">
                    <span class="info-label">日付</span>
                    <span class="info-sep">｜</span>
                    <span class="info-value"><?php echo esc_html($schedule_date ?: '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">時間</span>
                    <span class="info-sep">｜</span>
                    <span class="info-value"><?php echo esc_html(($schedule_start && $schedule_end) ? "{$schedule_start}〜{$schedule_end}" : '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">会場</span>
                    <span class="info-sep">｜</span>
                    <span class="info-value"><?php echo esc_html($venue_display); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">性別</span>
                    <span class="info-sep">｜</span>
                    <span class="info-value"><?php echo esc_html($gender_label); ?></span>
                </div>
                <?php if ($team_name): ?>
                <div class="info-row">
                    <span class="info-label">対戦チーム</span>
                    <span class="info-sep">｜</span>
                    <span class="info-value"><?php echo esc_html($team_name); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <form id="match-invite-approval-form" class="approval-form">
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wp_rest'); ?>">

                <div class="form-group">
                    <label for="school_name">学校名またはクラブ名 <span class="required">*</span></label>
                    <input type="text" id="school_name" name="school_name" required
                           placeholder="例：○○中学校"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="approver_name">お名前 <span class="required">*</span></label>
                    <input type="text" id="approver_name" name="approver_name" required
                           placeholder="例：山田太郎"
                           class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                        ✅ 承認
                    </button>
                    <button type="button" name="action" value="reject" class="btn btn-reject" id="reject-btn">
                        ❌ 拒否
                    </button>
                </div>
            </form>

            <div id="result-message" class="result-message" style="display: none;"></div>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
