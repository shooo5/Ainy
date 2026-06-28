<?php

/**

 * 選手 usermeta 書込・normalize 正本

 *

 * @package AidUnite

 */



if (!defined('ABSPATH')) {

    exit;

}



/**

 * POST から登録フォーム入力を正規化

 *

 * @param array<string, mixed> $raw

 * @param array<string, mixed> $context is_minor_team, team_leader_id

 * @return array<string, mixed>

 */

function aidunite_player_normalize_registration_input_from_post(array $raw, array $context = []) {

    $is_minor_team = !empty($context['is_minor_team']);



    $player_name_sei = sanitize_text_field((string) ($raw['player_name_sei'] ?? ''));

    $player_name_mei = sanitize_text_field((string) ($raw['player_name_mei'] ?? ''));

    $player_kana_sei = sanitize_text_field((string) ($raw['player_kana_sei'] ?? ''));

    $player_kana_mei = sanitize_text_field((string) ($raw['player_kana_mei'] ?? ''));



    $player_name = trim($player_name_sei . ' ' . $player_name_mei);

    if ($player_name === '' && !empty($raw['player_name'])) {

        $player_name = sanitize_text_field((string) $raw['player_name']);

    }



    $player_kana = trim($player_kana_sei . ' ' . $player_kana_mei);

    if ($player_kana === '' && !empty($raw['player_kana'])) {

        $player_kana = sanitize_text_field((string) $raw['player_kana']);

    }



    $email_raw = (string) ($raw['player_email'] ?? '');

    $player_email = function_exists('aidunite_normalize_email')

        ? aidunite_normalize_email($email_raw)

        : sanitize_email($email_raw);



    $player_data = [

        'player_name' => $player_name,

        'player_name_kana' => $player_kana,

        'player_name_sei' => $player_name_sei,

        'player_name_mei' => $player_name_mei,

        'player_kana_sei' => $player_kana_sei,

        'player_kana_mei' => $player_kana_mei,

        'birth_date' => sanitize_text_field((string) ($raw['player_birth_date'] ?? '')),

        'grade' => sanitize_text_field((string) ($raw['player_grade'] ?? '')),

        'position' => sanitize_text_field((string) ($raw['player_position'] ?? '')),

        'nickname' => sanitize_text_field((string) ($raw['player_nickname'] ?? '')),

        'club_team' => sanitize_text_field((string) ($raw['player_club_team'] ?? '')),

        'player_email' => $player_email,

        'height' => sanitize_text_field((string) ($raw['player_height'] ?? '')),

        'weight' => sanitize_text_field((string) ($raw['player_weight'] ?? '')),

        'password_option' => 'auto',

        'custom_password' => '',

    ];



    $parent = [];

    $emergency = [];



    if ($is_minor_team) {

        $parent_name_sei = sanitize_text_field((string) ($raw['parent_name_sei'] ?? ''));

        $parent_name_mei = sanitize_text_field((string) ($raw['parent_name_mei'] ?? ''));

        $parent_email_raw = (string) ($raw['parent_email'] ?? '');

        $parent_email = function_exists('aidunite_normalize_email')

            ? aidunite_normalize_email($parent_email_raw)

            : sanitize_email($parent_email_raw);



        $parent = [

            'parent_name' => trim($parent_name_sei . ' ' . $parent_name_mei),

            'parent_name_sei' => $parent_name_sei,

            'parent_name_mei' => $parent_name_mei,

            'parent_email' => $parent_email,

            'parent_emergency_contact' => sanitize_text_field((string) ($raw['parent_emergency_contact'] ?? '')),

            'create_account' => false,

        ];

    } else {

        $emergency_email_raw = (string) ($raw['emergency_contact_email'] ?? '');

        $emergency_email = function_exists('aidunite_normalize_email')

            ? aidunite_normalize_email($emergency_email_raw)

            : sanitize_email($emergency_email_raw);



        $emergency = [

            'emergency_contact_name' => sanitize_text_field((string) ($raw['emergency_contact_name'] ?? '')),

            'emergency_contact_relationship' => sanitize_text_field((string) ($raw['emergency_contact_relationship'] ?? '')),

            'emergency_contact_phone' => sanitize_text_field((string) ($raw['emergency_contact_phone'] ?? '')),

            'emergency_contact_email' => $emergency_email,

            'create_account' => false,

        ];

    }



    return [

        'player' => $player_data,

        'parent' => $parent,

        'emergency' => $emergency,

        'is_minor_team' => $is_minor_team,

    ];

}



/**

 * 登録入力のサーバー側バリデーション

 *

 * @param array<string, mixed> $normalized

 * @param bool                 $is_minor_team

 * @return string[]

 */

function aidunite_player_validate_registration_input(array $normalized, $is_minor_team, array $opts = []) {

    $errors = [];

    $player = is_array($normalized['player'] ?? null) ? $normalized['player'] : [];

    $registered_by_parent = !empty($opts['registered_by_parent']);



    if (empty($player['player_name_sei']) || empty($player['player_name_mei'])) {

        $errors[] = '選手名（姓・名）は必須です';

    }

    if (empty($player['birth_date'])) {

        $errors[] = '生年月日は必須です';

    }

    if (empty($player['nickname'])) {

        $errors[] = 'ニックネームは必須です';

    }



    if ($is_minor_team && !$registered_by_parent) {

        $parent = is_array($normalized['parent'] ?? null) ? $normalized['parent'] : [];

        if (empty($parent['parent_email'])) {

            $errors[] = '未成年チームでは保護者メールアドレスは必須です';

        }

        $player_email = (string) ($player['player_email'] ?? '');

        $parent_email = (string) ($parent['parent_email'] ?? '');

        if ($player_email !== '' && $parent_email !== '') {

            $norm_player = function_exists('aidunite_normalize_email')

                ? aidunite_normalize_email($player_email)

                : sanitize_email($player_email);

            $norm_parent = function_exists('aidunite_normalize_email')

                ? aidunite_normalize_email($parent_email)

                : sanitize_email($parent_email);

            if ($norm_player !== '' && $norm_player === $norm_parent) {

                $errors[] = '選手メールと保護者メールは別のアドレスを入力してください（中学以下は保護者メールを優先します）';

            }

        }

    }



    return $errors;

}



/**

 * POST から編集フォーム入力を正規化

 *

 * @param array<string, mixed> $post

 * @return array<string, mixed>

 */

function aidunite_player_normalize_edit_input_from_post(array $post) {

    $player_name_sei = sanitize_text_field((string) ($post['player_name_sei'] ?? ''));

    $player_name_mei = sanitize_text_field((string) ($post['player_name_mei'] ?? ''));

    $player_kana_sei = sanitize_text_field((string) ($post['player_kana_sei'] ?? ''));

    $player_kana_mei = sanitize_text_field((string) ($post['player_kana_mei'] ?? ''));



    $email_raw = (string) ($post['player_email'] ?? '');

    $player_email = function_exists('aidunite_normalize_email')

        ? aidunite_normalize_email($email_raw)

        : sanitize_email($email_raw);



    $parent_email_raw = (string) ($post['parent_email'] ?? '');

    $parent_email = function_exists('aidunite_normalize_email')

        ? aidunite_normalize_email($parent_email_raw)

        : sanitize_email($parent_email_raw);



    $fields = [

        'player_name' => trim($player_name_sei . ' ' . $player_name_mei),

        'player_name_sei' => $player_name_sei,

        'player_name_mei' => $player_name_mei,

        'player_name_kana' => trim($player_kana_sei . ' ' . $player_kana_mei),

        'player_kana_sei' => $player_kana_sei,

        'player_kana_mei' => $player_kana_mei,

        'player_nickname' => sanitize_text_field((string) ($post['player_nickname'] ?? '')),

        'player_birth_date' => sanitize_text_field((string) ($post['player_birth_date'] ?? '')),

        'player_grade' => sanitize_text_field((string) ($post['player_grade'] ?? '')),

        'player_position' => sanitize_text_field((string) ($post['player_position'] ?? '')),

        'player_club_team' => sanitize_text_field((string) ($post['player_club_team'] ?? '')),

        'player_email' => $player_email,

        'player_height' => sanitize_text_field((string) ($post['player_height'] ?? '')),

        'player_weight' => sanitize_text_field((string) ($post['player_weight'] ?? '')),

    ];



    if (function_exists('aidunite_normalize_player_payload')) {

        $fields = aidunite_normalize_player_payload($fields);

    }



    return [

        'player' => $fields,

        'parent' => [

            'parent_name' => trim(

                sanitize_text_field((string) ($post['parent_name_sei'] ?? ''))

                . ' '

                . sanitize_text_field((string) ($post['parent_name_mei'] ?? ''))

            ),

            'parent_name_sei' => sanitize_text_field((string) ($post['parent_name_sei'] ?? '')),

            'parent_name_mei' => sanitize_text_field((string) ($post['parent_name_mei'] ?? '')),

            'parent_email' => $parent_email,

            'parent_emergency_contact' => sanitize_text_field((string) ($post['parent_emergency_contact'] ?? '')),

        ],

    ];

}



/**

 * 選手編集フォームの usermeta 保存（page-edit-player 用・唯一の書き込み口）

 *

 * @param int                  $player_id

 * @param array<string, mixed> $normalized aidunite_player_normalize_edit_input_from_post の戻り値

 * @param bool                 $include_parent 未成年チーム時 true

 * @return bool

 */

function aidunite_player_persist_edit_meta($player_id, array $normalized, $include_parent = false) {

    $player_id = (int) $player_id;

    if ($player_id <= 0) {

        return false;

    }



    $player_fields = is_array($normalized['player'] ?? null) ? $normalized['player'] : [];

    foreach ($player_fields as $key => $value) {

        update_user_meta($player_id, (string) $key, $value);

    }



    if (

        !empty($player_fields['player_birth_date'])

        && empty($player_fields['player_grade'])

        && function_exists('aidunite_calculate_grade_from_birthdate')

    ) {

        $grade = aidunite_calculate_grade_from_birthdate((string) $player_fields['player_birth_date']);

        if ($grade !== '') {

            update_user_meta($player_id, 'player_grade', $grade);

        }

    }



    if ($include_parent) {

        $parent_fields = is_array($normalized['parent'] ?? null) ? $normalized['parent'] : [];

        foreach ($parent_fields as $key => $value) {

            update_user_meta($player_id, (string) $key, $value);

        }

    }



    return true;

}



/**

 * 保護者紐付け待ちフラグ

 *

 * @param int  $player_id

 * @param bool $needs

 */

function aidunite_player_persist_needs_parent_link_flag($player_id, $needs = true) {

    $player_id = (int) $player_id;

    if ($player_id <= 0) {

        return;

    }

    if ($needs) {

        update_user_meta($player_id, 'needs_parent_link', true);

    } else {

        delete_user_meta($player_id, 'needs_parent_link');

    }

}



/**

 * チーム長登録フォーム等の入力を canonical + legacy メタ配列へ

 *

 * @param array<string, mixed> $player_data

 * @param array<string, mixed> $context team_leader_id, is_adult

 * @return array{canonical: array<string, string>, extras: array<string, mixed>, legacy: array<string, string>}

 */

function aidunite_player_normalize_registration_from_form(array $player_data, array $context = []) {

    $team_leader_id = (int) ($context['team_leader_id'] ?? 0);

    $birth_date = (string) ($player_data['birth_date'] ?? $player_data['player_birth_date'] ?? '');



    $canonical = [

        'player_name' => (string) ($player_data['player_name'] ?? ''),

        'player_name_sei' => (string) ($player_data['player_name_sei'] ?? ''),

        'player_name_mei' => (string) ($player_data['player_name_mei'] ?? ''),

        'player_name_kana' => (string) ($player_data['player_name_kana'] ?? ''),

        'player_kana_sei' => (string) ($player_data['player_kana_sei'] ?? ''),

        'player_kana_mei' => (string) ($player_data['player_kana_mei'] ?? ''),

        'player_birth_date' => $birth_date,

        'player_grade' => (string) ($player_data['grade'] ?? $player_data['player_grade'] ?? ''),

        'player_position' => (string) ($player_data['position'] ?? $player_data['player_position'] ?? ''),

        'player_nickname' => (string) ($player_data['nickname'] ?? $player_data['player_nickname'] ?? ''),

        'player_club_team' => (string) ($player_data['club_team'] ?? $player_data['player_club_team'] ?? ''),

        'player_email' => (string) ($player_data['player_email'] ?? ''),

        'player_height' => (string) ($player_data['height'] ?? $player_data['player_height'] ?? ''),

        'player_weight' => (string) ($player_data['weight'] ?? $player_data['player_weight'] ?? ''),

    ];



    if (function_exists('aidunite_normalize_player_payload')) {

        $canonical = aidunite_normalize_player_payload($canonical);

    }



    $extras = [

        'jersey_number' => (string) ($player_data['jersey_number'] ?? ''),

        'registered_by_team_leader' => $team_leader_id,

        'registration_date' => current_time('mysql'),

    ];

    if (!empty($context['is_adult'])) {

        $extras['is_adult_player'] = true;

    }



    $legacy = [

        'birth_date' => $birth_date,

        'grade' => (string) ($player_data['grade'] ?? $player_data['player_grade'] ?? ''),

        'position' => (string) ($player_data['position'] ?? $player_data['player_position'] ?? ''),

    ];



    return [

        'canonical' => $canonical,

        'extras' => $extras,

        'legacy' => $legacy,

    ];

}



/**

 * @param int                  $player_id

 * @param array<string, mixed> $fields

 * @param bool                 $skip_empty

 */

function aidunite_player_write_meta_map($player_id, array $fields, $skip_empty = false) {

    $player_id = (int) $player_id;

    if ($player_id <= 0) {

        return;

    }

    foreach ($fields as $key => $value) {

        if ($value === true) {

            update_user_meta($player_id, (string) $key, true);

            continue;

        }

        $str = is_scalar($value) ? (string) $value : '';

        if ($skip_empty && $str === '') {

            continue;

        }

        update_user_meta($player_id, (string) $key, $str);

    }

}



/**

 * 生年月日から age / player_grade / grade を保存（登録・編集共通）

 *

 * @param int    $player_id

 * @param string $birth_date

 * @param string $grade_from_form

 */

function aidunite_player_persist_age_and_grade_from_birth($player_id, $birth_date, $grade_from_form = '') {

    $player_id = (int) $player_id;

    $birth_date = trim((string) $birth_date);

    if ($player_id <= 0 || $birth_date === '') {

        return;

    }



    try {

        $birth = new DateTime($birth_date);

        $today = new DateTime();

        $age = $today->diff($birth)->y;

        update_user_meta($player_id, 'age', $age);

    } catch (Exception $e) {

        return;

    }



    if (!function_exists('aidunite_calculate_grade_from_birthdate')) {

        return;

    }



    $grade = aidunite_calculate_grade_from_birthdate($birth_date);

    if ($grade === '') {

        return;

    }



    update_user_meta($player_id, 'player_grade', $grade);

    update_user_meta($player_id, 'grade', $grade);

}



/**

 * チーム長による選手登録の usermeta 保存（parent-functions 用・唯一の書き込み口）

 *

 * @param int                  $player_id

 * @param array<string, mixed> $player_data  登録フォーム入力

 * @param array<string, mixed> $context      team_leader_id, skip_empty, is_adult

 */

function aidunite_player_persist_registration_meta($player_id, array $player_data, array $context = []) {

    $player_id = (int) $player_id;

    if ($player_id <= 0) {

        return false;

    }



    $skip_empty = !empty($context['skip_empty']);

    $packed = aidunite_player_normalize_registration_from_form($player_data, $context);



    aidunite_player_write_meta_map($player_id, $packed['canonical'], $skip_empty);

    aidunite_player_write_meta_map($player_id, $packed['extras'], $skip_empty);

    aidunite_player_write_meta_map($player_id, $packed['legacy'], $skip_empty);



    $birth = (string) ($packed['canonical']['player_birth_date'] ?? $packed['legacy']['birth_date'] ?? '');

    $grade = (string) ($player_data['grade'] ?? $player_data['player_grade'] ?? '');

    aidunite_player_persist_age_and_grade_from_birth($player_id, $birth, $grade);



    return true;

}



/**

 * 成人選手登録の緊急連絡先 usermeta

 *

 * @param int                  $player_id

 * @param array<string, mixed> $emergency_contact_data

 * @param bool                 $skip_empty

 */

function aidunite_player_persist_emergency_contact_meta($player_id, array $emergency_contact_data, $skip_empty = true) {

    $player_id = (int) $player_id;

    if ($player_id <= 0 || empty($emergency_contact_data)) {

        return false;

    }



    $fields = [

        'emergency_contact_name' => (string) ($emergency_contact_data['emergency_contact_name'] ?? ''),

        'emergency_contact_relationship' => (string) ($emergency_contact_data['emergency_contact_relationship'] ?? ''),

        'emergency_contact_phone' => (string) ($emergency_contact_data['emergency_contact_phone'] ?? ''),

        'emergency_contact_email' => (string) ($emergency_contact_data['emergency_contact_email'] ?? ''),

        'emergency_contact_linked_date' => current_time('mysql'),

    ];



    aidunite_player_write_meta_map($player_id, $fields, $skip_empty);



    return true;

}

/**
 * @param int    $player_id
 * @param string $url
 */
function aidunite_player_persist_photo_url($player_id, $url) {
    $player_id = (int) $player_id;
    $url = esc_url_raw((string) $url);
    if ($player_id <= 0 || $url === '') {
        return;
    }

    update_user_meta($player_id, aidunite_player_read_photo_meta_key(), $url);
}

/**
 * @param int $player_id
 */
function aidunite_player_persist_photo_remove($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return;
    }

    delete_user_meta($player_id, aidunite_player_read_photo_meta_key());
}

/**
 * @param int $code
 * @return string
 */
function aidunite_player_persist_photo_upload_error_message($code) {
    switch ((int) $code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'ファイルサイズが大きすぎます。';
        case UPLOAD_ERR_PARTIAL:
            return '画像のアップロードが途中で中断されました。';
        case UPLOAD_ERR_NO_FILE:
            return '画像ファイルが選択されていません。';
        default:
            return '画像のアップロードに失敗しました。';
    }
}

/**
 * @param array<string, mixed> $file $_FILES 要素
 * @return string|WP_Error
 */
function aidunite_player_persist_photo_handle_upload(array $file) {
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('player_photo_missing', '画像ファイルを選択してください。');
    }

    if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('player_photo_upload', aidunite_player_persist_photo_upload_error_message((int) $file['error']));
    }

    $max_bytes = aidunite_player_read_photo_max_upload_bytes();
    if (!empty($file['size']) && (int) $file['size'] > $max_bytes) {
        return new WP_Error(
            'player_photo_size',
            'ファイルサイズは' . aidunite_player_read_photo_max_upload_label() . '以下にしてください。'
        );
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $checked = wp_check_filetype($file['name'] ?? '', [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ]);
    if (empty($checked['ext']) || empty($checked['type'])) {
        return new WP_Error('player_photo_type', 'JPG / PNG / WebP の画像を選択してください。');
    }

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $movefile = wp_handle_upload($file, [
        'test_form' => false,
        'mimes' => [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ],
    ]);

    if (!$movefile || !empty($movefile['error'])) {
        return new WP_Error(
            'player_photo_upload',
            (string) ($movefile['error'] ?? '画像のアップロードに失敗しました。')
        );
    }

    if (!empty($movefile['file']) && function_exists('aidunite_resize_image')) {
        require_once get_template_directory() . '/functions/messaging/file-upload-functions.php';
        $resized = aidunite_resize_image($movefile['file'], 480, 480);
        if ($resized && $resized !== $movefile['file'] && file_exists($resized)) {
            $upload_dir = wp_upload_dir();
            $old_url = (string) ($movefile['url'] ?? '');
            $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $resized);
            if ($old_url !== '' && $old_url !== $new_url && file_exists($movefile['file'])) {
                wp_delete_file($movefile['file']);
            }
            $movefile['file'] = $resized;
            $movefile['url'] = $new_url;
        }
    }

    $url = (string) ($movefile['url'] ?? '');
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return new WP_Error('player_photo_upload', '画像のアップロードに失敗しました。');
    }

    return $url;
}

/**
 * 登録・編集フォームから写真を保存
 *
 * @param int                  $player_id
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 * @return string[] エラーメッセージ
 */
function aidunite_player_persist_photo_from_request($player_id, array $post, array $files = []) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return [];
    }

    $errors = [];
    $remove = !empty($post['player_photo_remove']) && (string) $post['player_photo_remove'] === '1';

    if ($remove) {
        aidunite_player_persist_photo_remove($player_id);
        return [];
    }

    $file = is_array($files['player_photo_file'] ?? null) ? $files['player_photo_file'] : null;
    if ($file === null || empty($file['tmp_name'])) {
        return [];
    }

    $uploaded = aidunite_player_persist_photo_handle_upload($file);
    if (is_wp_error($uploaded)) {
        $errors[] = $uploaded->get_error_message();
        return $errors;
    }

    aidunite_player_persist_photo_url($player_id, $uploaded);

    return $errors;
}


