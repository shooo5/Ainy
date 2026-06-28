<?php
/**
 * チームメンバー一覧ボード（代表者 / 保護者）
 *
 * @package AidUnite
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$ctx = is_array($args['ctx'] ?? null) ? $args['ctx'] : [];
if (empty($ctx['ok'])) {
    return;
}

$is_parent = !empty($ctx['is_parent_view']);
$board_class = $is_parent ? 'team-members-board' : 'team-members-board team-members-board--leader';
$rows = is_array($ctx['rows'] ?? null) ? $ctx['rows'] : [];
$parent_rows = is_array($ctx['parent_rows'] ?? null) ? $ctx['parent_rows'] : [];
$chips = is_array($ctx['team_chips'] ?? null) ? $ctx['team_chips'] : [];
$chips_compact = !empty($ctx['team_chips_compact']);
$show_nickname = !empty($ctx['show_nickname_column']);
$show_avatar = !empty($ctx['show_avatar_column']);
$theme_key = (string) ($ctx['theme_key'] ?? 'boys');
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = 'boys';
}

$show_leader_tabs = !$is_parent && !empty($ctx['show_leader_tabs']);
$list_tab = (string) ($ctx['list_tab'] ?? 'members');
$parent_tab = (string) ($ctx['parent_tab'] ?? 'registered');
$pending_approval_ctx = is_array($args['pending_approval_ctx'] ?? null) ? $args['pending_approval_ctx'] : [];
$pending_count = (int) ($pending_approval_ctx['pending_count'] ?? ($ctx['parent_counts']['pending'] ?? 0));
$team_id = (int) ($ctx['team_id'] ?? 0);

$is_members_tab = $list_tab !== 'parents';
$is_parents_list = $list_tab === 'parents';
$is_parent_pending_tab = $is_parents_list && $parent_tab === 'pending';

$team_query = $team_id > 0 ? ['team_id' => $team_id] : [];
$members_tab_url = home_url('/team-members?' . http_build_query(array_merge($team_query, ['list_tab' => 'members'])));
$parents_tab_url = home_url('/team-members?' . http_build_query(array_merge($team_query, ['list_tab' => 'parents'])));
$parents_pending_url = home_url('/team-members?' . http_build_query(array_merge($team_query, ['list_tab' => 'parents', 'parent_tab' => 'pending'])));
?>

<div class="<?php echo esc_attr($board_class); ?>" data-team-members-board data-team-theme="<?php echo esc_attr($theme_key); ?>">
  <div class="team-members-board__header">
    <div class="team-members-board__title-wrap">
      <span class="team-members-board__role-badge"><?php echo esc_html((string) ($ctx['role_badge'] ?? '')); ?></span>
      <h2 class="team-members-board__title"><?php echo esc_html((string) ($ctx['page_title'] ?? '')); ?></h2>
    </div>
    <div class="team-members-board__actions">
      <a href="<?php echo esc_url((string) ($ctx['add_url'] ?? home_url('/player-add'))); ?>" class="team-members-board__btn team-members-board__btn--primary">
        <?php echo aidunite_render_theme_icon($is_parents_list ? 'mail' : 'add', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php echo esc_html((string) ($ctx['add_label'] ?? '追加')); ?>
      </a>
      <?php if (!empty($ctx['can_export']) && $is_members_tab) : ?>
      <button type="button" class="team-members-board__btn team-members-board__btn--outline" data-team-members-export>
        <?php echo aidunite_render_theme_icon('save', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        一覧をエクスポート
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="team-members-board__team-card">
    <img src="<?php echo esc_url((string) ($ctx['team_logo'] ?? '')); ?>" alt="" class="team-members-board__team-logo" width="56" height="56">
    <div>
      <p class="team-members-board__team-name"><?php echo esc_html((string) ($ctx['team_name'] ?? 'チーム')); ?></p>
      <?php if (!empty($chips)) : ?>
      <div class="team-members-board__chips">
        <?php foreach ($chips as $index => $chip) :
            if ($chips_compact && $index > 0) {
                break;
            }
            if (!is_array($chip)) {
                continue;
            }
            ?>
        <span class="team-members-board__chip">
          <span class="team-members-board__chip-label"><?php echo esc_html((string) ($chip['label'] ?? '')); ?>：</span>
          <?php echo esc_html((string) ($chip['value'] ?? '')); ?>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($show_leader_tabs) : ?>
  <nav class="team-members-board__tabs" aria-label="メンバー管理タブ">
    <a href="<?php echo esc_url($members_tab_url); ?>" class="team-members-board__tab<?php echo $is_members_tab ? ' is-active' : ''; ?>">
      メンバー一覧
    </a>
    <a href="<?php echo esc_url($parents_tab_url); ?>" class="team-members-board__tab<?php echo $is_parents_list ? ' is-active' : ''; ?>">
      保護者一覧
      <?php if ($pending_count > 0) : ?>
      <span class="team-members-board__tab-badge"><?php echo (int) $pending_count; ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <?php endif; ?>

  <?php if ($show_leader_tabs && $is_parents_list) : ?>
  <nav class="team-members-board__subtabs" aria-label="保護者一覧サブタブ">
    <a href="<?php echo esc_url($parents_tab_url); ?>" class="team-members-board__subtab<?php echo !$is_parent_pending_tab ? ' is-active' : ''; ?>">
      登録済み
    </a>
    <a href="<?php echo esc_url($parents_pending_url); ?>" class="team-members-board__subtab<?php echo $is_parent_pending_tab ? ' is-active' : ''; ?>">
      承認待ち
      <?php if ($pending_count > 0) : ?>
      <span class="team-members-board__tab-badge"><?php echo (int) $pending_count; ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <?php endif; ?>

  <?php if ($is_parent_pending_tab) : ?>
    <?php
    get_template_part('template-parts/parent/guardian', 'pending-approval-panel', [
        'team_id' => $team_id,
        'pending_approval_ctx' => $pending_approval_ctx,
    ]);
    ?>
  <?php elseif ($is_parents_list && !empty($parent_rows)) : ?>
  <div class="team-members-board__table-wrap">
    <table class="team-members-board__table team-members-board__table--parents" data-team-parents-table>
      <thead>
        <tr>
          <th>性</th>
          <th>名</th>
          <th>フリガナ性</th>
          <th>フリガナ名</th>
          <th>メール</th>
          <th>紐付け選手</th>
          <th>決済有無</th>
          <th>経路</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parent_rows as $parent_row) :
            if (!is_array($parent_row)) {
                continue;
            }
            $detail_json = wp_json_encode($parent_row['detail'] ?? [], JSON_UNESCAPED_UNICODE);
            ?>
        <tr data-parent-id="<?php echo esc_attr((string) (int) ($parent_row['user_id'] ?? 0)); ?>">
          <td class="team-members-board__cell-value"><?php echo esc_html((string) (($parent_row['parent_name_sei'] ?? '') !== '' ? $parent_row['parent_name_sei'] : '—')); ?></td>
          <td class="team-members-board__cell-value team-members-board__cell-value--name"><?php echo esc_html((string) (($parent_row['parent_name_mei'] ?? '') !== '' ? $parent_row['parent_name_mei'] : '—')); ?></td>
          <td class="team-members-board__cell-value"><?php echo esc_html((string) (($parent_row['parent_kana_sei'] ?? '') !== '' ? $parent_row['parent_kana_sei'] : '—')); ?></td>
          <td class="team-members-board__cell-value"><?php echo esc_html((string) (($parent_row['parent_kana_mei'] ?? '') !== '' ? $parent_row['parent_kana_mei'] : '—')); ?></td>
          <td class="team-members-board__cell-value team-members-board__cell-value--mark"><?php echo esc_html((string) ($parent_row['email_mark'] ?? '—')); ?></td>
          <td class="team-members-board__cell-value team-members-board__cell-value--wrap" title="<?php echo esc_attr((string) ($parent_row['linked_child_summary'] ?? '')); ?>">
            <?php
            $linked_count = (int) ($parent_row['linked_child_count'] ?? 0);
            if ($linked_count > 0) {
                echo esc_html((string) ($parent_row['linked_child_summary'] ?? ''));
            } else {
                echo '—';
            }
            ?>
          </td>
          <td class="team-members-board__cell-value team-members-board__cell-value--mark<?php echo !empty($parent_row['tuition_registered']) ? ' team-members-board__cell-value--paid' : ''; ?>">
            <?php echo esc_html((string) ($parent_row['tuition_payment_mark'] ?? '—')); ?>
          </td>
          <td class="team-members-board__cell-value"><?php echo esc_html((string) ($parent_row['invite_type_label'] ?? '—')); ?></td>
          <td class="team-members-board__cell-value">
            <button
              type="button"
              class="team-members-board__action team-members-board__action--text"
              data-parent-detail-open
              data-parent-detail="<?php echo esc_attr($detail_json !== false ? $detail_json : '{}'); ?>"
              aria-haspopup="dialog"
            >
              詳細
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div id="team-parent-detail-modal" class="team-parent-detail-modal" hidden>
    <div class="team-parent-detail-modal__overlay" data-parent-detail-close tabindex="-1" aria-hidden="true"></div>
    <div class="team-parent-detail-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="team-parent-detail-modal-title" tabindex="-1">
      <div class="team-parent-detail-modal__header">
        <h3 id="team-parent-detail-modal-title" class="team-parent-detail-modal__title">保護者詳細</h3>
        <button type="button" class="team-parent-detail-modal__close" data-parent-detail-close aria-label="閉じる">×</button>
      </div>
      <dl class="team-parent-detail-modal__body" data-parent-detail-body></dl>
      <div class="team-parent-detail-modal__footer">
        <button type="button" class="btn btn-secondary" data-parent-detail-close>閉じる</button>
      </div>
    </div>
  </div>
  <?php elseif ($is_members_tab && !empty($rows)) : ?>
  <div class="team-members-board__table-wrap">
    <table class="team-members-board__table" data-team-members-table>
      <thead>
        <tr>
          <?php if ($show_avatar) : ?>
          <th class="team-members-board__th-avatar" aria-label="写真"><span class="screen-reader-text">写真</span></th>
          <?php endif; ?>
          <th>性</th>
          <th>名</th>
          <?php if ($show_nickname) : ?>
          <th>ニックネーム</th>
          <?php endif; ?>
          <th>学年</th>
          <th>身長(cm)</th>
          <th>ポジション</th>
          <?php if (!empty($ctx['show_parent_column'])) : ?>
          <th>保護者名</th>
          <?php endif; ?>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row) :
            if (!is_array($row)) {
                continue;
            }
            $player_id = (int) ($row['user_id'] ?? 0);
            $position = (string) ($row['player_position'] ?? '');
            $player_sei = (string) ($row['player_name_sei'] ?? '');
            $player_mei = (string) ($row['player_name_mei'] ?? '');
            $player_label = trim($player_sei . ' ' . $player_mei);
            if ($player_label === '') {
                $player_label = (string) ($row['player_name'] ?? 'この選手');
            }
            $grade_display = $is_parent
                ? (string) ($row['player_grade'] ?? '')
                : (string) ($row['player_grade_list'] ?? $row['player_grade'] ?? '');
            $height_display = (string) ($row['player_height_digits'] ?? '');
            ?>
        <tr data-player-id="<?php echo esc_attr((string) $player_id); ?>" data-player-label="<?php echo esc_attr($player_label); ?>">
          <?php if ($show_avatar) : ?>
          <td class="team-members-board__cell-avatar">
            <img src="<?php echo esc_url((string) ($row['avatar_url'] ?? '')); ?>" alt="" class="team-members-board__avatar" width="36" height="36">
          </td>
          <?php endif; ?>
          <td class="team-members-board__cell-value"><?php echo esc_html($player_sei !== '' ? $player_sei : '—'); ?></td>
          <td class="team-members-board__cell-value"><?php echo esc_html($player_mei !== '' ? $player_mei : '—'); ?></td>
          <?php if ($show_nickname) : ?>
          <td class="team-members-board__cell-value"><?php echo esc_html((string) (($row['player_nickname'] ?? '') !== '' ? $row['player_nickname'] : '—')); ?></td>
          <?php endif; ?>
          <td class="team-members-board__cell-value"><?php echo esc_html($grade_display !== '' ? $grade_display : '—'); ?></td>
          <td class="team-members-board__cell-value"><?php echo esc_html($height_display !== '' ? $height_display : '—'); ?></td>
          <td class="team-members-board__cell-value">
            <?php if ($position !== '') : ?>
            <span class="team-members-board__position"><?php echo esc_html($position); ?></span>
            <?php else : ?>
            —
            <?php endif; ?>
          </td>
          <?php if (!empty($ctx['show_parent_column'])) : ?>
          <td class="team-members-board__cell-value"><?php echo esc_html((string) (($row['parent_name'] ?? '') !== '' ? $row['parent_name'] : '—')); ?></td>
          <?php endif; ?>
          <td>
            <div class="team-members-board__row-actions">
              <a href="<?php echo esc_url((string) ($row['detail_url'] ?? '')); ?>" class="team-members-board__action team-members-board__action--icon" aria-label="詳細">
                <?php echo aidunite_render_theme_icon('visibility', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </a>
              <a href="<?php echo esc_url((string) ($row['edit_url'] ?? '')); ?>" class="team-members-board__action team-members-board__action--icon team-members-board__action--edit" aria-label="編集">
                <?php echo aidunite_render_theme_icon('stylus', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </a>
              <?php if (!empty($ctx['can_delete'])) : ?>
              <button type="button" class="team-members-board__action team-members-board__action--icon team-members-board__action--delete delete-player-btn" data-player-id="<?php echo esc_attr((string) $player_id); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('delete_player_' . $player_id)); ?>" aria-label="削除">
                <?php echo aidunite_render_theme_icon('delete_forever', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else : ?>
  <div class="team-members-board__empty">
    <p><?php echo esc_html((string) ($ctx['empty_message'] ?? '')); ?></p>
    <a href="<?php echo esc_url((string) ($ctx['add_url'] ?? home_url('/player-add'))); ?>" class="team-members-board__btn team-members-board__btn--primary">
      <?php echo aidunite_render_theme_icon($is_parents_list ? 'mail' : 'add', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <?php echo esc_html((string) ($ctx['add_label'] ?? '追加')); ?>
    </a>
  </div>
  <?php endif; ?>

  <?php if (!empty($ctx['footer_note'])) : ?>
  <p class="team-members-board__footer-note"><?php echo esc_html((string) $ctx['footer_note']); ?></p>
  <?php endif; ?>
</div>
