/**
 * Board Module Client Interactions
 */
window.BoardModule = {
    initKanban() {
        console.log('[BoardModule] Kanban initialized.');
    },
    async updateTaskStatus(taskId, newStatus) {
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('status', newStatus);
        const res = await fetch('/taskmanager/ajax?action=update_task_status', {
            method: 'POST',
            body: formData
        });
        return await res.json();
    }
};
