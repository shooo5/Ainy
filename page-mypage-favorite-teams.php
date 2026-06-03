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

<style>
.favorite-teams-container {
    max-width: 800px;
    margin: 0 auto;
}
body:not(.web-app-integrated-ui) .page-mypage-favorite-teams {
    background: var(--bg-light);
    min-height: 100vh;
    padding: var(--spacing-lg) var(--spacing-md);
    padding-bottom: calc(80px + env(safe-area-inset-bottom, 0) + var(--spacing-lg));
}
body:not(.web-app-integrated-ui) .favorite-teams-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}
body:not(.web-app-integrated-ui) .favorite-teams-title {
    margin: 0;
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--text-primary);
}
.favorite-teams-empty {
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-base);
    padding: var(--spacing-xl);
    text-align: center;
}
.favorite-teams-empty__text {
    margin: 0 0 var(--spacing-xs);
    font-size: var(--font-size-base);
    color: var(--text-primary);
}
.favorite-teams-empty__sub {
    margin: 0 0 var(--spacing-md);
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
}
.favorite-teams-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.favorite-teams-item {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-base);
    margin-bottom: var(--spacing-sm);
}
.favorite-teams-item__name {
    font-weight: 600;
    color: var(--text-primary);
}
.favorite-teams-item__name--link {
    text-decoration: none;
}
.favorite-teams-item__name--link:hover {
    color: var(--primary-color);
    text-decoration: underline;
}
.favorite-teams-item__name--link:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    border-radius: var(--radius-small);
}
.favorite-teams-item__actions {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}
.favorite-teams-item__actions .btn-sm {
    padding: var(--spacing-xs) var(--spacing-sm);
    font-size: var(--font-size-sm);
}
.favorite-team-btn--remove {
    color: var(--text-secondary);
    border-color: var(--border-light);
}
.favorite-team-btn--remove:hover {
    color: var(--danger-color, #dc3545);
    border-color: var(--danger-color, #dc3545);
}
</style>

<script>
(function() {
    document.querySelectorAll('.favorite-team-btn--remove').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var teamId = btn.getAttribute('data-team-id');
            if (!teamId || !window.aidunite_favorite_teams) return;
            var url = window.aidunite_favorite_teams.rest_url.replace(/\/$/, '') + '/aidunite/v1/favorite-teams/toggle';
            btn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aidunite_favorite_teams.nonce },
                body: JSON.stringify({ team_id: parseInt(teamId, 10) }),
                credentials: 'same-origin'
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success && data.added === false) {
                    var li = btn.closest('.favorite-teams-item');
                    if (li) li.remove();
                    var list = document.querySelector('.favorite-teams-list');
                    if (list && !list.querySelector('.favorite-teams-item')) {
                        location.reload();
                    }
                }
            }).finally(function() { btn.disabled = false; });
        });
    });
})();
</script>

<?php get_footer(); ?>
