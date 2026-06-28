<?php
/**
 * Template Name: お気に入りチーム一覧
 * チーム代表者向け：お気に入り登録したチームの一覧
 */

$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = $auth_result->user_id;
$favorites = function_exists('aidunite_get_favorite_teams_with_names') ? aidunite_get_favorite_teams_with_names($current_user_id) : [];

get_header();

$favorites_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-mypage-favorite-teams',
        'title' => 'お気に入りチーム',
    ]);
    $favorites_shell_opened = true;
} else {
    echo '<main class="page-mypage-favorite-teams"><div class="favorite-teams-container">';
    echo '<header class="favorite-teams-header"><h1 class="favorite-teams-title">⭐ お気に入りチーム</h1>';
    echo '<a href="' . esc_url(home_url('/mypage')) . '" class="btn btn-secondary">← マイページに戻る</a></header>';
}
?>
    <div class="favorite-teams-container">

        <?php if (empty($favorites)) : ?>
        <div class="favorite-teams-empty" role="status">
            <p class="favorite-teams-empty__text">お気に入りチームはまだありません。</p>
            <p class="favorite-teams-empty__sub">試合掲示板やマッチ詳細から「☆ お気に入りに追加」で登録できます。</p>
            <a href="<?php echo esc_url(home_url('/match-board-own')); ?>" class="btn btn-primary">試合掲示板へ</a>
        </div>
        <?php else : ?>
        <ul class="favorite-teams-list">
            <?php foreach ($favorites as $fav) : ?>
            <li class="favorite-teams-item">
                <?php if (!empty($fav['profile_url'])) : ?>
                <a href="<?php echo esc_url($fav['profile_url']); ?>" class="favorite-teams-item__name favorite-teams-item__name--link"><?php echo esc_html($fav['name']); ?></a>
                <?php else : ?>
                <span class="favorite-teams-item__name"><?php echo esc_html($fav['name']); ?></span>
                <?php endif; ?>
                <div class="favorite-teams-item__actions">
                    <?php if (!empty($fav['profile_url'])) : ?>
                    <a href="<?php echo esc_url($fav['profile_url']); ?>" class="btn btn-secondary btn-sm">公開プロフィール</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/match-board-own')); ?>" class="btn btn-primary btn-sm">試合を探す</a>
                    <button type="button" class="favorite-team-btn favorite-team-btn--remove" data-team-id="<?php echo (int) $fav['id']; ?>" data-favorite="1" aria-label="お気に入りから削除">★ 削除</button>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

<?php
if ($favorites_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div></main>';
}
?>

<?php get_footer(); ?>
