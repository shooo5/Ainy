<?php
/**
 * チーム活動地域（都道府県・東京23区/市部）の選択肢とメタ保存
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_team_activity_prefecture_options')) {
    /**
     * @return array<string,string> value => label
     */
    function aidunite_team_activity_prefecture_options() {
        return [
            '北海道' => '北海道',
            '青森県' => '青森県',
            '岩手県' => '岩手県',
            '宮城県' => '宮城県',
            '秋田県' => '秋田県',
            '山形県' => '山形県',
            '福島県' => '福島県',
            '茨城県' => '茨城県',
            '栃木県' => '栃木県',
            '群馬県' => '群馬県',
            '埼玉県' => '埼玉県',
            '千葉県' => '千葉県',
            '東京都' => '東京都',
            '神奈川県' => '神奈川県',
            '新潟県' => '新潟県',
            '富山県' => '富山県',
            '石川県' => '石川県',
            '福井県' => '福井県',
            '山梨県' => '山梨県',
            '長野県' => '長野県',
            '岐阜県' => '岐阜県',
            '静岡県' => '静岡県',
            '愛知県' => '愛知県',
            '三重県' => '三重県',
            '滋賀県' => '滋賀県',
            '京都府' => '京都府',
            '大阪府' => '大阪府',
            '兵庫県' => '兵庫県',
            '奈良県' => '奈良県',
            '和歌山県' => '和歌山県',
            '鳥取県' => '鳥取県',
            '島根県' => '島根県',
            '岡山県' => '岡山県',
            '広島県' => '広島県',
            '山口県' => '山口県',
            '徳島県' => '徳島県',
            '香川県' => '香川県',
            '愛媛県' => '愛媛県',
            '高知県' => '高知県',
            '福岡県' => '福岡県',
            '佐賀県' => '佐賀県',
            '長崎県' => '長崎県',
            '熊本県' => '熊本県',
            '大分県' => '大分県',
            '宮崎県' => '宮崎県',
            '鹿児島県' => '鹿児島県',
            '沖縄県' => '沖縄県',
        ];
    }
}

if (!function_exists('aidunite_team_activity_prefecture_filter_options')) {
    /**
     * 管理者フィルター用（都道府県一覧 + すべて）
     *
     * @return array<string,string>
     */
    function aidunite_team_activity_prefecture_filter_options() {
        $options = ['' => 'すべて'];
        foreach (aidunite_team_activity_prefecture_options() as $val => $label) {
            $options[$val] = $label;
        }

        return $options;
    }
}

if (!function_exists('aidunite_team_activity_tokyo_ward_options')) {
    /**
     * @return array<string,string>
     */
    function aidunite_team_activity_tokyo_ward_options() {
        $wards = [
            '千代田区', '中央区', '港区', '新宿区', '文京区', '台東区', '墨田区', '江東区',
            '品川区', '目黒区', '大田区', '世田谷区', '渋谷区', '中野区', '杉並区', '豊島区',
            '北区', '荒川区', '板橋区', '練馬区', '足立区', '葛飾区', '江戸川区',
        ];
        return array_combine($wards, $wards);
    }
}

if (!function_exists('aidunite_team_activity_tokyo_city_options')) {
    /**
     * @return array<string,string>
     */
    function aidunite_team_activity_tokyo_city_options() {
        $cities = [
            '八王子市', '立川市', '武蔵野市', '三鷹市', '青梅市', '府中市', '昭島市', '調布市',
            '町田市', '小金井市', '小平市', '日野市', '東村山市', '国分寺市', '国立市',
            '福生市', '狛江市', '東大和市', '清瀬市', '東久留米市', '武蔵村山市', '多摩市',
            '稲城市', '羽村市', 'あきる野市', '西東京市',
        ];
        return array_combine($cities, $cities);
    }
}

if (!function_exists('aidunite_team_activity_region_block_from_prefecture')) {
    /**
     * 旧8ブロック region メタ（管理フィルター互換）
     */
    function aidunite_team_activity_region_block_from_prefecture($prefecture) {
        $prefecture = (string) $prefecture;
        $map = [
            '北海道' => '北海道',
            '青森県' => '東北', '岩手県' => '東北', '宮城県' => '東北', '秋田県' => '東北', '山形県' => '東北', '福島県' => '東北',
            '茨城県' => '関東', '栃木県' => '関東', '群馬県' => '関東', '埼玉県' => '関東', '千葉県' => '関東', '東京都' => '関東', '神奈川県' => '関東',
            '新潟県' => '中部', '富山県' => '中部', '石川県' => '中部', '福井県' => '中部', '山梨県' => '中部', '長野県' => '中部', '岐阜県' => '中部', '静岡県' => '中部', '愛知県' => '中部',
            '三重県' => '関西', '滋賀県' => '関西', '京都府' => '関西', '大阪府' => '関西', '兵庫県' => '関西', '奈良県' => '関西', '和歌山県' => '関西',
            '鳥取県' => '中国', '島根県' => '中国', '岡山県' => '中国', '広島県' => '中国', '山口県' => '中国',
            '徳島県' => '四国', '香川県' => '四国', '愛媛県' => '四国', '高知県' => '四国',
            '福岡県' => '九州', '佐賀県' => '九州', '長崎県' => '九州', '熊本県' => '九州', '大分県' => '九州', '宮崎県' => '九州', '鹿児島県' => '九州', '沖縄県' => '沖縄',
        ];

        return $map[$prefecture] ?? '';
    }
}

if (!function_exists('aidunite_get_team_activity_profile')) {
    /**
     * @param int $team_id
     * @return array{activity_prefecture:string,activity_area_type:string,activity_area:string,region:string}
     */
    function aidunite_get_team_activity_profile($team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            return [
                'activity_prefecture' => '',
                'activity_area_type'  => '',
                'activity_area'       => '',
                'region'              => '',
            ];
        }
        $pref = (string) get_post_meta($team_id, 'activity_prefecture', true);
        if ($pref === '') {
            $legacy = (string) get_post_meta($team_id, 'region', true);
            if ($legacy !== '' && $legacy !== '未設定') {
                $pref = aidunite_team_activity_legacy_region_to_prefecture($legacy);
            }
        }
        $type = (string) get_post_meta($team_id, 'activity_area_type', true);
        $area = (string) get_post_meta($team_id, 'activity_area', true);
        $block = (string) get_post_meta($team_id, 'region', true);
        if ($block === '' && $pref !== '') {
            $block = aidunite_team_activity_region_block_from_prefecture($pref);
        }

        return [
            'activity_prefecture' => $pref,
            'activity_area_type'  => $type,
            'activity_area'       => $area,
            'region'              => $block,
        ];
    }
}

if (!function_exists('aidunite_team_activity_legacy_region_to_prefecture')) {
    function aidunite_team_activity_legacy_region_to_prefecture($legacy_region) {
        $legacy_region = (string) $legacy_region;
        if ($legacy_region === '北海道') {
            return '北海道';
        }
        if ($legacy_region === '東北') {
            return '';
        }
        if ($legacy_region === '関東') {
            return '';
        }
        return '';
    }
}

if (!function_exists('aidunite_team_activity_build_key')) {
    /**
     * @param int $team_id
     * @return string 比較キー（都道府県|エリア）
     */
    function aidunite_team_activity_build_key($team_id) {
        $p = aidunite_get_team_activity_profile((int) $team_id);
        $pref = (string) ($p['activity_prefecture'] ?? '');
        if ($pref === '') {
            return '';
        }
        $area = (string) ($p['activity_area'] ?? '');
        if ($pref === '東京都' && $area !== '') {
            return $pref . '|' . $area;
        }

        return $pref;
    }
}

if (!function_exists('aidunite_team_activity_compare')) {
    /**
     * @return string exact|prefecture|out|unknown
     */
    function aidunite_team_activity_compare($viewer_team_id, $recruit_team_id) {
        $a = aidunite_team_activity_build_key((int) $viewer_team_id);
        $b = aidunite_team_activity_build_key((int) $recruit_team_id);
        if ($a === '' || $b === '') {
            return 'unknown';
        }
        if ($a === $b) {
            return 'exact';
        }
        $pa = explode('|', $a, 2)[0];
        $pb = explode('|', $b, 2)[0];
        if ($pa !== '' && $pa === $pb) {
            return 'prefecture';
        }

        return 'out';
    }
}

if (!function_exists('aidunite_team_activity_resolve_area_from_input')) {
    /**
     * POST から activity_area を解決（区・市で name を分離。旧 activity_area もフォールバック）
     *
     * @param array  $input
     * @param string $type  tokyo_ward|tokyo_city
     */
    function aidunite_team_activity_resolve_area_from_input(array $input, $type) {
        $type = (string) $type;
        $canonical = isset($input['activity_area']) ? trim(sanitize_text_field((string) $input['activity_area'])) : '';
        $ward = isset($input['activity_area_ward']) ? trim(sanitize_text_field((string) $input['activity_area_ward'])) : '';
        $city = isset($input['activity_area_city']) ? trim(sanitize_text_field((string) $input['activity_area_city'])) : '';
        $sync = isset($input['activity_area_sync']) ? trim(sanitize_text_field((string) $input['activity_area_sync'])) : '';

        if ($type === 'tokyo_ward') {
            if ($canonical !== '') {
                return $canonical;
            }
            if ($ward !== '') {
                return $ward;
            }
            if ($sync !== '') {
                return $sync;
            }
        } elseif ($type === 'tokyo_city') {
            if ($city !== '') {
                return $city;
            }
            if ($sync !== '') {
                return $sync;
            }
        }

        if ($canonical !== '') {
            return $canonical;
        }
        if ($ward !== '' && ($type === '' || $type === 'tokyo_ward')) {
            return $ward;
        }
        if ($city !== '' && ($type === '' || $type === 'tokyo_city')) {
            return $city;
        }

        return '';
    }
}

if (!function_exists('aidunite_team_activity_save_meta')) {
    /**
     * @param int   $team_id
     * @param array $input activity_prefecture, activity_area_type, activity_area（または activity_area_ward / activity_area_city）
     */
    function aidunite_team_activity_save_meta($team_id, array $input) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            return;
        }
        $pref = isset($input['activity_prefecture']) ? sanitize_text_field((string) $input['activity_prefecture']) : '';
        $type = isset($input['activity_area_type']) ? sanitize_text_field((string) $input['activity_area_type']) : '';
        $area = function_exists('aidunite_team_activity_resolve_area_from_input')
            ? aidunite_team_activity_resolve_area_from_input($input, $type)
            : (isset($input['activity_area']) ? sanitize_text_field((string) $input['activity_area']) : '');

        $pref_opts = aidunite_team_activity_prefecture_options();
        if ($pref !== '' && !isset($pref_opts[$pref])) {
            $pref = '';
        }
        if ($pref !== '東京都') {
            $type = '';
            $area = '';
        } else {
            $ward_raw = isset($input['activity_area_ward']) ? trim(sanitize_text_field((string) $input['activity_area_ward'])) : '';
            $city_raw = isset($input['activity_area_city']) ? trim(sanitize_text_field((string) $input['activity_area_city'])) : '';
            $canonical_raw = isset($input['activity_area']) ? trim(sanitize_text_field((string) $input['activity_area'])) : '';
            $sync_raw = isset($input['activity_area_sync']) ? trim(sanitize_text_field((string) $input['activity_area_sync'])) : '';

            if ($type === '' && ($canonical_raw !== '' || $ward_raw !== '' || $sync_raw !== '')) {
                $type = 'tokyo_ward';
            } elseif ($type === '' && $city_raw !== '') {
                $type = 'tokyo_city';
            }

            if ($type === 'tokyo_ward') {
                $ward_opts = aidunite_team_activity_tokyo_ward_options();
                if ($area === '' && $canonical_raw !== '') {
                    $area = $canonical_raw;
                }
                if ($area === '' && $ward_raw !== '') {
                    $area = $ward_raw;
                }
                if ($area === '' && $sync_raw !== '') {
                    $area = $sync_raw;
                }
                if ($area !== '' && !isset($ward_opts[$area])) {
                    $area = '';
                }
                if ($area === '') {
                    $type = '';
                }
            } elseif ($type === 'tokyo_city') {
                $city_opts = aidunite_team_activity_tokyo_city_options();
                if ($area === '' && $city_raw !== '') {
                    $area = $city_raw;
                }
                if ($area === '' && $sync_raw !== '') {
                    $area = $sync_raw;
                }
                if ($area !== '' && !isset($city_opts[$area])) {
                    $area = '';
                }
                if ($area === '') {
                    $type = '';
                }
            } else {
                $type = '';
                $area = '';
            }
        }

        update_post_meta($team_id, 'activity_prefecture', $pref);
        update_post_meta($team_id, 'activity_area_type', $type);
        update_post_meta($team_id, 'activity_area', $area);
        $block = $pref !== '' ? aidunite_team_activity_region_block_from_prefecture($pref) : '';
        if ($block !== '') {
            update_post_meta($team_id, 'region', $block);
        }
    }
}

if (!function_exists('aidunite_team_activity_display_label')) {
    /**
     * UI 表示用の活動地域ラベル
     */
    function aidunite_team_activity_display_label($team_id) {
        $p = aidunite_get_team_activity_profile((int) $team_id);
        $parts = [];
        if (!empty($p['activity_prefecture'])) {
            $parts[] = (string) $p['activity_prefecture'];
        }
        if (!empty($p['activity_area'])) {
            $parts[] = (string) $p['activity_area'];
        }
        if ($parts !== []) {
            return implode(' ', $parts);
        }
        $block = (string) ($p['region'] ?? '');
        if ($block !== '' && $block !== '未設定') {
            return $block;
        }

        return '地域未設定';
    }
}

if (!function_exists('aidunite_enqueue_team_activity_fields_script')) {
    function aidunite_enqueue_team_activity_fields_script() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <script>
        (function () {
            function aiduniteSyncActivityAreaHidden() {
                var sync = document.getElementById('activity_area_sync');
                var typeEl = document.getElementById('activity_area_type');
                var wardEl = document.getElementById('activity_area_ward');
                var cityEl = document.getElementById('activity_area_city');
                var t = typeEl ? typeEl.value : '';
                if (t === 'tokyo_ward' && wardEl) {
                    if (sync) sync.value = wardEl.value || '';
                } else if (t === 'tokyo_city' && cityEl) {
                    if (sync) sync.value = cityEl.value || '';
                } else if (sync) {
                    sync.value = '';
                }
            }
            function aiduniteSyncActivityTokyo() {
                var pref = document.getElementById('activity_prefecture');
                var wrap = document.getElementById('aidunite-team-activity-tokyo-wrap');
                var type = document.getElementById('activity_area_type');
                var wardEl = document.getElementById('activity_area_ward');
                var cityEl = document.getElementById('activity_area_city');
                if (!pref || !wrap) return;
                var isTokyo = pref.value === '東京都';
                var typeCell = document.getElementById('team-reg-tokyo-type-cell');
                if (typeCell) {
                    typeCell.style.display = isTokyo ? '' : 'none';
                }
                wrap.style.display = isTokyo ? '' : 'none';
                var wardWrap = document.querySelector('.aidunite-team-activity-tokyo-ward-wrap');
                var cityWrap = document.querySelector('.aidunite-team-activity-tokyo-city-wrap');
                if (!isTokyo && type) type.value = '';
                var t = type ? type.value : '';
                if (wardWrap) wardWrap.style.display = (isTokyo && t === 'tokyo_ward') ? '' : 'none';
                if (cityWrap) cityWrap.style.display = (isTokyo && t === 'tokyo_city') ? '' : 'none';
                if (wardEl) {
                    wardEl.required = isTokyo && t === 'tokyo_ward';
                    if (isTokyo && t === 'tokyo_ward') {
                        wardEl.setAttribute('name', 'activity_area');
                    } else {
                        wardEl.removeAttribute('name');
                    }
                }
                if (cityEl) {
                    cityEl.required = isTokyo && t === 'tokyo_city';
                    if (isTokyo && t === 'tokyo_city') {
                        cityEl.setAttribute('name', 'activity_area_city');
                    } else {
                        cityEl.removeAttribute('name');
                    }
                }
                aiduniteSyncActivityAreaHidden();
            }
            document.addEventListener('change', function (e) {
                if (!e.target) return;
                if (e.target.id === 'activity_prefecture' || e.target.id === 'activity_area_type'
                    || e.target.id === 'activity_area_ward' || e.target.id === 'activity_area_city') {
                    aiduniteSyncActivityTokyo();
                }
            });
            document.addEventListener('DOMContentLoaded', aiduniteSyncActivityTokyo);
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || !form.querySelector || !form.querySelector('#activity_prefecture')) {
                    return;
                }
                aiduniteSyncActivityAreaHidden();
            }, true);
        })();
        </script>
        <?php
    }
}

if (!function_exists('aidunite_render_team_activity_fields')) {
    /**
     * 登録・管理フォーム用 HTML 断片（都道府県 + 東京サブ）
     *
     * @param int   $team_id 0=新規
     * @param array $args    context: front（登録）| team_settings | wp_admin
     *                        section: all | prefecture | tokyo
     */
    function aidunite_render_team_activity_fields($team_id = 0, $args = []) {
        $context = is_array($args) && isset($args['context']) ? (string) $args['context'] : 'front';
        $section = is_array($args) && isset($args['section']) ? (string) $args['section'] : 'all';
        $show_pref = ($section === 'all' || $section === 'prefecture');
        $show_tokyo = ($section === 'all' || $section === 'tokyo');
        $show_tokyo_type = ($section === 'all' || $section === 'tokyo' || $section === 'tokyo_type');
        $show_tokyo_ward = ($section === 'all' || $section === 'tokyo' || $section === 'tokyo_ward');
        $show_tokyo_city = ($section === 'all' || $section === 'tokyo' || $section === 'tokyo_city');

        $is_team_settings = ($context === 'team_settings');
        $is_wp_admin = ($context === 'wp_admin');

        if ($is_team_settings) {
            $group_class = 'team-settings-dash__field';
            $label_class = '';
            $select_class = 'team-settings-dash__select';
            $wrap_class = '';
        } elseif ($is_wp_admin) {
            $group_class = 'aidunite-team-activity-fields__row';
            $label_class = '';
            $select_class = 'regular-text';
            $wrap_class = 'aidunite-team-activity-fields aidunite-team-activity-fields--wp-admin';
        } elseif ($context === 'team_registration') {
            $group_class = 'team-reg-field';
            $label_class = 'team-reg-label';
            $select_class = 'team-reg-input team-reg-select';
            $wrap_class = 'aidunite-team-activity-fields team-reg-activity-fields';
        } else {
            $group_class = 'member-register-form-group';
            $label_class = 'member-register-form-label';
            $select_class = 'member-register-form-input';
            $wrap_class = 'aidunite-team-activity-fields';
        }

        $profile = $team_id > 0 ? aidunite_get_team_activity_profile((int) $team_id) : [
            'activity_prefecture' => '',
            'activity_area_type'  => '',
            'activity_area'       => '',
            'region'              => '',
        ];
        $pref = (string) ($profile['activity_prefecture'] ?? '');
        $type = (string) ($profile['activity_area_type'] ?? '');
        $area = (string) ($profile['activity_area'] ?? '');
        $is_tokyo = ($pref === '東京都');

        $use_outer_wrap = ($wrap_class !== '' && $section === 'all');
        if ($use_outer_wrap) {
            echo '<div class="' . esc_attr($wrap_class) . '">';
        }

        if ($show_pref) {
            ?>
            <div class="<?php echo esc_attr($group_class); ?>">
                <label class="<?php echo esc_attr($label_class); ?>" for="activity_prefecture">都道府県 <span class="required" aria-hidden="true">*</span></label>
                <select id="activity_prefecture" name="activity_prefecture" class="<?php echo esc_attr($select_class); ?>" required<?php echo $is_wp_admin ? ' style="max-width:100%;"' : ''; ?>>
                    <option value="">選択してください</option>
                    <?php foreach (aidunite_team_activity_prefecture_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($pref, $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }

        if ($show_tokyo_type && $section === 'tokyo_type') {
            ?>
            <div class="<?php echo esc_attr($group_class); ?> team-reg-field--tokyo-type">
                <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_type">区分</label>
                <select id="activity_area_type" name="activity_area_type" class="<?php echo esc_attr($select_class); ?>">
                    <option value="">選択してください</option>
                    <option value="tokyo_ward" <?php selected($type, 'tokyo_ward'); ?>>23区</option>
                    <option value="tokyo_city" <?php selected($type, 'tokyo_city'); ?>>23区外（市部）</option>
                </select>
            </div>
            <?php
        }

        if ($show_tokyo && $section !== 'tokyo_type' && $section !== 'tokyo_ward' && $section !== 'tokyo_city') {
            $tokyo_grid_class = $is_team_settings ? 'form-grid form-grid-2 aidunite-team-activity-tokyo-grid' : '';
            $tokyo_open = ($section === 'all' || $section === 'tokyo');
            if ($tokyo_open) {
                ?>
            <div class="aidunite-team-activity-tokyo-wrap<?php echo $is_team_settings ? ' aidunite-team-activity-tokyo-wrap--settings' : ''; ?>" id="aidunite-team-activity-tokyo-wrap" style="<?php echo $is_tokyo ? '' : 'display:none;'; ?>"<?php echo $is_team_settings ? ' data-span-full="1"' : ''; ?>>
                <?php
            }
            if ($tokyo_grid_class !== '') {
                echo '<div class="' . esc_attr($tokyo_grid_class) . '">';
            }
            if ($show_tokyo_type) {
                ?>
                <div class="<?php echo esc_attr($group_class); ?>">
                    <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_type">東京都内の区分</label>
                    <select id="activity_area_type" name="activity_area_type" class="<?php echo esc_attr($select_class); ?>"<?php echo $is_wp_admin ? ' style="max-width:100%;"' : ''; ?>>
                        <option value="">選択してください</option>
                        <option value="tokyo_ward" <?php selected($type, 'tokyo_ward'); ?>>23区</option>
                        <option value="tokyo_city" <?php selected($type, 'tokyo_city'); ?>>23区外（市部）</option>
                    </select>
                </div>
                <?php
            }
            if ($show_tokyo_ward) {
                ?>
                <div class="<?php echo esc_attr($group_class); ?> aidunite-team-activity-tokyo-ward-wrap" style="<?php echo ($type === 'tokyo_ward') ? '' : 'display:none;'; ?>">
                    <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_ward">区</label>
                    <select id="activity_area_ward" name="activity_area" class="<?php echo esc_attr($select_class); ?> aidunite-activity-area-select" data-tokyo-kind="ward"<?php echo $is_wp_admin ? ' style="max-width:100%;"' : ''; ?><?php echo ($type === 'tokyo_ward' && $area !== '') ? ' required' : ''; ?>>
                        <option value="">選択してください</option>
                        <?php foreach (aidunite_team_activity_tokyo_ward_options() as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($type === 'tokyo_ward' ? $area : '', $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php
            }
            if ($show_tokyo_city) {
                ?>
                <div class="<?php echo esc_attr($group_class); ?> aidunite-team-activity-tokyo-city-wrap" style="<?php echo ($type === 'tokyo_city') ? '' : 'display:none;'; ?>">
                    <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_city">市</label>
                    <select id="activity_area_city" name="activity_area_city" class="<?php echo esc_attr($select_class); ?> aidunite-activity-area-select" data-tokyo-kind="city"<?php echo $is_wp_admin ? ' style="max-width:100%;"' : ''; ?>>
                        <option value="">選択してください</option>
                        <?php foreach (aidunite_team_activity_tokyo_city_options() as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($type === 'tokyo_city' ? $area : '', $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php
            }
            if ($tokyo_grid_class !== '') {
                echo '</div>';
            }
            if ($section === 'all' || $section === 'tokyo') {
                ?>
                <input type="hidden" name="activity_area_sync" id="activity_area_sync" value="<?php echo esc_attr($area); ?>" />
            </div>
                <?php
            }
        }

        if ($section === 'tokyo_ward') {
            ?>
            <div class="<?php echo esc_attr($group_class); ?> aidunite-team-activity-tokyo-ward-wrap team-reg-field--tokyo-area" style="<?php echo ($type === 'tokyo_ward') ? '' : 'display:none;'; ?>">
                <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_ward">地域</label>
                <select id="activity_area_ward" name="activity_area" class="<?php echo esc_attr($select_class); ?> aidunite-activity-area-select" data-tokyo-kind="ward"<?php echo ($type === 'tokyo_ward' && $area !== '') ? ' required' : ''; ?>>
                    <option value="">選択してください</option>
                    <?php foreach (aidunite_team_activity_tokyo_ward_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($type === 'tokyo_ward' ? $area : '', $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }

        if ($section === 'tokyo_city') {
            ?>
            <div class="<?php echo esc_attr($group_class); ?> aidunite-team-activity-tokyo-city-wrap team-reg-field--tokyo-area" style="<?php echo ($type === 'tokyo_city') ? '' : 'display:none;'; ?>">
                <label class="<?php echo esc_attr($label_class); ?>" for="activity_area_city">地域</label>
                <select id="activity_area_city" name="activity_area_city" class="<?php echo esc_attr($select_class); ?> aidunite-activity-area-select" data-tokyo-kind="city">
                    <option value="">選択してください</option>
                    <?php foreach (aidunite_team_activity_tokyo_city_options() as $val => $label) : ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($type === 'tokyo_city' ? $area : '', $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }

        if ($section === 'tokyo_city') {
            ?>
            <input type="hidden" name="activity_area_sync" id="activity_area_sync" value="<?php echo esc_attr($area); ?>" />
            <?php
        }

        if ($use_outer_wrap) {
            echo '</div>';
        }

        if ($is_wp_admin) {
            ?>
            <style>
            .aidunite-team-activity-fields--wp-admin .aidunite-team-activity-fields__row { margin-bottom: 12px; }
            .aidunite-team-activity-fields--wp-admin .aidunite-team-activity-fields__row label { display: block; font-weight: 600; margin-bottom: 4px; }
            .aidunite-team-activity-fields--wp-admin select { width: 100%; max-width: 320px; }
            </style>
            <?php
        }
    }
}
