<?php
/**
 * The template for displaying the footer.
 *
 * @since vantage-child 1.0
 * @license GPL 2.0
 */

// トップページ（フロントページ）のみ Ainy フッターを表示
$show_ainy_footer = is_front_page()
	&& empty($GLOBALS['aidunite_team_chat_page'])
	&& (!function_exists('aidunite_is_web_app_page') || !aidunite_is_web_app_page());
?>

<?php if ($show_ainy_footer) : ?>
<!-- Ainy Unite フッター インラインスタイル -->
<style>
/* Ainy Unite フッターデザイン - ヘッダーと統一感のあるミニマルでクリーンなデザイン */
.ainy-footer {
  background: #f8f9fa;
  color: #343a40;
  padding: 4rem 0 2rem;
  margin-top: 4rem;
  border-top: 1px solid #e9ecef;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.ainy-footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.ainy-footer-content {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 3rem;
  margin-bottom: 3rem;
}

/* フッターロゴセクション */
.ainy-footer-logo {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.ainy-footer-logo-icon {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 800;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.ainy-footer-logo-text h3 {
  color: #1a1a1a;
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  letter-spacing: -0.02em;
}

.ainy-footer-logo-text p {
  color: #6c757d;
  font-size: 0.95rem;
  line-height: 1.5;
  margin: 0;
  max-width: 300px;
}

/* SNSアイコン */
.ainy-footer-social {
  display: flex;
  gap: 1rem;
  margin-top: 1.5rem;
}

.ainy-social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #ffffff;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  color: #6c757d;
  text-decoration: none;
  transition: all 0.2s ease;
}

.ainy-social-link:hover {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* フッターセクション */
.ainy-footer-section h3 {
  color: #1a1a1a;
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0 0 1.5rem 0;
  letter-spacing: -0.01em;
}

.ainy-footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.ainy-footer-links li {
  margin-bottom: 0.75rem;
}

.ainy-footer-links a {
  color: #6c757d;
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  display: inline-block;
}

.ainy-footer-links a:hover {
  color: #3b82f6;
  transform: translateX(4px);
}

/* フッターボトム */
.ainy-footer-bottom {
  border-top: 1px solid #e9ecef;
  padding-top: 2rem;
  text-align: center;
}

.ainy-footer-bottom p {
  color: #6c757d;
  font-size: 0.9rem;
  margin: 0;
}

/* モバイル用ボトムナビ（未ログイン等・統一ナビ .ainy-bottom-nav--app は web-app-integrated-ui.css） */
.ainy-bottom-nav:not(.ainy-bottom-nav--app) {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: #ffffff;
  border-top: 1px solid #e9ecef;
  padding: 0.75rem 0;
  z-index: 1000;
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
}

.ainy-bottom-nav:not(.ainy-bottom-nav--app):not(.ainy-bottom-nav--mypage-v2) .ainy-bottom-nav-inner {
  display: flex;
  justify-content: space-around;
  align-items: center;
  width: 100%;
}

.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  color: #6c757d;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.2s ease;
  padding: 0.5rem;
  border-radius: 8px;
  min-width: 60px;
}

.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link svg {
  margin-bottom: 0.25rem;
  transition: all 0.2s ease;
}

.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link:hover,
.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link.active {
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.1);
}

.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link:hover svg,
.ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link.active svg {
  transform: scale(1.1);
}

/* レスポンシブデザイン */
@media (max-width: 900px) {
  .ainy-footer-content {
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
  }

  .ainy-footer-logo {
    grid-column: 1 / -1;
    margin-bottom: 2rem;
  }
}

@media (max-width: 600px) {
  .ainy-footer {
    padding: 3rem 0 1.5rem;
  }

  .ainy-footer-container {
    padding: 0 16px;
  }

  .ainy-footer-content {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .ainy-footer-logo {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }

  .ainy-footer-logo-text p {
    max-width: none;
  }

  .ainy-footer-social {
    margin-top: 1rem;
  }

  .ainy-footer-bottom {
    padding-top: 1.5rem;
  }

  /* モバイル用ボトムナビゲーションの調整（統一ナビ除外） */
  .ainy-bottom-nav:not(.ainy-bottom-nav--app) {
    padding: 0.5rem 0;
  }

  .ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link {
    font-size: 0.75rem;
    min-width: 50px;
  }

  .ainy-bottom-nav:not(.ainy-bottom-nav--app) .ainy-bottom-nav-link svg {
    width: 20px;
    height: 20px;
  }
}
</style>
<?php endif; ?>

<?php
// header.php で開いたラッパーを閉じる（get_footer 時点で .full-container → #main → #page-wrapper の順）
if (!empty($GLOBALS['aidunite_layout_wrapper_depth'])) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 閉じタグのみ
	echo "</div><!-- .full-container -->\n";
	echo "</div><!-- #main.site-main -->\n";
	echo "</div><!-- #page-wrapper -->\n";
	unset($GLOBALS['aidunite_layout_wrapper_depth']);
}
?>

<!-- Ainy Unite フッター（トップページのみ） -->
<?php if ($show_ainy_footer) : ?>
<footer class="ainy-footer">
    <div class="ainy-footer-container">
        <div class="ainy-footer-content">
            <!-- 左側: ロゴとキャッチコピー、SNSアイコン -->
            <div class="ainy-footer-section">
                <div class="ainy-footer-logo">
                    <div class="ainy-footer-logo-icon">A</div>
                    <div class="ainy-footer-logo-text">
                        <h3>Ainy Unite</h3>
                        <p>スポーツチームの運営をサポートする、新しいプラットフォーム</p>
                    </div>
                </div>
                <div class="ainy-footer-social">
                    <a href="https://twitter.com/ainyunite" class="ainy-social-link" title="X (Twitter)" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>
                    <a href="https://facebook.com/ainyunite" class="ainy-social-link" title="Facebook" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="https://instagram.com/ainyunite" class="ainy-social-link" title="Instagram" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 6.62 5.367 11.987 11.988 11.987 6.62 0 11.987-5.367 11.987-11.987C24.014 5.367 18.637.001 12.017.001zM8.449 16.988c-1.297 0-2.448-.49-3.323-1.297C4.198 14.895 3.708 13.744 3.708 12.447s.49-2.448 1.297-3.323c.875-.807 2.026-1.297 3.323-1.297s2.448.49 3.323 1.297c.807.875 1.297 2.026 1.297 3.323s-.49 2.448-1.297 3.323c-.875.807-2.026 1.297-3.323 1.297zm7.83-9.781c-.49 0-.98.196-1.353.569-.373.373-.569.863-.569 1.353s.196.98.569 1.353c.373.373.863.569 1.353.569s.98-.196 1.353-.569c.373-.373.569-.863.569-1.353s-.196-.98-.569-1.353c-.373-.373-.863-.569-1.353-.569z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- 中央: ナビゲーション -->
            <div class="ainy-footer-section">
                <h3>ナビゲーション</h3>
                <ul class="ainy-footer-links">
                    <li><a href="<?php echo home_url('/about'); ?>">会社概要</a></li>
                    <li><a href="<?php echo home_url('/guide'); ?>">使い方ガイド</a></li>
                    <li><a href="<?php echo home_url('/faq'); ?>">よくある質問</a></li>
                    <li><a href="<?php echo home_url('/contact'); ?>">お問い合わせ</a></li>
                    <li><a href="<?php echo home_url('/feedback'); ?>">フィードバック</a></li>
                </ul>
            </div>

            <!-- 右側: 法的情報 -->
            <div class="ainy-footer-section">
                <h3>法的情報</h3>
                <ul class="ainy-footer-links">
                    <li><a href="<?php echo home_url('/privacy-policy'); ?>">プライバシーポリシー</a></li>
                    <li><a href="<?php echo home_url('/terms-of-service'); ?>">利用規約</a></li>
                    <li><a href="<?php echo home_url('/cookie-policy'); ?>">Cookieポリシー</a></li>
                    <li><a href="<?php echo home_url('/disclaimer'); ?>">免責事項</a></li>
                </ul>
            </div>
        </div>

        <!-- フッターボトム -->
        <div class="ainy-footer-bottom">
            <p>&copy; 2025 Ainy Unite. All rights reserved.</p>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php if (is_page('team-registration') || is_page('team-registration-complete') || (function_exists('aidunite_is_team_registration_complete_request') && aidunite_is_team_registration_complete_request())) : ?>
<footer class="team-reg-site-footer" role="contentinfo">
  <ul class="team-reg-site-footer__links">
    <li><a href="<?php echo esc_url(home_url('/terms/')); ?>">利用規約</a></li>
    <li><a href="<?php echo esc_url(home_url('/privacy/')); ?>">プライバシーポリシー</a></li>
  </ul>
  <p class="team-reg-site-footer__copy">&copy; <?php echo esc_html(gmdate('Y')); ?> Ainy</p>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>

</div><!-- #page -->

<?php
$show_bottom_nav = function_exists('aidunite_should_show_bottom_nav') && aidunite_should_show_bottom_nav();
?>
<?php if ($show_bottom_nav) : ?>
<!-- ボトムナビ（チャットページでは非表示＝下端は chat input area のみ・LINE風） -->
    <?php get_template_part('template-parts/ainy', 'bottom-nav'); ?>
<?php endif; ?>

</body>
</html>
