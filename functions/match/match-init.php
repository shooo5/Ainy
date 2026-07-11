<?php
/**
 * マッチ関連の一括読込（REST API・スコアロジック）
 * functions.php の No.36 ブロックを集約
 */

if (!defined('ABSPATH')) {
  exit;
}

$theme_dir = get_stylesheet_directory();

require_once $theme_dir . '/functions/match/match-cancel-policy.php';
require_once $theme_dir . '/functions/match/match-request-persist-read.php';
require_once $theme_dir . '/functions/match/match-request-persist.php';
require_once $theme_dir . '/functions/match/match-request-persist-submit.php';
require_once $theme_dir . '/functions/match/match-feedback-persist-read.php';
require_once $theme_dir . '/functions/match/match-feedback-persist.php';
require_once $theme_dir . '/functions/match/match-board-persist.php';
require_once $theme_dir . '/functions/match/match-board-persist-read.php';
require_once $theme_dir . '/functions/match/rest-match-board-card-seen.php';
require_once $theme_dir . '/functions/match/match-request-functions.php';
require_once $theme_dir . '/functions/rest-match-request.php';
require_once $theme_dir . '/functions/match/rest-match-board-request.php';
require_once $theme_dir . '/functions/match/match-invite-functions.php';
require_once $theme_dir . '/functions/match/rest-match-invite.php';
require_once $theme_dir . '/functions/match/rest-match-history.php';
require_once $theme_dir . '/functions/match/rest-match-candidates.php';
require_once $theme_dir . '/functions/match/rest-match-feedback.php';
require_once $theme_dir . '/functions/match/admin-match-feedback-list.php';
require_once $theme_dir . '/functions/match/match-feedback-automation.php';
require_once $theme_dir . '/functions/match/match-feedback-form-render.php';
require_once $theme_dir . '/functions/match/rest-rematch-suggestions.php';
require_once $theme_dir . '/functions/match/post-match-module.php';
require_once $theme_dir . '/functions/match/rest-post-match-module.php';
require_once $theme_dir . '/functions/match/rest-match-analytics.php';
require_once $theme_dir . '/functions/match/match-score.php';
