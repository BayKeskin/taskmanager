/**
 * Dispatcher Module Interactions
 */
window.Dispatcher = {
    async checkStatus() {
        const res = await fetch('/taskmanager/ajax?action=check_dispatcher_status');
        return await res.json();
    },
    async syncWorkspaces() {
        const res = await fetch('/taskmanager/ajax?action=sync_workspaces', { method: 'POST' });
        return await res.json();
    }
};
