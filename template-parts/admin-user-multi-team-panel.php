<?php
/**
 * 管理ユーザー一覧: マルチチーム診断パネル
 * 親テンプレートの foreach 内で $user_id 等がスコープにあること。
 *
 * @package AidUnite
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!isset($user_id)) {
    return;
}
?>
<div class="meta-info-row" style="background:rgba(102, 126, 234, 0.12); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:3px solid rgba(102, 126, 234, 0.45);">
  <div class="meta-key" style="font-weight:700;">⇆ マルチチーム（managed）</div>
  <div class="meta-value">
    <?php if (!empty($multi_team_needs_attention)) : ?>
      <p style="margin:0 0 var(--spacing-sm); padding:var(--spacing-sm); background:rgba(220, 53, 69, 0.12); border:1px solid rgba(220, 53, 69, 0.35); border-radius:var(--radius-small); color:var(--danger-color); font-weight:600;">
        publish 済みチームが <?php echo (int) count($leader_teams_publish); ?> 件あるのに managed が <?php echo (int) count($managed_team_ids); ?> 件です。「修復」を押すか、ページを再読み込みしてください。
      </p>
    <?php endif; ?>
    <p style="margin:0 0 var(--spacing-sm);"><strong>managed_team_ids（DB）:</strong>
      <code style="word-break:break-all;"><?php echo esc_html(isset($managed_team_ids_raw) && $managed_team_ids_raw !== '' && $managed_team_ids_raw !== false ? (string) $managed_team_ids_raw : '(未設定)'); ?></code></p>
    <p style="margin:0 0 var(--spacing-sm);"><strong>操作中（publish）:</strong>
      <?php
      if (!empty($managed_team_ids)) {
          echo '<code>' . esc_html(wp_json_encode($managed_team_ids)) . '</code>';
          if (count($managed_team_ids) >= 2) {
              echo ' <span style="color:var(--success-color); font-weight:600;">（切り替えUI 有効）</span>';
          }
      } else {
          echo '<span style="color:var(--text-muted);">(空)</span>';
      }
      ?></p>
    <p style="margin:0 0 var(--spacing-sm);"><strong>team_id（レガシー）:</strong>
      <?php echo esc_html((string) (get_user_meta($user_id, 'team_id', true) ?: '(未設定)')); ?></p>
    <p style="margin:0 0 var(--spacing-sm);"><strong>primary_team_id（メイン）:</strong>
      <?php echo esc_html((string) (get_user_meta($user_id, 'primary_team_id', true) ?: '(未設定)')); ?></p>
    <p style="margin:0 0 var(--spacing-sm);"><strong>current_operating_team_id:</strong>
      <?php
      $cur_op = isset($current_operating_team_id) ? $current_operating_team_id : '';
      echo esc_html($cur_op !== '' && $cur_op !== false ? (string) $cur_op : '(未設定)');
      ?></p>
    <p style="margin:0 0 var(--spacing-sm);"><strong>代表者として紐づく team 投稿:</strong></p>
    <?php if (empty($leader_teams_all)) : ?>
      <p style="margin:0 0 var(--spacing-sm); color:var(--danger-color); font-weight:600;">0件 — 2チーム運用でも WordPress 上は team 投稿が紐づいていません。</p>
    <?php else : ?>
      <ul style="margin:0 0 var(--spacing-sm) var(--spacing-base); padding:0; list-style:disc;">
        <?php foreach ($leader_teams_all as $ltid) :
            $ltid = (int) $ltid;
            $lt = get_post($ltid);
            if (!$lt) {
                continue;
            }
            $lt_leader = function_exists('aidunite_team_resolve_leader_user_id')
                ? aidunite_team_resolve_leader_user_id($ltid)
                : 0;
            ?>
          <li><strong>ID <?php echo esc_html((string) $ltid); ?></strong> — <?php echo esc_html($lt->post_title); ?>
            <span style="color:var(--text-secondary);">(<?php echo esc_html($lt->post_status); ?>, author:<?php echo esc_html((string) $lt->post_author); ?>, leader:<?php echo esc_html((string) $lt_leader); ?>)</span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" style="display:flex; gap:var(--spacing-sm); align-items:center; flex-wrap:wrap;">
      <?php wp_nonce_field('reconcile_managed_' . $user_id); ?>
      <input type="hidden" name="reconcile_managed_teams_user_id" value="<?php echo esc_attr($user_id); ?>">
      <button type="submit" class="button button-primary">managed_team_ids を修復</button>
    </form>
  </div>
</div>
