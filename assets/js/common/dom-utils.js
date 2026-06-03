/**
 * AidUnite DOM ユーティリティ（共通）
 * テスト環境では global.document を優先し、ブラウザでは document を返す。
 * 複数ファイルで重複していた getDocument() を1か所に集約。
 */
function getDocument() {
    if (typeof global !== 'undefined' && global.document) {
        return global.document;
    }
    return typeof document !== 'undefined' ? document : null;
}

if (typeof window !== 'undefined') {
    window.getDocument = getDocument;
}
if (typeof global !== 'undefined') {
    global.getDocument = getDocument;
}
