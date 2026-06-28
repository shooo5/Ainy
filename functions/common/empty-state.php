<?php
/**
 * 統一空の状態コンポーネント
 *
 * すべてのページで統一された空の状態（Empty State）を表示するための関数
 * エラーと区別できるデザインで、ユーザーに次のアクションを促します
 */

if (!function_exists('aidunite_empty_state')) {
    /**
     * 統一空の状態コンポーネントを出力
     *
     * @param array $args {
     *     @type string $icon アイコン（絵文字、オプション）デフォルト: null（表示しない）
     *     @type string $title タイトル デフォルト: 'データがありません'
     *     @type string $message メッセージ デフォルト: '表示するデータがありません'
     *     @type string $description 詳細説明（オプション）
     *     @type array $action CTAボタンの設定 {
     *         @type string $text ボタンテキスト
     *         @type string $url ボタンのURL
     *         @type string $class 追加のCSSクラス（オプション）
     *     }
     *     @type string $type タイプ（'default', 'info', 'warning', 'success'）デフォルト: 'default'
     *     @type bool $show_help ヘルプリンクを表示するか デフォルト: false
     *     @type string $custom_class カスタムCSSクラス
     * }
     * @return string HTML出力
     */
    function aidunite_empty_state($args = []) {
        $defaults = [
            'icon' => null, // デフォルトではアイコンを表示しない
            'title' => 'データがありません',
            'message' => '表示するデータがありません',
            'description' => '',
            'action' => null,
            'type' => 'default', // 'default', 'info', 'warning', 'success'
            'show_help' => false,
            'custom_class' => ''
        ];

        $args = wp_parse_args($args, $defaults);

        // タイプ別のデフォルト設定
        $type_configs = [
            'default' => [
                'color' => '#495057',
                'bg_color' => '#f8f9fa',
                'border_color' => '#dee2e6',
                'accent_color' => '#6c757d'
            ],
            'info' => [
                'color' => '#0d6efd',
                'bg_color' => '#e7f1ff',
                'border_color' => '#b3d7ff',
                'accent_color' => '#0d6efd'
            ],
            'warning' => [
                'color' => '#856404',
                'bg_color' => '#fff3cd',
                'border_color' => '#ffecb5',
                'accent_color' => '#ffc107'
            ],
            'success' => [
                'color' => '#155724',
                'bg_color' => '#d1e7dd',
                'border_color' => '#badbcc',
                'accent_color' => '#198754'
            ]
        ];

        $type_config = $type_configs[$args['type']] ?? $type_configs['default'];

        // CSSクラスを構築
        $classes = ['aidunite-empty-state'];
        $classes[] = 'aidunite-empty-state--' . $args['type'];
        if ($args['custom_class']) {
            $classes[] = $args['custom_class'];
        }
        $class_attr = esc_attr(implode(' ', $classes));

        // スタイル属性
        $style_attr = sprintf(
            'background-color: %s; border-color: %s;',
            esc_attr($type_config['bg_color']),
            esc_attr($type_config['border_color'])
        );

        ob_start();
        ?>
        <div class="<?php echo $class_attr; ?>" style="<?php echo $style_attr; ?>">
            <div class="aidunite-empty-state__content">
                <?php if ($args['icon']) : ?>
                    <div class="aidunite-empty-state__icon" style="color: <?php echo esc_attr($type_config['accent_color']); ?>;">
                        <?php echo esc_html($args['icon']); ?>
                    </div>
                <?php endif; ?>
                <h3 class="aidunite-empty-state__title" style="color: <?php echo esc_attr($type_config['color']); ?>;">
                    <?php echo esc_html($args['title']); ?>
                </h3>
                <p class="aidunite-empty-state__message">
                    <?php echo esc_html($args['message']); ?>
                </p>
                <?php if ($args['description']) : ?>
                    <p class="aidunite-empty-state__description">
                        <?php echo esc_html($args['description']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($args['action'] && isset($args['action']['text']) && isset($args['action']['url'])) : ?>
                    <div class="aidunite-empty-state__action">
                        <a href="<?php echo esc_url($args['action']['url']); ?>"
                           class="aidunite-empty-state__button <?php echo isset($args['action']['class']) ? esc_attr($args['action']['class']) : ''; ?>"
                           style="background-color: <?php echo esc_attr($type_config['accent_color']); ?>; border-color: <?php echo esc_attr($type_config['accent_color']); ?>;">
                            <?php echo esc_html($args['action']['text']); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($args['show_help']) : ?>
                    <div class="aidunite-empty-state__help">
                        <a href="<?php echo esc_url(home_url('/guide')); ?>" class="aidunite-empty-state__help-link">
                            使い方ガイドを見る
                        </a>
                        <span class="aidunite-empty-state__help-separator">|</span>
                        <a href="<?php echo esc_url(home_url('/faq')); ?>" class="aidunite-empty-state__help-link">
                            よくある質問
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// 空の状態スタイルは assets/css/components/empty-state.css（enqueue.php）
if (!function_exists('aidunite_empty_state_styles')) {
    function aidunite_empty_state_styles() {
        static $styles_printed = false;
        if ($styles_printed) {
            return;
        }
        $styles_printed = true;
        $css = get_stylesheet_directory() . '/assets/css/components/empty-state.css';
        if (is_readable($css)) {
            wp_enqueue_style(
                'aidunite-empty-state',
                get_stylesheet_directory_uri() . '/assets/css/components/empty-state.css',
                ['aidunite-style'],
                (string) filemtime($css)
            );
        }
    }

    add_action('wp_enqueue_scripts', 'aidunite_empty_state_styles', 15);
}
