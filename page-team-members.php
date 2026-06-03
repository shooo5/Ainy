<?php
/**
 * Template Name: チームメンバー一覧
 */

// 統一認証・権限チェック（チーム代表者のみ）
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$current_user_id = $auth_result->user_id;
$user_info = aidunite_get_user_info($current_user_id);
$effective_role = $user_info['user_type'] ?? 'general';

// チームID取得
// - 管理者: URLクエリ `team_id` を優先
// - チームリーダー: 自身の所属チーム
$is_admin = isset($auth_result->user_role) && $auth_result->user_role === 'administrator';
$team_id = $is_admin ? intval($_GET['team_id'] ?? 0) : (intval($user_info['team_id'] ?? 0));
if (!$team_id) {
    wp_die('チーム情報が見つかりません。');
}

// チーム情報取得
$team_post = get_post($team_id);
$team_name = $team_post ? $team_post->post_title : 'チーム';
$team_name_meta = get_post_meta($team_id, 'team_name', true);
if ($team_name_meta) {
    $team_name = $team_name_meta;
}

// 選手削除処理
if (isset($_POST['delete_player']) && isset($_POST['player_id']) && wp_verify_nonce($_POST['delete_player_nonce'], 'delete_player_' . $_POST['player_id'])) {
    $player_user_id = intval($_POST['player_id']);

    // 自分のチームの選手か確認
    $player_team_id = get_user_meta($player_user_id, 'team_id', true);
    if ($player_team_id == $team_id) {
        // ユーザーを削除
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($player_user_id);

        $delete_message = '<div class="notice notice-success"><p>選手を削除しました。</p></div>';
    } else {
        $delete_message = '<div class="notice notice-error"><p>この選手を削除する権限がありません。</p></div>';
    }
}

// 保護者削除処理
if (isset($_POST['delete_parent']) && isset($_POST['parent_id']) && wp_verify_nonce($_POST['delete_parent_nonce'], 'delete_parent_' . $_POST['parent_id'])) {
    $parent_user_id = intval($_POST['parent_id']);

    // 自分のチームの保護者か確認
    $parent_team_id = get_user_meta($parent_user_id, 'team_id', true);
    if ($parent_team_id == $team_id) {
        // ユーザーを削除
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($parent_user_id);

        $delete_message = '<div class="notice notice-success"><p>保護者を削除しました。</p></div>';
    } else {
        $delete_message = '<div class="notice notice-error"><p>この保護者を削除する権限がありません。</p></div>';
    }
}

// 選手・保護者データ取得
$players = aidunite_get_team_members($team_id, 'player');
$parents = aidunite_get_team_members($team_id, 'parent');

get_header();

$team_members_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-team-members',
        'title' => 'メンバー一覧',
        'subtitle' => '所属チーム: ' . $team_name,
        'actions' => [
            [
                'type' => 'link',
                'href' => home_url('/player-add'),
                'icon' => '＋',
                'aria' => '選手を新規登録',
            ],
        ],
    ]);
    $team_members_shell_opened = true;
}
?>

<style>
/* チームメンバー一覧ページ専用スタイル（一体型UI時は web-app-integrated-ui.css に委譲） */
body:not(.web-app-integrated-ui) .page-team-members {
    background: var(--bg-light);
    min-height: 100vh;
    padding: 2rem 0;
}

.team-members-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.page-header h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
}

.page-header .team-name {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.page-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.members-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
}


.members-section h2 {
    margin: 0 0 1.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.members-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.members-table thead {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
}

.members-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-light);
}

.members-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
}

.members-table tbody tr:hover {
    background: var(--bg-secondary);
}

.members-table .actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-small {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-edit {
    background: #17a2b8;
    color: white;
}

.btn-edit:hover {
    background: var(--info-color);
    transform: translateY(-2px);
}

.btn-delete {
    background: #dc3545;
    color: white;
}

.btn-delete:hover {
    background: var(--danger-color);
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.notice {
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid;
}

.notice-success {
    background: rgba(40, 167, 69, 0.1);
    border-left-color: var(--success-color);
    color: var(--success-color);
}

.notice-error {
    background: rgba(220, 53, 69, 0.1);
    border-left-color: var(--danger-color);
    color: var(--danger-color);
}

@media (max-width: 768px) {
    .members-table {
        font-size: 0.875rem;
    }

    .members-table th,
    .members-table td {
        padding: 0.75rem 0.5rem;
    }

    .members-table .actions {
        flex-direction: column;
    }
}
</style>

<?php if (!$team_members_shell_opened) : ?>
<div class="team-dashboard-container page-team-members">
<?php endif; ?>
    <div class="team-members-container">
        <?php if (isset($delete_message)) : ?>
            <?php echo $delete_message; ?>
        <?php endif; ?>

        <!-- メンバー一覧（選手と保護者を紐付け） -->
        <div class="members-section">
            <h2><?php echo aidunite_render_theme_icon('group', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> メンバー一覧</h2>

            <?php if (!empty($players)) : ?>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>選手名</th>
                        <th>ニックネーム</th>
                        <th>学年</th>
                        <th>ポジション</th>
                        <th>身長</th>
                        <th>保護者</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player) :
                        $player_id = is_object($player) ? $player->ID : ($player['user_id'] ?? 0);
                        if (!$player_id) continue;

                        // 選手情報取得
                        $player_name_sei = get_user_meta($player_id, 'player_name_sei', true);
                        $player_name_mei = get_user_meta($player_id, 'player_name_mei', true);
                        $player_name = trim(($player_name_sei ?: '') . ' ' . ($player_name_mei ?: ''));
                        if (empty($player_name)) {
                            $player_name = get_user_meta($player_id, 'player_name', true) ?: '未設定';
                        }
                        $player_nickname = get_user_meta($player_id, 'player_nickname', true);
                        $player_birth_date = get_user_meta($player_id, 'player_birth_date', true);
                        $player_grade = get_user_meta($player_id, 'player_grade', true);
                        $player_position = get_user_meta($player_id, 'player_position', true);
                        $player_height = get_user_meta($player_id, 'player_height', true);
                        // 後方互換性のため、旧キーも確認
                        if (empty($player_height)) {
                            $player_height = get_user_meta($player_id, 'height', true);
                        }

                        // デバッグ用（本番環境では削除）
                        // error_log("Player ID: {$player_id}, Height: " . var_export($player_height, true));

                        // 生年月日から学年を計算（未設定の場合）
                        if (empty($player_grade) && !empty($player_birth_date)) {
                            $birth = new DateTime($player_birth_date);
                            $today = new DateTime();
                            $age = $today->diff($birth)->y;
                            // 簡易的な学年計算（4月1日基準）
                            $month = (int)$today->format('m');
                            $player_grade = $month >= 4 ? ($age - 6) : ($age - 7);
                            if ($player_grade < 1) $player_grade = '未就学';
                            elseif ($player_grade > 12) $player_grade = '卒業';
                            else $player_grade = $player_grade . '年生';
                        }

                        // 保護者情報取得（選手のユーザーメタから取得）
                        $parent_name_sei = get_user_meta($player_id, 'parent_name_sei', true);
                        $parent_name_mei = get_user_meta($player_id, 'parent_name_mei', true);
                        $parent_name = trim(($parent_name_sei ?: '') . ' ' . ($parent_name_mei ?: ''));
                        if (empty($parent_name)) {
                            $parent_name = get_user_meta($player_id, 'parent_name', true) ?: '';
                        }

                        // 保護者アカウントが存在する場合、その情報も確認
                        if (empty($parent_name)) {
                            $parent = aidunite_get_player_parent($player_id);
                            if ($parent) {
                                $parent_name = $parent->display_name;
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo esc_html($player_name); ?></td>
                        <td><?php echo esc_html($player_nickname ?: '-'); ?></td>
                        <td><?php echo esc_html($player_grade ?: '-'); ?></td>
                        <td><?php echo esc_html($player_position ?: '-'); ?></td>
                        <td><?php echo esc_html($player_height ? $player_height . 'cm' : '-'); ?></td>
                        <td><?php echo esc_html($parent_name ?: '-'); ?></td>
                        <td class="actions">
                            <a href="<?php echo home_url('/edit-player?player_id=' . $player_id); ?>" class="btn-small btn-edit">
                                <?php echo aidunite_render_theme_icon('stylus', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 編集
                            </a>
                            <button type="button" class="btn-small btn-delete delete-player-btn" data-player-id="<?php echo esc_attr($player_id); ?>" data-nonce="<?php echo wp_create_nonce('delete_player_' . $player_id); ?>">
                                <?php echo aidunite_render_theme_icon('delete_forever', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 削除
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <div class="empty-state">
                <div class="empty-state-icon"><?php echo aidunite_render_theme_icon('person_man', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <p>登録されている選手はいません。</p>
                <a href="<?php echo home_url('/player-add'); ?>" class="btn btn-primary" style="margin-top: 1rem;">
                    <?php echo aidunite_render_theme_icon('add', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 選手を新規登録
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php
if ($team_members_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<style>
/* トースト通知スタイル（中央表示） */
.player-delete-toast {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 100000;
    pointer-events: auto;
    animation: toastFadeIn 0.3s ease-out;
}

.player-delete-toast-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    min-width: 300px;
    max-width: 500px;
    text-align: center;
}

.player-delete-toast-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.player-delete-toast-message {
    margin-top: 1rem;
}

.toast-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.toast-content {
    font-size: 1rem;
    color: var(--text-secondary);
}

.player-delete-toast[data-type="success"] .player-delete-toast-card {
    border-top: 4px solid var(--success-color);
}

.player-delete-toast[data-type="error"] .player-delete-toast-card {
    border-top: 4px solid #dc3545;
}

.player-delete-toast[data-type="confirm"] .player-delete-toast-card {
    border-top: 4px solid #ffc107;
}

.toast-actions .btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    transition: all 0.2s ease;
}

@keyframes toastFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

/* オーバーレイ */
.toast-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99998;
    pointer-events: auto;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // トースト通知を表示する関数（画面中央表示）
    function getToastIconHtml(type) {
        const iconMap = { success: 'check_circle', error: 'brightness_alert', info: 'info', confirm: 'brightness_alert' };
        if (typeof AidUniteThemeIcons !== 'undefined') {
            return AidUniteThemeIcons.html(iconMap[type] || 'info', 28);
        }
        return '';
    }

    function showToast(message, type = 'info') {
        // 既存のトーストとオーバーレイを削除
        const existingToast = document.querySelector('.player-delete-toast');
        const existingOverlay = document.querySelector('.toast-overlay');
        if (existingToast) existingToast.remove();
        if (existingOverlay) existingOverlay.remove();

        // オーバーレイを作成
        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        document.body.appendChild(overlay);

        // トーストを作成
        const toast = document.createElement('div');
        toast.className = 'player-delete-toast';
        toast.setAttribute('data-type', type);

        const icon = getToastIconHtml(type);
        const title = type === 'success' ? '削除完了' : type === 'error' ? '削除失敗' : 'お知らせ';

        toast.innerHTML = `
            <div class="player-delete-toast-card">
                <div class="player-delete-toast-icon">${icon}</div>
                <div class="player-delete-toast-message">
                    <div class="toast-title">${title}</div>
                    <div class="toast-content">${escapeHtml(message)}</div>
                </div>
            </div>
        `;

        document.body.appendChild(toast);

        // 自動削除
        const autoHideDelay = type === 'success' ? 2000 : 5000;
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translate(-50%, -60%)';
                overlay.style.opacity = '0';
                setTimeout(() => {
                    if (toast.parentElement) toast.remove();
                    if (overlay.parentElement) overlay.remove();
                }, 300);
            }
        }, autoHideDelay);

        // オーバーレイクリックで閉じる
        overlay.addEventListener('click', function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, -60%)';
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
                if (overlay.parentElement) overlay.remove();
            }, 300);
        });
    }

    // HTMLエスケープ関数
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 削除ボタンのイベントリスナー
    document.querySelectorAll('.delete-player-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const playerId = this.dataset.playerId;
            const nonce = this.dataset.nonce;
            const row = this.closest('tr');
            const playerName = row.querySelector('td:first-child')?.textContent?.trim() || 'この選手';

            // 確認トースト通知を表示
            showConfirmToast(
                '削除の確認',
                `本当に「${playerName}」を削除しますか？<br>この操作は取り消せません。`,
                function() {
                    // 確認OK → 削除実行
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'delete_player_ajax',
                            player_id: playerId,
                            nonce: nonce
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('選手を削除しました', 'success');
                            // 行を非表示
                            if (row) {
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-20px)';
                                setTimeout(() => {
                                    if (row.parentElement) row.remove();
                                }, 300);
                            }
                        } else {
                            showToast(data.data?.message || '削除に失敗しました', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('削除中にエラーが発生しました', 'error');
                    });
                }
            );
        });
    });

    // 確認トースト通知を表示する関数
    function showConfirmToast(title, message, onConfirm) {
        // 既存のトーストとオーバーレイを削除
        const existingToast = document.querySelector('.player-delete-toast');
        const existingOverlay = document.querySelector('.toast-overlay');
        if (existingToast) existingToast.remove();
        if (existingOverlay) existingOverlay.remove();

        // オーバーレイを作成
        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        document.body.appendChild(overlay);

        // トーストを作成
        const toast = document.createElement('div');
        toast.className = 'player-delete-toast';
        toast.setAttribute('data-type', 'confirm');

        toast.innerHTML = `
            <div class="player-delete-toast-card">
                <div class="player-delete-toast-icon">${getToastIconHtml('confirm')}</div>
                <div class="player-delete-toast-message">
                    <div class="toast-title">${title}</div>
                    <div class="toast-content">${message}</div>
                </div>
                <div class="toast-actions" style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center;">
                    <button class="btn btn-confirm" style="background: #dc3545; color: white; padding: 0.75rem 2rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        削除する
                    </button>
                    <button class="btn btn-cancel" style="background: #6c757d; color: white; padding: 0.75rem 2rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                        キャンセル
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(toast);

        // 削除ボタンのイベント
        const confirmBtn = toast.querySelector('.btn-confirm');
        confirmBtn.addEventListener('click', function() {
            // トーストを閉じる
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, -60%)';
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
                if (overlay.parentElement) overlay.remove();
            }, 300);

            // 確認コールバックを実行
            if (onConfirm) onConfirm();
        });

        // キャンセルボタンのイベント
        const cancelBtn = toast.querySelector('.btn-cancel');
        cancelBtn.addEventListener('click', function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, -60%)';
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
                if (overlay.parentElement) overlay.remove();
            }, 300);
        });

        // オーバーレイクリックで閉じる（キャンセル扱い）
        overlay.addEventListener('click', function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translate(-50%, -60%)';
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
                if (overlay.parentElement) overlay.remove();
            }, 300);
        });
    }
});
</script>

<?php get_footer(); ?>
