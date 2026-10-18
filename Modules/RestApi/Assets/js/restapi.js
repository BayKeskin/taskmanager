/**
 * RestApi Client Interactions
 */
window.RestApi = {
    async fetchWithAuth(url, options = {}) {
        options.headers = options.headers || {};
        const token = localStorage.getItem('tm_api_token') || 'tm_admin_talha_keskin_token_2026';
        options.headers['Authorization'] = 'Bearer ' + token;
        options.headers['Content-Type'] = options.headers['Content-Type'] || 'application/json';
        const res = await fetch(url, options);
        return await res.json();
    }
};
