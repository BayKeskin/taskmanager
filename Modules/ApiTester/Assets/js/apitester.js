/**
 * ApiTester Module Interactions
 */
window.ApiTester = {
    copyCurlCommand(selector) {
        const el = document.querySelector(selector);
        if (el) {
            navigator.clipboard.writeText(el.innerText);
            const msg = window.APITESTER_DOCS_I18N?.curl_copied || '';
            if (msg) {
                if (typeof showToast === 'function') showToast(msg, 'success');
                else alert(msg);
            }
        }
    },
    copyToken() {
        const textEl = document.getElementById('tokenText');
        const text = textEl ? textEl.innerText : '';
        const msg = window.APITESTER_DOCS_I18N?.token_copied || '';
        navigator.clipboard.writeText(text).then(() => {
            if (msg) {
                if (typeof showToast === 'function') {
                    showToast(msg, 'success');
                } else {
                    alert(msg);
                }
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
