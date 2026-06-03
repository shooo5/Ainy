<?php
/**
 * ヘッダー用: 複数チーム管理時の「操作中チーム」切替 UI の設定・マークアップ。
 *
 * @package AidUnite
 * @see docs/spec/schedule.md 第6節・第18節
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * フロント用の切替 UI 設定（2チーム以上の team_leader のみ有効）。
 *
 * @param int $user_id
 * @return array{enabled:bool,restUrlSetCurrent?:string,nonce?:string,currentTeamId?:int,teams?:array<int,array{id:int,title:string}>,teamPublicProfileUrls?:array<string,string>,teamSettingsUrl?:string,matchBoardUrl?:string}
 */
function aidunite_get_operating_team_header_switcher_config($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['enabled' => false];
    }
    if (!function_exists('aidunite_get_managed_team_ids') || !function_exists('aidunite_get_current_team_id')) {
        return ['enabled' => false];
    }
    $role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';
    if ($role !== 'team_leader' && !user_can($user_id, 'manage_options')) {
        return ['enabled' => false];
    }
    $managed = aidunite_get_managed_team_ids($user_id);
    if (count($managed) < 2) {
        return ['enabled' => false];
    }
    $teams = [];
    $team_public_profile_urls = [];
    foreach ($managed as $tid) {
        $tid = (int) $tid;
        $meta_name = (string) get_post_meta($tid, 'team_name', true);
        $teams[] = [
            'id' => $tid,
            'title' => $meta_name !== '' ? $meta_name : (get_the_title($tid) ?: ('Team ' . $tid)),
        ];
        if (function_exists('aidunite_get_team_public_profile_url')) {
            $team_public_profile_urls[(string) $tid] = aidunite_get_team_public_profile_url($tid);
        }
    }
    $team_settings_url = function_exists('aidunite_get_team_settings_page_url')
        ? aidunite_get_team_settings_page_url()
        : home_url('/team-settings');

    return [
        'enabled' => true,
        'restUrlSetCurrent' => rest_url('aidunite/v1/current-operating-team'),
        'nonce' => wp_create_nonce('wp_rest'),
        'currentTeamId' => (int) aidunite_get_current_team_id($user_id),
        'teams' => $teams,
        'teamPublicProfileUrls' => $team_public_profile_urls,
        'teamSettingsUrl' => $team_settings_url,
        'matchBoardUrl' => home_url('/match-board-own'),
    ];
}

/**
 * 操作中チーム切替セレクト（ヘッダー・チーム設定など共通）。
 *
 * @param array<string, mixed> $args select_id, label, wrapper_class, label_class, select_class, aria_label
 */
function aidunite_render_operating_team_switcher_select(array $args = []) {
    if (!is_user_logged_in()) {
        return;
    }
    $args = wp_parse_args($args, [
        'select_id' => 'ainy-operating-team-select',
        'label' => '操作中',
        'wrapper_class' => 'ainy-operating-team',
        'label_class' => 'ainy-operating-team__label',
        'select_class' => 'ainy-operating-team__select',
        'aria_label' => '操作中のチームを切り替え',
    ]);
    $cfg = aidunite_get_operating_team_header_switcher_config(get_current_user_id());
    if (empty($cfg['enabled']) || empty($cfg['teams'])) {
        return;
    }
    $current = (int) ($cfg['currentTeamId'] ?? 0);
    $select_id = (string) $args['select_id'];
    ?>
    <div class="<?php echo esc_attr((string) $args['wrapper_class']); ?>">
        <label class="<?php echo esc_attr((string) $args['label_class']); ?>" for="<?php echo esc_attr($select_id); ?>">
            <?php echo esc_html((string) $args['label']); ?>
        </label>
        <select
            id="<?php echo esc_attr($select_id); ?>"
            class="<?php echo esc_attr((string) $args['select_class']); ?>"
            data-aidunite-operating-team-select
            aria-label="<?php echo esc_attr((string) $args['aria_label']); ?>"
        >
            <?php foreach ($cfg['teams'] as $t) : ?>
                <option value="<?php echo esc_attr((string) $t['id']); ?>" <?php selected($current, (int) $t['id']); ?>>
                    <?php echo esc_html($t['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

/**
 * ログイン済みヘッダー内に操作中チーム用のセレクトを出力（有効時のみ）。
 */
function aidunite_render_operating_team_header_control() {
    if (!is_user_logged_in()) {
        return;
    }
    $cfg = aidunite_get_operating_team_header_switcher_config(get_current_user_id());
    if (empty($cfg['enabled'])) {
        return;
    }
    ?>
    <div id="ainy-operating-team-root">
        <?php
        aidunite_render_operating_team_switcher_select([
            'select_id' => 'ainy-operating-team-select',
            'label' => '操作中',
            'aria_label' => '操作中のチームを切り替え',
        ]);
        ?>
    </div>
    <?php
}
