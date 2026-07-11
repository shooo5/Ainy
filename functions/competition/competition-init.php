<?php
/**
 * 大会・イベント機能 bootstrap
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$competition_dir = get_stylesheet_directory() . '/functions/competition';

require_once $competition_dir . '/competition-persist.php';
require_once $competition_dir . '/competition-persist-read.php';
require_once $competition_dir . '/competition-persist-submit.php';
require_once $competition_dir . '/competition-generator-templates.php';
require_once $competition_dir . '/competition-generator-slot.php';
require_once $competition_dir . '/competition-generator.php';
require_once $competition_dir . '/competition-reminder.php';
require_once $competition_dir . '/competition-payment.php';
require_once $competition_dir . '/competition-public.php';
require_once $competition_dir . '/competition-refund.php';
require_once get_stylesheet_directory() . '/functions/post-types/competition.php';
require_once $competition_dir . '/rest-competition.php';
