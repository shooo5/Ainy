<?php
/**
 * Template Name: 退会完了
 * 退会確認リンククリック後の表示。スラッグを confirm-withdrawal にした固定ページで使用。
 * docs/withdrawal-risks-and-gaps.md 1.2, 5.6
 * 代表者: step=rep_confirm で「相手に通知するか」選択、step=rep_scheduled で受付完了表示。
 */

get_header();

$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
$step = isset($_GET['step']) ? sanitize_text_field(wp_unslash($_GET['step'])) : '';
$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$is_error = ($error === 'invalid');
$is_rep_confirm = ($step === 'rep_confirm' && $token !== '');
$is_rep_scheduled = ($step === 'rep_scheduled');
?>

<div class="confirm-withdrawal-container" style="max-width: 560px; margin: 2rem auto; padding: 0 var(--spacing-base, 1rem);">
  <section class="confirm-withdrawal-section" style="background: var(--bg-light, #f5f5f5); border-radius: var(--radius-base, 8px); padding: 2rem; box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.08));">
    <?php if ($is_error) : ?>
      <h1 style="margin-top: 0; color: var(--danger-color, #dc3545);">リンクが無効です</h1>
      <p>この退会確認リンクは無効か、有効期限（24時間）が過ぎています。既に退会手続きが完了している場合もこの画面になります。</p>
      <p>退会をご希望の場合は、<a href="<?php echo esc_url(home_url('/member-withdrawal')); ?>">退会申請ページ</a>からあらためて申請してください。</p>
      <p>メールが届かない場合は迷惑メールフォルダやドメインの受信設定をご確認いただくか、お問い合わせください。</p>
    <?php elseif ($is_rep_confirm) : ?>
      <h1 style="margin-top: 0;">代表者退会の確認</h1>
      <p>1か月後にチームを解散し、ご本人のアカウントを削除する手続きを進めます。チームの他メンバーには自動で通知されます。</p>
      <p><strong>決まっている練習試合の相手チームに、解散の旨を通知しますか？</strong></p>
      <form method="post" action="<?php echo esc_url(home_url('/confirm-withdrawal')); ?>">
        <input type="hidden" name="rep_withdrawal_confirm" value="1">
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
        <p style="margin: 1rem 0;">
          <label style="display: inline-block; margin-right: 1rem;"><input type="radio" name="notify_opponents" value="1" required> する（相手代表者に通知する）</label>
          <label style="display: inline-block;"><input type="radio" name="notify_opponents" value="0"> しない</label>
        </p>
        <p style="margin-top: 1.5rem;">
          <button type="submit" class="button" style="padding: 0.5rem 1rem; background: var(--primary-color, #0073aa); color: #fff; border: none; border-radius: var(--radius-small, 4px); cursor: pointer;">退会手続きを確定する</button>
        </p>
      </form>
    <?php elseif ($is_rep_scheduled) : ?>
      <h1 style="margin-top: 0;">退会手続きを受け付けました</h1>
      <p>代表者退会の手続きを受け付けました。1か月後にチームが解散し、ご本人のアカウントが削除されます。</p>
      <p>チームの他メンバーには通知済みです。相手に通知を選んだ場合は、決まっている試合の相手チーム代表者にも通知しています。</p>
      <p>それまでに取り消しをご希望の場合はお問い合わせください。</p>
      <p style="margin-top: 1.5rem;">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="button" style="display: inline-block; padding: 0.5rem 1rem; background: var(--primary-color, #0073aa); color: #fff; text-decoration: none; border-radius: var(--radius-small, 4px);">トップページへ</a>
      </p>
    <?php else : ?>
      <h1 style="margin-top: 0;">退会が完了しました</h1>
      <p>ご利用ありがとうございました。退会手続きが完了しています。</p>
      <p>データの復元はできません。ご不明な点はお問い合わせください。</p>
      <p style="margin-top: 1.5rem;">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="button" style="display: inline-block; padding: 0.5rem 1rem; background: var(--primary-color, #0073aa); color: #fff; text-decoration: none; border-radius: var(--radius-small, 4px);">トップページへ</a>
      </p>
    <?php endif; ?>
  </section>
</div>

<?php get_footer(); ?>
