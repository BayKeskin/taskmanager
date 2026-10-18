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
    }
};
