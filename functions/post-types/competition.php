<?php
/**
 * 大会・カップ・イベント CPT 登録（メタ編集は persist 経由のみ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * competition_event / competition_entry / competition_block
 */
function aidunite_register_competition_post_types() {
    $common = [
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'capability_type' => 'post',
        'supports' => ['title'],
        'has_archive' => false,
    ];

    register_post_type('competition_event', array_merge($common, [
        'labels' => [
            'name' => '大会・イベント',
            'singular_name' => '大会・イベント',
            'menu_name' => '大会・イベント',
        ],
    ]));

    register_post_type('competition_entry', array_merge($common, [
        'labels' => [
            'name' => '大会参加',
            'singular_name' => '大会参加',
            'menu_name' => '大会参加',
        ],
    ]));

    register_post_type('competition_block', array_merge($common, [
        'labels' => [
            'name' => '大会スケジュールブロック',
            'singular_name' => '大会スケジュールブロック',
            'menu_name' => '大会ブロック',
        ],
    ]));

    register_post_type('competition_fixture', array_merge($common, [
        'labels' => [
            'name' => '大会試合',
            'singular_name' => '大会試合',
            'menu_name' => '大会試合',
        ],
    ]));
}
add_action('init', 'aidunite_register_competition_post_types');
