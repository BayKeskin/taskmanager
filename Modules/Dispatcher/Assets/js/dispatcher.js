/**
 * Dispatcher Module Interactions
 */
window.Dispatcher = {
    async checkStatus() {
        const base = (window.BASE_URL || '').replace(/\/+$/, '');
        const res = await fetch(`${base}/ajax?action=check_dispatcher_status`);
        return await res.json();
    },
    async syncWorkspaces() {
        const base = (window.BASE_URL || '').replace(/\/+$/, '');
        const res = await fetch(`${base}/ajax?action=sync_workspaces`, { method: 'POST' });
        return await res.json();
    }
};
