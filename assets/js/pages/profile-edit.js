/**
 * プロフィール編集ページ用JavaScript
 */

// getDocument は js/common/dom-utils.js で定義（共通 enqueue で先行読込）

document.addEventListener('DOMContentLoaded', function() {
    initializeProfileEdit();
});

function initializeProfileEdit() {
    // アバター関連の要素を取得
    const avatarOptions = document.querySelectorAll('.profile-avatar-option');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarEmoji = document.getElementById('avatarEmoji');
    const selectedEmojiInput = document.getElementById('selectedEmoji');

    // フォーム要素を取得
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const userBio = document.getElementById('user_bio');
    const profileForm = document.querySelector('form');

    // アバター絵文字選択機能
    if (avatarOptions.length > 0) {
        initializeAvatarSelection(avatarOptions, avatarPreview, avatarEmoji, selectedEmojiInput);
    }



    // パスワード確認機能
    if (newPassword && confirmPassword) {
        initializePasswordValidation(newPassword, confirmPassword);
    }

    // 文字数制限機能
    if (userBio) {
        initializeCharacterLimit(userBio, 500);
    }

    // フォーム送信前のバリデーション
    if (profileForm) {
        initializeFormValidation(profileForm);
    }

    // リアルタイムプレビュー機能
    initializeRealTimePreview();
}

/**
 * アバター絵文字選択機能を初期化
 */
function initializeAvatarSelection(avatarOptions, avatarPreview, avatarEmoji, selectedEmojiInput) {
    avatarOptions.forEach(option => {
        option.addEventListener('click', function() {
            // 選択状態を更新
            avatarOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');

            // プレビューを更新
            const emoji = this.dataset.emoji;
            avatarEmoji.textContent = emoji;
            selectedEmojiInput.value = emoji;

            // アニメーション効果
            avatarPreview.style.transform = 'scale(1.1)';
            setTimeout(() => {
                avatarPreview.style.transform = 'scale(1)';
            }, 200);
        });
    });
}



/**
 * パスワード確認機能を初期化
 */
function initializePasswordValidation(newPassword, confirmPassword) {
    function validatePassword() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('パスワードが一致しません');
                confirmPassword.style.borderColor = '#dc3545';
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#28a745';
            }
        } else {
            confirmPassword.setCustomValidity('');
            confirmPassword.style.borderColor = '#e9ecef';
        }
    }

    newPassword.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', validatePassword);

    // パスワード強度チェック
    newPassword.addEventListener('input', function() {
        const strength = checkPasswordStrength(this.value);
        updatePasswordStrengthIndicator(strength);
    });
}

/**
 * パスワード強度をチェック
 */
function checkPasswordStrength(password) {
    let score = 0;

    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score < 2) return 'weak';
    if (score < 4) return 'medium';
    return 'strong';
}

/**
 * パスワード強度インジケーターを更新
 */
function updatePasswordStrengthIndicator(strength) {
    const doc = getDocument();
    if (!doc) return;

    let existingIndicator = doc.getElementById('password-strength');
    if (!existingIndicator) {
        existingIndicator = doc.createElement('div');
        existingIndicator.id = 'password-strength';
        existingIndicator.style.marginTop = '0.5rem';
        existingIndicator.style.fontSize = '0.85rem';
        const passwordInput = doc.getElementById('new_password');
        if (passwordInput && passwordInput.parentNode) {
            passwordInput.parentNode.appendChild(existingIndicator);
        }
    }

    const messages = {
        weak: { text: '弱い', color: '#dc3545' },
        medium: { text: '普通', color: '#ffc107' },
        strong: { text: '強い', color: '#28a745' }
    };

    existingIndicator.textContent = `パスワード強度: ${messages[strength].text}`;
    existingIndicator.style.color = messages[strength].color;
}

/**
 * 文字数制限機能を初期化
 */
function initializeCharacterLimit(textarea, maxLength) {
    const doc = getDocument();
    if (!doc || !textarea) return;

    let existingCounter = doc.getElementById('character-counter');
    if (!existingCounter) {
        existingCounter = doc.createElement('div');
        existingCounter.id = 'character-counter';
        existingCounter.style.fontSize = '0.85rem';
        existingCounter.style.color = '#6c757d';
        existingCounter.style.marginTop = '0.25rem';
        if (textarea.parentNode) {
            textarea.parentNode.appendChild(existingCounter);
        }
    }

    function updateCounter() {
        const currentLength = textarea.value.length;
        const remaining = maxLength - currentLength;

        existingCounter.textContent = `${currentLength}/${maxLength} 文字`;

        if (remaining < 0) {
            existingCounter.style.color = '#dc3545';
            textarea.style.borderColor = '#dc3545';
        } else if (remaining < 50) {
            existingCounter.style.color = '#ffc107';
            textarea.style.borderColor = '#ffc107';
        } else {
            existingCounter.style.color = '#6c757d';
            textarea.style.borderColor = '#e9ecef';
        }
    }

    textarea.addEventListener('input', function() {
        if (this.value.length > maxLength) {
            this.value = this.value.substring(0, maxLength);
        }
        updateCounter();
    });

    // 初期表示
    updateCounter();
}

/**
 * フォームバリデーションを初期化
 */
function initializeFormValidation(form) {
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                isValid = false;
            } else {
                field.style.borderColor = '#e9ecef';
            }
        });

        if (!isValid) {
            e.preventDefault();
            showMessage('必須項目を入力してください。', 'error');
        }
    });
}

/**
 * リアルタイムプレビュー機能を初期化
 */
function initializeRealTimePreview() {
    const displayNameInput = document.getElementById('display_name');
    const displayNamePreview = document.getElementById('display-name-preview');

    if (displayNameInput && displayNamePreview) {
        displayNameInput.addEventListener('input', function() {
            displayNamePreview.textContent = this.value || '表示名';
        });
    }
}

/**
 * メッセージを表示
 */
function showMessage(message, type = 'info') {
    const doc = getDocument();
    if (!doc) return;

    const messageDiv = doc.createElement('div');
    messageDiv.className = `profile-message ${type}`;
    messageDiv.textContent = message;

    const container = doc.querySelector('.profile-edit-card');
    const header = doc.querySelector('.profile-edit-header');

    if (container && header) {
        container.insertBefore(messageDiv, header.nextSibling);
    }

    // 3秒後に自動削除
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

/**
 * フォームデータをリセット
 */
function resetForm() {
    const doc = getDocument();
    if (!doc) return;

    const form = doc.querySelector('form');
    if (form) {
        form.reset();

        // カスタムフィールドもリセット
        const avatarPreview = doc.getElementById('avatarPreview');
        const avatarEmoji = doc.getElementById('avatarEmoji');
        const selectedEmojiInput = doc.getElementById('selectedEmoji');

        if (avatarPreview && avatarEmoji && selectedEmojiInput) {
            avatarPreview.innerHTML = '<span id="avatarEmoji">👤</span>';
            selectedEmojiInput.value = '👤';
        }

        // パスワード強度インジケーターをリセット
        const strengthIndicator = doc.getElementById('password-strength');
        if (strengthIndicator) {
            strengthIndicator.remove();
        }

        // 文字数カウンターをリセット
        const characterCounter = doc.getElementById('character-counter');
        if (characterCounter) {
            characterCounter.textContent = '0/500 文字';
            characterCounter.style.color = '#6c757d';
        }
    }
}

// テスト用にグローバルに公開（モジュール読み込み時に確実に実行される）
if (typeof global !== 'undefined') {
    global.checkPasswordStrength = checkPasswordStrength;
    global.updatePasswordStrengthIndicator = updatePasswordStrengthIndicator;
    global.showMessage = showMessage;
    global.resetForm = resetForm;
    global.initializeCharacterLimit = initializeCharacterLimit;
    global.initializeProfileEdit = initializeProfileEdit;
    global.initializeAvatarSelection = initializeAvatarSelection;
    global.initializePasswordValidation = initializePasswordValidation;
    global.initializeFormValidation = initializeFormValidation;
    global.initializeRealTimePreview = initializeRealTimePreview;
}
