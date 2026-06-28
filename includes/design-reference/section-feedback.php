            <div class="design-section" id="feedback-modal-guide">
                <h2 class="section-title">💬 フィードバック・モーダル統一定義</h2>
                <p class="section-description">
                    モーダル・トースト・バナー・アラート等の UI パターンを、<strong>目的（何のため）</strong>と<strong>見た目（どう見える）</strong>の2軸で整理したガイドです。
                    詳細は <a class="feedback-doc-link" href="<?php echo esc_url(get_template_directory_uri() . '/docs/UI-Feedback-and-Modal-Guide.md'); ?>" target="_blank" rel="noopener">docs/UI-Feedback-and-Modal-Guide.md</a> を参照してください。
                </p>

                <div class="feedback-legend">
                    <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規（新規はこれのみ）</span>
                    <span class="feedback-status-badge feedback-status-badge--warn">⚠️ 移行対象</span>
                    <span class="feedback-status-badge feedback-status-badge--ng">❌ 禁止 / 廃止</span>
                </div>

                <div class="design-part">
                    <h4>21-A：AidUnite 5系統（目的による分類）</h4>
                    <table class="feedback-matrix">
                        <thead>
                            <tr>
                                <th>系統</th>
                                <th>定義</th>
                                <th>正規 API</th>
                                <th>状態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>A. 確認モーダル</strong></td>
                                <td>削除・キャンセル等の判断</td>
                                <td><code>showConfirmModal()</code></td>
                                <td>✅ 正規</td>
                            </tr>
                            <tr>
                                <td><strong>B. 結果トースト</strong></td>
                                <td>操作結果の報告</td>
                                <td><code>showToastNotification()</code></td>
                                <td>✅ 正規</td>
                            </tr>
                            <tr>
                                <td><strong>C. インライン</strong></td>
                                <td>フォームエラー・ページ内固定</td>
                                <td><code>.alert</code> / <code>.form-error</code></td>
                                <td>✅ 正規</td>
                            </tr>
                            <tr>
                                <td><strong>D. 永続お知らせ</strong></td>
                                <td>他ユーザーからの通知（一覧に残る）</td>
                                <td>お知らせ CPT</td>
                                <td>✅ 正規</td>
                            </tr>
                            <tr>
                                <td><strong>E. Web Push</strong></td>
                                <td>OS 通知センター（バックグラウンド着信）</td>
                                <td><code>AinyWebPush</code> / <code>aidunite_send_web_push()</code></td>
                                <td>✅ 正規</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="design-part">
                    <h4>21-B：世の中のパターンと AidUnite での扱い（見た目プレビュー）</h4>
                    <p class="part-description">各カードは静止プレビューです。実際の挙動は下の「21-C：ライブデモ」で確認できます。</p>

                    <div class="feedback-pattern-grid">
                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">中央トースト（Toast）</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">操作結果を短時間表示。自動で消える。判断は不要。</p>
                            <p class="feedback-pattern-card__api">API: <code>showToastNotification(msg, type)</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-dim"></div>
                                <div class="feedback-preview-toast-card">
                                    <div class="feedback-preview-toast-card__icon">🎉</div>
                                    <div class="feedback-preview-toast-card__title">完了</div>
                                    <div class="feedback-preview-toast-card__text">保存しました</div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">確認モーダル（Dialog）</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">破壊的操作の前に OK / キャンセルを求める。背面をブロック。</p>
                            <p class="feedback-pattern-card__api">API: <code>showConfirmModal(options)</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-dim"></div>
                                <div class="feedback-preview-confirm">
                                    <div class="feedback-preview-confirm__header">削除確認</div>
                                    <div class="feedback-preview-confirm__body">削除してもよろしいですか？</div>
                                    <div class="feedback-preview-confirm__footer">
                                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--cancel">キャンセル</span>
                                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--danger">削除</span>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">バナー通知（Banner）</h5>
                                <span class="feedback-status-badge feedback-status-badge--warn">⚠️ 移行中</span>
                            </div>
                            <p class="feedback-pattern-card__desc">画面上部の横長バー。ログイン成功等に使われていたが、正規トーストへ統合予定。</p>
                            <p class="feedback-pattern-card__api">レガシー: <code>showNotification()</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-banner">
                                    <span>🎉 ログイン成功！</span>
                                    <span aria-hidden="true">✕</span>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">スナックバー（Snackbar）</h5>
                                <span class="feedback-status-badge feedback-status-badge--warn">⚠️ 非採用</span>
                            </div>
                            <p class="feedback-pattern-card__desc">画面端（多くは下部）の細いバー。Material Design 由来。AidUnite では中央トーストを正規とする。</p>
                            <p class="feedback-pattern-card__api">新規実装禁止 → トーストへ</p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-snackbar">保存しました</div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">インラインアラート</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">ページ内に固定。フォーム全体のエラーや注意書き。消えるまで表示。</p>
                            <p class="feedback-pattern-card__api">HTML: <code>.alert .alert-*</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-inline">
                                    <div class="alert alert-warning"><strong>警告</strong> 入力を確認してください。</div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">OS 通知 — alert()</h5>
                                <span class="feedback-status-badge feedback-status-badge--ng">❌ 禁止</span>
                            </div>
                            <p class="feedback-pattern-card__desc"><code>window.alert()</code> — OS / ブラウザ標準。OK ボタンのみ。結果表示に使われがちだが禁止。</p>
                            <p class="feedback-pattern-card__api">代替: <code>showToastNotification()</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">イメージ</span>
                                <div class="feedback-preview-dim"></div>
                                <div class="feedback-preview-native">
                                    <div class="feedback-preview-native__title">このページより</div>
                                    <div class="feedback-preview-native__text">保存しました</div>
                                    <div class="feedback-preview-native__actions">
                                        <button type="button" class="feedback-preview-native__btn" tabindex="-1">OK</button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">OS 通知 — confirm()</h5>
                                <span class="feedback-status-badge feedback-status-badge--ng">❌ 禁止</span>
                            </div>
                            <p class="feedback-pattern-card__desc"><code>window.confirm()</code> — OS / ブラウザ標準。OK + キャンセル。削除確認等に散在（移行対象）。</p>
                            <p class="feedback-pattern-card__api">代替: <code>showConfirmModal()</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">イメージ</span>
                                <div class="feedback-preview-dim"></div>
                                <div class="feedback-preview-native feedback-preview-native--confirm">
                                    <div class="feedback-preview-native__title">このページより</div>
                                    <div class="feedback-preview-native__text">削除しますか？</div>
                                    <div class="feedback-preview-native__actions">
                                        <button type="button" class="feedback-preview-native__btn" tabindex="-1">キャンセル</button>
                                        <button type="button" class="feedback-preview-native__btn" tabindex="-1">OK</button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">Web Push（OS プッシュ通知）</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">サイト外・バックグラウンドでも OS 通知センターに届く。許可制。<code>alert()</code> とは別物。</p>
                            <p class="feedback-pattern-card__api">JS: <code>AinyWebPush</code>（<code>web-push-notifications.js</code>）</p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="aidunite-demo-webpush">
                                    <div class="aidunite-demo-webpush__icon" aria-hidden="true">🏀</div>
                                    <div class="aidunite-demo-webpush__body">
                                        <div class="aidunite-demo-webpush__app">Ainy Unite</div>
                                        <div class="aidunite-demo-webpush__title">マッチ申請が届きました</div>
                                        <div class="aidunite-demo-webpush__text">〇〇チームから申請があります</div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">未読バッジ / ドット</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">アイコン上の件数・未読点。永続お知らせの存在を示す（トーストではない）。</p>
                            <p class="feedback-pattern-card__api">CSS: <code>.count-badge</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-badge">
                                    <span class="feedback-preview-badge__icon-wrap">
                                        🔔
                                        <span class="count-badge count-badge--overlay">3</span>
                                    </span>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">機能モーダル（詳細・入力）</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 各機能</span>
                            </div>
                            <p class="feedback-pattern-card__desc">スケジュール詳細・新規登録確認など。Yes/No 確認ではなく情報表示・入力が目的。</p>
                            <p class="feedback-pattern-card__api">例: <code>AidUniteScheduleModal</code></p>
                            <div class="feedback-preview-stage" aria-hidden="true">
                                <span class="feedback-preview-stage__label">プレビュー</span>
                                <div class="feedback-preview-dim"></div>
                                <div class="feedback-preview-confirm">
                                    <div class="feedback-preview-confirm__header">新規スケジュール登録</div>
                                    <div class="feedback-preview-confirm__body">📅 2026年6月1日<br>登録しますか？</div>
                                    <div class="feedback-preview-confirm__footer">
                                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--primary">新規登録</span>
                                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--cancel">キャンセル</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="design-part">
                    <h4>21-C：ライブデモ</h4>

                    <h5 class="feedback-demo-subtitle">✅ 正規 API</h5>
                    <p class="part-description">本番で使用するコンポーネントです。</p>
                    <div class="feedback-demo-actions">
                        <button type="button" class="btn btn-success" onclick="demoShowToast('success')">結果トースト（成功）</button>
                        <button type="button" class="btn btn-danger" onclick="demoShowToast('error')">結果トースト（エラー）</button>
                        <button type="button" class="btn btn-primary" onclick="demoShowConfirmModal()">確認モーダル</button>
                        <button type="button" class="btn btn-danger" onclick="demoShowConfirmModalDanger()">確認モーダル（削除）</button>
                        <button type="button" class="btn btn-info" onclick="demoShowWebPushLive()">Web Push（OS 通知・許可が必要）</button>
                        <button type="button" class="btn btn-secondary" onclick="demoShowWebPushMock()">Web Push（見本モック）</button>
                    </div>
                    <p class="part-description">Web Push はサイトを開いていなくても OS 通知センターに届きます。体験ボタンはブラウザの <code>Notification</code> API を使用します（HTTPS 推奨）。</p>

                    <div class="design-part" id="ref-first-match-billing-modal">
                        <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">初試合成立・課金案内モーダル</h5>
                        <p class="part-description">
                            実チーム初回試合成立後、代表者のマイページで1回表示される中央モーダルです（オンボーディング・ボット試合は対象外）。
                            本番は <code>payment-first-match-prompt.js</code> ＋ REST <code>/payment-exit/first-match-prompt</code>。
                            仕様は <code>docs/spec/team.md</code> §12.1。
                        </p>
                        <div class="feedback-demo-actions">
                            <button type="button" class="btn btn-primary" onclick="demoShowFirstMatchBillingModal()">初試合成立モーダルを表示</button>
                        </div>
                        <p class="part-description">デザイン確認用のサンプルデータです。ボタン操作はメタ保存や遷移を行いません。</p>
                    </div>

                    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">⚠️ 移行対象パターン（見本のみ）</h5>
                    <p class="part-description">バナー・スナックバーは<strong>非採用</strong>です。体験後は正規の <code>showToastNotification</code> へ統一してください。</p>
                    <div class="feedback-demo-actions">
                        <button type="button" class="btn btn-warning" onclick="demoShowBannerNotification()">バナー通知を表示</button>
                        <button type="button" class="btn btn-secondary" onclick="demoShowSnackbarNotification()">スナックバーを表示</button>
                    </div>
                    <p class="feedback-demo-note"><strong>⚠️ 新規実装禁止</strong> — ログイン成功バー（<code>showNotification</code>）等は順次 <code>showToastNotification</code> へ移行します。</p>

                    <div class="code-snippet">
                        <div class="code-header">
                            <span>正規 API 使用例</span>
                            <button class="btn-copy" onclick="copyCode('feedback-api-code')">コピー</button>
                        </div>
                        <pre id="feedback-api-code"><code>// B. 結果トースト
showToastNotification('保存しました', 'success');

// A. 確認モーダル
showConfirmModal({
  title: '削除確認',
  message: 'このスケジュールを削除してもよろしいですか？',
  confirmLabel: '削除する',
  cancelLabel: 'キャンセル',
  confirmVariant: 'danger',
  onConfirm: () => { /* 削除処理 */ }
});

// C. インライン（HTML）
// &lt;div class="alert alert-danger"&gt;...&lt;/div&gt;

// E. Web Push（OS プッシュ — 許可後）
// AinyWebPush.requestPermission() / aidunite_send_web_push()</code></pre>
                    </div>
                </div>

                <div class="design-part" id="feedback-spinner-toast">
                    <h4>21-E：ローディングスピナー × トースト（併用パターン）</h4>
                    <p class="part-description">
                        非同期処理では<strong>処理中はスピナーで待たせ、完了後にスピナーを消してトーストで結果を伝える</strong>のが正規フローです。
                        スピナーだけ／トーストだけにしないでください。
                    </p>

                    <table class="feedback-matrix ref-spinner-toast-matrix">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>スピナー種別</th>
                                <th>API / 実装</th>
                                <th>トースト</th>
                                <th>使用箇所（代表）</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>ST-1</strong></td>
                                <td>フルスクリーン</td>
                                <td><code>showLoadingSpinner()</code></td>
                                <td>成功 / 失敗</td>
                                <td><code>loading-spinner-utils.js</code>（汎用 API）</td>
                            </tr>
                            <tr>
                                <td><strong>ST-2</strong></td>
                                <td>フルスクリーン（Ajax）</td>
                                <td><code>AidUniteAjaxUtils.showLoading()</code></td>
                                <td>コールバック内 <code>showToastNotification</code></td>
                                <td><code>schedule-modal.js</code>（メモ保存・削除）、<code>schedule-management.js</code></td>
                            </tr>
                            <tr>
                                <td><strong>ST-3</strong></td>
                                <td>ボタン内</td>
                                <td><code>loadingSpinnerManager.setupFormLoading()</code></td>
                                <td><code>form-notifications.js</code> 経由</td>
                                <td><code>.ajax-form</code> 送信</td>
                            </tr>
                            <tr>
                                <td><strong>ST-4</strong></td>
                                <td>ボタン内（手動）</td>
                                <td><code>&lt;span class="button-loading-spinner"&gt;</code></td>
                                <td>申請結果 toast</td>
                                <td><code>page-match-detail.php</code>（マッチ申請・キャンセル等）</td>
                            </tr>
                            <tr>
                                <td><strong>ST-5</strong></td>
                                <td>ページ内オーバーレイ</td>
                                <td><code>#loading-spinner</code></td>
                                <td>エラー時 toast（成功時はリダイレクト多め）</td>
                                <td><code>schedule-edit.js</code> / <code>page-schedule-edit.php</code></td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">21-E-B：操作フロー（静止プレビュー）</h5>
                    <p class="part-description">いずれも <strong>①操作中（スピナー）→ ②完了（トースト）</strong> の順序です。</p>

                    <div class="feedback-pattern-grid feedback-pattern-grid--flow">
                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">ST-1 / ST-2 フルスクリーン</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">削除・保存など画面全体をブロック。REST/Ajax 完了後にトースト。</p>
                            <div class="feedback-preview-flow" aria-hidden="true">
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">① 操作中</span>
                                    <div class="feedback-preview-fs">
                                        <div class="spinner spinner-md spinner-primary feedback-preview-fs__ring"></div>
                                        <span class="feedback-preview-fs__text">保存中...</span>
                                    </div>
                                </div>
                                <span class="feedback-preview-flow__arrow" aria-hidden="true">→</span>
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">② 完了</span>
                                    <div class="feedback-preview-toast-card feedback-preview-toast-card--inline">
                                        <div class="feedback-preview-toast-card__icon">🎉</div>
                                        <div class="feedback-preview-toast-card__title">完了</div>
                                        <div class="feedback-preview-toast-card__text">削除しました</div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">ST-3 / ST-4 ボタン内</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">主ボタン上で処理中を表示。フォーム送信・マッチ申請ボタン等。</p>
                            <div class="feedback-preview-flow" aria-hidden="true">
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">① 操作中</span>
                                    <button type="button" class="btn btn-primary feedback-preview-btn-loading" disabled>
                                        <span class="button-loading-spinner"></span> 申請中...
                                    </button>
                                </div>
                                <span class="feedback-preview-flow__arrow" aria-hidden="true">→</span>
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">② 完了</span>
                                    <div class="feedback-preview-toast-card feedback-preview-toast-card--inline feedback-preview-toast-card--success">
                                        <div class="feedback-preview-toast-card__icon">✅</div>
                                        <div class="feedback-preview-toast-card__text">申請が完了しました</div>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article class="feedback-pattern-card">
                            <div class="feedback-pattern-card__head">
                                <h5 class="feedback-pattern-card__title">ST-5 ページ内オーバーレイ</h5>
                                <span class="feedback-status-badge feedback-status-badge--ok">✅ 正規</span>
                            </div>
                            <p class="feedback-pattern-card__desc">スケジュール登録フォーム送信時。<code>#loading-spinner</code> を表示。</p>
                            <div class="feedback-preview-flow" aria-hidden="true">
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">① 操作中</span>
                                    <div class="feedback-preview-page-overlay">
                                        <div class="spinner spinner-md spinner-primary"></div>
                                        <span>登録中...</span>
                                    </div>
                                </div>
                                <span class="feedback-preview-flow__arrow" aria-hidden="true">→</span>
                                <div class="feedback-preview-flow__step">
                                    <span class="feedback-preview-flow__label">② 失敗時</span>
                                    <div class="feedback-preview-toast-card feedback-preview-toast-card--inline feedback-preview-toast-card--error">
                                        <div class="feedback-preview-toast-card__icon">⚠️</div>
                                        <div class="feedback-preview-toast-card__text">登録に失敗しました</div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">21-E-C：ライブデモ</h5>
                    <p class="part-description">実際の API を使った体験です。スピナー表示 → 約1.5秒後に非表示 → トースト表示。</p>
                    <div class="feedback-demo-actions ref-inline-wrap">
                        <button type="button" class="btn btn-primary" onclick="demoSpinnerThenToast('success')">フルスクリーン → 成功トースト</button>
                        <button type="button" class="btn btn-danger" onclick="demoSpinnerThenToast('error')">フルスクリーン → エラートースト</button>
                        <button type="button" class="btn btn-success" id="demo-btn-spinner-toast-success" onclick="demoButtonSpinnerThenToast(this, 'success')">ボタン内 → 成功トースト</button>
                        <button type="button" class="btn btn-secondary" id="demo-btn-spinner-toast-error" onclick="demoButtonSpinnerThenToast(this, 'error')">ボタン内 → エラートースト</button>
                    </div>
                    <div class="code-snippet">
                        <div class="code-header">
                            <span>併用パターン コード例</span>
                            <button class="btn-copy" onclick="copyCode('feedback-spinner-toast-code')">コピー</button>
                        </div>
                        <pre id="feedback-spinner-toast-code"><code>// ST-1 フルスクリーン → トースト
const spinnerId = showLoadingSpinner('保存中...', 'しばらくお待ちください');
try {
  await saveData();
  hideLoadingSpinner(spinnerId);
  showToastNotification('保存しました', 'success');
} catch (e) {
  hideLoadingSpinner(spinnerId);
  showToastNotification('保存に失敗しました', 'error');
}

// ST-3 ボタン内（form-notifications と同系）
const state = loadingSpinnerManager.setupFormLoading(form, '.submit-button', '送信中...');
// ... fetch 後
loadingSpinnerManager.clearFormLoading(state);
showToastNotification('処理が完了しました', 'success');</code></pre>
                    </div>
                </div>

                <div class="design-part">
                    <h4>21-D：OS 通知（❌ 禁止・参考のみ）</h4>
                    <p class="part-description"><code>alert()</code> / <code>confirm()</code> 等の <strong>OS 通知（ネイティブダイアログ）</strong> は AidUnite では<strong>使用禁止</strong>です。以下は移行比較のための体験用ボタンです。</p>
                    <div class="feedback-demo-actions feedback-demo-actions--legacy">
                        <button type="button" class="btn btn-secondary" onclick="demoShowOsAlert()">OS alert() を表示</button>
                        <button type="button" class="btn btn-secondary" onclick="demoShowOsConfirm()">OS confirm() を表示</button>
                    </div>
                    <p class="feedback-demo-note"><strong>❌ 本番禁止</strong> — OS / ブラウザ依存で Design Tokens と整合しません。結果通知は <code>showToastNotification</code>、確認は <code>showConfirmModal</code> を使用してください。</p>
                    <div class="code-snippet">
                        <div class="code-header">
                            <span>レガシー API（移行対象・新規禁止）</span>
                            <button class="btn-copy" onclick="copyCode('feedback-os-legacy-code')">コピー</button>
                        </div>
                        <pre id="feedback-os-legacy-code"><code>// ❌ 禁止
alert('保存しました');
if (confirm('削除しますか？')) { deleteItem(); }

// ✅ 正規
showToastNotification('保存しました', 'success');
showConfirmModal({
  title: '削除確認',
  message: '削除しますか？',
  confirmVariant: 'danger',
  onConfirm: () => deleteItem()
});</code></pre>
                    </div>
                </div>
            </div>