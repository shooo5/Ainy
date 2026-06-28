<?php
/**
 * The front page template file.
 * トップページ（0ベース・静かで強い／信頼感重視）
 *
 * @package Ainy
 */

get_header();

$fp_icon_base = aidunite_get_theme_icons_uri();
$fp_team_image_path = get_template_directory() . '/assets/images/Ainy-Team.png';
$fp_team_image_url = is_readable( $fp_team_image_path )
  ? get_template_directory_uri() . '/assets/images/Ainy-Team.png'
  : get_template_directory_uri() . '/assets/images/problem-illustration.png';
$fp_create_team_url = home_url( '/member-registration' );
?>

<div id="primary" class="content-area content-area--front">
  <main id="main" class="site-main">

    <div class="ainy-welcome-page">

      <!-- ファーストビュー（新デザイン） -->
      <section class="ainy-welcome-hero" aria-labelledby="ainy-welcome-title">
          <div class="ainy-welcome-left">
            <div class="ainy-welcome-badge">
              <span class="ainy-badge-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('basketball', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              <span class="ainy-badge-text">Ainyへようこそ！</span>
              <span class="ainy-badge-lines" aria-hidden="true"></span>
            </div>

            <h1 id="ainy-welcome-title" class="ainy-welcome-title">
              <span class="ainy-title-line ainy-title-top">練習試合の調整を、</span>
              <span class="ainy-title-line ainy-title-bottom">もっとかんたんに。</span>
            </h1>

            <p class="ainy-welcome-description">
              試合調整、スケジュール管理、<br>
              チーム連絡をAinyでまとめましょう。
            </p>

            <a href="<?php echo esc_url( $fp_create_team_url ); ?>" class="ainy-create-team-btn">
              <span class="ainy-create-team-btn__icon" aria-hidden="true">✨</span>
              無料でチームを作成する
            </a>

            <div class="ainy-welcome-meta">
              <div class="ainy-welcome-meta__item">
                <?php echo aidunite_render_theme_icon('mode_heat', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                最短1分で開始
              </div>
              <div class="ainy-welcome-meta__item">
                <?php echo aidunite_render_theme_icon('lock', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                登録無料
              </div>
              <div class="ainy-welcome-meta__item">
                <?php echo aidunite_render_theme_icon('group', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                あとからメンバー招待OK
              </div>
            </div>
          </div>

          <div class="ainy-welcome-right">
            <div class="ainy-visual-card">
              <div class="ainy-visual-bg" aria-hidden="true"></div>
              <img
                src="<?php echo esc_url( $fp_team_image_url ); ?>"
                alt="Ainyチーム"
                class="ainy-team-img"
                width="480"
                height="480"
                loading="eager"
                decoding="async"
              >
            </div>
          </div>
      </section>

      <!-- 機能紹介（旧：共感＋解決フロー相当） -->
      <section class="ainy-feature-section" aria-labelledby="ainy-feature-section-title">
          <h2 id="ainy-feature-section-title" class="ainy-section-title">Ainyでできること</h2>

          <div class="ainy-feature-grid">
            <article class="ainy-feature-card">
              <div class="ainy-feature-icon purple">
                <?php echo aidunite_get_theme_icon_svg('person-search', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
              <h3>練習試合を探せる</h3>
              <p>
                自チームにあった<br>
                練習相手を簡単見つけられます。
              </p>
            </article>

            <article class="ainy-feature-card">
              <div class="ainy-feature-icon blue">
                <?php echo aidunite_get_theme_icon_svg('calendar_month', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
              <h3>スケジュール共有</h3>
              <p>
                練習や試合の予定を<br>
                チームで共有できます。
              </p>
            </article>

            <article class="ainy-feature-card">
              <div class="ainy-feature-icon green">
                <?php echo aidunite_get_theme_icon_svg('sms', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
              <h3>チームで連絡</h3>
              <p>
                チャットでスムーズに<br>
                連絡・調整ができます。
              </p>
            </article>

            <article class="ainy-feature-card">
              <div class="ainy-feature-icon orange">
                <?php echo aidunite_get_theme_icon_svg('handshake', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </div>
              <h3>試合調整を効率化</h3>
              <p>
                やり取りや日程調整を<br>
                Ainyがサポートします。
              </p>
            </article>
          </div>
      </section>

    </div>

    <!-- 安心材料（トラスト・ビジュアル） -->
    <section class="aidunite-fp-section aidunite-fp-assurance" aria-labelledby="fp-assurance-title">
      <div class="aidunite-fp-container">
        <h2 id="fp-assurance-title" class="aidunite-fp-section-title">チームでも安心して使える仕組みです。</h2>
        <p class="aidunite-fp-assurance__subtitle">
          学校・クラブチームでも安心して使えるよう、安全性と管理体制を整えています。
        </p>
      </div>
      <!-- 画像上部の空き帯をCSSで視覚的に詰める（根本対応はPNGトリミング） -->
      <div class="aidunite-fp-assurance__visual">
        <img
          src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/features-trust-section.png' ); ?>"
          alt="チームでも安心して使える仕組み"
          class="aidunite-fp-assurance__image"
          loading="lazy"
          decoding="async"
        >
      </div>
    </section>

    <!-- 成長サイクル -->
    <section class="aidunite-fp-section aidunite-fp-growth-cycle" aria-labelledby="fp-growth-cycle-title">
      <div class="aidunite-fp-container">

        <h2 id="fp-growth-cycle-title" class="aidunite-fp-section-title">
          Ainyが考える、<br class="aidunite-fp-section-title__break" aria-hidden="true">成長のサイクル。
        </h2>

        <div class="aidunite-fp-growth-cycle__panel">
          <p class="aidunite-fp-growth-cycle__caption">
            この繰り返しが、成長を生みます。
          </p>

          <div class="aidunite-fp-growth-cycle__nodes">
            <div class="aidunite-fp-growth-cycle__node">
              <span class="aidunite-fp-growth-cycle__node-title">練習</span>
              <small class="aidunite-fp-growth-cycle__node-desc">できたことを積み上げる</small>
            </div>
            <div class="aidunite-fp-growth-cycle__node">
              <span class="aidunite-fp-growth-cycle__node-title">試合</span>
              <small class="aidunite-fp-growth-cycle__node-desc">実戦で試す</small>
            </div>
            <div class="aidunite-fp-growth-cycle__node">
              <span class="aidunite-fp-growth-cycle__node-title">課題</span>
              <small class="aidunite-fp-growth-cycle__node-desc">ミス・改善点を把握</small>
            </div>
          </div>
        </div>

        <div class="aidunite-fp-growth-cycle__closing">
          <p class="aidunite-fp-growth-cycle__maincopy">
            練習でできたことを、<br>
            試合で<span class="aidunite-fp-growth-cycle__emphasis">使える力</span>に変える。
          </p>

          <p class="aidunite-fp-growth-cycle__lead">
            Ainyは、試合を組む手間を減らし、<br>
            このサイクルを回しやすくします。
          </p>

          <p class="aidunite-fp-growth-cycle__bridge">
            その結果、
          </p>

          <ul class="aidunite-fp-empathy__list aidunite-fp-growth-cycle__issues" role="list">
            <li class="aidunite-fp-empathy__item">
              <span class="aidunite-fp-empathy__check" aria-hidden="true"></span>
              <span class="aidunite-fp-empathy__item-text">どこを修正すればいいのか見えてくる</span>
            </li>
            <li class="aidunite-fp-empathy__item">
              <span class="aidunite-fp-empathy__check" aria-hidden="true"></span>
              <span class="aidunite-fp-empathy__item-text">チームの成長を実感できる</span>
            </li>
            <li class="aidunite-fp-empathy__item">
              <span class="aidunite-fp-empathy__check" aria-hidden="true"></span>
              <span class="aidunite-fp-empathy__item-text">次の練習の方向が明確になる</span>
            </li>
          </ul>

          <p class="aidunite-fp-growth-cycle__catch">
            試合を増やせば、成長は変わる。
          </p>
        </div>

        <div class="aidunite-fp-growth-cycle__message">
          <p class="aidunite-fp-growth-cycle__message-lead">
            子どもの成長を<br>
            <span class="aidunite-fp-growth-cycle__message-highlight">「見えるもの」</span>にする。
          </p>
          <p class="aidunite-fp-growth-cycle__message-sub aidunite-fp-growth-cycle__message-sub--oneline">
            試合をきっかけに成長をアウトプットする。
          </p>
          <p class="aidunite-fp-growth-cycle__message-sub aidunite-fp-growth-cycle__message-sub--brand">
            Ainyは、成長の循環を支えます。
          </p>
        </div>

        <div class="aidunite-fp-growth-cycle__cta">
          <a href="<?php echo esc_url( home_url( '/member-registration' ) ); ?>" class="aidunite-fp-btn aidunite-fp-btn-primary">
            無料で登録する
          </a>
        </div>

      </div>
    </section>

    <!-- 料金プラン（デザイン調整用プレースホルダー） -->
    <section class="ainy-pricing-placeholder" aria-labelledby="fp-pricing-placeholder-title">
      <div class="aidunite-fp-container">
        <h2 id="fp-pricing-placeholder-title" class="ainy-section-title">料金プラン</h2>
        <p class="ainy-pricing-placeholder__lead">
          アカウント登録・チーム申請は無料です。本格利用時の Match / Club プランはサービスページでご確認いただけます。
        </p>
        <div class="ainy-pricing-placeholder__grid">
          <div class="ainy-pricing-placeholder__slot" aria-hidden="true">
            <span class="ainy-pricing-placeholder__slot-label">Match プラン</span>
            <span class="ainy-pricing-placeholder__slot-note">デザイン調整予定</span>
          </div>
          <div class="ainy-pricing-placeholder__slot" aria-hidden="true">
            <span class="ainy-pricing-placeholder__slot-label">Club プラン</span>
            <span class="ainy-pricing-placeholder__slot-note">デザイン調整予定</span>
          </div>
        </div>
        <p class="ainy-pricing-placeholder__link-wrap">
          <a href="<?php echo esc_url( home_url( '/service/' ) ); ?>" class="ainy-pricing-placeholder__link">サービス・料金の詳細</a>
        </p>
      </div>
    </section>

    <?php if ( function_exists( 'aidunite_is_developer_preview_mode' ) && aidunite_is_developer_preview_mode() ) : ?>
    <!-- 開発者プレビュー時のみ表示 -->
    <section class="aidunite-fp-section aidunite-fp-ui-showcase" aria-labelledby="fp-ui-title">
      <div class="aidunite-fp-container">
        <h2 id="fp-ui-title" class="aidunite-fp-section-title">実際の画面を見てみよう</h2>
        <div class="aidunite-fp-ui-grid">
          <div class="aidunite-fp-ui-item">
            <div class="aidunite-fp-ui-placeholder">スケジュール管理画面</div>
            <h3>スケジュール管理</h3>
            <p>練習や試合の日程を一目で確認</p>
          </div>
          <div class="aidunite-fp-ui-item">
            <div class="aidunite-fp-ui-placeholder">マッチ候補</div>
            <h3>マッチ候補</h3>
            <p>条件に合うチームを自動で提案</p>
          </div>
          <div class="aidunite-fp-ui-item">
            <div class="aidunite-fp-ui-placeholder">マイページ</div>
            <h3>マイページ</h3>
            <p>チーム情報や活動履歴を管理</p>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- 最後のCTA -->
    <section class="aidunite-fp-final-cta">
      <div class="aidunite-fp-container">
        <p class="aidunite-fp-final-cta-lead">子どもたちに、もっと試合の機会を。</p>
        <div class="aidunite-fp-register-mini" role="region" aria-label="登録の流れ">
          <p class="aidunite-fp-register-mini__label">かんたん登録（約3分）</p>
          <div class="aidunite-fp-register-mini__flow">
            <span>個人登録</span>
            <span class="aidunite-fp-register-mini__arrow" aria-hidden="true"><?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span>チーム申請</span>
          </div>
        </div>
        <div class="aidunite-fp-final-cta-actions">
          <a href="<?php echo esc_url( home_url( '/member-registration' ) ); ?>" class="aidunite-fp-btn aidunite-fp-btn-primary">無料で登録する</a>
        </div>
      </div>
    </section>

  </main>
</div>

<?php
get_footer();
