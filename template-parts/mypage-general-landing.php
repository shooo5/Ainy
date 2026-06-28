<?php
/**
 * 一般ユーザー（general）チーム申請前マイページ
 *
 * ヒーロー・機能紹介は front-page.php と同一レイアウト（front-page.css 依存）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$team_registration_url = isset($args['team_registration_url'])
    ? (string) $args['team_registration_url']
    : home_url('/team-registration');
$privacy_url = home_url('/privacy-policy');
$icon_base = aidunite_get_theme_icons_uri();
$team_image_path = get_stylesheet_directory() . '/assets/images/Ainy-Team.png';
$team_image_url = is_readable($team_image_path)
    ? get_stylesheet_directory_uri() . '/assets/images/Ainy-Team.png'
    : get_stylesheet_directory_uri() . '/assets/images/problem-illustration.png';
?>

<div class="mypage-general-landing" role="main">
  <div class="ainy-welcome-page">
    <section class="ainy-welcome-hero" aria-labelledby="mypage-general-welcome-title">
      <div class="ainy-welcome-left">
        <div class="ainy-welcome-badge">
          <span class="ainy-badge-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('basketball', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
          <span class="ainy-badge-text">チーム運営をはじめよう</span>
          <span class="ainy-badge-lines" aria-hidden="true"></span>
        </div>

        <h1 id="mypage-general-welcome-title" class="ainy-welcome-title">
          <span class="ainy-title-line ainy-title-top">あなただけのチームを、</span>
          <span class="ainy-title-line ainy-title-bottom">ここから作成。</span>
        </h1>

        <p class="ainy-welcome-description">
          チームを作成すると、スケジュール管理や<br>
          練習試合の調整ができるようになります。
        </p>

        <a href="<?php echo esc_url($team_registration_url); ?>" class="ainy-create-team-btn" id="quick-icon-team-create">
          <span class="ainy-create-team-btn__icon" aria-hidden="true">✨</span>
          無料でチームを作成する
        </a>

        <div class="ainy-welcome-meta">
          <div class="ainy-welcome-meta__item">⚡ 最短1分で開始</div>
          <div class="ainy-welcome-meta__item">🔒 登録無料</div>
          <div class="ainy-welcome-meta__item">👥 あとからメンバー招待OK</div>
        </div>
      </div>

      <div class="ainy-welcome-right">
        <div class="ainy-visual-card">
          <div class="ainy-visual-bg" aria-hidden="true"></div>
          <img
            src="<?php echo esc_url($team_image_url); ?>"
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

    <section class="ainy-feature-section" aria-labelledby="mypage-general-feature-section-title">
      <h2 id="mypage-general-feature-section-title" class="ainy-section-title">Ainyでできること</h2>

      <div class="ainy-feature-grid">
        <article class="ainy-feature-card">
          <div class="ainy-feature-icon purple">
            <img src="<?php echo esc_url($icon_base . 'person-search.svg'); ?>" alt="" width="28" height="28" decoding="async">
          </div>
          <h3>練習試合を探せる</h3>
          <p>
            自チームにあった<br>
            対戦相手を簡単見つけられます。
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
            <img src="<?php echo esc_url($icon_base . 'sms.svg'); ?>" alt="" width="28" height="28" decoding="async">
          </div>
          <h3>チームで連絡</h3>
          <p>
            チャットでスムーズに<br>
            連絡・調整ができます。
          </p>
        </article>

        <article class="ainy-feature-card">
          <div class="ainy-feature-icon orange">
            <img src="<?php echo esc_url($icon_base . 'handshake.svg'); ?>" alt="" width="28" height="28" decoding="async">
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

  <aside class="mypage-general-trust" aria-label="安心してご利用いただけます">
    <ul class="mypage-general-trust__grid">
      <li class="mypage-general-trust__item">
        <span class="mypage-general-trust__item-label">セキュリティ</span>
        <span class="mypage-general-trust__item-text">安全なデータ管理で保護</span>
      </li>
      <li class="mypage-general-trust__item">
        <span class="mypage-general-trust__item-label">安全利用</span>
        <span class="mypage-general-trust__item-text">チーム運営に適した設計</span>
      </li>
      <li class="mypage-general-trust__item">
        <span class="mypage-general-trust__item-label">サポート</span>
        <span class="mypage-general-trust__item-text">困ったときもサポート体制</span>
      </li>
    </ul>
    <p class="mypage-general-trust__footnote">
      <a href="<?php echo esc_url($privacy_url); ?>" class="mypage-general-trust__link">プライバシーポリシー</a>
    </p>
  </aside>
</div>
