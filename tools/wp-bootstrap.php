<?php
/**
 * テーマ tools 用 WordPress ブートストラップ（CLI / ブラウザ共通）
 *
 * Local の MySQL ポートは site 直下の local-site.json にあるため、
 * wp-config.php 側でも同様に解決する（CLI の DB 接続エラー対策）。
 */

if (!function_exists('aidunite_tools_find_wp_load')) {
    /**
     * @return string wp-load.php の絶対パス（見つからなければ空）
     */
    function aidunite_tools_find_wp_load() {
        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            $candidate = $dir . '/wp-load.php';
            if (is_readable($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        $fallback = dirname(__DIR__, 4) . '/wp-load.php';

        return is_readable($fallback) ? $fallback : '';
    }
}

if (!function_exists('aidunite_tools_resolve_local_db_host')) {
    /**
     * Local site の local-site.json から DB_HOST を組み立てる
     *
     * @return string 例: 127.0.0.1:10005（未検出時は空）
     */
    function aidunite_tools_resolve_local_db_host() {
        $wp_load = aidunite_tools_find_wp_load();
        if ($wp_load === '') {
            return '';
        }
        $public_dir = dirname($wp_load);
        // public = .../app/public → 2階層上が Local サイトルート（aidunite-local）
        $site_root = dirname($public_dir, 2);
        $resolver = $public_dir . '/ainy-local-db-host.php';
        if (is_readable($resolver)) {
            require_once $resolver;
            if (function_exists('ainy_resolve_local_wp_db_host')) {
                return (string) ainy_resolve_local_wp_db_host($site_root);
            }
        }

        return '';
    }
}

if (!function_exists('aidunite_tools_probe_mysql_from_wp_config')) {
    /**
     * wp-load 前に MySQL 到達性だけ確認（wp_die HTML を避ける）
     *
     * @return true|array{code:string,message:string,db_host?:string}
     */
    function aidunite_tools_probe_mysql_from_wp_config() {
        $wp_load = aidunite_tools_find_wp_load();
        if ($wp_load === '') {
            return ['code' => 'no_wp_load', 'message' => 'wp-load.php が見つかりません'];
        }
        $config_path = dirname($wp_load) . '/wp-config.php';
        if (!is_readable($config_path)) {
            return ['code' => 'no_wp_config', 'message' => 'wp-config.php が読めません'];
        }
        $content = (string) file_get_contents($config_path);
        $name = 'local';
        $user = 'root';
        $pass = 'root';
        if (preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']*)'\s*\)/", $content, $m)) {
            $name = $m[1];
        }
        if (preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']*)'\s*\)/", $content, $m)) {
            $user = $m[1];
        }
        if (preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $content, $m)) {
            $pass = $m[1];
        }

        $db_host = aidunite_tools_resolve_local_db_host();
        if ($db_host === '') {
            if (preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']*)'\s*\)/", $content, $m)) {
                $db_host = $m[1];
            } else {
                $db_host = 'localhost';
            }
        }

        $host = $db_host;
        $port = null;
        if (strpos($db_host, ':') !== false) {
            list($host, $port_str) = explode(':', $db_host, 2);
            $port = (int) $port_str;
        }

        if (!extension_loaded('mysqli')) {
            return [
                'code'    => 'no_mysqli',
                'message' => 'PHP の mysqli 拡張が無効です。Local の「Open site shell」から実行するか、Local 付属の php.exe を使ってください。',
                'db_host' => $db_host,
            ];
        }
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        $mysqli = @new mysqli($host, $user, $pass, $name, $port ?: (int) ini_get('mysqli.default_port'));
        if ($mysqli->connect_errno) {
            return [
                'code'    => 'db_connect_failed',
                'message' => $mysqli->connect_error,
                'db_host' => $db_host,
            ];
        }
        $mysqli->close();

        return true;
    }
}

if (!function_exists('aidunite_tools_bootstrap_wp')) {
    /**
     * @return bool
     */
    function aidunite_tools_bootstrap_wp() {
        $wp_load = aidunite_tools_find_wp_load();
        if ($wp_load === '') {
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "wp-load.php が見つかりません。themes/aidunite-original/tools から実行しているか確認してください。\n");
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo "wp-load.php not found\n";
            }

            return false;
        }

        if (PHP_SAPI === 'cli' && function_exists('aidunite_tools_probe_mysql_from_wp_config')) {
            $probe = aidunite_tools_probe_mysql_from_wp_config();
            if (is_array($probe)) {
                fwrite(STDERR, aidunite_tools_db_connection_help() . "\n");
                fwrite(STDERR, '詳細: ' . ($probe['message'] ?? '') . ' (host=' . ($probe['db_host'] ?? '') . ")\n");

                return false;
            }
        }

        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', false);
        }

        require_once $wp_load;

        return true;
    }
}

if (!function_exists('aidunite_tools_db_connection_help')) {
    function aidunite_tools_db_connection_help() {
        $host = aidunite_tools_resolve_local_db_host();
        $lines = [
            'WordPress のデータベースに接続できませんでした。',
            '',
            '確認してください:',
            '  1. Local でサイト「aidunite-local」を Start（緑）にする',
            '  2. ブラウザで http://aidunite-d.local/ が開けるか',
            '  3. CLI では wp-config.php が local-site.json の MySQL ポートを使うこと',
        ];
        if ($host !== '') {
            $lines[] = '  期待する DB_HOST（Local）: ' . $host;
        }
        $lines[] = '';
        $lines[] = '推奨: Local の「Open site shell」で次の絶対パスへ cd してから php を実行';
        $lines[] = '  cd /d D:\\LocalSites\\aidunite-local\\app\\public\\wp-content\\themes\\aidunite-original';
        $lines[] = '  php tools/test-db-connection.php';
        $lines[] = '  php tools/run-match-board-status-repair.php';
        $lines[] = '';
        $lines[] = 'ブラウザ（管理者ログイン後）:';
        $lines[] = '  .../tools/run-match-board-status-repair.php';
        $lines[] = '  .../tools/run-notification-type-migrate.php';

        return implode("\n", $lines);
    }
}
