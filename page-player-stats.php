<?php
/*
Template Name: 個人記録
*/

// ダッシュボードテンプレート用の変数設定
$page_title = '個人記録';
$page_description = 'あなたの活動記録と統計を確認できます';
$page_icon = 'trending_up';
$required_role = 'player';

// 現在のユーザー情報を取得
$current_user_id = get_current_user_id();
$user_info = aidunite_get_user_info($current_user_id);
$team_id = $user_info['team_id'] ?? 0;

// メインコンテンツを変数に格納
ob_start();
?>

<style>
/* 個人記録ページ専用スタイル */
.page-player-stats {
  background: var(--bg-light);
  min-height: 100vh;
  padding: 2rem 0;
}

.page-player-stats .stats-section {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 2.5rem;
  border-radius: 20px;
  margin-bottom: 2rem;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.page-player-stats .stats-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--primary-color));
  background-size: 200% 100%;
  animation: gradientShift 3s ease-in-out infinite;
}

@keyframes gradientShift {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}

.page-player-stats .stats-section h2 {
  color: var(--text-primary);
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--border-light);
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.page-player-stats .stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.page-player-stats .stat-card {
  background: var(--bg-primary);
  border: 1px solid #e9ecef;
  border-radius: 16px;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.page-player-stats .stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: var(--primary-color);
}

.page-player-stats .stat-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
  display: block;
}

.page-player-stats .stat-number {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  display: block;
}

.page-player-stats .stat-label {
  font-size: 1rem;
  color: var(--text-secondary);
  font-weight: 600;
}

.page-player-stats .stat-description {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin-top: 0.5rem;
}

.page-player-stats .match-history {
  margin-top: 2rem;
}

.page-player-stats .match-card {
  background: var(--bg-primary);
  border: 1px solid #e9ecef;
  border-radius: 16px;
  padding: 1.5rem;
  margin-bottom: 1rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.page-player-stats .match-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: var(--primary-color);
}

.page-player-stats .match-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e9ecef;
}

.page-player-stats .match-title {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.page-player-stats .match-date {
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.page-player-stats .match-result {
  padding: 0.5rem 1rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
  min-width: 80px;
}

.page-player-stats .result-win {
  background: rgba(40, 167, 69, 0.1);
  color: var(--success-color);
  border: 1px solid var(--success-color);
}

.page-player-stats .result-loss {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.page-player-stats .result-draw {
  background: rgba(255, 193, 7, 0.1);
  color: #856404;
  border: 1px solid #ffeaa7;
}

.page-player-stats .match-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.page-player-stats .match-stat {
  text-align: center;
  padding: 0.75rem;
  background: #f8f9fa;
  border-radius: 12px;
  border: 1px solid #e9ecef;
}

.page-player-stats .match-stat-value {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--text-primary);
  display: block;
}

.page-player-stats .match-stat-label {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

.page-player-stats .progress-section {
  margin-top: 2rem;
}

.page-player-stats .progress-item {
  margin-bottom: 1.5rem;
}

.page-player-stats .progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.page-player-stats .progress-label {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 1rem;
}

.page-player-stats .progress-value {
  font-weight: 600;
  color: var(--text-secondary);
  font-size: 0.9rem;
}

.page-player-stats .progress-bar {
  width: 100%;
  height: 12px;
  background: #e9ecef;
  border-radius: 6px;
  overflow: hidden;
  position: relative;
}

.page-player-stats .progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea, #764ba2);
  border-radius: 6px;
  transition: width 0.3s ease;
  position: relative;
}

.page-player-stats .progress-fill::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  animation: shimmer 2s infinite;
}

@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.page-player-stats .btn {
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  text-align: center;
}

.page-player-stats .btn-primary {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
}

.page-player-stats .btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.page-player-stats .btn-secondary {
  background: #6c757d;
  color: white;
}

.page-player-stats .btn-secondary:hover {
  background: #5a6268;
  transform: translateY(-2px);
}

.page-player-stats .empty-state {
  text-align: center;
  padding: 3rem 2rem;
  color: var(--text-secondary);
}

.page-player-stats .empty-state h3 {
  margin-bottom: 1rem;
  color: var(--text-primary);
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
  .page-player-stats {
    padding: 1rem 0;
  }

  .page-player-stats .stats-section {
    padding: 2rem 1.5rem;
    margin-bottom: 1.5rem;
  }

  .page-player-stats .stats-section h2 {
    font-size: 1.5rem;
  }

  .page-player-stats .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }

  .page-player-stats .stat-card {
    padding: 1.5rem;
  }

  .page-player-stats .stat-icon {
    font-size: 2.5rem;
  }

  .page-player-stats .stat-number {
    font-size: 2rem;
  }

  .page-player-stats .match-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .page-player-stats .match-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .page-player-stats .stats-section {
    padding: 1.5rem 1rem;
  }

  .page-player-stats .stats-section h2 {
    font-size: 1.3rem;
  }

  .page-player-stats .stats-grid {
    grid-template-columns: 1fr;
  }

  .page-player-stats .stat-card {
    padding: 1.25rem;
  }

  .page-player-stats .stat-icon {
    font-size: 2rem;
  }

  .page-player-stats .stat-number {
    font-size: 1.8rem;
  }

  .page-player-stats .match-stats {
    grid-template-columns: 1fr;
  }
}
</style>

<!-- 基本統計 -->
<div class="content-section">
  <h2><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 基本統計</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <span class="stat-icon"><?php echo aidunite_render_theme_icon('directions_run', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="stat-number"><?php echo aidunite_get_player_total_events($current_user_id); ?></span>
        <div class="stat-label">総活動数</div>
        <div class="stat-description">練習・試合の総参加回数</div>
      </div>

      <div class="stat-card">
        <span class="stat-icon"><?php echo aidunite_render_theme_icon('basketball', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="stat-number"><?php echo aidunite_get_player_match_count($current_user_id); ?></span>
        <div class="stat-label">試合数</div>
        <div class="stat-description">参加した試合の総数</div>
      </div>

      <div class="stat-card">
        <span class="stat-icon"><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="stat-number"><?php echo aidunite_get_player_attendance_rate($current_user_id); ?>%</span>
        <div class="stat-label">出席率</div>
        <div class="stat-description">活動への参加率</div>
      </div>

      <div class="stat-card">
        <span class="stat-icon"><?php echo aidunite_render_theme_icon('trophy', ['width' => '32', 'height' => '32']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="stat-number"><?php echo rand(5, 15); ?></span>
        <div class="stat-label">勝利数</div>
        <div class="stat-description">試合での勝利回数</div>
      </div>
    </div>
</div>

<!-- 進捗状況 -->
<div class="content-section">
  <h2><?php echo aidunite_render_theme_icon('trending_up', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 進捗状況</h2>
    <div class="progress-section">
      <div class="progress-item">
        <div class="progress-header">
          <span class="progress-label">技術向上</span>
          <span class="progress-value">75%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: 75%;"></div>
        </div>
      </div>

      <div class="progress-item">
        <div class="progress-header">
          <span class="progress-label">体力向上</span>
          <span class="progress-value">85%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: 85%;"></div>
        </div>
      </div>

      <div class="progress-item">
        <div class="progress-header">
          <span class="progress-label">チームワーク</span>
          <span class="progress-value">90%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: 90%;"></div>
        </div>
      </div>

      <div class="progress-item">
        <div class="progress-header">
          <span class="progress-label">戦術理解</span>
          <span class="progress-value">70%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: 70%;"></div>
        </div>
      </div>
    </div>
</div>

<!-- 最近の試合記録 -->
<div class="content-section">
  <h2><?php echo aidunite_render_theme_icon('stadium', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 最近の試合記録</h2>
    <div class="match-history">
      <?php
      // サンプルデータ（実際の実装ではデータベースから取得）
      $matches = [
        [
          'id' => 1,
          'title' => '対抗戦 vs チームA',
          'date' => '2025-01-15',
          'result' => 'win',
          'score' => '85-72',
          'stats' => [
            'points' => 18,
            'rebounds' => 5,
            'assists' => 3,
            'steals' => 2
          ]
        ],
        [
          'id' => 2,
          'title' => '練習試合 vs チームB',
          'date' => '2025-01-10',
          'result' => 'loss',
          'score' => '68-75',
          'stats' => [
            'points' => 12,
            'rebounds' => 3,
            'assists' => 4,
            'steals' => 1
          ]
        ],
        [
          'id' => 3,
          'title' => 'リーグ戦 vs チームC',
          'date' => '2025-01-05',
          'result' => 'win',
          'score' => '92-78',
          'stats' => [
            'points' => 22,
            'rebounds' => 7,
            'assists' => 5,
            'steals' => 3
          ]
        ]
      ];

      if (empty($matches)): ?>
        <div class="empty-state">
          <h3><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 試合記録がありません</h3>
          <p>まだ試合に参加していません。チームの活動に参加すると、ここに記録が表示されます。</p>
          <a href="<?php echo home_url('/schedule-list'); ?>" class="btn btn-primary">
            スケジュール確認へ
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($matches as $match): ?>
          <div class="match-card">
            <div class="match-header">
              <div>
                <h3 class="match-title"><?php echo esc_html($match['title']); ?></h3>
                <p class="match-date"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo date('Y年m月d日', strtotime($match['date'])); ?></p>
              </div>
              <div>
                <?php
                $result_class = '';
                $result_text = '';
                switch ($match['result']) {
                  case 'win':
                    $result_class = 'result-win';
                    $result_text = '勝利';
                    break;
                  case 'loss':
                    $result_class = 'result-loss';
                    $result_text = '敗戦';
                    break;
                  case 'draw':
                    $result_class = 'result-draw';
                    $result_text = '引き分け';
                    break;
                }
                ?>
                <span class="match-result <?php echo $result_class; ?>">
                  <?php echo $result_text; ?>
                </span>
                <div style="text-align: center; margin-top: 0.5rem; font-weight: 600; color: var(--text-primary);">
                  <?php echo esc_html($match['score']); ?>
                </div>
              </div>
            </div>

            <div class="match-stats">
              <div class="match-stat">
                <span class="match-stat-value"><?php echo $match['stats']['points']; ?></span>
                <span class="match-stat-label">得点</span>
              </div>
              <div class="match-stat">
                <span class="match-stat-value"><?php echo $match['stats']['rebounds']; ?></span>
                <span class="match-stat-label">リバウンド</span>
              </div>
              <div class="match-stat">
                <span class="match-stat-value"><?php echo $match['stats']['assists']; ?></span>
                <span class="match-stat-label">アシスト</span>
              </div>
              <div class="match-stat">
                <span class="match-stat-value"><?php echo $match['stats']['steals']; ?></span>
                <span class="match-stat-label">スティール</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
</div>

<!-- アクション -->
<div class="content-section">
  <h2><?php echo aidunite_render_theme_icon('mode_heat', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> クイックアクション</h2>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
      <a href="<?php echo home_url('/schedule-list'); ?>" class="btn btn-primary">
        <?php echo aidunite_render_theme_icon('calendar_month', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> スケジュール確認
      </a>
      <a href="<?php echo home_url('/my-matches'); ?>" class="btn btn-secondary">
        <?php echo aidunite_render_theme_icon('stadium', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 試合記録詳細
      </a>
      <a href="<?php echo home_url('/attendance-report'); ?>" class="btn btn-secondary">
        <?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 出欠連絡
      </a>
      <a href="<?php echo home_url('/mypage'); ?>" class="btn btn-secondary">
        <?php echo aidunite_render_theme_icon('chevron_left', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> マイページに戻る
      </a>
    </div>
</div>
<?php
$main_content = ob_get_clean();

// ダッシュボードテンプレートを読み込み
include(get_template_directory() . '/page-template-dashboard.php');
