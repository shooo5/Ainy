<?php
/**
 * Template Name: マッチ申請一覧
 * マッチ申請一覧（テーブル形式・管理者は申請ID列あり）
 * 列: 申請チーム, 日程, 時間, 会場, 会場名, 性別, 募集チーム数（男子 ◯/● 女子 ◯/●）, 申請ステータス, リマインド通知, 操作
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';

/**
 * スケジュールの募集チーム数（男子・女子の現在数/定員）を返す
 *
 * @param int $schedule_id スケジュールID
 * @return array{ male_current: int, female_current: int, male_cap: int, female_cap: int }
 */
function aidunite_get_schedule_recruitment_counts($schedule_id) {
    $male_cap = (int) (get_post_meta($schedule_id, 'male_capacity', true) ?: 0);
    $female_cap = (int) (get_post_meta($schedule_id, 'female_capacity', true) ?: 0);
    $participants = get_post_meta($schedule_id, 'participants', true);
    $male_current = 0;
    $female_current = 0;
    if (!empty($participants)) {
        $ids = array_filter(array_map('trim', explode(',', $participants)));
        foreach ($ids as $tid) {
            $g = get_post_meta($tid, 'team_gender_option', true);
            if (function_exists('aidunite_normalize_team_gender_option')) {
                $g = aidunite_normalize_team_gender_option((string) $g);
            }
            if ($g === 'male') {
                $male_current++;
            } elseif ($g === 'female') {
                $female_current++;
            }
        }
    }
    return [
        'male_current' => $male_current,
        'female_current' => $female_current,
        'male_cap' => $male_cap,
        'female_cap' => $female_cap,
    ];
}
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = $auth_result->user_id;
  $is_admin = in_array('administrator', (array) wp_get_current_user()->roles, true);

// マッチ申請を取得（管理者は全件、チームリーダーは自チームが受け取った申請のみ）
$args = [
    'post_type' => 'match_request',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'post_status' => ['publish', 'draft'],
];

$requests_raw = get_posts($args);
$requests_list = [];

foreach ($requests_raw as $request) {
    $to_schedule_id = get_post_meta($request->ID, 'to_schedule_id', true);
    $from_schedule_id = get_post_meta($request->ID, 'from_schedule_id', true);
    if (!$to_schedule_id) {
        $to_schedule_id = get_post_meta($request->ID, 'my_schedule_id', true);
        $from_schedule_id = get_post_meta($request->ID, 'my_schedule_id', true);
    }
    if (!$to_schedule_id) {
        continue;
    }
    $schedule = get_post($to_schedule_id);
    if (!$schedule) {
        continue;
    }
    // チームリーダーは「自チームのスケジュール宛の申請」のみ表示
    if (!$is_admin && (int) $schedule->post_author !== (int) $current_user_id) {
        continue;
    }

    $from_schedule_id = (int) get_post_meta($request->ID, 'from_schedule_id', true) ?: (int) get_post_meta($request->ID, 'my_schedule_id', true);
    $from_team_id = (int) get_post_meta($request->ID, 'from_team_id', true);
    $to_team_id = (int) get_post_meta($request->ID, 'to_team_id', true);
    if (!$to_team_id) {
        $to_author_id = get_post_field('post_author', $to_schedule_id);
        $to_team_id = (int) get_user_meta($to_author_id, 'team_id', true);
    }
    if (!$from_team_id) {
        $from_author_id = (int) $request->post_author;
        $from_team_id = (int) get_user_meta($from_author_id, 'team_id', true);
    }

    $from_team_name = $from_team_id ? get_the_title($from_team_id) : '—';
    $to_team_name = $to_team_id ? get_the_title($to_team_id) : '—';
    $schedule_date = get_post_meta($to_schedule_id, 'schedule_date', true);
    $start = get_post_meta($to_schedule_id, 'schedule_start_time', true);
    $end = get_post_meta($to_schedule_id, 'schedule_end_time', true);
    $time_disp = trim($start . '〜' . $end) === '〜' ? '—' : trim($start . '〜' . $end);
    $gender_raw = get_post_meta($to_schedule_id, 'schedule_gender', true) ?: get_post_meta($to_schedule_id, 'gender_condition', true);
    $gender_label = function_exists('aidunite_admin_list_format_gender_display')
        ? aidunite_admin_list_format_gender_display((string) $gender_raw)
        : ($gender_raw ?: '—');
    $status = get_post_meta($request->ID, 'status', true);
    $reminder_sent = get_post_meta($request->ID, 'reminder_sent_at', true);

    // 募集チーム数（男子 ◯/● 女子 ◯/●）を to_schedule_id 単位でキャッシュして付与
    static $schedule_recruitment_cache = [];
    if (!isset($schedule_recruitment_cache[$to_schedule_id])) {
        $schedule_recruitment_cache[$to_schedule_id] = aidunite_get_schedule_recruitment_counts($to_schedule_id);
    }
    $recruitment_counts = $schedule_recruitment_cache[$to_schedule_id];

    $requests_list[] = [
        'request_id' => $request->ID,
        'request' => $request,
        'to_schedule_id' => $to_schedule_id,
        'recruitment_counts' => $recruitment_counts,
        'from_schedule_id' => $from_schedule_id,
        'schedule' => $schedule,
        'from_team_id' => $from_team_id,
        'to_team_id' => $to_team_id,
        'from_team_name' => $from_team_name,
        'to_team_name' => $to_team_name,
        'schedule_date' => function_exists('aidunite_admin_list_format_date_display')
            ? aidunite_admin_list_format_date_display((string) $schedule_date)
            : ($schedule_date ?: '—'),
        'time_disp' => $time_disp,
        'venue_parts' => function_exists('aidunite_admin_list_get_schedule_venue_parts')
            ? aidunite_admin_list_get_schedule_venue_parts($to_schedule_id)
            : ['place_label' => '—', 'venue_name' => '—'],
        'gender_label' => $gender_label,
        'status' => $status,
        'reminder_sent' => $reminder_sent,
        'can_respond' => ($status === '申請中' || $status === 'publish' || $status === 'draft') && (int) $schedule->post_author === (int) $current_user_id,
    ];
}

// 検索・フィルター（オプション）
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
// ステータス未指定時はキャンセル・拒否済みを通常一覧から除外（ドロップダウンで個別指定したときのみ表示）
if ($status_filter === '' && function_exists('aidunite_normalize_match_request_status')) {
    $requests_list = array_values(array_filter($requests_list, function ($r) {
        $norm = aidunite_normalize_match_request_status((string) ($r['status'] ?? ''), '');
        return !in_array($norm, ['canceled', 'rejected'], true);
    }));
}

if ($status_filter !== '') {
    if ($status_filter === '申請中') {
        $requests_list = array_filter($requests_list, function ($r) {
            return in_array($r['status'], ['申請中', 'publish', 'draft'], true);
        });
    } else {
        $requests_list = array_filter($requests_list, function ($r) use ($status_filter) {
            return $r['status'] === $status_filter;
        });
    }
    $requests_list = array_values($requests_list);
}

$total_count = count($requests_list);
$per_page = defined('AIDUNITE_MATCH_REQUESTS_PER_PAGE') ? (int) AIDUNITE_MATCH_REQUESTS_PER_PAGE : 20;
$per_page = max(1, min(100, $per_page));
$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $per_page;
$requests_page = array_slice($requests_list, $offset, $per_page);

get_header();

$match_requests_shell_opened = false;
$match_requests_subtitle = $is_admin ? '全チームの申請を管理' : '自チーム宛の申請';
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-match-requests page-match-requests-wrap',
        'title' => 'マッチ申請一覧',
        'subtitle' => $match_requests_subtitle,
        'back_url' => home_url('/match-board-own'),
    ]);
    $match_requests_shell_opened = true;
} else {
    echo '<div class="wrap page-match-requests-wrap" style="width:100%;max-width:100%;margin:auto;">';
}
?>

<?php if (!$match_requests_shell_opened) : ?>
  <h1>マッチ申請一覧</h1>
<?php endif; ?>

  <div class="search-filter-section" style="background:var(--bg-secondary); padding:var(--spacing-lg); margin:var(--spacing-lg) 0; border-radius:var(--radius-base);">
    <h3 style="margin:0 0 var(--spacing-base);">検索・フィルター</h3>
    <form method="get" style="display:flex; flex-wrap:wrap; gap:var(--spacing-base); align-items:flex-end;">
      <div>
        <label for="status_filter">申請ステータス:</label>
        <select id="status_filter" name="status_filter" style="padding:var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
          <option value="">すべて</option>
          <option value="申請中" <?php selected($status_filter, '申請中'); ?>>申請中</option>
          <option value="publish" <?php selected($status_filter, 'publish'); ?>>publish</option>
          <option value="draft" <?php selected($status_filter, 'draft'); ?>>draft</option>
          <option value="accepted" <?php selected($status_filter, 'accepted'); ?>>承認済み</option>
          <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>拒否済み</option>
          <option value="established" <?php selected($status_filter, 'established'); ?>>成立</option>
          <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>キャンセル</option>
        </select>
      </div>
      <button type="submit" class="button button-primary">検索</button>
      <a href="<?php echo esc_url(get_permalink()); ?>" class="button">リセット</a>
    </form>
    <div style="margin-top:var(--spacing-base); padding:var(--spacing-sm); background:var(--bg-primary); border-radius:var(--radius-small);">
      <strong>全 <?php echo (int) $total_count; ?> 件</strong>
      <?php if ($total_pages > 1): ?>
        （<?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_count); ?> 件目）
      <?php endif; ?>
    </div>
  </div>

  <?php if ($total_pages > 1): ?>
  <nav class="match-requests-pagination" style="margin-bottom:var(--spacing-base);" aria-label="ページ送り">
    <?php
    $base = add_query_arg(array_filter(['status_filter' => $status_filter]), get_permalink());
    echo paginate_links([
        'base' => $base . '%_%',
        'format' => '?paged=%#%',
        'current' => $current_page,
        'total' => $total_pages,
        'prev_text' => '&laquo; 前へ',
        'next_text' => '次へ &raquo;',
    ]);
    ?>
  </nav>
  <?php endif; ?>

  <?php if (empty($requests_list)) : ?>
  <div class="page-match-requests-empty" style="text-align:center; padding:var(--spacing-xl); background:var(--bg-secondary); border-radius:var(--radius-base); margin:var(--spacing-lg) 0;">
    <p style="margin:0; font-size:var(--font-size-lg); color:var(--text-secondary);">マッチ申請はありません</p>
    <p style="margin:var(--spacing-sm) 0 0;"><?php echo $is_admin ? '申請データがありません。' : '自チーム宛の申請が届くとここに表示されます。'; ?></p>
  </div>
  <?php else : ?>
  <table class="wp-list-table widefat fixed striped page-match-requests-table">
    <thead>
      <tr>
        <th class="col-checkbox">
          <?php if ($is_admin) : ?>
          <label class="aidunite-admin-checkbox" title="すべて選択（将来用）">
            <input type="checkbox" class="aidunite-admin-checkbox__input" disabled aria-label="すべて選択">
            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
          </label>
          <?php endif; ?>
        </th>
        <th class="col-no">No</th>
        <?php if ($is_admin) : ?>
        <th class="admin-cell admin-cell--id">申請ID</th>
        <?php endif; ?>
        <th class="admin-cell admin-cell--text">申請チーム</th>
        <th class="admin-cell admin-cell--date">日程</th>
        <th class="admin-cell admin-cell--time">時間</th>
        <th class="admin-cell admin-cell--venue">会場</th>
        <th class="admin-cell admin-cell--text">会場名</th>
        <th class="admin-cell admin-cell--gender">性別</th>
        <th>募集チーム数</th>
        <th>申請ステータス</th>
        <th>リマインド通知</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests_page as $i => $r) :
        $row_no = $offset + $i + 1;
        $status = $r['status'];
        $status_display = $status;
        if ($status === 'publish' || $status === 'draft') {
            $status_display = '申請中';
        } elseif ($status === 'accepted' || $status === 'established') {
            $status_display = '承認済み';
        } elseif ($status === 'rejected') {
            $status_display = '拒否済み';
        } elseif ($status === 'canceled') {
            $status_display = 'キャンセル';
        }
        $status_class = 'status-pending';
        if (in_array($status, ['accepted', 'established'], true)) {
            $status_class = 'status-accepted';
        } elseif (in_array($status, ['rejected', 'canceled'], true)) {
            $status_class = 'status-rejected';
        }
        $reminder_disp = !empty($r['reminder_sent']) ? '送信済み' : '—';
      ?>
      <tr data-request-id="<?php echo esc_attr($r['request_id']); ?>">
        <td class="col-checkbox">
          <?php if ($is_admin) : ?>
          <label class="aidunite-admin-checkbox">
            <input type="checkbox" class="match-request-row-checkbox aidunite-admin-checkbox__input" value="<?php echo (int) $r['request_id']; ?>" aria-label="<?php echo esc_attr('申請ID ' . (int) $r['request_id']); ?>">
            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
          </label>
          <?php endif; ?>
        </td>
        <td class="col-no"><?php echo (int) $row_no; ?></td>
        <?php if ($is_admin) : ?>
        <?php aidunite_admin_list_echo_cell('id', (string) $r['request_id']); ?>
        <?php endif; ?>
        <?php aidunite_admin_list_echo_cell('text', $r['from_team_name']); ?>
        <?php aidunite_admin_list_echo_cell('date', $r['schedule_date']); ?>
        <?php aidunite_admin_list_echo_cell('time', $r['time_disp']); ?>
        <?php
        $venue_parts = $r['venue_parts'] ?? ['place_label' => '—', 'venue_name' => '—'];
        aidunite_admin_list_echo_cell('venue', $venue_parts['place_label']);
        aidunite_admin_list_echo_cell('text', $venue_parts['venue_name']);
        ?>
        <?php aidunite_admin_list_echo_cell('gender', $r['gender_label']); ?>
        <td><?php
          $rc = $r['recruitment_counts'];
          echo '男子 ' . (int) $rc['male_current'] . '/' . (int) $rc['male_cap'] . '　女子 ' . (int) $rc['female_current'] . '/' . (int) $rc['female_cap'];
        ?></td>
        <td>
          <span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_display); ?></span>
        </td>
        <td><?php echo esc_html($reminder_disp); ?></td>
        <td>
          <?php
          $detail_url = $r['from_schedule_id']
              ? home_url('/match-detail?schedule_id=' . $r['from_schedule_id'])
              : home_url('/match-detail?request_id=' . $r['request_id']);
          ?>
          <?php if ($r['can_respond']) : ?>
            <button type="button" class="button button-small match-approve-btn">承認</button>
            <button type="button" class="button button-small match-reject-btn">拒否</button>
            <a href="<?php echo esc_url($detail_url); ?>" class="button button-small">詳細</a>
          <?php else : ?>
            <a href="<?php echo esc_url($detail_url); ?>" class="button button-small">詳細</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
  <nav class="match-requests-pagination" style="margin-top:var(--spacing-base);" aria-label="ページ送り">
    <?php
    $base_bottom = add_query_arg(array_filter(['status_filter' => $status_filter]), get_permalink());
    echo paginate_links([
        'base' => $base_bottom . '%_%',
        'format' => '?paged=%#%',
        'current' => $current_page,
        'total' => $total_pages,
        'prev_text' => '&laquo; 前へ',
        'next_text' => '次へ &raquo;',
    ]);
    ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>

<?php
if ($match_requests_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<style>
.page-match-requests-wrap .status-pending { background:rgba(255,193,7,0.15); color:var(--warning-color); }
.page-match-requests-wrap .status-accepted { background:rgba(40,167,69,0.15); color:var(--success-color); }
.page-match-requests-wrap .status-rejected { background:rgba(220,53,69,0.15); color:var(--danger-color); }
</style>

<script>
jQuery(document).ready(function($) {
  $('.match-approve-btn, .match-reject-btn').on('click', function() {
    var row = $(this).closest('tr');
    var requestId = row.data('request-id');
    var isApprove = $(this).hasClass('match-approve-btn');
    var status = isApprove ? 'accepted' : 'rejected';
    var actionLabel = isApprove ? '承認済み' : '拒否済み';

    $.ajax({
      url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
      method: 'POST',
      data: {
        action: 'au_update_match_request_status',
        security: '<?php echo wp_create_nonce('au_match_nonce'); ?>',
        request_id: requestId,
        status: status
      },
      success: function(res) {
        if (res && res.success) {
          if (typeof showToastNotification !== 'undefined') {
            showToastNotification('申請を' + actionLabel + 'しました', 'success');
          } else { alert('申請を' + actionLabel + 'しました'); }
          row.find('.status-badge').removeClass('status-pending').addClass(isApprove ? 'status-accepted' : 'status-rejected').text(actionLabel);
          row.find('.match-approve-btn, .match-reject-btn').remove();
        } else {
          if (typeof showToastNotification !== 'undefined') {
            showToastNotification(res && res.data ? res.data : '更新に失敗しました', 'error');
          } else { alert('更新に失敗しました'); }
        }
      },
      error: function() {
        if (typeof showToastNotification !== 'undefined') {
          showToastNotification('更新に失敗しました', 'error');
        } else { alert('更新に失敗しました'); }
      }
    });
  });
});
</script>

<?php get_footer(); ?>
