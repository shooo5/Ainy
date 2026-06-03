<?php
/**
 * マルチチーム文脈: managed_team_ids / current_operating_team_id と単一 team_id の後方互換。
 *
 * @package AidUnite
 * @see docs/spec/schedule.md 第18節
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var string user_meta: 代表者が管理する team 投稿IDの配列（JSON 配列推奨） */
if (!defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS')) {
    define('AIDUNITE_USER_META_MANAGED_TEAM_IDS', 'managed_team_ids');
}

/** @var string user_meta: 操作中の team 投稿ID（スケジュール作成・MR申請等の actor） */
if (!defined('AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID')) {
    define('AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID', 'current_operating_team_id');
}

/**
 * ユーザーが「管理・操作の文脈で扱うことのできる」team ID の一覧（正の整数、重複なし）。
 * 優先: `managed_team_ids` → `team_memberships` のキー → 単一 `team_id`。
 *
 * @param int $user_id WP ユーザーID
 * @return int[]
 */
/**
 * チーム作成申請が承認されたとき、申請者ユーザーに managed / team_id / 代表者ロールを付与する。
 * pending_team_id はクリアする。複数回呼んでも team_id の重複追加はしない。
 *
 * @param int $user_id 申請者（post_author）
 * @param int $team_id 承認された team 投稿 ID
 */
/**
 * usermeta `managed_team_ids` を読み取り（複数行・JSON・配列を統合）。
 *
 * @param int $user_id
 * @return int[]
 */
function aidunite_read_user_managed_team_ids_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    $meta_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';
    $raw_all = get_user_meta($user_id, $meta_key, false);
    if (!is_array($raw_all) || $raw_all === []) {
        return aidunite_normalize_team_id_list(get_user_meta($user_id, $meta_key, true));
    }
    $merged = [];
    foreach ($raw_all as $raw) {
        foreach (aidunite_normalize_team_id_list($raw) as $tid) {
            $merged[] = (int) $tid;
        }
    }
    $merged = array_values(array_unique(array_filter($merged)));
    sort($merged);
    return $merged;
}

/**
 * 代表者として申請・承認された publish 済み team 投稿 ID を列挙（データ修復・マージ用）。
 *
 * @param int $user_id
 * @return int[]
 */
/**
 * 代表者に紐づく team 投稿 ID（著者または team_leader_id。管理画面承認で author が管理者のケースを含む）。
 *
 * @param int        $user_id
 * @param string[]   $post_statuses 例: ['publish'] / ['publish','pending']
 * @return int[]
 */
/**
 * DB 直クエリで代表者の team を列挙（get_posts / meta_query 取りこぼし対策）。
 *
 * @param int      $user_id
 * @param string[] $post_statuses
 * @return int[]
 */
function aidunite_discover_team_ids_for_leader_sql($user_id, array $post_statuses = ['publish']) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    $post_statuses = array_values(array_filter(array_map('strval', $post_statuses)));
    if (empty($post_statuses)) {
        $post_statuses = ['publish'];
    }
    $allowed = ['publish', 'pending', 'draft', 'trash', 'private', 'future'];
    $post_statuses = array_values(array_intersect($post_statuses, $allowed));
    if (empty($post_statuses)) {
        $post_statuses = ['publish'];
    }

    $placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));
    $args = array_merge($post_statuses, [$user_id, (string) $user_id, (string) $user_id]);
    $sql = "SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_leader
              ON p.ID = pm_leader.post_id AND pm_leader.meta_key = 'team_leader_id'
            WHERE p.post_type = 'team'
              AND p.post_status IN ($placeholders)
              AND (
                p.post_author = %d
                OR pm_leader.meta_value = %s
                OR pm_leader.meta_value = %d
              )
            ORDER BY p.ID ASC";
    $prepared = $wpdb->prepare($sql, $args);
    $rows = $wpdb->get_col($prepared);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $tid) {
        $tid = (int) $tid;
        if ($tid > 0) {
            $out[] = $tid;
        }
    }
    return array_values(array_unique($out));
}

/**
 * team 投稿の代表者ユーザー ID（team_leader_id 優先、なければ post_author）。
 *
 * @param int $team_id
 * @return int
 */
function aidunite_team_resolve_leader_user_id($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $leader = (int) get_post_meta($team_id, 'team_leader_id', true);
    if ($leader > 0 && get_userdata($leader)) {
        return $leader;
    }

    $author = (int) get_post_field('post_author', $team_id);
    if ($author > 0 && get_userdata($author)) {
        return $author;
    }

    return 0;
}

/**
 * 代表者と team 投稿の post_author / team_leader_id を一致させる（管理承認で author が管理者の修復用）。
 *
 * @param int $team_id
 * @param int $user_id
 */
function aidunite_team_sync_post_leader_linkage($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0 || $user_id <= 0) {
        return;
    }

    $leader_meta = (int) get_post_meta($team_id, 'team_leader_id', true);
    if ($leader_meta !== $user_id) {
        update_post_meta($team_id, 'team_leader_id', $user_id);
    }

    $author = (int) get_post_field('post_author', $team_id);
    if ($author !== $user_id) {
        wp_update_post([
            'ID' => $team_id,
            'post_author' => $user_id,
        ]);
    }
}

function aidunite_discover_team_ids_for_leader($user_id, array $post_statuses = ['publish']) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $out = aidunite_discover_team_ids_for_leader_sql($user_id, $post_statuses);

    $user = get_userdata($user_id);
    if ($user && is_email($user->user_email)) {
        $email = $user->user_email;
        $post_statuses = array_values(array_filter(array_map('strval', $post_statuses)));
        if (empty($post_statuses)) {
            $post_statuses = ['publish'];
        }
        $by_mail = get_posts([
            'post_type' => 'team',
            'post_status' => $post_statuses,
            'posts_per_page' => 100,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'contact_mail',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
        ]);
        if (is_array($by_mail)) {
            foreach ($by_mail as $tid) {
                $tid = (int) $tid;
                if ($tid > 0 && !in_array($tid, $out, true)) {
                    $out[] = $tid;
                }
            }
        }
    }

    sort($out);
    return $out;
}

function aidunite_discover_publish_team_ids_for_leader($user_id) {
    return aidunite_discover_team_ids_for_leader($user_id, ['publish']);
}

/**
 * managed_team_ids を投稿著者ベースで修復し、必要なら usermeta を更新する。
 *
 * @param int $user_id
 * @return int[] publish 済みの managed 一覧
 */
function aidunite_reconcile_user_managed_team_ids($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    $meta_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';

    $ids = aidunite_read_user_managed_team_ids_meta($user_id);
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy > 0 && !in_array($legacy, $ids, true)) {
        $ids[] = $legacy;
    }
    $primary_meta = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary_meta > 0 && !in_array($primary_meta, $ids, true)) {
        $ids[] = $primary_meta;
    }
    if (empty($ids)) {
        $memberships = get_user_meta($user_id, 'team_memberships', true);
        if (is_array($memberships)) {
            foreach (array_keys($memberships) as $k) {
                $tid = (int) $k;
                if ($tid > 0) {
                    $ids[] = $tid;
                }
            }
        }
    }

    foreach (aidunite_discover_publish_team_ids_for_leader($user_id) as $tid) {
        if (!in_array($tid, $ids, true)) {
            $ids[] = $tid;
        }
    }

    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);
    $ids = aidunite_filter_team_ids_to_publish_for_operations($ids);

    if (!empty($ids)) {
        $stored = aidunite_read_user_managed_team_ids_meta($user_id);
        $stored_pub = aidunite_filter_team_ids_to_publish_for_operations($stored);
        if ($stored_pub !== $ids) {
            update_user_meta($user_id, $meta_key, wp_json_encode($ids));
        }
    }

    aidunite_sync_user_primary_team_meta($user_id, $ids);

    return $ids;
}

/**
 * メインチーム（primary_team_id / レガシー team_id）と操作中チームを先頭チームに揃える。
 *
 * @param int   $user_id
 * @param int[] $publish_ids publish 済み managed
 */
function aidunite_sync_user_primary_team_meta($user_id, array $publish_ids) {
    $user_id = (int) $user_id;
    $publish_ids = array_values(array_unique(array_map('intval', $publish_ids)));
    sort($publish_ids);
    if ($user_id <= 0 || empty($publish_ids)) {
        return;
    }

    $primary = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary <= 0 || !in_array($primary, $publish_ids, true)) {
        $primary = (int) $publish_ids[0];
        update_user_meta($user_id, 'primary_team_id', $primary);
    }

    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy !== $primary) {
        update_user_meta($user_id, 'team_id', $primary);
    }

}

function aidunite_user_attach_approved_team_membership($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return;
    }

    delete_user_meta($user_id, 'pending_team_id');

    $primary_before = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary_before <= 0) {
        $legacy_before = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy_before > 0) {
            update_user_meta($user_id, 'primary_team_id', $legacy_before);
        }
    }

    $ids = aidunite_reconcile_user_managed_team_ids($user_id);
    if (!in_array($team_id, $ids, true)) {
        $ids[] = $team_id;
        $ids = aidunite_filter_team_ids_to_publish_for_operations($ids);
        $meta_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';
        update_user_meta($user_id, $meta_key, wp_json_encode($ids));
        aidunite_sync_user_primary_team_meta($user_id, $ids);
    }

    $primary = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary <= 0) {
        update_user_meta($user_id, 'primary_team_id', $team_id);
        update_user_meta($user_id, 'team_id', $team_id);
    }

    update_user_meta($user_id, 'user_type', 'team_leader');
    update_user_meta($user_id, 'aidunite_role', 'team_leader');

    $publish_ids = aidunite_filter_team_ids_to_publish_for_operations(
        aidunite_read_user_managed_team_ids_meta($user_id)
    );
    if (!empty($publish_ids)) {
        aidunite_sync_user_primary_team_meta($user_id, $publish_ids);
    }

    $primary_tid = (int) get_user_meta($user_id, 'primary_team_id', true);
    if ($primary_tid > 0 && function_exists('aidunite_set_current_operating_team_id')) {
        aidunite_set_current_operating_team_id($user_id, $primary_tid);
    }
}

/**
 * 指定 team からユーザーの所属を外す（チーム削除・解散用）。
 * マルチチーム代表者は managed から当該 ID のみ除去し、他チームがあれば team_id / team_leader ロールを維持する。
 *
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_user_remove_team_membership($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return;
    }

    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy === $team_id) {
        delete_user_meta($user_id, 'team_id');
    }

    $meta_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';
    $ids = aidunite_read_user_managed_team_ids_meta($user_id);
    $ids = array_values(array_diff($ids, [$team_id]));
    if ($ids === []) {
        delete_user_meta($user_id, $meta_key);
    } else {
        update_user_meta($user_id, $meta_key, wp_json_encode($ids));
        if (function_exists('aidunite_sync_user_primary_team_meta')) {
            aidunite_sync_user_primary_team_meta($user_id, $ids);
        }
    }

    $resolved_leader = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : 0;
    $is_team_leader_user = $resolved_leader === $user_id
        || (string) get_user_meta($user_id, 'aidunite_role', true) === 'team_leader';

    if ($is_team_leader_user) {
        $remaining = function_exists('aidunite_get_managed_team_ids')
            ? aidunite_get_managed_team_ids($user_id)
            : $ids;
        if ($remaining === []) {
            update_user_meta($user_id, 'user_type', 'general');
            delete_user_meta($user_id, 'aidunite_role');
            if (defined('AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID')) {
                delete_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID);
            }
        }
        return;
    }

    update_user_meta($user_id, 'aidunite_role', 'general');
}

/**
 * チーム作成申請が却下されたとき、申請者の pending / 当該 team に紐づく所属メタを外す。
 *
 * @param int $user_id post_author
 * @param int $team_id 却下された team 投稿 ID
 */
function aidunite_user_detach_rejected_pending_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return;
    }

    $pend = (int) get_user_meta($user_id, 'pending_team_id', true);
    if ($pend === $team_id) {
        delete_user_meta($user_id, 'pending_team_id');
        if (function_exists('aidunite_get_user_pending_application_team_ids')) {
            $remaining = array_values(array_diff(
                aidunite_get_user_pending_application_team_ids($user_id),
                [$team_id]
            ));
            if (!empty($remaining)) {
                update_user_meta($user_id, 'pending_team_id', (int) $remaining[0]);
            }
        }
    }

    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy === $team_id) {
        delete_user_meta($user_id, 'team_id');
    }

    $meta_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';
    $ids = aidunite_read_user_managed_team_ids_meta($user_id);
    $ids = array_values(array_diff($ids, [$team_id]));
    if (empty($ids)) {
        delete_user_meta($user_id, $meta_key);
    } else {
        update_user_meta($user_id, $meta_key, wp_json_encode($ids));
    }

    if (empty(aidunite_get_managed_team_ids($user_id))) {
        update_user_meta($user_id, 'user_type', 'general');
        delete_user_meta($user_id, 'aidunite_role');
        delete_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID);
    }
}

/**
 * スケジュール・MR・決済等の操作文脈で参照してよい team 投稿か（承認済み `publish` のみ）。
 * 申請中 `pending` やゴミ ID は false。
 *
 * @param int $team_id team 投稿 ID
 */
function aidunite_team_cpt_is_publish_for_operation_context($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team') {
        return false;
    }
    return $post->post_status === 'publish';
}

/**
 * @param int[] $ids
 * @return int[]
 */
function aidunite_filter_team_ids_to_publish_for_operations(array $ids) {
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && aidunite_team_cpt_is_publish_for_operation_context($id)) {
            $out[] = $id;
        }
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

function aidunite_get_managed_team_ids($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    static $cache = [];
    static $loading = [];
    if (isset($cache[$user_id])) {
        return $cache[$user_id];
    }
    if (isset($loading[$user_id])) {
        return aidunite_filter_team_ids_to_publish_for_operations(
            aidunite_read_user_managed_team_ids_meta($user_id)
        );
    }
    $loading[$user_id] = true;

    if (apply_filters('aidunite_auto_reconcile_managed_team_ids', true)) {
        $reconciled = aidunite_reconcile_user_managed_team_ids($user_id);
        if (!empty($reconciled)) {
            unset($loading[$user_id]);
            $cache[$user_id] = $reconciled;
            return $reconciled;
        }
    }

    $ids = aidunite_read_user_managed_team_ids_meta($user_id);
    if (!empty($ids)) {
        unset($loading[$user_id]);
        $cache[$user_id] = aidunite_filter_team_ids_to_publish_for_operations($ids);
        return $cache[$user_id];
    }

    $memberships = get_user_meta($user_id, 'team_memberships', true);
    if (is_array($memberships) && !empty($memberships)) {
        $ids = [];
        foreach (array_keys($memberships) as $k) {
            $tid = (int) $k;
            if ($tid > 0) {
                $ids[] = $tid;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        unset($loading[$user_id]);
        $cache[$user_id] = aidunite_filter_team_ids_to_publish_for_operations($ids);
        return $cache[$user_id];
    }

    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    unset($loading[$user_id]);
    $cache[$user_id] = $legacy > 0 ? aidunite_filter_team_ids_to_publish_for_operations([$legacy]) : [];
    return $cache[$user_id];
}

/**
 * @param mixed $raw JSON 文字列 / 配列 / カンマ区切り
 * @return int[]
 */
function aidunite_normalize_team_id_list($raw) {
    if ($raw === '' || $raw === null) {
        return [];
    }
    if (is_array($raw)) {
        $source = $raw;
    } elseif (is_string($raw) && is_serialized($raw)) {
        $un = maybe_unserialize($raw);
        if (is_array($un)) {
            $source = $un;
        } elseif (is_string($un)) {
            $raw = $un;
            $source = null;
        } else {
            $source = null;
        }
    } else {
        $source = null;
    }

    if ($source === null) {
        if (is_int($raw) || (is_string($raw) && ctype_digit(ltrim($raw, '-')))) {
            $n = (int) $raw;
            return $n > 0 ? [$n] : [];
        }
        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim !== '' && $trim[0] === '[') {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    $source = $decoded;
                }
            }
            if ($source === null) {
                $parts = preg_split('/\s*,\s*/', $trim, -1, PREG_SPLIT_NO_EMPTY);
                $source = $parts ?: [];
            }
        }
    }

    if (!is_array($source)) {
        return [];
    }
    $out = [];
    foreach ($source as $v) {
        $n = (int) $v;
        if ($n > 0) {
            $out[] = $n;
        }
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

/**
 * 指定 team への操作・閲覧の土台として「所属・管理がある」か（単一 team_id 互換込み）。
 *
 * @param int $user_id
 * @param int $team_id
 */
function aidunite_user_has_managed_team_access($user_id, $team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !aidunite_team_cpt_is_publish_for_operation_context($team_id)) {
        return false;
    }
    $managed = aidunite_get_managed_team_ids($user_id);
    if (in_array($team_id, $managed, true)) {
        return true;
    }
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    return $legacy > 0 && $legacy === $team_id;
}

/**
 * 保存済み current_operating_team_id の生値一覧（umeta_id 昇順）。
 *
 * @param int $user_id
 * @return int[]
 */
function aidunite_get_all_stored_operating_team_ids($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    global $wpdb;
    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC",
            $user_id,
            AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID
        )
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $v) {
        $tid = (int) $v;
        if ($tid > 0) {
            $out[] = $tid;
        }
    }

    return $out;
}

/**
 * current_operating_team_id の重複行を1件に統一する。
 *
 * @param int $user_id
 * @param int $canonical_team_id
 */
function aidunite_dedupe_user_current_operating_team_meta($user_id, $canonical_team_id) {
    $user_id = (int) $user_id;
    $canonical_team_id = (int) $canonical_team_id;
    if ($user_id <= 0) {
        return;
    }
    delete_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID);
    if ($canonical_team_id > 0) {
        update_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID, (string) $canonical_team_id);
    }
}

/**
 * 重複 meta があるときは umeta_id が最新の managed 内 ID を採用し、1件に修復する。
 *
 * @param int $user_id
 * @return int
 */
function aidunite_resolve_stored_operating_team_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    $stored = aidunite_get_all_stored_operating_team_ids($user_id);
    if ($stored === []) {
        return 0;
    }
    $managed = aidunite_get_managed_team_ids($user_id);
    $chosen = 0;
    foreach ($stored as $tid) {
        if (in_array($tid, $managed, true)) {
            $chosen = $tid;
        }
    }
    if (count($stored) > 1 && $chosen > 0) {
        aidunite_dedupe_user_current_operating_team_meta($user_id, $chosen);
    }

    return $chosen;
}

/**
 * 操作中の team ID（0=なし）。未設定時は primary_team_id → team_id → managed 先頭の順でフォールバック。
 *
 * @param int|null $user_id null のとき現在ユーザー
 * @return int
 */
function aidunite_get_current_team_id($user_id = null) {
    $uid = $user_id !== null ? (int) $user_id : get_current_user_id();
    if ($uid <= 0) {
        return 0;
    }

    $managed = aidunite_get_managed_team_ids($uid);

    $cur = function_exists('aidunite_resolve_stored_operating_team_id')
        ? (int) aidunite_resolve_stored_operating_team_id($uid)
        : (int) get_user_meta($uid, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID, true);
    if ($cur > 0 && in_array($cur, $managed, true)) {
        return $cur;
    }

    $primary = (int) get_user_meta($uid, 'primary_team_id', true);
    if ($primary > 0 && in_array($primary, $managed, true)) {
        return $primary;
    }

    $legacy = (int) get_user_meta($uid, 'team_id', true);
    if (
        $legacy > 0
        && aidunite_team_cpt_is_publish_for_operation_context($legacy)
        && (empty($managed) || in_array($legacy, $managed, true))
    ) {
        return $legacy;
    }

    if (!empty($managed)) {
        return (int) $managed[0];
    }

    return 0;
}

/**
 * 操作中 team を保存。managed に含まれる ID のみ許可。
 *
 * @param int $user_id
 * @param int $team_id
 * @return bool|\WP_Error
 */
function aidunite_set_current_operating_team_id($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0) {
        return new WP_Error('invalid_user', 'Invalid user.');
    }
    if ($team_id <= 0) {
        delete_user_meta($user_id, AIDUNITE_USER_META_CURRENT_OPERATING_TEAM_ID);
        return true;
    }
    if (!aidunite_user_has_managed_team_access($user_id, $team_id)) {
        return new WP_Error('team_not_allowed', 'そのチームとして操作する権限がありません。');
    }
    aidunite_dedupe_user_current_operating_team_meta($user_id, $team_id);
    return true;
}

/**
 * スケジュール登録・レガシー REST 等で使う「代表者の操作 team」解決。
 * current → レガシー user_meta team_id → team 投稿の team_members に含まれる先頭 team。
 *
 * @param int $user_id
 * @return int
 */
function aidunite_resolve_user_team_id_for_schedule_ops($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    $tid = (int) aidunite_get_current_team_id($user_id);
    if ($tid > 0) {
        return $tid;
    }
    $legacy = (int) get_user_meta($user_id, 'team_id', true);
    if ($legacy > 0 && aidunite_team_cpt_is_publish_for_operation_context($legacy)) {
        return $legacy;
    }
    $user_teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'team_members',
                'value' => '"' . $user_id . '"',
                'compare' => 'LIKE',
            ],
        ],
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    return !empty($user_teams) ? (int) $user_teams[0] : 0;
}

/**
 * schedule 投稿の「所有者チーム」推定（post meta `team_id` 優先、欠損時は著者の操作中 / レガシー）。
 *
 * @param int $schedule_post_id
 * @return int
 */
function aidunite_resolve_schedule_owner_team_id($schedule_post_id) {
    $pid = (int) $schedule_post_id;
    if ($pid <= 0) {
        return 0;
    }
    $tid = (int) get_post_meta($pid, 'team_id', true);
    if ($tid > 0 && aidunite_team_cpt_is_publish_for_operation_context($tid)) {
        return $tid;
    }
    $author = (int) get_post_field('post_author', $pid);
    if ($author <= 0) {
        return 0;
    }
    return (int) aidunite_get_current_team_id($author);
}

/**
 * 操作中チーム（current_operating_team_id）の schedule のみに絞る meta_query。
 * カレンダー・マイページ・スケジュール管理の「今どのチームで見ているか」に合わせる。
 *
 * @param int $user_id
 * @return array|null
 */
function aidunite_schedule_team_meta_query_for_operating_team($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    $tid = (int) aidunite_get_current_team_id($user_id);
    if ($tid <= 0) {
        return null;
    }

    return [
        'key'     => 'team_id',
        'value'   => $tid,
        'compare' => '=',
    ];
}

/**
 * schedule の team_id メタが、ユーザーが管理するいずれかのチームと一致する meta_query 1要素（横断閲覧用）。
 *
 * @param int $user_id
 * @return array|null
 */
function aidunite_schedule_team_meta_query_for_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    $ids = aidunite_get_managed_team_ids($user_id);
    if (empty($ids)) {
        return null;
    }
    if (count($ids) === 1) {
        return [
            'key' => 'team_id',
            'value' => (int) $ids[0],
            'compare' => '=',
        ];
    }
    return [
        'key' => 'team_id',
        'value' => array_values(array_map('intval', $ids)),
        'compare' => 'IN',
    ];
}

/**
 * 二ユーザーの managed（またはレガシー単一 team_id）所属に共通の team ID があるか。
 * 例: チーム代表が別チーム所属の選手を誤って操作しない／複数所属で共有チームのみ許可。
 *
 * @param int $user_a
 * @param int $user_b
 */
function aidunite_users_share_managed_team($user_a, $user_b) {
    $user_a = (int) $user_a;
    $user_b = (int) $user_b;
    if ($user_a <= 0 || $user_b <= 0) {
        return false;
    }
    if (!function_exists('aidunite_get_managed_team_ids')) {
        $ta = (int) get_user_meta($user_a, 'team_id', true);
        $tb = (int) get_user_meta($user_b, 'team_id', true);
        return $ta > 0 && $ta === $tb;
    }
    $ids_a = aidunite_get_managed_team_ids($user_a);
    $ids_b = aidunite_get_managed_team_ids($user_b);
    if (empty($ids_a) || empty($ids_b)) {
        return false;
    }
    return !empty(array_intersect($ids_a, $ids_b));
}

/**
 * usermeta の JSON_CONTAINS 等が使える DB か（MySQL 5.7.8+ / MariaDB 10.2.3+ 目安）。
 */
function aidunite_db_supports_json_contains_user_meta() {
    global $wpdb;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ver = (string) $wpdb->db_version();
    if (stripos($ver, 'mariadb') !== false) {
        $num = preg_replace('/-.*/', '', $ver);
        $ok = version_compare($num, '10.2.3', '>=');
    } else {
        $ok = version_compare($ver, '5.7.8', '>=');
    }
    return $ok;
}

/**
 * recruit で schedule_gender を both にできるか（MVP: 常に不可）。
 *
 * @param int    $schedule_post_id 新規は 0
 * @param string $incoming_canonical male|female（正規化済み推奨）
 */
function aidunite_recruit_both_gender_save_permitted($schedule_post_id, $incoming_canonical) {
    if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($incoming_canonical)) {
        return false;
    }
    if (function_exists('aidunite_normalize_gender_canonical')) {
        $g = aidunite_normalize_gender_canonical((string) $incoming_canonical);
        if ($g === '' && strtolower(trim((string) $incoming_canonical)) === 'both') {
            return false;
        }
    }
    if (strtolower(trim((string) $incoming_canonical)) === 'both') {
        return false;
    }

    return true;
}

/**
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_get_team_context(WP_REST_Request $request) {
    $uid = get_current_user_id();
    if ($uid <= 0) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }
    $current = (int) aidunite_get_current_team_id($uid);
    $managed = aidunite_get_managed_team_ids($uid);
    $teams = [];
    foreach ($managed as $tid) {
        $teams[] = [
            'id' => $tid,
            'title' => get_the_title($tid) ?: (string) $tid,
        ];
    }
    return new WP_REST_Response([
        'current_team_id' => $current,
        'managed_team_ids' => $managed,
        'teams' => $teams,
    ], 200);
}

/**
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_post_current_operating_team(WP_REST_Request $request) {
    $uid = get_current_user_id();
    if ($uid <= 0) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $team_id = isset($params['team_id']) ? (int) $params['team_id'] : 0;
    $res = aidunite_set_current_operating_team_id($uid, $team_id);
    if (is_wp_error($res)) {
        $status = ($res->get_error_code() === 'invalid_user') ? 400 : 403;
        return new WP_Error($res->get_error_code(), $res->get_error_message(), ['status' => $status]);
    }
    return new WP_REST_Response([
        'success' => true,
        'current_team_id' => (int) aidunite_get_current_team_id($uid),
    ], 200);
}

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/team-context', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_get_team_context',
        'permission_callback' => function () {
            if (!class_exists('AidUniteAuthMiddleware', false)) {
                require_once get_template_directory() . '/functions/common/auth-middleware.php';
            }
            return AidUniteAuthMiddleware::require_auth(false)->is_valid();
        },
    ]);
    register_rest_route('aidunite/v1', '/current-operating-team', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_post_current_operating_team',
        'permission_callback' => function () {
            if (!class_exists('AidUniteAuthMiddleware', false)) {
                require_once get_template_directory() . '/functions/common/auth-middleware.php';
            }
            return AidUniteAuthMiddleware::require_auth(false)->is_valid();
        },
    ]);
});
