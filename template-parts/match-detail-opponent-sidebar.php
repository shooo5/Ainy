<?php
/**
 * マッチ詳細：相手チームサイドバー（プロフィールカード群）
 *
 * 期待する変数: $other_team_id, $other_team_data, $my_team_id, $my_team_data,
 * $match_detail_venue_label (optional), $match_detail_common_context (optional)
 *
 * @package AidUnite
 */
if (!defined('ABSPATH')) {
    exit;
}

$other_team_id = isset($other_team_id) ? (int) $other_team_id : 0;
$other_team_data = isset($other_team_data) && is_array($other_team_data) ? $other_team_data : [];
$my_team_id = isset($my_team_id) ? (int) $my_team_id : 0;
$my_team_data = isset($my_team_data) && is_array($my_team_data) ? $my_team_data : [];
$venue_label = isset($match_detail_venue_label) ? (string) $match_detail_venue_label : '';
$common_context = isset($match_detail_common_context) && is_array($match_detail_common_context)
    ? $match_detail_common_context
    : [];

$team_name = (string) ($other_team_data['name'] ?? '—');
$team_logo = (string) ($other_team_data['logo'] ?? '');
$category = trim((string) ($other_team_data['category'] ?? ''));
$region = trim((string) ($other_team_data['region'] ?? ''));
$description = trim((string) ($other_team_data['description'] ?? ''));
$achievements = trim((string) ($other_team_data['achievements'] ?? ''));

$profile_url = ($other_team_id > 0 && function_exists('aidunite_get_team_public_profile_url'))
    ? aidunite_get_team_public_profile_url($other_team_id)
    : '';

$stats = ($other_team_id > 0 && function_exists('aidunite_match_detail_opponent_match_stats'))
    ? aidunite_match_detail_opponent_match_stats($other_team_id)
    : ['total' => 0, 'recent' => 0];

$activity_lines = function_exists('aidunite_match_detail_opponent_activity_lines')
    ? aidunite_match_detail_opponent_activity_lines($other_team_id, $other_team_data, $venue_label)
    : [];

$common_points = function_exists('aidunite_match_detail_common_points')
    ? aidunite_match_detail_common_points($my_team_id, $other_team_id, $my_team_data, $other_team_data, $common_context)
    : [];

$intro_long = $description !== '' && (function_exists('mb_strlen') ? mb_strlen($description) : strlen($description)) > 120;
$intro_short = $description;
if ($intro_long && function_exists('mb_substr')) {
    $intro_short = mb_substr($description, 0, 120, 'UTF-8') . '…';
} elseif ($intro_long) {
    $intro_short = substr($description, 0, 120) . '…';
}

$sidebar_intro_id = 'matchDetailOpponentIntro-' . (int) $other_team_id;
?>
<div class="match-detail-sidebar-inner">
    <!-- ① チームプロフィール -->
    <section class="card match-detail-opponent-profile" aria-labelledby="match-detail-opponent-profile-title">
        <div class="card-body">
            <div class="match-detail-opponent-profile__head">
                <?php if ($team_logo !== '') : ?>
                <img class="match-detail-opponent-profile__avatar" src="<?php echo esc_url($team_logo); ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
                <?php else : ?>
                <span class="match-detail-opponent-profile__avatar match-detail-opponent-profile__avatar--initial" aria-hidden="true"><?php echo esc_html(function_exists('aidunite_match_detail_team_avatar_initial') ? aidunite_match_detail_team_avatar_initial($team_name) : '?'); ?></span>
                <?php endif; ?>
                <div class="match-detail-opponent-profile__identity">
                    <?php if ($profile_url !== '') : ?>
                    <a class="match-detail-opponent-profile__name" href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html($team_name); ?></a>
                    <?php else : ?>
                    <p class="match-detail-opponent-profile__name"><?php echo esc_html($team_name); ?></p>
                    <?php endif; ?>
                    <?php if ($category !== '' && $category !== 'カテゴリ未設定') : ?>
                    <p class="match-detail-opponent-profile__meta"><?php echo esc_html($category); ?></p>
                    <?php endif; ?>
                    <?php if ($region !== '' && $region !== '地域未設定') : ?>
                    <p class="match-detail-opponent-profile__meta"><?php echo esc_html($region); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($description !== '') : ?>
            <div class="match-detail-opponent-profile__intro">
                <p class="match-detail-opponent-profile__intro-text" id="<?php echo esc_attr($sidebar_intro_id); ?>-short"><?php echo esc_html($intro_short); ?></p>
                <?php if ($intro_long) : ?>
                <p class="match-detail-opponent-profile__intro-text match-detail-opponent-profile__intro-text--full" id="<?php echo esc_attr($sidebar_intro_id); ?>-full" hidden><?php echo esc_html($description); ?></p>
                <button type="button" class="match-other-only-team-toggle match-detail-opponent-profile__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($sidebar_intro_id); ?>-full"
                    onclick="var s=document.getElementById('<?php echo esc_js($sidebar_intro_id); ?>-short'); var f=document.getElementById('<?php echo esc_js($sidebar_intro_id); ?>-full'); var o=f.hidden; f.hidden=!o; s.hidden=o; this.setAttribute('aria-expanded', o?'true':'false'); this.textContent=o?'紹介文を隠す':'紹介文をすべて表示';">
                    紹介文をすべて表示
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($activity_lines)) : ?>
    <!-- ② 活動情報 -->
    <section class="card match-detail-opponent-activity" aria-labelledby="match-detail-opponent-activity-title">
        <div class="card-header">
            <h2 id="match-detail-opponent-activity-title" class="card-title">活動情報</h2>
        </div>
        <div class="card-body">
            <ul class="match-detail-opponent-list">
                <?php foreach ($activity_lines as $line) : ?>
                <li><?php echo esc_html($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($stats['total'])) : ?>
    <!-- ③ 対戦実績 -->
    <section class="card match-detail-opponent-stats" aria-labelledby="match-detail-opponent-stats-title">
        <div class="card-header">
            <h2 id="match-detail-opponent-stats-title" class="card-title">対戦実績</h2>
        </div>
        <div class="card-body match-detail-opponent-stats__body">
            <p class="match-detail-opponent-stats__line"><strong>成立試合</strong> <?php echo esc_html((string) (int) $stats['total']); ?>件</p>
            <?php if (!empty($stats['recent'])) : ?>
            <p class="match-detail-opponent-stats__line match-detail-opponent-stats__line--sub">最近1ヶ月で <?php echo esc_html((string) (int) $stats['recent']); ?>試合</p>
            <?php endif; ?>
            <?php if ($achievements !== '') : ?>
            <p class="match-detail-opponent-stats__note"><?php echo esc_html($achievements); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php elseif ($achievements !== '') : ?>
    <section class="card match-detail-opponent-stats" aria-labelledby="match-detail-opponent-stats-title">
        <div class="card-header">
            <h2 id="match-detail-opponent-stats-title" class="card-title">チーム実績</h2>
        </div>
        <div class="card-body">
            <p class="match-detail-opponent-stats__note"><?php echo esc_html($achievements); ?></p>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($common_points)) : ?>
    <!-- ④ 共通点 -->
    <section class="card match-detail-opponent-common" aria-labelledby="match-detail-opponent-common-title">
        <div class="card-header">
            <h2 id="match-detail-opponent-common-title" class="card-title">共通点</h2>
        </div>
        <div class="card-body">
            <div class="match-detail-opponent-chips" role="list">
                <?php foreach ($common_points as $point) : ?>
                <span class="badge match-detail-opponent-chip" role="listitem"><?php echo esc_html($point); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
