<?php
/**
 * 分析基盤 bootstrap
 */

if (!defined('ABSPATH')) {
    exit;
}

$aidunite_analytics_dir = __DIR__;

require_once $aidunite_analytics_dir . '/analytics-config.php';
require_once $aidunite_analytics_dir . '/metrics-registry.php';
require_once $aidunite_analytics_dir . '/analytics-db.php';
require_once $aidunite_analytics_dir . '/page-analytics-aggregate.php';
require_once $aidunite_analytics_dir . '/page-analytics-tracker.php';
require_once $aidunite_analytics_dir . '/rest-page-analytics.php';
require_once $aidunite_analytics_dir . '/analytics-persist.php';
require_once $aidunite_analytics_dir . '/page-events.php';
require_once $aidunite_analytics_dir . '/rest-page-events.php';
require_once $aidunite_analytics_dir . '/team-funnel.php';
require_once $aidunite_analytics_dir . '/team-active.php';
require_once $aidunite_analytics_dir . '/pv-analytics.php';
require_once $aidunite_analytics_dir . '/churn-analytics.php';
require_once $aidunite_analytics_dir . '/ai-monthly-report.php';
require_once $aidunite_analytics_dir . '/admin-analytics-ui.php';
require_once $aidunite_analytics_dir . '/analytics-retention.php';
