# Ainy 正式リリース準備 — タスク一覧

最終更新: 2026-07-10  
評価基準: **正式リリース**（「動くか」ではなく本番運用・法務・決済・セキュリティ・テストを前提）  
評価者: Cursor（コードベース横断レビュー）

---

## サマリー

| 項目 | 結果 |
|------|------|
| **完成度** | **68 / 100** |
| **リリース判定** | **現状 No**（限定ベータは P0 完了後に条件付き可） |
| **機能の箱** | コア機能は概ね実装済み |
| **品質の箱** | テスト・セキュリティ・UI統一・運用手順が不足 |

### 評価ラベル

| ラベル | 意味 |
|--------|------|
| `完了` | 本番リリース品質（テスト済み・仕様整合・運用可能） |
| `要修正` | 実装はあるがリリース前に手当て必須 |
| `未実装` | スタブ・欠落・運用不可 |

---

## ドメイン別ステータス（俯瞰）

### 機能

| ドメイン | ステータス | メモ |
|----------|------------|------|
| 認証 | 要修正 | `auth-middleware.php` 統一化済み。マルチチーム文脈が複雑 |
| チーム管理 | 要修正 | 登録・承認・解散まで実装。legacy `team_id` 併存 |
| マッチング | 要修正 | persist 層充実。巨大ページ・レガシー経路残存 |
| スケジュール | 要修正 | normalize/persist 整備。`schedule-edit.js` 約2900行 |
| チャット | 要修正 | REST/SSE 実装。`chat-functions.php` 2600行超 |
| 通知（アプリ内・メール） | 要修正 | `notification-api.php` で一本化 |
| 通知（Web Push） | **製品外（完了）** | `aidunite_web_push_product_enabled()` 既定 false |
| 選手管理 | 要修正 | `player-persist.php` あり。E2E 不足 |
| 保護者機能 | 要修正 | ページ POST 正本。REST は温存のみ |
| 大会機能 | 要修正 | 限定公開フラグ追加済。実運用検証浅い |
| 決済（Match） | 要修正 | Checkout/Webhook/出口ゲート。未払い制限有効 |
| 決済（Club） | 要修正 | Connect 月謝。運用複雑度高 |
| Stripe Connect | 要修正 | オンボーディング実装。本番設定検証不足 |
| 月謝管理 | 要修正 | Connect 未完チームは機能不全になりうる |

### UI/UX

| 観点 | ステータス |
|------|------------|
| PC | 要修正 |
| スマホ | 要修正 |
| 導線 | 要修正 |
| エラーハンドリング | 要修正 |
| 空状態 | 要修正 |
| ローディング | 要修正 |
| レスポンシブ | 要修正 |

### セキュリティ

| 観点 | ステータス |
|------|------------|
| 権限管理 | 要修正 |
| REST API | 要修正 |
| Nonce | 要修正 |
| サニタイズ | 要修正 |
| XSS | 要修正 |
| CSRF | 要修正 |

### データ設計

| 観点 | ステータス |
|------|------------|
| Normalize | 要修正 |
| Persist | 要修正 |
| DB 整合性 | 要修正 |
| 削除時整合性 | 要修正 |

### 保守性

| 観点 | ステータス |
|------|------------|
| 重複コード | 要修正 |
| 命名 | 要修正 |
| 責務分離 | 要修正 |
| 技術的負債 | 要修正 |

### Stripe

| 観点 | ステータス |
|------|------------|
| Checkout | 要修正 |
| Webhook | 要修正 |
| Portal | 要修正 |
| Connect | 要修正 |
| 例外処理 | 要修正 |

### テスト

| 観点 | ステータス |
|------|------------|
| 正常系 | 要修正 |
| 異常系 | **未実装** |
| 権限 | **要修正（マトリクス17行）** |
| 複数チーム | 要修正 |
| 複数ブラウザ | **未実装** |

---

## タスク一覧（優先度順）

### P0 — リリースブロッカー（必須）

| ID | タスク | ステータス | 担当ドメイン | 完了条件 |
|----|--------|------------|--------------|----------|
| P0-01 | 本番デプロイ手順の確定と検証 | 要修正 | Stripe / 運用 | 手順書 [`docs/operations/production-deploy.md`](../operations/production-deploy.md) 整備済。**残:** ステージング/本番での実行記録・スモーク PASS |
| P0-02 | Match/Club 決済 E2E 全シナリオ実行 | 要修正 | 決済 | チェックリスト [`docs/reports/payment-e2e-checklist.md`](payment-e2e-checklist.md) 整備済。**残:** TC-PAY-001〜015 の実行・CSV 記録（特に Portal / Gate B / 手動系） |
| P0-03 | 未払い制限の段階的ロールアウト設計 | 要修正 | 決済 / 法務 | feature flag・猶予・管理者バイパス・監視アラート。`aidunite_payment_user_flows_enabled()` の運用方針文書化 |
| P0-04 | 公開 REST エンドポイントのセキュリティ監査 | **完了** | セキュリティ | Push REST 無効・保護者 REST 既定 OFF・マッチ招待/大会公開にレート制限 |
| P0-05 | Web Push の製品方針決定と実装 | **完了（製品から外す）** | 通知 | `aidunite_web_push_product_enabled()` 既定 false。REST 未登録・送信 no-op |
| P0-06 | 退会・解散・代表者譲渡 × Stripe 結合テスト | 要修正 | 決済 / データ | 実装済（`withdrawal-*` / `team-dissolution-*` / `payment-exit-scheduler`）。**残:** [`payment-e2e-checklist.md`](payment-e2e-checklist.md) TC-PAY-009/014/015 の PASS 記録 |

### P1 — リリース前強く推奨

| ID | タスク | ステータス | 担当ドメイン | 完了条件 |
|----|--------|------------|--------------|----------|
| P1-01 | PHPUnit 統合テストの CI 復帰 | **完了** | テスト | GitHub Actions Tests #2 緑。unit 148 + simple-integration 7。[`github-ci-setup.md`](../operations/github-ci-setup.md) |
| P1-02 | 権限マトリクス自動テスト | **完了** | セキュリティ / テスト | `PermissionMatrixCatalog` 17行 + `PermissionMatrixEvaluator` + `PermissionMatrixTest`。CI unit スイートに含まれる |
| P1-03 | XSS 監査（JS innerHTML 箇所） | **要修正（match-detail・parent-payment）** | セキュリティ | match-detail ボタン安全化済。`parent-payment.js` 履歴テーブルを DOM API 化。**残:** communication / admin-competition 等 |
| P1-04 | マルチチーム操作の結合テスト | **要修正（SimpleIntegration 拡張）** | チーム / マッチ / 決済 | チーム切替・未知 team 拒否の自動テスト追加。**残:** WP 実 DB / E2E でチェックリスト実行 |
| P1-05 | Connect オンボーディング未完時の UX | **完了** | 月謝 / Stripe | `aidunite_payment_read_tuition_block_message()` で代表者/保護者文言を統一 |
| P1-06 | Webhook 失敗時のリカバリ手順 | **要修正（文書完了）** | Stripe | [`stripe-webhook-recovery.md`](../operations/stripe-webhook-recovery.md)。**残:** 本番で再送ドリル1回 |
| P1-07 | 本番監視の最低限セットアップ | **要修正（文書完了）** | 運用 | [`production-monitoring.md`](../operations/production-monitoring.md)。**残:** 外形監視ツール設定 |

### P2 — 品質・保守性（リリース直後でも可、ただし早期推奨）

| ID | タスク | ステータス | 担当ドメイン | 完了条件 |
|----|--------|------------|--------------|----------|
| P2-01 | legacy meta マイグレーション完了 | **要修正（計画+台帳完了）** | データ | [`legacy-meta-migration-plan.md`](legacy-meta-migration-plan.md) + [`legacy-inventory.md`](legacy-inventory.md) + [`legacy-p0-action-checklist.md`](legacy-p0-action-checklist.md) |
| P2-02 | 空状態コンポーネントの全画面展開 | 要修正 | UI/UX | お気に入りチーム削除時の空状態（JS）追加済。schedule-month-view は PHP 空要素連携 |
| P2-03 | エラー・ローディング UI の統一 | 要修正 | UI/UX | schedule-modal try/catch に debug ログ追加。主要製品 JS は前回まで対応済 |
| P2-04 | 神ファイルの分割リファクタ | **要修正（計画完了）** | 保守性 | [`god-file-split-plan.md`](god-file-split-plan.md) |
| P2-05 | 大会機能の限定公開フラグ | **完了** | 大会 | `AIDUNITE_COMPETITION_ENABLED` / `AIDUNITE_COMPETITION_PUBLIC_ENABLED`。管理ページ・公開 LP・公開 REST をゲート |
| P2-06 | 整合性診断の本番運用化 | **要修正（文書完了）** | データ | [`data-integrity-scheduled-runs.md`](../operations/data-integrity-scheduled-runs.md) |
| P2-07 | 未使用 REST・スケルトンページの整理 | **要修正（棚卸完了）** | 保守性 / セキュリティ | [`skeleton-rest-inventory.md`](skeleton-rest-inventory.md) |

### P3 — 改善・体制

| ID | タスク | ステータス | 担当ドメイン | 完了条件 |
|----|--------|------------|--------------|----------|
| P3-01 | docs/spec のチーム共有体制 | 要修正 | 保守性 | `*.md` gitignore 方針見直し、仕様書を開発チームが参照可能に |
| P3-02 | スマホ実機チェックリスト | 未実装 | UI/UX | 主要導線（マイページ・掲示板・スケジュール・決済）の実機 QA 記録 |
| P3-03 | 通知 type 表記の完全統一 | 要修正 | 通知 | backlog B8 解消、`notification.md` + config 正本化 |
| P3-04 | 会場 both/either 表記統一 | 要修正 | データ / UI | backlog B2 解消 |
| P3-05 | `docs/archive/` 死蔵アセット整理 | 未実装 | 保守性 | 未使用 CSS/JS の削除 or アーカイブ明示 |

---

## ドメイン別タスク（実装観点）

### 認証・権限

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| AUTH-01 | 全 REST で `AidUniteAuthMiddleware::rest_require` 適用の漏れ洗い出し | **完了** | [`rest-auth-middleware-audit.md`](rest-auth-middleware-audit.md)。`/mypage-joy-context` 含め AM 化完了 |
| AUTH-02 | 管理者 `team_id` フォールバック時の警告 UI | 要修正 | `auth-middleware.php` `aidunite_user_resolve_admin_team_id` |
| AUTH-03 | 管理者ログイン OTP の本番運用手順 | 要修正 | `functions/user/admin-login-security.php` |

### チーム・マルチチーム

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| TEAM-01 | `managed_team_ids` / `current_operating_team_id` の境界ケーステスト | 要修正 | `functions/common/team-context.php` |
| TEAM-02 | チーム解散フローの Stripe・通知・スケジュール整合 | 要修正 | `functions/member/team-dissolution-*.php` |
| TEAM-03 | legacy `team_id` メタの廃止計画 | 要修正 | `functions/user/user-persist.php` |
| TEAM-04 | 代表者譲渡 × 決済 Checkout 14日ルールの E2E | 要修正 | `functions/team/team-leader-transfer.php` |

### マッチング・スケジュール

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| MATCH-01 | 掲示板ステータス compute の正本一本化 | 要修正 | backlog B7 |
| MATCH-02 | `page-match-detail.php` の分割・テスト | 要修正 | `page-match-detail.php` |
| SCHED-01 | `schedule-edit.js` 分割と legacy meta 書込廃止 | 要修正 | `assets/js/schedule/schedule-edit.js` |
| SCHED-02 | メタキー移行スクリプトの本番実行計画 | 要修正 | `functions/schedule/meta-key-migration.php` |

### チャット・通知

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| CHAT-01 | チャット重複ルームの本番監視 | 要修正 | `functions/messaging/` |
| CHAT-02 | SSE 本番負荷・タイムアウト検証 | 要修正 | `functions/messaging/sse-functions.php` |
| NOTIF-01 | Web Push スタブの除去 or 本実装 | **完了（製品外）** | `functions/notify/web-push-api.php` |
| NOTIF-02 | Push `__return_true` エンドポイントの認証設計 | **完了（REST未登録）** | `web-push-api.php` — フラグ OFF 時ルート未登録 |
| NOTIF-03 | 配信ログ・冪等キーの本番運用確認 | 要修正 | `functions/notify/notification-delivery-log.php` |

### 保護者・選手

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| PARENT-01 | 保護者招待〜月謝支払いの E2E | 要修正 | `page-invite-guardian.php`, `page-parent-payment.php` |
| PARENT-02 | 温存 REST の削除 or 接続判断 | 要修正 | `functions/parent/rest-parent-invite.php` |
| PLAYER-01 | 未成年チーム・保護者紐付けの権限テスト | 要修正 | `functions/player/player-persist.php` |

### 大会

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| COMP-01 | 参加費 Checkout → 返金の本番 Stripe 検証 | 要修正 | `functions/competition/competition-refund.php` |
| COMP-02 | 公開 bracket/slug エンドポイントのレート制限 | **完了** | `functions/competition/rest-competition.php` |
| COMP-03 | 管理 UI の XSS 対策（innerHTML） | 要修正 | `admin-competition-event.js` 強化済 |

### 決済・Stripe

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| PAY-01 | 本番 Stripe 鍵・Webhook URL・Connect 設定チェックリスト | **要修正（文書完了）** | `docs/operations/stripe-production-checklist.md` |
| PAY-02 | 未払い制限リダイレクトの誤検知調査 | 要修正 | `functions/payment/payment-restrictions.php` |
| PAY-03 | Gate A/B 出口ルールの E2E（祝日・トライアル・複数チーム割引） | 要修正 | `functions/payment/payment-exit-gates.php` |
| PAY-04 | Club 月謝：月末アンカー・日割りなしの Stripe 実地確認 | 要修正 | `functions/payment/payment-tuition.php` |
| PAY-05 | Billing Portal 権限・return URL の検証 | 要修正 | `functions/payment/stripe-billing.php` |
| PAY-06 | Webhook イベント網羅テスト（subscription/invoice/connect） | 要修正 | `functions/payment/stripe-webhook.php` |

### 退会・法務

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| LEGAL-01 | 退会データ移管（案B）の本番ドライラン | **要修正（手順完了）** | `docs/legal/compliance-tracker.md` LEGAL-01 節 |
| LEGAL-02 | 規約第5条6項と未払い制限の運用マニュアル | **要修正（手順完了）** | `docs/legal/compliance-tracker.md` LEGAL-02 節 |

### UI/UX

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| UX-01 | `aidunite_empty_state()` の主要画面への適用 | 要修正 | 通知・掲示板・スケジュール管理 JS・マイページ Joy 含む展開済 |
| UX-02 | 空状態の直色指定を CSS 変数へ | **完了** | `empty-state.php`, `empty-state.css` |
| UX-03 | JS 空 catch の排除とユーザー向けエラー表示 | 要修正 | 製品 JS 主要箇所に debug ログ追加済。design-reference-demo 等は対象外 |
| UX-04 | マイページ・掲示板・スケジュールのスマホ実機 QA | **要修正（チェックリスト完了）** | `docs/reports/mobile-qa-checklist.md` |
| UX-05 | オンボーディング導線の迷子ポイント洗い出し | 要修正 | `template-parts/onboarding-bot-chat-modal.php` |

### セキュリティ

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| SEC-01 | REST `permission_callback` 全件インベントリ | **完了（公開系）** | [`rest-public-endpoints-inventory.md`](rest-public-endpoints-inventory.md) + [`rest-auth-middleware-audit.md`](rest-auth-middleware-audit.md) |
| SEC-02 | 保護者 signup 公開 REST のトークン検証強化 | 要修正 | `functions/parent/rest-parent-invite.php` |
| SEC-03 | E2E API が本番で無効であることのデプロイ検証 | **要修正（文書完了）** | `docs/reports/post-nonce-audit.md` SEC-03 節 |
| SEC-04 | フォーム POST の nonce 漏れ洗い出し | **要修正（棚卸完了）** | `docs/reports/post-nonce-audit.md` |

### データ・保守性

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| DATA-01 | normalize backlog（B1〜B8）の PO 判断と実装 | 要修正 | `docs/reports/spreadsheets/ainy-canonical-data-dictionary-backlog.csv` |
| DATA-02 | persist read/submit の未移行ドメイン一覧化 | **要修正（台帳完了）** | [`legacy-inventory.md`](legacy-inventory.md) + [`normalize-services-management-table.csv`](normalize-services-management-table.csv)。**残:** PO 判断（DATA-01）と migrate 本番実行 |
| DATA-03 | 削除時 Stripe キャンセルと DB のトランザクション設計 | 要修正 | withdrawal / dissolution |
| MAINT-01 | `functions.php` の require 分割 | 要修正 | `functions.php` |
| MAINT-02 | 重複 schedule/match ユーティリティの統合 | 要修正 | 各 common 関数 |

### テスト

| ID | タスク | ステータス | 関連ファイル |
|----|--------|------------|--------------|
| TEST-01 | Payment E2E スクリプトの CI 組込 | 要修正 | `scripts/PaymentE2ETest.ps1` |
| TEST-02 | 異常系テストケース追加（決済失敗・権限拒否） | 未実装 | `tests/`（要復帰） |
| TEST-03 | 複数チーム E2E シナリオ | 要修正 | `functions/e2e/test-data-api.php` |
| TEST-04 | 複数ブラウザ（Chrome/Safari/iOS）スモーク | 未実装 | — |

---

## 重大問題 TOP10（タスク化）

| # | 問題 | 対応タスク ID |
|---|------|---------------|
| 1 | 自動テストがリポジトリ外/無効 | P1-01, TEST-01, TEST-02 |
| 2 | 本番デプロイで Stripe SDK が落ちうる | P0-01, PAY-01 |
| 3 | 未払い制限が本番有効（即ブロックリスク） | P0-03, PAY-02, LEGAL-02 |
| 4 | Web Push がスタブのまま UI 露出の可能性 | ~~P0-05~~ **解消**（製品外・REST無効） |
| 5 | REST `__return_true` の悪用リスク | P0-04 **一部完了**, SEC-01, COMP-02 **完了** |
| 6 | JS innerHTML による XSS | P1-03, COMP-03 |
| 7 | マルチチーム × 決済 × 出口ゲート未検証 | P0-06, P1-04, PAY-03 |
| 8 | 仕様書がデプロイ先にない | P3-01 |
| 9 | 神ファイル集中（変更が全壊しうる） | P2-04, MATCH-02, SCHED-01 |
| 10 | 整合性診断ツールが本番除外 | P2-06 |

---

## 軽微改善 TOP20（タスク化）

| # | 改善 | 対応タスク ID |
|---|------|---------------|
| 1 | 空状態コンポーネント全画面展開 | UX-01 |
| 2 | 空状態の Design Tokens 化 | ~~UX-02~~ **完了** |
| 3 | JS 空 catch 排除 | UX-03 |
| 4 | jQuery 依存の段階廃止 | P2-04 |
| 5 | 未使用 REST 整理 | PARENT-02, P2-07 |
| 6 | schedule-edit TODO コメント解消 | SCHED-01 |
| 7 | ローディング UI 統一 | P2-03 |
| 8 | エラー文言のユーザー向け整理 | P2-03 |
| 9 | 管理者 team_id フォールバック警告 | AUTH-02 |
| 10 | 通知 type 表記統一 | P3-03, NOTIF-03 |
| 11 | 会場 both/either 統一 | P3-04 |
| 12 | 掲示板ステータス正本化 | MATCH-01 |
| 13 | スマホ実機チェック | P3-02, UX-04 |
| 14 | スケルトンページ整理 | P2-07 |
| 15 | E2E 結果 CSV の管理方針 | TEST-01 |
| 16 | error_log → 構造化ログ | P1-07 |
| 17 | Service Worker 認証設計 | NOTIF-02 |
| 18 | 大会公開 slug の SEO/キャッシュ | COMP-02 |
| 19 | 選手写真アップロード再検証 | PLAYER-01 |
| 20 | docs/archive 整理 | P3-05 |

---

## リリース判定フロー（推奨）

```
[現状] 完成度 58点 → 一般公開 NO
    ↓
P0 全完了（決済・セキュリティ・Push方針）
    ↓
P1 の TEST / 権限 / XSS 完了
    ↓
限定ベータ（招待制・監視体制あり）GO
    ↓
P2 品質タスク + 実地運用 2〜4週
    ↓
一般公開 GO 検討
```

---

## 変更履歴

| 日付 | 内容 | 更新者 |
|------|------|--------|
| 2026-07-11 | P1-03 parent-payment XSS・P1-04 マルチチーム切替テスト・P1-05 Connect 文言統一 | Cursor |
| 2026-07-11 | P1-01 CI 緑化完了・P1-02 権限マトリクス17行（member payment-setup / guardian team-settings 追加） | Cursor |
| 2026-07-11 | P1-01 ローカル全 PASS・github-ci-setup.md / P1-02 PermissionMatrix 実装 / P1-03 match-detail ボタン安全化 | Cursor |
| 2026-07-10 | P1-01〜07 / P2-01/04/06/07 / PAY-01 / LEGAL / SEC / UX-04 一括（CI・fixture・Connect UX・運用文書） | Cursor |
| 2026-07-10 | P1-03 続き（chat/communication/schedule-edit）、P2-02 通知・掲示板、P2-03 空catch、AUTH-01 バックログ AM 化 | Cursor |
| 2026-07-10 | P1-03 XSS（大会管理・schedule-modal）、P2-02 空状態展開、P2-03 ローディング/空catch、AUTH-01 REST監査 MD | Cursor |
| 2026-07-10 | P2-05 大会限定公開フラグ、P2-02/UX-02 empty-state Tokens 化、P1-03 match-history XSS、REST インベントリ MD | Cursor |
| 2026-07-10 | P0-4 / P0-5 実装（Web Push 無効化、公開 REST 監査・レート制限） | Cursor |
| 2026-07-10 | 初版作成（正式リリース前提レビューをタスクベースに整理） | Cursor |
| 2026-07-10 | P0-01 デプロイ手順書・P0-02/06 E2E チェックリスト追加、P0-01/02/06 の進捗メモ更新 | Cursor |
