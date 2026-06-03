<?php
/**
 * AidUnite キャッシュマネージャー
 *
 * 統一されたキャッシュ管理機能を提供します。
 *
 * @version 1.0.0
 * @created 2024-12
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * キャッシュマネージャークラス
 */
class AidUniteCacheManager {

    /**
     * キャッシュグループ定義
     */
    const GROUP_SCHEDULES = 'schedules';
    const GROUP_TEAMS = 'teams';
    const GROUP_USERS = 'users';
    const GROUP_MATCHES = 'matches';
    const GROUP_NOTIFICATIONS = 'notifications';

    /**
     * デフォルトの有効期限（秒）
     */
    const DEFAULT_EXPIRATION = 3600; // 1時間

    /**
     * キャッシュを取得
     *
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ
     * @param mixed $default デフォルト値
     * @return mixed キャッシュ値またはデフォルト値
     */
    public static function get($key, $group = 'default', $default = false) {
        $cache_key = self::build_key($key, $group);

        // オブジェクトキャッシュを試行
        $value = wp_cache_get($cache_key, $group);
        if ($value !== false) {
            return $value;
        }

        // トランジェントキャッシュを試行
        $value = get_transient($cache_key);
        if ($value !== false) {
            // オブジェクトキャッシュにも保存
            wp_cache_set($cache_key, $value, $group, self::DEFAULT_EXPIRATION);
            return $value;
        }

        return $default;
    }

    /**
     * キャッシュを設定
     *
     * @param string $key キャッシュキー
     * @param mixed $value キャッシュ値
     * @param int $expiration 有効期限（秒）
     * @param string $group キャッシュグループ
     * @return bool 成功した場合true
     */
    public static function set($key, $value, $expiration = null, $group = 'default') {
        if ($expiration === null) {
            $expiration = self::DEFAULT_EXPIRATION;
        }

        $cache_key = self::build_key($key, $group);

        // オブジェクトキャッシュに保存
        $result1 = wp_cache_set($cache_key, $value, $group, $expiration);

        // トランジェントキャッシュにも保存（永続化）
        $result2 = set_transient($cache_key, $value, $expiration);

        return $result1 && $result2;
    }

    /**
     * キャッシュを削除
     *
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ
     * @return bool 成功した場合true
     */
    public static function delete($key, $group = 'default') {
        $cache_key = self::build_key($key, $group);

        // オブジェクトキャッシュから削除
        wp_cache_delete($cache_key, $group);

        // トランジェントキャッシュからも削除
        delete_transient($cache_key);

        return true;
    }

    /**
     * グループ単位でキャッシュを削除
     *
     * @param string $group キャッシュグループ
     * @return bool 成功した場合true
     */
    public static function flush_group($group) {
        // オブジェクトキャッシュから削除
        if (function_exists('wp_cache_delete_group')) {
            wp_cache_delete_group($group);
        } else {
            // フォールバック: 全キャッシュを削除
            wp_cache_flush();
        }

        // トランジェントキャッシュは個別に削除する必要がある
        // 注意: トランジェントキャッシュのグループ削除はWordPressの標準機能ではサポートされていない

        return true;
    }

    /**
     * すべてのキャッシュを削除
     *
     * @return bool 成功した場合true
     */
    public static function flush_all() {
        wp_cache_flush();
        // 注意: トランジェントキャッシュの全削除は慎重に行う必要がある

        return true;
    }

    /**
     * キャッシュキーを構築
     *
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ
     * @return string 完全なキャッシュキー
     */
    private static function build_key($key, $group) {
        return 'aidunite_' . $group . '_' . $key;
    }

    /**
     * キャッシュが存在するかチェック
     *
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ
     * @return bool 存在する場合true
     */
    public static function exists($key, $group = 'default') {
        $cache_key = self::build_key($key, $group);

        // オブジェクトキャッシュをチェック
        $value = wp_cache_get($cache_key, $group);
        if ($value !== false) {
            return true;
        }

        // トランジェントキャッシュをチェック
        $value = get_transient($cache_key);
        return $value !== false;
    }

    /**
     * キャッシュを取得または生成
     *
     * @param string $key キャッシュキー
     * @param callable $callback キャッシュが存在しない場合に実行するコールバック
     * @param int $expiration 有効期限（秒）
     * @param string $group キャッシュグループ
     * @return mixed キャッシュ値またはコールバックの戻り値
     */
    public static function remember($key, $callback, $expiration = null, $group = 'default') {
        $value = self::get($key, $group);

        if ($value !== false) {
            return $value;
        }

        $value = call_user_func($callback);
        self::set($key, $value, $expiration, $group);

        return $value;
    }
}
