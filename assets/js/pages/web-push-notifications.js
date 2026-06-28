/**
 * Webプッシュ通知システム
 * Service Workerと連携してプッシュ通知を実装
 */

class AinyWebPush {
    constructor() {
        this.isSupported = 'serviceWorker' in navigator && 'PushManager' in window;
        this.registration = null;
        this.subscription = null;

        if (this.isSupported) {
            this.init();
        }
    }

    async init() {
        try {
            // Service Worker登録
            this.registration = await navigator.serviceWorker.register('/sw.js');
            console.log('Service Worker registered:', this.registration);

            // 既存の購読状況を確認
            this.subscription = await this.registration.pushManager.getSubscription();

            // UIを更新
            this.updateUI();

        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    }

    async requestPermission() {
        if (!this.isSupported) {
            return false;
        }

        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            await this.subscribe();
            return true;
        }

        return false;
    }

    async subscribe() {
        if (!this.registration) {
            throw new Error('Service Worker not registered');
        }

        try {
            // VAPIDキーを取得
            const response = await fetch('/wp-json/aidunite/v1/push/vapid-key');
            const { publicKey } = await response.json();

            // プッシュ購読
            this.subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(publicKey)
            });

            // サーバーに購読情報を送信
            await this.sendSubscriptionToServer(this.subscription);

            this.updateUI();
            this.showToast('プッシュ通知が有効になりました', 'success');

        } catch (error) {
            console.error('Push subscription failed:', error);
            this.showToast('プッシュ通知の設定に失敗しました', 'error');
        }
    }

    async unsubscribe() {
        if (!this.subscription) {
            return;
        }

        try {
            await this.subscription.unsubscribe();

            // サーバーから購読情報を削除
            await this.removeSubscriptionFromServer();

            this.subscription = null;
            this.updateUI();
            this.showToast('プッシュ通知を無効にしました', 'info');

        } catch (error) {
            console.error('Push unsubscription failed:', error);
        }
    }

    async sendSubscriptionToServer(subscription) {
        const response = await fetch('/wp-json/aidunite/v1/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify({
                subscription: subscription.toJSON()
            })
        });

        if (!response.ok) {
            throw new Error('Failed to send subscription to server');
        }
    }

    async removeSubscriptionFromServer() {
        const response = await fetch('/wp-json/aidunite/v1/push/unsubscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify({
                endpoint: this.subscription.endpoint
            })
        });

        if (!response.ok) {
            throw new Error('Failed to remove subscription from server');
        }
    }

    updateUI() {
        const pushButton = document.getElementById('push-notification-toggle');
        if (!pushButton) return;

        if (!this.isSupported) {
            pushButton.textContent = 'プッシュ通知未対応';
            pushButton.disabled = true;
            return;
        }

        if (this.subscription) {
            pushButton.textContent = 'プッシュ通知を無効にする';
            pushButton.onclick = () => this.unsubscribe();
        } else {
            pushButton.textContent = 'プッシュ通知を有効にする';
            pushButton.onclick = () => this.requestPermission();
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    showToast(message, type) {
        if (window.formNotifications && typeof window.formNotifications.showToast === 'function') {
            window.formNotifications.showToast(message, type);
        } else {
            aiduniteToast(message, type || 'info');
        }
    }

    // テスト通知送信
    async sendTestNotification() {
        try {
            const response = await fetch('/wp-json/aidunite/v1/push/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('テスト通知を送信しました', 'success');
            } else {
                this.showToast('テスト通知の送信に失敗しました', 'error');
            }
        } catch (error) {
            console.error('Test notification failed:', error);
            this.showToast('テスト通知の送信でエラーが発生しました', 'error');
        }
    }
}

// グローバルインスタンス
    let ainyWebPush;

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', () => {
    ainyWebPush = new AinyWebPush();

    // テストボタンのイベントリスナー
    const testButton = document.getElementById('test-push-notification');
    if (testButton) {
        testButton.addEventListener('click', () => {
            ainyWebPush.sendTestNotification();
        });
    }
});

// Service Workerからのメッセージを受信
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', event => {
        if (event.data && event.data.type === 'NOTIFICATION_CLICKED') {
            // 通知クリック時の処理
            const { url, notificationId } = event.data;

            if (url) {
                window.open(url, '_blank');
            }

            // 通知を既読にする
            if (notificationId) {
                fetch('/wp-json/aidunite/v1/mark-read', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': wpApiSettings.nonce
                    },
                    body: JSON.stringify({
                        id: notificationId
                    })
                }).catch(error => {
                    console.error('Failed to mark notification as read:', error);
                });
            }
        }
    });
}

// 通知許可状況の監視
if ('Notification' in window) {
    // 許可状況が変更された時の処理
    const checkPermission = () => {
        if (Notification.permission === 'denied') {
            console.log('Notifications are blocked by the user');
        } else if (Notification.permission === 'granted') {
            console.log('Notifications are allowed');
        }
    };

    // 定期的に許可状況をチェック
    setInterval(checkPermission, 30000); // 30秒ごと
}
