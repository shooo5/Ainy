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

// 空の状態のスタイルを出力（初回のみ）
if (!function_exists('aidunite_empty_state_styles')) {
    function aidunite_empty_state_styles() {
        static $styles_printed = false;
        if ($styles_printed) {
            return;
        }
        $styles_printed = true;
        ?>
        <style>
        .aidunite-empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            padding: 3rem 2rem;
            border-radius: 12px;
            border: 1px solid;
            margin: 2rem 0;
            text-align: center;
            transition: all 0.3s ease;
            animation: fadeInUp 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        /* 背景の装飾的な要素（控えめ） */
        .aidunite-empty-state::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0,0,0,0.02) 0%, transparent 70%);
            pointer-events: none;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .aidunite-empty-state:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .aidunite-empty-state__content {
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .aidunite-empty-state__icon {
            font-size: 2.5rem;
            line-height: 1;
            margin-bottom: 1.25rem;
            opacity: 0.7;
        }

        .aidunite-empty-state__title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.75rem 0;
            line-height: 1.4;
            letter-spacing: -0.02em;
        }

        .aidunite-empty-state__message {
            font-size: 1rem;
            color: #6c757d;
            margin: 0 0 0.5rem 0;
            line-height: 1.7;
        }

        .aidunite-empty-state__description {
            font-size: 0.95rem;
            color: #868e96;
            margin: 0 0 1.75rem 0;
            line-height: 1.7;
        }

        .aidunite-empty-state__action {
            margin-top: 1.75rem;
        }

        .aidunite-empty-state__button {
            display: inline-block;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            border: 1px solid transparent;
        }

        .aidunite-empty-state__button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
            color: #fff;
            text-decoration: none;
        }

        .aidunite-empty-state__button:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .aidunite-empty-state__help {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            font-size: 0.875rem;
        }

        .aidunite-empty-state__help-link {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .aidunite-empty-state__help-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }

        .aidunite-empty-state__help-separator {
            margin: 0 0.75rem;
            color: #adb5bd;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .aidunite-empty-state {
                min-height: 240px;
                padding: 2rem 1.5rem;
            }

            .aidunite-empty-state__icon {
                font-size: 2rem;
                margin-bottom: 1rem;
            }

            .aidunite-empty-state__title {
                font-size: 1.25rem;
            }

            .aidunite-empty-state__message {
                font-size: 0.95rem;
            }

            .aidunite-empty-state__description {
                font-size: 0.875rem;
            }

            .aidunite-empty-state__button {
                padding: 0.65rem 1.75rem;
                font-size: 0.9rem;
            }
        }

        /* タイプ別のスタイル調整 */
        .aidunite-empty-state--info {
            border-style: solid;
        }

        .aidunite-empty-state--warning {
            border-style: solid;
        }

        .aidunite-empty-state--success {
            border-style: solid;
        }
        </style>
        <?php
    }

    // スタイルを自動出力（フッターで）
    add_action('wp_footer', 'aidunite_empty_state_styles', 1);
}
