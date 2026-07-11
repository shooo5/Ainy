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
