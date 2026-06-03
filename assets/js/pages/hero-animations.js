/**
 * Ainy ヒーローセクション マイクロアニメーション
 * 「Ainyのサービスの町」を表現する統一感のある世界観
 */

class AinyHeroAnimation {
    constructor() {
        console.log('AinyHeroAnimation: 初期化開始');
        this.isInitialized = false;
        this.init();
    }

    init() {
        if (this.isInitialized) {
            console.log('AinyHeroAnimation: 既に初期化済み');
            return;
        }

        console.log('AinyHeroAnimation: 初期化中...');

        // ヒーローエリアの準備
        this.prepareHeroArea();

        // アニメーション要素の作成
        this.createTownElements();

        // 順次表示アニメーションの開始
        this.startSequentialAnimation();

        this.isInitialized = true;
        console.log('TUNAGERUHeroAnimation: 初期化完了');
    }

    prepareHeroArea() {
        console.log('TUNAGERUHeroAnimation: ヒーローエリア準備中...');
        const heroVisual = document.querySelector('.aidunite-hero-visual');
        if (!heroVisual) {
            console.error('TUNAGERUHeroAnimation: ヒーロービジュアルエリアが見つかりません');
            return;
        }
        console.log('TUNAGERUHeroAnimation: ヒーロービジュアルエリア発見');

        // 既存のアニメーションコンテナを削除
        const existingContainer = heroVisual.querySelector('.tunageru-town-container');
        if (existingContainer) {
            existingContainer.remove();
            console.log('TUNAGERUHeroAnimation: 既存のアニメーションコンテナを削除');
        }

        // 新しい町のコンテナを作成
        const townContainer = document.createElement('div');
        townContainer.className = 'tunageru-town-container';
        townContainer.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            opacity: 0.9;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        townContainer.innerHTML = this.createTownSVG();
        heroVisual.appendChild(townContainer);
        console.log('TUNAGERUHeroAnimation: 町のコンテナを追加');
    }

    createTownSVG() {
        console.log('TUNAGERUHeroAnimation: SVG作成中...');
        return `
            <svg class="tunageru-town" viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg">
                <!-- 背景の町並み -->
                <defs>
                    <linearGradient id="buildingGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:#6a5af9;stop-opacity:0.1" />
                        <stop offset="100%" style="stop-color:#b16cea;stop-opacity:0.05" />
                    </linearGradient>
                    <filter id="glow">
                        <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>

                <!-- 背景の建物群 -->
                <g class="background-buildings" opacity="0">
                    <rect x="50" y="200" width="60" height="150" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="120" y="180" width="50" height="170" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="180" y="220" width="70" height="130" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="260" y="190" width="55" height="160" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="325" y="210" width="65" height="140" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="400" y="180" width="60" height="170" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="470" y="200" width="55" height="150" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="535" y="190" width="70" height="160" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="615" y="220" width="50" height="130" fill="url(#buildingGradient)" rx="5"/>
                    <rect x="675" y="200" width="65" height="150" fill="url(#buildingGradient)" rx="5"/>
                </g>

                <!-- 1. スケジュール管理センター -->
                <g class="schedule-center" opacity="0">
                    <rect x="100" y="150" width="80" height="60" fill="#6a5af9" opacity="0.8" rx="8"/>
                    <text x="140" y="175" text-anchor="middle" fill="white" font-size="8" font-weight="bold">SCHEDULE</text>
                    <!-- カレンダーアイコン -->
                    <rect x="115" y="160" width="15" height="12" fill="white" opacity="0.9" rx="2"/>
                    <rect x="117" y="162" width="11" height="8" fill="#6a5af9" rx="1"/>
                    <circle cx="122" cy="166" r="1" fill="white"/>
                    <circle cx="126" cy="166" r="1" fill="white"/>
                    <circle cx="130" cy="166" r="1" fill="white"/>
                    <!-- アニメーション：スケジュール登録の動き -->
                    <g class="schedule-animation">
                        <rect x="125" y="175" width="8" height="2" fill="white" opacity="0.8">
                            <animate attributeName="opacity" values="0.8; 1; 0.8" dur="2s" repeatCount="indefinite"/>
                        </rect>
                        <rect x="125" y="178" width="6" height="2" fill="white" opacity="0.6">
                            <animate attributeName="opacity" values="0.6; 1; 0.6" dur="2s" repeatCount="indefinite" begin="0.5s"/>
                        </rect>
                    </g>
                </g>

                <!-- 2. マッチングセンター -->
                <g class="matching-center" opacity="0">
                    <rect x="300" y="140" width="90" height="70" fill="#b16cea" opacity="0.8" rx="8"/>
                    <text x="345" y="165" text-anchor="middle" fill="white" font-size="8" font-weight="bold">MATCHING</text>
                    <!-- スマートフォン -->
                    <rect x="315" y="150" width="20" height="30" fill="#333" rx="3"/>
                    <rect x="317" y="152" width="16" height="26" fill="#b16cea" rx="2"/>
                    <!-- マッチングアイコン -->
                    <g class="matching-animation">
                        <circle cx="325" cy="160" r="2" fill="white">
                            <animate attributeName="opacity" values="0.5; 1; 0.5" dur="1.5s" repeatCount="indefinite"/>
                        </circle>
                        <circle cx="330" cy="160" r="2" fill="white">
                            <animate attributeName="opacity" values="0.5; 1; 0.5" dur="1.5s" repeatCount="indefinite" begin="0.5s"/>
                        </circle>
                        <circle cx="335" cy="160" r="2" fill="white">
                            <animate attributeName="opacity" values="0.5; 1; 0.5" dur="1.5s" repeatCount="indefinite" begin="1s"/>
                        </circle>
                        <!-- 成功マーク -->
                        <g class="success-mark" opacity="0">
                            <circle cx="325" cy="175" r="4" fill="#4CAF50"/>
                            <path d="M322 175 L324 177 L328 173" stroke="white" stroke-width="1" fill="none">
                                <animate attributeName="stroke-dasharray" values="0 10; 10 0" dur="0.5s" begin="2s" fill="freeze"/>
                            </path>
                            <animate attributeName="opacity" values="0; 1" dur="0.5s" begin="2s" fill="freeze"/>
                        </g>
                    </g>
                </g>

                <!-- 3. サポートセンター -->
                <g class="support-center" opacity="0">
                    <rect x="500" y="160" width="85" height="50" fill="#ff6b6b" opacity="0.8" rx="8"/>
                    <text x="542" y="180" text-anchor="middle" fill="white" font-size="8" font-weight="bold">SUPPORT</text>
                    <!-- ハートアイコン -->
                    <path d="M530 170 Q525 165 520 170 Q520 175 530 180 Q540 175 540 170 Q535 165 530 170" fill="white" opacity="0.9">
                        <animate attributeName="opacity" values="0.9; 1; 0.9" dur="1.5s" repeatCount="indefinite"/>
                        <animateTransform attributeName="transform" type="scale" values="1; 1.1; 1" dur="1.5s" repeatCount="indefinite"/>
                    </path>
                </g>

                <!-- 4. チーム間の繋がり（道路） -->
                <g class="connections" opacity="0">
                    <!-- スケジュール → マッチング -->
                    <path d="M180 180 Q240 160 300 175" stroke="#ffd700" stroke-width="3" fill="none" opacity="0.6">
                        <animate attributeName="stroke-dasharray" values="0 200; 200 0" dur="3s" repeatCount="indefinite"/>
                    </path>
                    <!-- マッチング → サポート -->
                    <path d="M390 175 Q440 160 500 185" stroke="#ffd700" stroke-width="3" fill="none" opacity="0.6">
                        <animate attributeName="stroke-dasharray" values="0 200; 200 0" dur="3s" repeatCount="indefinite" begin="1s"/>
                    </path>
                    <!-- サポート → スケジュール -->
                    <path d="M500 200 Q400 220 180 210" stroke="#ffd700" stroke-width="3" fill="none" opacity="0.6">
                        <animate attributeName="stroke-dasharray" values="0 200; 200 0" dur="3s" repeatCount="indefinite" begin="2s"/>
                    </path>
                </g>

                <!-- 5. 動く人々 -->
                <g class="moving-people" opacity="0">
                    <!-- 人1（スケジュール登録） -->
                    <g class="person-1">
                        <circle cx="80" cy="220" r="6" fill="#6a5af9"/>
                        <rect x="77" y="226" width="6" height="12" fill="#6a5af9"/>
                        <rect x="83" y="220" width="8" height="4" fill="#6a5af9" rx="2">
                            <animate attributeName="transform" type="rotate" values="0 87 222; 10 87 222; 0 87 222" dur="2s" repeatCount="indefinite"/>
                        </rect>
                    </g>
                    <!-- 人2（マッチング確認） -->
                    <g class="person-2">
                        <circle cx="280" cy="210" r="6" fill="#b16cea"/>
                        <rect x="277" y="216" width="6" height="12" fill="#b16cea"/>
                        <rect x="283" y="210" width="8" height="4" fill="#b16cea" rx="2">
                            <animate attributeName="transform" type="rotate" values="0 287 212; -10 287 212; 0 287 212" dur="2s" repeatCount="indefinite"/>
                        </rect>
                    </g>
                    <!-- 人3（サポート受ける） -->
                    <g class="person-3">
                        <circle cx="480" cy="220" r="6" fill="#ff6b6b"/>
                        <rect x="477" y="226" width="6" height="12" fill="#ff6b6b"/>
                        <rect x="483" y="220" width="8" height="4" fill="#ff6b6b" rx="2">
                            <animate attributeName="transform" type="rotate" values="0 487 222; 15 487 222; 0 487 222" dur="2s" repeatCount="indefinite"/>
                        </rect>
                    </g>
                </g>

                <!-- 6. スポーツ要素 -->
                <g class="sports-elements" opacity="0">
                    <!-- バスケットボール -->
                    <g class="basketball">
                        <circle cx="200" cy="280" r="8" fill="#ff8c00" opacity="0.9">
                            <animate attributeName="cy" values="280; 260; 280" dur="2s" repeatCount="indefinite"/>
                        </circle>
                        <path d="M192 280 Q200 270 208 280" stroke="#333" stroke-width="1" fill="none" opacity="0.7">
                            <animate attributeName="opacity" values="0.7; 1; 0.7" dur="2s" repeatCount="indefinite"/>
                        </path>
                    </g>
                    <!-- サッカーボール -->
                    <g class="soccer-ball">
                        <circle cx="400" cy="290" r="6" fill="white" opacity="0.9">
                            <animate attributeName="cy" values="290; 270; 290" dur="2.5s" repeatCount="indefinite"/>
                        </circle>
                        <path d="M394 290 Q400 285 406 290" stroke="#333" stroke-width="1" fill="none" opacity="0.7">
                            <animate attributeName="opacity" values="0.7; 1; 0.7" dur="2.5s" repeatCount="indefinite"/>
                        </path>
                    </g>
                </g>

                <!-- 7. データフロー（光の粒子） -->
                <g class="data-flow" opacity="0">
                    <circle cx="150" cy="120" r="2" fill="#ffd700" opacity="0.8">
                        <animate attributeName="cx" values="150; 300; 500; 150" dur="4s" repeatCount="indefinite"/>
                        <animate attributeName="cy" values="120; 100; 120; 120" dur="4s" repeatCount="indefinite"/>
                    </circle>
                    <circle cx="350" cy="110" r="2" fill="#ffd700" opacity="0.8">
                        <animate attributeName="cx" values="350; 500; 150; 350" dur="4s" repeatCount="indefinite" begin="1s"/>
                        <animate attributeName="cy" values="110; 130; 110; 110" dur="4s" repeatCount="indefinite" begin="1s"/>
                    </circle>
                    <circle cx="550" cy="130" r="2" fill="#ffd700" opacity="0.8">
                        <animate attributeName="cx" values="550; 150; 300; 550" dur="4s" repeatCount="indefinite" begin="2s"/>
                        <animate attributeName="cy" values="130; 110; 130; 130" dur="4s" repeatCount="indefinite" begin="2s"/>
                    </circle>
                </g>

                <!-- 8. 町の活気（星や光） -->
                <g class="town-vibrancy" opacity="0">
                    <g class="stars">
                        <path d="M100 80 L102 85 L107 85 L103 88 L105 93 L100 90 L95 93 L97 88 L93 85 L98 85 Z" fill="#ffd700" opacity="0">
                            <animate attributeName="opacity" values="0; 1; 0" dur="3s" repeatCount="indefinite"/>
                            <animateTransform attributeName="transform" type="scale" values="0; 1; 0" dur="3s" repeatCount="indefinite"/>
                        </path>
                        <path d="M300 70 L302 75 L307 75 L303 78 L305 83 L300 80 L295 83 L297 78 L293 75 L298 75 Z" fill="#ffd700" opacity="0">
                            <animate attributeName="opacity" values="0; 1; 0" dur="3s" repeatCount="indefinite" begin="1s"/>
                            <animateTransform attributeName="transform" type="scale" values="0; 1; 0" dur="3s" repeatCount="indefinite" begin="1s"/>
                        </path>
                        <path d="M500 85 L502 90 L507 90 L503 93 L505 98 L500 95 L495 98 L497 93 L493 90 L498 90 Z" fill="#ffd700" opacity="0">
                            <animate attributeName="opacity" values="0; 1; 0" dur="3s" repeatCount="indefinite" begin="2s"/>
                            <animateTransform attributeName="transform" type="scale" values="0; 1; 0" dur="3s" repeatCount="indefinite" begin="2s"/>
                        </path>
                    </g>
                </g>
            </svg>
        `;
    }

    startSequentialAnimation() {
        console.log('TUNAGERUHeroAnimation: 順次アニメーション開始');
        const elements = [
            { selector: '.background-buildings', delay: 0, duration: 1000 },
            { selector: '.schedule-center', delay: 500, duration: 800 },
            { selector: '.matching-center', delay: 1000, duration: 800 },
            { selector: '.support-center', delay: 1500, duration: 800 },
            { selector: '.connections', delay: 2000, duration: 1000 },
            { selector: '.moving-people', delay: 2500, duration: 800 },
            { selector: '.sports-elements', delay: 3000, duration: 800 },
            { selector: '.data-flow', delay: 3500, duration: 800 },
            { selector: '.town-vibrancy', delay: 4000, duration: 800 }
        ];

        elements.forEach((element, index) => {
            setTimeout(() => {
                console.log(`TUNAGERUHeroAnimation: ${element.selector} アニメーション開始`);
                this.animateElement(element.selector, element.duration);
            }, element.delay);
        });
    }

    animateElement(selector, duration) {
        const element = document.querySelector(selector);
        if (!element) {
            console.error(`TUNAGERUHeroAnimation: ${selector} 要素が見つかりません`);
            return;
        }

        console.log(`TUNAGERUHeroAnimation: ${selector} 要素をアニメーション中...`);

        // フェードインアニメーション
        element.style.transition = `opacity ${duration}ms ease-out`;
        element.style.opacity = '1';

        // 個別のアニメーション効果
        if (selector === '.background-buildings') {
            this.animateBuildings();
        } else if (selector === '.connections') {
            this.animateConnections();
        }
    }

    animateBuildings() {
        console.log('TUNAGERUHeroAnimation: 建物アニメーション開始');
        const buildings = document.querySelectorAll('.background-buildings rect');
        buildings.forEach((building, index) => {
            setTimeout(() => {
                building.style.transition = 'opacity 0.5s ease-out';
                building.style.opacity = '1';
            }, index * 100);
        });
    }

    animateConnections() {
        console.log('TUNAGERUHeroAnimation: 接続アニメーション開始');
        const connections = document.querySelectorAll('.connections path');
        connections.forEach((connection, index) => {
            setTimeout(() => {
                connection.style.transition = 'opacity 0.8s ease-out';
                connection.style.opacity = '1';
            }, index * 200);
        });
    }
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    console.log('AinyHeroAnimation: DOMContentLoaded イベント発火');
    // 少し遅延させてからアニメーション開始
    setTimeout(() => {
        console.log('AinyHeroAnimation: アニメーション初期化開始');
        new AinyHeroAnimation();
    }, 500);
});
