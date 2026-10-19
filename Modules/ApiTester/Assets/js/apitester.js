/**
 * ApiTester Module Interactions
 */
window.ApiTester = {
    copyCurlCommand(selector) {
        const el = document.querySelector(selector);
        if (el) {
            navigator.clipboard.writeText(el.innerText);
            alert('cURL command copied to clipboard!');
        }
    },
    copyToken() {
        const textEl = document.getElementById('tokenText');
        const text = textEl ? textEl.innerText : '';
        const msg = (window.APITESTER_DOCS_I18N && window.APITESTER_DOCS_I18N.token_copied) ? window.APITESTER_DOCS_I18N.token_copied : 'API Token panoya kopyalandı!';
        navigator.clipboard.writeText(text).then(() => {
            if (typeof showToast === 'function') {
                showToast(msg, 'success');
            } else {
                alert(msg);
            }
        });
    }
};

function copyToken() {
    window.ApiTester.copyToken();
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnCopyApiToken');
    if (btn) {
        btn.addEventListener('click', () => {
            window.ApiTester.copyToken();
        });
    }
});
