<?php
/**
 * Template Name: FAQ
 */

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$faq_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class'    => 'page-faq',
        'title'         => 'FAQ',
        'subtitle'      => 'よくある質問と回答をご確認ください',
        'content_class' => 'ainy-webapp-content--support',
        'back'          => true,
        'back_url'      => home_url('/mypage/'),
    ]);
    $faq_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container page-faq">';
    echo '<div class="dashboard-header"><h1>FAQ</h1><p>よくある質問と回答をご確認ください</p></div>';
}
?>

  <section class="dashboard-section">
    <h2>📋 よくある質問</h2>
    <div class="main-content-area">
      <div class="faq-container">
        <!-- アカウント関連 -->
        <div class="faq-category">
          <h3>👤 アカウント関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>アカウントの作成方法を教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>トップページの「新規登録」ボタンから、メールアドレスとパスワードを入力してアカウントを作成できます。登録後、確認メールが送信されますので、メール内のリンクをクリックしてアカウントを有効化してください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>パスワードを忘れた場合の対処法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>ログイン画面の「パスワードを忘れた方」リンクをクリックし、登録済みのメールアドレスを入力してください。パスワードリセット用のリンクがメールで送信されます。</p>
            </div>
          </div>
        </div>

        <!-- チーム関連 -->
        <div class="faq-category">
          <h3>🏆 チーム関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>チームの作成方法を教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>マイページから「チーム作成」を選択し、チーム名、活動地域、スポーツ種目などの基本情報を入力してください。作成後、メンバーを招待できます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>チームメンバーの招待方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>チーム管理画面から「メンバー招待」を選択し、招待したい方のメールアドレスを入力してください。招待メールが送信され、承認後にチームに参加できます。</p>
            </div>
          </div>
        </div>

        <!-- スケジュール関連 -->
        <div class="faq-category">
          <h3>📅 スケジュール関連</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>練習スケジュールの作成方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>スケジュール管理画面から「新規作成」を選択し、日時、場所、内容を入力してください。メンバー全員に通知が送信され、出欠の回答を集計できます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>スケジュールの変更・キャンセル方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>スケジュール詳細画面から「編集」または「削除」を選択できます。変更・削除時は、メンバー全員に自動で通知が送信されます。</p>
            </div>
          </div>
        </div>

        <!-- マッチング関連 -->
        <div class="faq-category">
          <h3>🤝 試合募集・マッチング</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>練習試合の募集の流れを教えてください</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>①スケジュールを登録 → ②「試合募集」として公開 → ③マッチボードで相手を探す → ④申請・承認 → ⑤試合成立、の順です。成立後はチャットで詳細を調整できます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>申請を承認・却下するには？</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>通知一覧またはマッチ管理画面から申請を開き、「承認」または「却下」を選択してください。承認すると試合が成立し、双方に通知が届きます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>募集条件が変わったと表示される</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>申請後に募集側が会場・時間・性別条件などを変更した場合、再確認が必要になります。マッチ詳細画面の案内に従い、再申請または承諾を行ってください。</p>
            </div>
          </div>
        </div>

        <!-- 出欠・保護者 -->
        <div class="faq-category">
          <h3>✅ 出欠・保護者連絡（Clubプラン）</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>出欠の回答方法</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>スケジュール詳細または出欠画面から「出席」「欠席」「未定」を選択してください。締切前であれば変更できます。Clubプランでご利用いただけます。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>保護者を招待するには？</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>チーム設定の「保護者招待」からQRコードまたはリンクを発行し、保護者の方に共有してください。保護者は専用の登録画面から参加できます。</p>
            </div>
          </div>
        </div>

        <!-- 料金関連 -->
        <div class="faq-category">
          <h3>💰 料金・お支払い</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>料金プランの違いは？</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p><strong>Matchプラン</strong>は試合募集・スケジュール・チャットなどマッチング機能向けです。<strong>Clubプラン</strong>はMatchの機能に加え、出欠管理・保護者連絡・月謝管理（Stripe）・メンバー管理が利用できます。詳細は<a href="<?php echo esc_url(home_url('/plan-info')); ?>">料金プラン</a>をご覧ください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>無料トライアルについて</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>初回は2ヶ月間の無料トライアルをご利用いただけます。トライアル終了前に<a href="<?php echo esc_url(home_url('/payment-setup')); ?>">支払い設定</a>でカード登録をお願いします。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>支払いに失敗した・カードを変更したい</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p><a href="<?php echo esc_url(home_url('/payment-setup')); ?>">支払い設定</a>からカード情報を更新してください。解約・プラン変更でお困りの場合は<a href="<?php echo esc_url(home_url('/contact')); ?>">お問い合わせ</a>ください（チーム名と登録メールをお知らせください）。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>MatchからClubへアップグレードするには？</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>初回試合成立後、支払い設定画面からClubプランへアップグレードできます。成立前はMatchプランのご利用となります。</p>
            </div>
          </div>
        </div>

        <!-- 技術サポート -->
        <div class="faq-category">
          <h3>🔧 技術サポート</h3>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>アプリが正常に動作しない場合</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>ブラウザのキャッシュをクリアするか、別のブラウザでお試しください。問題が解決しない場合は、お問い合わせフォームからご連絡ください。</p>
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question" onclick="toggleFaq(this)">
              <span>推奨ブラウザについて</span>
              <span class="faq-toggle">+</span>
            </div>
            <div class="faq-answer">
              <p>Chrome、Firefox、Safari、Edgeの最新版での動作を推奨しています。Internet Explorerはサポート対象外です。</p>
            </div>
          </div>
        </div>
      </div>

      <!-- お問い合わせセクション -->
      <div class="contact-section">
        <h3>📞 お問い合わせ</h3>
        <p>上記のFAQで解決しない場合は、お気軽にお問い合わせください。</p>
        <a href="<?php echo esc_url(home_url('/contact')); ?>" class="btn btn-primary">お問い合わせフォーム</a>
        <a href="<?php echo esc_url(home_url('/guide')); ?>" class="btn btn-secondary">使い方ガイド</a>
      </div>
    </div>
  </section>

<?php
if ($faq_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}

get_footer();
