<?php
/*--------------------------------------------------------------
  サンプルページプレビュー
--------------------------------------------------------------*/

/**
 * Template Name: サンプルページプレビュー
 */

// 管理者権限チェック
if (!current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

get_header();

// ページネーション設定
$items_per_page = 10;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// おしゃれなデザインCSS
echo '';
?>

<div class="sample-wrapper">
<div class="sample-container">
    <div class="sample-card">

      <!-- ダッシュボードヘッダー -->
      <div class="dashboard-header">
        <h1>サンプルページプレビュー</h1>
        <p>各ページのサンプル画面を確認できます</p>
      </div>

      <!-- タブナビゲーション -->
      <div class="tab-navigation">
        <button class="tab-btn active" data-tab="match-board">🏟️ 試合掲示板</button>
        <button class="tab-btn" data-tab="match-detail">⚔️ マッチ詳細</button>
        <button class="tab-btn" data-tab="schedule-edit">📅 スケジュール編集</button>
        <button class="tab-btn" data-tab="team-management">👥 チーム管理</button>
        <button class="tab-btn" data-tab="user-management">👤 ユーザー管理</button>
      </div>

      <!-- 試合掲示板タブのコンテンツ -->
      <div id="match-board" class="tab-content active">
        <!-- 検索・フィルター機能 -->
        <div class="card">
          <div class="card-header">
            <h3>🔍 検索・フィルター</h3>
          </div>
          <div class="card-body">
            <form method="GET" class="search-form">
              <div class="form-row">
                <div class="form-group">
                  <label for="search_team">チーム名</label>
                  <input type="text" class="form-control" id="search_team" name="search_team" placeholder="チーム名を入力">
                </div>
                <div class="form-group">
                  <label for="search_location">地域</label>
                  <select class="form-control" id="search_location" name="search_location">
                    <option value="">すべての地域</option>
                    <option value="東京都">東京都</option>
                    <option value="大阪府">大阪府</option>
                    <option value="京都府">京都府</option>
                    <option value="神奈川県">神奈川県</option>
                    <option value="愛知県">愛知県</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="filter_gender">性別条件</label>
                  <select class="form-control" id="filter_gender" name="filter_gender">
                    <option value="">すべて</option>
                    <option value="男性">男性</option>
                    <option value="女性">女性</option>
                    <option value="男女混合">男女混合</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="filter_venue">会場条件</label>
                  <select class="form-control" id="filter_venue" name="filter_venue">
                    <option value="">どちらでも</option>
                    <option value="ホーム">ホーム</option>
                    <option value="アウェイ">アウェイ</option>
                  </select>
                </div>
                <div class="form-group">
                  <button type="submit" class="btn btn-primary">検索</button>
                </div>
              </div>
              <div class="form-row">
                <a href="#" class="btn btn-secondary">検索条件をクリア</a>
              </div>
            </form>
          </div>
        </div>

        <!-- マッチ候補セクション -->
        <div class="card">
          <div class="card-header">
            <h3>📅 自チームスケジュール：A-004（8/30土 14:00〜16:00）</h3>
            <span class="status-badge recruiting">🔄 マッチ候補あり</span>
          </div>
          <div class="card-body">
            <p><strong>🏟️ 会場：</strong>⚫⚫高校 / <strong>性別：</strong>男子 / <strong>種別：</strong>練習試合</p>

            <h4>🔄 マッチ候補（マッチ度順）</h4>

            <!-- マッチ度100%のチーム（最優先表示） -->
            <div class="team-candidate-card">
              <div class="team-candidate-header">
                <div class="team-info">
                  <h5>⚽ Cチーム</h5>
                  <p>🆔 スケジュールID：A-001（8/28木 9:00〜16:00）</p>
                  <p>🏟️ ⚫⚫高校 / 性別：男女 / 募集中 4/8チーム</p>
                </div>
                <div class="match-score">
                  <span class="score-badge perfect">マッチ度: 100%</span>
                </div>
              </div>
              <div class="team-candidate-actions">
                <a href="#" class="btn btn-primary">詳細を見る</a>
              </div>
            </div>

            <!-- マッチ度90%のチーム -->
            <div class="team-candidate-card">
              <div class="team-candidate-header">
                <div class="team-info">
                  <h5>⚽ Eチーム</h5>
                  <p>🆔 スケジュールID：B-002（8/29金 13:00〜16:00）</p>
                  <p>🏟️ ⚫⚫高校 / 性別：男子 / 募集中 2/6チーム</p>
                </div>
                <div class="match-score">
                  <span class="score-badge high">マッチ度: 90%</span>
                </div>
              </div>
              <div class="team-candidate-actions">
                <a href="#" class="btn btn-primary">詳細を見る</a>
              </div>
            </div>

            <!-- マッチ度80%のチーム -->
            <div class="team-candidate-card">
              <div class="team-candidate-header">
                <div class="team-info">
                  <h5>⚽ Bチーム</h5>
                  <p>🆔 スケジュールID：A-001（8/28木 9:00〜16:00）</p>
                  <p>🏟️ ⚫⚫高校 / 性別：男女 / 募集中 4/8チーム</p>
                </div>
                <div class="match-score">
                  <span class="score-badge medium">マッチ度: 80%</span>
                </div>
              </div>
              <div class="team-candidate-actions">
                <a href="#" class="btn btn-primary">詳細を見る</a>
              </div>
            </div>

            <!-- マッチ度60%のチーム -->
            <div class="team-candidate-card">
              <div class="team-candidate-header">
                <div class="team-info">
                  <h5>⚽ Dチーム</h5>
                  <p>🆔 スケジュールID：A-001（8/28木 9:00〜16:00）</p>
                  <p>🏟️ ⚫⚫高校 / 性別：男女 / 募集中 4/8チーム</p>
                </div>
                <div class="match-score">
                  <span class="score-badge low">マッチ度: 60%</span>
                </div>
              </div>
              <div class="team-candidate-actions">
                <a href="#" class="btn btn-primary">詳細を見る</a>
              </div>
            </div>
          </div>
        </div>

        <!-- 掲示板一覧セクション -->
        <div class="card">
          <div class="card-header">
            <h3>📋 マッチしていない掲示板一覧</h3>
          </div>
          <div class="card-body">
            <!-- 掲示板1 -->
            <div class="board-item">
              <div class="board-item-header">
                <div class="board-info">
                  <h5>🆔 スケジュールID：A-001（8/28木 9:00〜16:00）</h5>
                  <p>🏟️ 会場：⚫⚫高校 / 性別：男女 / 種別：練習試合</p>
                </div>
                <div class="board-status">
                  <span class="status-badge recruiting">募集中</span>
                  <span class="team-count">4/8チーム</span>
                  <a href="#" class="btn btn-primary">詳細を見る</a>
                </div>
              </div>
            </div>

            <!-- 掲示板2 -->
            <div class="board-item">
              <div class="board-item-header">
                <div class="board-info">
                  <h5>🆔 スケジュールID：B-001（8/29金 13:00〜16:00）</h5>
                  <p>🏟️ 会場：⚫⚫高校 / 性別：女子 / 種別：練習試合</p>
                </div>
                <div class="board-status">
                  <span class="status-badge recruiting">募集中</span>
                  <span class="team-count">4/6チーム</span>
                  <a href="#" class="btn btn-primary">詳細を見る</a>
                </div>
              </div>
            </div>

            <!-- 掲示板3 -->
            <div class="board-item">
              <div class="board-item-header">
                <div class="board-info">
                  <h5>🆔 スケジュールID：A-003（8/30土 14:00〜16:00）</h5>
                  <p>🏟️ 会場：⚫⚫高校 / 性別：男子 / 種別：練習試合</p>
                </div>
                <div class="board-status">
                  <span class="status-badge completed">試合確定</span>
                  <span class="team-count">4/4チーム</span>
                  <a href="#" class="btn btn-primary">詳細を見る</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ページネーション -->
        <div class="card">
          <div class="card-body">
            <div class="pagination-container">
              <div class="pagination-info">
                <span class="pagination-text">全 15 件中 1-10 件を表示</span>
              </div>
              <nav class="pagination-nav" aria-label="ページネーション">
                <ul class="pagination">
                  <li class="page-item disabled">
                    <a href="#" tabindex="-1" class="page-link">
                      <i class="fas fa-chevron-left"></i>
                      <span class="page-text">前へ</span>
                    </a>
                  </li>
                  <li class="page-item active">
                    <a href="#" class="page-link">1</a>
                  </li>
                  <li class="page-item">
                    <a href="#" class="page-link">2</a>
                  </li>
                  <li class="page-item">
                    <a href="#" class="page-link">
                      <span class="page-text">次へ</span>
                      <i class="fas fa-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        </div>
      </div>

      <!-- マッチ詳細タブのコンテンツ -->
      <div id="match-detail" class="tab-content">
        <div class="card">
          <div class="card-header">
            <h3>⚔️ マッチ詳細サンプル</h3>
          </div>
          <div class="card-body">
            <!-- ヘッダーセクション -->
            <div class="match-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-radius: var(--radius-large); padding: var(--spacing-xl); margin-bottom: var(--spacing-xl); color: var(--text-light); text-align: center; position: relative; overflow: hidden;">
              <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: var(--spacing-sm); text-shadow: 2px 2px 4px rgba(0,0,0,0.3); position: relative; z-index: 1;">マッチ詳細</h1>
              <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: var(--spacing-lg); position: relative; z-index: 1;">練習試合の詳細情報</p>
              <div style="display: inline-block; background: linear-gradient(45deg, var(--warning-color), #FFA500); color: var(--text-primary); padding: var(--spacing-sm) var(--spacing-lg); border-radius: var(--radius-large); font-weight: 700; font-size: 1.1rem; box-shadow: var(--shadow-md); animation: pulse 2s infinite; position: relative; z-index: 1;">マッチ度: ☆（100%）</div>
            </div>

            <!-- チーム対戦セクション -->
            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 30px; margin-bottom: 40px; align-items: center;">
              <!-- 自チーム -->
              <div style="background: var(--bg-primary); border-radius: var(--radius-large); padding: var(--spacing-xl); box-shadow: var(--shadow-lg); transition: all 0.3s ease; position: relative; overflow: hidden; border-top: 4px solid var(--success-color);">
                <div style="text-align: center; margin-bottom: var(--spacing-lg);">
                  <img src="https://via.placeholder.com/120x120/4CAF50/FFFFFF?text=Team+A" alt="サンプルチームA" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border-light); transition: all 0.3s ease;">
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: var(--spacing-base); color: var(--text-primary);">サンプルチームA</h3>
                <div style="display: grid; gap: var(--spacing-sm);">
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--success-color), #8BC34A); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">⚽</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">サッカー</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--success-color), #8BC34A); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">👥</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">U-15</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--success-color), #8BC34A); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">📍</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">東京都</span>
                  </div>
                </div>
              </div>

              <!-- VS -->
              <div style="text-align: center; position: relative;">
                <div style="background: linear-gradient(45deg, var(--danger-color), #FF8E53); color: var(--text-light); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0 auto; box-shadow: var(--shadow-lg); animation: bounce 2s infinite;">VS</div>
                <div style="margin-top: var(--spacing-sm); font-size: 0.9rem; color: var(--text-secondary); font-weight: 600;">対戦</div>
              </div>

              <!-- 相手チーム -->
              <div style="background: var(--bg-primary); border-radius: var(--radius-large); padding: var(--spacing-xl); box-shadow: var(--shadow-lg); transition: all 0.3s ease; position: relative; overflow: hidden; border-top: 4px solid var(--info-color);">
                <div style="text-align: center; margin-bottom: var(--spacing-lg);">
                  <img src="https://via.placeholder.com/120x120/2196F3/FFFFFF?text=Team+B" alt="サンプルチームB" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border-light); transition: all 0.3s ease;">
                </div>
                <h3 style="font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: var(--spacing-base); color: var(--text-primary);">サンプルチームB</h3>
                <div style="display: grid; gap: var(--spacing-sm);">
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--info-color), #03A9F4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">⚽</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">サッカー</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--info-color), #03A9F4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">👥</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">U-15</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-xs) 0;">
                    <div style="width: 20px; height: 20px; background: linear-gradient(45deg, var(--info-color), #03A9F4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 12px; font-weight: bold;">📍</div>
                    <span style="font-size: 0.9rem; color: var(--text-secondary);">神奈川県</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- マッチ詳細セクション -->
            <div style="background: var(--bg-primary); border-radius: var(--radius-large); padding: var(--spacing-xl); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-lg);">
              <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--spacing-lg); color: var(--text-primary); display: flex; align-items: center; gap: var(--spacing-sm);">📅 試合詳細</h2>
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color); transition: all 0.3s ease;">
                  <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">📅 日付</div>
                  <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">2024年3月15日（金）</div>
                </div>
                <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color); transition: all 0.3s ease;">
                  <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">🕐 時間</div>
                  <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">19:00 〜 21:00</div>
                </div>
                <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color); transition: all 0.3s ease;">
                  <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">🏟️ 会場</div>
                  <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">○○サッカー場</div>
                </div>
                <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color); transition: all 0.3s ease;">
                  <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">🏆 試合種別</div>
                  <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">練習試合</div>
                </div>
              </div>
            </div>

            <!-- 相手チーム詳細情報 -->
            <div style="background: var(--bg-primary); border-radius: var(--radius-large); padding: var(--spacing-xl); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-lg);">
              <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: var(--spacing-lg); color: var(--text-primary); display: flex; align-items: center; gap: var(--spacing-sm);">サンプルチームB 詳細情報</h2>

              <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color); margin-bottom: var(--spacing-lg);">
                <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">📝 チーム紹介</div>
                <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: normal; line-height: 1.6;">技術と戦術を重視したサッカーチームです。チームワークを大切にし、一人ひとりの成長をサポートしています。</div>
              </div>

              <div style="background: linear-gradient(135deg, var(--bg-secondary), var(--border-light)); padding: var(--spacing-lg); border-radius: var(--radius-medium); border-left: 4px solid var(--primary-color);">
                <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: var(--spacing-xs); font-weight: 600;">🏅 実績</div>
                <div style="font-size: 1.1rem; color: var(--text-primary); font-weight: normal; line-height: 1.6;">2023年度 県大会準優勝<br>2022年度 地区大会優勝</div>
              </div>
            </div>

            <!-- ステータス表示 -->
            <div style="background: var(--bg-primary); border-radius: var(--radius-large); padding: var(--spacing-xl); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-lg); text-align: center;">
              <div style="display: inline-block; padding: var(--spacing-base) var(--spacing-xl); border-radius: var(--radius-large); font-weight: 700; font-size: 1.1rem; margin-bottom: var(--spacing-lg); background: linear-gradient(45deg, var(--warning-color), #FF9800); color: var(--text-light); box-shadow: var(--shadow-md);">⏳ 申請中</div>

              <div style="display: flex; gap: var(--spacing-base); justify-content: center; flex-wrap: wrap;">
                <button style="padding: var(--spacing-base) var(--spacing-xl); border: none; border-radius: var(--radius-large); font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm); background: linear-gradient(45deg, var(--info-color), #03A9F4); color: var(--text-light); box-shadow: var(--shadow-md);">
                  <span>✅</span> 承認する
                </button>
                <button style="padding: var(--spacing-base) var(--spacing-xl); border: none; border-radius: var(--radius-large); font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm); background: linear-gradient(45deg, var(--danger-color), #E91E63); color: var(--text-light); box-shadow: var(--shadow-md);">
                  <span>❌</span> 拒否する
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- スケジュール編集タブのコンテンツ -->
      <div id="schedule-edit" class="tab-content">
        <div class="card">
          <div class="card-header">
            <h3>📅 スケジュール編集サンプル</h3>
          </div>
          <div class="card-body">
            <p>スケジュール編集画面のサンプルがここに表示されます。</p>
            <p>現在開発中です。</p>
          </div>
        </div>
      </div>

      <!-- チーム管理タブのコンテンツ -->
      <div id="team-management" class="tab-content">
        <div class="card">
          <div class="card-header">
            <h3>👥 チーム管理サンプル</h3>
          </div>
          <div class="card-body">
            <p>チーム管理画面のサンプルがここに表示されます。</p>
            <p>現在開発中です。</p>
          </div>
        </div>
      </div>

      <!-- ユーザー管理タブのコンテンツ -->
      <div id="user-management" class="tab-content">
        <div class="card">
          <div class="card-header">
            <h3>👤 ユーザー管理サンプル</h3>
          </div>
          <div class="card-body">
            <p>ユーザー管理画面のサンプルがここに表示されます。</p>
            <p>現在開発中です。</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php get_footer(); ?>
