<?php
/**
 * Template Name: マッチ申請一覧
 * マッチ申請一覧（テーブル形式・管理者は申請ID列あり）
 * 列: 申請チーム, 日程, 時間, 会場, 会場名, 性別, 募集チーム数（男子 ◯/● 女子 ◯/●）, 申請ステータス, リマインド通知, 操作
 * 管理者のみ: 募集schedule ID, 申請元schedule ID, 掲示板(match_board) ID
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';

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
    $mr = function_exists('aidunite_match_request_get_canonical_meta')
        ? aidunite_match_request_get_canonical_meta((int) $request->ID)
        : [];
    $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
    $from_schedule_id = (int) ($mr['from_schedule_id'] ?? 0);
    if ($to_schedule_id < 1) {
        $to_schedule_id = (int) ($mr['my_schedule_id'] ?? 0);
        $from_schedule_id = $to_schedule_id;
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

    if ($from_schedule_id < 1) {
        $from_schedule_id = (int) ($mr['my_schedule_id'] ?? 0);
    }
    $from_team_id = (int) ($mr['from_team_id'] ?? 0);
    $to_team_id = (int) ($mr['to_team_id'] ?? 0);
    if (!$to_team_id) {
        $to_author_id = get_post_field('post_author', $to_schedule_id);
        $to_team_id = aidunite_user_read_primary_team_id((int) $to_author_id);
    }
    if (!$from_team_id) {
        $from_author_id = (int) $request->post_author;
        $from_team_id = aidunite_user_read_primary_team_id((int) $from_author_id);
    }

    $from_team_name = $from_team_id ? get_the_title($from_team_id) : '—';
    $to_team_name = $to_team_id ? get_the_title($to_team_id) : '—';
    $sch = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle((int) $to_schedule_id)
        : [];
    $schedule_date = (string) ($sch['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date((int) $to_schedule_id)
        : ''));
    $start = (string) ($sch['start_time'] ?? '');
    $end = (string) ($sch['end_time'] ?? '');
    $time_disp = function_exists('aidunite_admin_list_format_time_range_display')
        ? aidunite_admin_list_format_time_range_display($start, $end)
        : (trim($start . '〜' . $end) === '〜' ? '—' : trim($start . '〜' . $end));
    $gender_raw = (string) ($sch['gender'] ?? '');
    if ($gender_raw === '' && function_exists('aidunite_schedule_read_gender_raw')) {
        $gender_raw = (string) aidunite_schedule_read_gender_raw((int) $to_schedule_id);
    }
    $gender_label = function_exists('aidunite_admin_list_format_gender_display')
        ? aidunite_admin_list_format_gender_display((string) $gender_raw)
        : ($gender_raw ?: '—');
    $status = (string) ($mr['status'] ?? '');
    $reminder_sent = (string) ($mr['reminder_sent_at'] ?? '');

    // 募集チーム数（男子 ◯/● 女子 ◯/●）を to_schedule_id 単位でキャッシュして付与
    static $schedule_recruitment_cache = [];
    if (!isset($schedule_recruitment_cache[$to_schedule_id])) {
        $schedule_recruitment_cache[$to_schedule_id] = function_exists('aidunite_get_schedule_recruitment_counts')
            ? aidunite_get_schedule_recruitment_counts($to_schedule_id)
            : ['male_current' => 0, 'female_current' => 0, 'male_cap' => 0, 'female_cap' => 0];
    }
    $recruitment_counts = $schedule_recruitment_cache[$to_schedule_id];

    $board_id = 0;
    $board_status = '';
    if ($is_admin && function_exists('aidunite_schedule_find_match_board_id_for_schedule')) {
        $board_id = aidunite_schedule_find_match_board_id_for_schedule((int) $to_schedule_id);
        if ($board_id > 0) {
            $board_status = (string) get_post_status($board_id);
        }
    }

    $requests_list[] = [
        'request_id' => $request->ID,
        'request' => $request,
        'to_schedule_id' => $to_schedule_id,
        'match_board_id' => $board_id,
        'match_board_status' => $board_status,
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
        <th class="admin-cell admin-cell--id" title="募集側 schedule（teamA 等・to_schedule_id）">募集schedule</th>
        <th class="admin-cell admin-cell--id" title="申請元 schedule（teamB 等・from_schedule_id / my_schedule_id）">申請元schedule</th>
        <th class="admin-cell admin-cell--id" title="match_board 投稿ID（meta schedule_id=募集schedule）">掲示板ID</th>
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
        <?php
        aidunite_admin_list_echo_cell('id', (string) (int) $r['to_schedule_id']);
        $from_sid = (int) ($r['from_schedule_id'] ?? 0);
        aidunite_admin_list_echo_cell('id', $from_sid > 0 ? (string) $from_sid : '—');
        $board_disp = '—';
        if (!empty($r['match_board_id'])) {
            $board_disp = (string) (int) $r['match_board_id'];
            if (!empty($r['match_board_status'])) {
                $board_disp .= ' (' . $r['match_board_status'] . ')';
            }
        }
        aidunite_admin_list_echo_cell('id', $board_disp);
        ?>
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
          $detail_args = ['schedule_id' => (int) $r['to_schedule_id']];
          if (!empty($r['from_schedule_id'])) {
              $detail_args['my_schedule_id'] = (int) $r['from_schedule_id'];
          }
          $detail_url = add_query_arg($detail_args, home_url('/match-detail/'));
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

<?php
wp_localize_script('aidunite-match-requests', 'aiduniteMatchRequestsPage', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'matchNonce' => wp_create_nonce('au_match_nonce'),
]);
get_footer();
?>
