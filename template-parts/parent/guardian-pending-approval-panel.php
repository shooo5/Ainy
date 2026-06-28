<?php
/**
 * 保護者承認待ちリスト（タブは team-members-board 側）
 *
 * @package AidUnite
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$team_id = (int) ($args['team_id'] ?? 0);
$pending_approval_ctx = is_array($args['pending_approval_ctx'] ?? null) ? $args['pending_approval_ctx'] : [];
$pending_parents = is_array($pending_approval_ctx['pending_parents'] ?? null) ? $pending_approval_ctx['pending_parents'] : [];
?>

<section class="team-members-board__pending" aria-labelledby="team-members-pending-heading">
  <h3 class="team-members-board__section-title" id="team-members-pending-heading">
    <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('group', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline') : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    保護者の承認待ち
  </h3>
  <p class="team-members-board__section-lead">
    QRからの参加申請です。内容を確認のうえ、承認または却下してください。
  </p>

  <?php if ($pending_parents !== []) : ?>
  <div class="team-members-board__pending-list">
    <?php foreach ($pending_parents as $pending_parent) : ?>
      <?php
      if (!is_array($pending_parent)) {
          continue;
      }
      $parent_user_id = (int) ($pending_parent['user_id'] ?? 0);
      if ($parent_user_id <= 0) {
          continue;
      }
      $display_name = (string) ($pending_parent['display_name'] ?? '');
      $parent_kana = (string) ($pending_parent['parent_kana'] ?? '');
      $parent_email = (string) ($pending_parent['parent_email'] ?? '');
      $parent_phone = (string) ($pending_parent['parent_phone'] ?? '');
      $requested_at = (string) ($pending_parent['requested_at'] ?? '');
      $invite_type = (string) ($pending_parent['invite_type'] ?? '');
      ?>
    <article class="team-members-board__pending-card">
      <div class="team-members-board__pending-card-main">
        <h4 class="team-members-board__pending-name"><?php echo esc_html($display_name); ?></h4>
        <dl class="team-members-board__pending-meta">
          <?php if ($parent_kana !== '') : ?>
          <div><dt>フリガナ</dt><dd><?php echo esc_html($parent_kana); ?></dd></div>
          <?php endif; ?>
          <div><dt>メール</dt><dd><?php echo esc_html($parent_email); ?></dd></div>
          <?php if ($parent_phone !== '') : ?>
          <div><dt>電話</dt><dd><?php echo esc_html($parent_phone); ?></dd></div>
          <?php endif; ?>
          <div><dt>申請日</dt><dd><?php echo esc_html($requested_at !== '' ? $requested_at : '—'); ?></dd></div>
          <?php if ($invite_type !== '') : ?>
          <div><dt>経路</dt><dd><?php echo esc_html(function_exists('aidunite_parent_read_invite_type_label') ? aidunite_parent_read_invite_type_label($invite_type) : '—'); ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>
      <div class="team-members-board__pending-actions">
        <form method="post">
          <input type="hidden" name="team_id" value="<?php echo esc_attr((string) $team_id); ?>">
          <input type="hidden" name="parent_user_id" value="<?php echo esc_attr((string) $parent_user_id); ?>">
          <?php wp_nonce_field('approve_parent_' . $parent_user_id, 'approve_parent_nonce'); ?>
          <button type="submit" name="approve_parent" value="1" class="team-members-board__btn team-members-board__btn--primary team-members-board__btn--compact">承認する</button>
        </form>
        <form method="post">
          <input type="hidden" name="team_id" value="<?php echo esc_attr((string) $team_id); ?>">
          <input type="hidden" name="parent_user_id" value="<?php echo esc_attr((string) $parent_user_id); ?>">
          <?php wp_nonce_field('reject_parent_' . $parent_user_id, 'reject_parent_nonce'); ?>
          <button type="submit" name="reject_parent" value="1" class="team-members-board__btn team-members-board__btn--outline team-members-board__btn--compact team-members-board__btn--danger" onclick="return confirm('この参加申請を却下しますか？');">却下</button>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="team-members-board__empty team-members-board__empty--pending">
    <p>承認待ちの保護者はいません。</p>
  </div>
  <?php endif; ?>
</section>
