<?php
/**
 * CLI: register-schedule-v2 登録経路の診断
 * Usage: php tools/register-schedule-v2-diagnose.php [user_id]
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
    fwrite(STDERR, "wp-load not found: {$wp_load}\n");
    exit(1);
}

define('WP_USE_THEMES', false);
require $wp_load;

$user_id = isset($argv[1]) ? (int) $argv[1] : 0;
if ($user_id < 1) {
    $leaders = get_users([
        'role__in' => ['administrator', 'team_leader'],
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
    $user_id = !empty($leaders) ? (int) $leaders[0]->ID : 1;
}

wp_set_current_user($user_id);
if (!defined('REST_REQUEST')) {
    define('REST_REQUEST', true);
}

$team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
    ? (int) aidunite_resolve_user_team_id_for_schedule_ops($user_id)
    : (int) get_user_meta($user_id, 'team_id', true);

echo "user_id={$user_id} team_id={$team_id} REST_REQUEST=" . (REST_REQUEST ? '1' : '0') . "\n";

$params = [
    'ui_schedule_kind' => 'recruit',
    'intent' => 'recruit',
    'date' => date('Y-m-d', strtotime('+7 days')),
    'start_date' => date('Y-m-d', strtotime('+7 days')),
    'start_time' => '10:00',
    'end_time' => '12:00',
    'venue_condition' => 'either',
    'gender_condition' => 'male',
    'male_teams' => 2,
    'female_teams' => 0,
    'note' => 'diagnose-cli',
];

$request = new class($params) {
    private $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function get_json_params()
    {
        return $this->params;
    }
};

try {
    $result = AidUniteScheduleRegistration::registerSchedule($request);
    if (is_wp_error($result)) {
        echo "WP_Error: " . $result->get_error_code() . " / " . $result->get_error_message() . "\n";
        echo json_encode($result->get_error_data(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(2);
    }
    echo "OK\n";
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    exit(0);
} catch (Throwable $e) {
    echo "Throwable: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(3);
}
