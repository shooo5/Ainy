<?php
/*
Template Name: 会員退会申請ページ
docs/team-withdrawal-flow.md, withdrawal-legal-disclosure-spec.md
*/

// ログインチェック
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

$current_user = wp_get_current_user();
$is_representative = false;
if ($current_user->ID) {
    $role = get_user_meta($current_user->ID, 'aidunite_role', true);
    if ($role === 'team_leader') {
        $is_representative = true;
    } else {
        $led_teams = get_posts([
            'post_type' => 'team',
            'author' => $current_user->ID,
            'post_status' => 'any',
            'posts_per_page' => 1,
        ]);
        if (empty($led_teams)) {
            $led = new WP_Query([
                'post_type' => 'team',
                'meta_query' => [['key' => 'team_leader_id', 'value' => $current_user->ID]],
                'posts_per_page' => 1,
            ]);
            $is_representative = $led->have_posts();
        } else {
            $is_representative = true;
        }
    }
}

get_header();
?>

<!-- <style>タグ削除済み：スタイルは aidunite-style.css へ統合 -->

<div class="member-withdrawal-container">
  <h1>会員退会申請</h1>

  <div class="withdrawal-form-wrapper">
    <?php if ($is_representative) : ?>
    <div class="warning-box representative-notice" style="border-left: 4px solid var(--danger-color, #dc3545);">
      <h3>⚠️ 代表者として登録されています</h3>
      <p>退会するには、次の<strong>いずれか</strong>を選んでください。</p>
      <div class="form-section rep-withdrawal-choice" id="rep-withdrawal-choice-section" aria-required="true">
        <p class="form-group">
          <label><input type="radio" name="rep_withdrawal_choice" value="transfer" id="rep_choice_transfer" <?php echo $is_representative ? '' : 'disabled'; ?>>
            <strong>代表者を他のメンバーに譲る</strong> … チーム設定から代表者を変更してから退会する</label>
        </p>
        <p class="form-group" id="rep-transfer-link-wrap" style="display: none; margin-left: 1.5rem;">
          <a href="<?php echo esc_url(aidunite_get_team_settings_page_url()); ?>">チーム設定ページへ</a>で代表者を譲ったうえで、退会申請を行ってください。
        </p>
        <p class="form-group">
          <label><input type="radio" name="rep_withdrawal_choice" value="dissolve" id="rep_choice_dissolve" <?php echo $is_representative ? '' : 'disabled'; ?>>
            <strong>チームを解散して退会する</strong> … 1か月後にチームが解散し、他メンバーに通知のうえ削除されます。月謝は解散日をもって解約されます。</label>
        </p>
      </div>
      <p class="form-note" id="rep-choice-required-note" style="display: none;">上記のいずれかを選択しないと退会申請を提出できません。</p>
    </div>
    <?php endif; ?>

    <div class="warning-box">
      <h3>⚠️ 退会について</h3>
      <p>退会申請を提出すると、<strong>ご登録のメールアドレスに確認メール</strong>が送信されます。メール内のリンクをクリックすると退会が完了し、以下の処理が行われます。</p>
      <ul>
        <li>アカウントが削除され、ログインできなくなります</li>
        <li>課金（システム利用料・月謝）は退会処理の直前に<strong>自動で解約</strong>されます。解約月の翌月1日までの請求が発生する場合があります。</li>
        <li>チーム・スケジュール・試合ログなどは削除されます</li>
        <li>メッセージ・支払い履歴等は運営側で履歴として保持します</li>
        <li>退会後は復旧できません</li>
      </ul>
      <p><strong>確認メールのリンクは24時間有効です。</strong>届かない場合は迷惑メールフォルダやドメインの受信設定をご確認ください。お問い合わせも可能です。</p>
      <p><strong>リンクをクリックするまでは取り消し可能です。</strong>クリック後に退会が完了し、取り消しはできません。</p>
      <p><strong>本当に退会されますか？</strong></p>
    </div>

    <div class="user-info">
      <h3>現在のアカウント情報</h3>
      <?php
      $user_meta = get_user_meta($current_user->ID);
      ?>
      <p><strong>ユーザー名：</strong><?php echo esc_html($current_user->user_login); ?></p>
      <p><strong>氏名：</strong><?php echo esc_html($current_user->display_name); ?></p>
      <p><strong>メールアドレス：</strong><?php echo esc_html($current_user->user_email); ?></p>
      <p><strong>登録日：</strong><?php echo esc_html(date('Y年m月d日', strtotime($current_user->user_registered))); ?></p>
    </div>

    <div id="error-message" class="error-message"></div>
    <div id="success-message" class="success-message"></div>
    <div id="loading" class="loading">送信中...</div>

    <form id="member-withdrawal-form" class="ajax-form" method="post">
      <?php wp_nonce_field('save_member_withdrawal', 'member_withdrawal_nonce'); ?>
      <input type="hidden" name="action" value="member_withdrawal">
      <input type="hidden" name="user_id" value="<?php echo esc_attr($current_user->ID); ?>">

      <div class="form-section">
        <h3>退会理由</h3>

        <div class="form-group">
          <label for="withdrawal_reason">退会理由 <span class="required">*</span></label>
          <select id="withdrawal_reason" name="withdrawal_reason" required>
            <option value="">選択してください</option>
            <option value="no_longer_need">サービスを利用する必要がなくなった</option>
            <option value="not_satisfied">サービスに満足できない</option>
            <option value="too_expensive">料金が高い</option>
            <option value="difficult_to_use">使いにくい</option>
            <option value="privacy_concerns">プライバシーの懸念</option>
            <option value="other">その他</option>
          </select>
        </div>

        <div class="form-group">
          <label for="withdrawal_reason_detail">詳細理由</label>
          <textarea id="withdrawal_reason_detail" name="withdrawal_reason_detail" rows="4" placeholder="退会理由の詳細をお聞かせください（任意）"></textarea>
        </div>
      </div>

      <div class="form-section">
        <h3>確認事項</h3>

        <div class="form-group">
          <label>
            <input type="checkbox" id="confirm_data_deletion" name="confirm_data_deletion" required>
            <span>データの削除に同意します <span class="required">*</span></span>
          </label>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" id="confirm_no_refund" name="confirm_no_refund" required>
            <span>退会後の返金はないことを理解しています <span class="required">*</span></span>
          </label>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" id="confirm_no_recovery" name="confirm_no_recovery" required>
            <span>退会後の復旧はできないことを理解しています <span class="required">*</span></span>
          </label>
        </div>
      </div>

      <div class="form-section">
        <h3>最終確認</h3>

        <div class="form-group">
          <label for="final_confirmation">「退会する」と入力してください <span class="required">*</span></label>
          <input type="text" id="final_confirmation" name="final_confirmation" placeholder="退会する" required>
          <div class="form-note">上記の文字列を正確に入力してください</div>
        </div>
      </div>

      <button type="submit" class="submit-button" id="submit-button" <?php echo $is_representative ? ' disabled aria-describedby="rep-choice-required-note"' : ''; ?>>
        退会申請を提出する
      </button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('member-withdrawal-form');
  const submitButton = document.getElementById('submit-button');
  const loading = document.getElementById('loading');
  const errorMessage = document.getElementById('error-message');
  const successMessage = document.getElementById('success-message');
  const finalConfirmation = document.getElementById('final_confirmation');
  const repSection = document.getElementById('rep-withdrawal-choice-section');
  const repChoiceTransfer = document.getElementById('rep_choice_transfer');
  const repChoiceDissolve = document.getElementById('rep_choice_dissolve');
  const repTransferLinkWrap = document.getElementById('rep-transfer-link-wrap');
  const repChoiceRequiredNote = document.getElementById('rep-choice-required-note');
  const isRep = !!repSection;

  if (isRep) {
    function updateRepChoiceUI() {
      const dissolve = repChoiceDissolve && repChoiceDissolve.checked;
      if (repTransferLinkWrap) repTransferLinkWrap.style.display = repChoiceTransfer && repChoiceTransfer.checked ? 'block' : 'none';
      if (repChoiceRequiredNote) repChoiceRequiredNote.style.display = repChoiceTransfer && repChoiceTransfer.checked ? 'block' : 'none';
      submitButton.disabled = !dissolve;
    }
    if (repChoiceTransfer) repChoiceTransfer.addEventListener('change', updateRepChoiceUI);
    if (repChoiceDissolve) repChoiceDissolve.addEventListener('change', updateRepChoiceUI);
    updateRepChoiceUI();
  }

  // 最終確認のバリデーション
  function validateFinalConfirmation() {
    if (finalConfirmation.value !== '退会する') {
      finalConfirmation.setCustomValidity('「退会する」と正確に入力してください');
    } else {
      finalConfirmation.setCustomValidity('');
    }
  }

  finalConfirmation.addEventListener('input', validateFinalConfirmation);

  // フォーム送信処理
  form.addEventListener('submit', function(e) {
    e.preventDefault();

    if (isRep && (!repChoiceDissolve || !repChoiceDissolve.checked)) {
      errorMessage.textContent = '退会するには「チームを解散して退会する」を選択してください。代表者を譲る場合はチーム設定から行ってから退会申請してください。';
      errorMessage.style.display = 'block';
      if (repChoiceRequiredNote) repChoiceRequiredNote.style.display = 'block';
      return;
    }

    // 最終確認の再チェック
    if (finalConfirmation.value !== '退会する') {
      errorMessage.textContent = '最終確認の入力が正しくありません。';
      errorMessage.style.display = 'block';
      return;
    }

    // 確認ダイアログ
    if (!confirm('本当に退会申請を提出しますか？\nこの操作は取り消すことができません。')) {
      return;
    }

    // フォームデータの取得
    const formData = new FormData(form);

    // 送信ボタンを無効化
    submitButton.disabled = true;
    loading.style.display = 'block';
    errorMessage.style.display = 'none';
    successMessage.style.display = 'none';

    // AJAX送信
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      loading.style.display = 'none';

      if (data.success) {
        successMessage.textContent = data.data.message || '退会確認メールを送信しました。メールのリンクから退会を完了してください。';
        successMessage.style.display = 'block';
        form.reset();

        // 10秒後にログアウトしてホームへ（メール確認を促す時間を考慮）
        setTimeout(() => {
          window.location.href = '<?php echo esc_url(wp_logout_url(home_url())); ?>';
        }, 10000);
      } else {
        errorMessage.textContent = data.data.message || '退会申請に失敗しました。';
        errorMessage.style.display = 'block';
        submitButton.disabled = false;
      }
    })
    .catch(error => {
      loading.style.display = 'none';
      errorMessage.textContent = '通信エラーが発生しました。';
      errorMessage.style.display = 'block';
      submitButton.disabled = false;
      console.error('Error:', error);
    });
  });
});
</script>

<?php get_footer(); ?>
