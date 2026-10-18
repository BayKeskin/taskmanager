// Modules/Mcp/Assets/js/mcp.js
window.TaskManagerMcp = {
    saveConfig: async function(projectId, catalogId, isEnabled, envValues) {
        const fd = new FormData();
        fd.append('project_id', projectId);
        fd.append('mcp_catalog_id', catalogId);
        fd.append('is_enabled', isEnabled ? '1' : '0');
        fd.append('env_values', JSON.stringify(envValues));
        const res = await fetch('/taskmanager/ajax?action=save_project_mcp', {
            method: 'POST',
            body: fd
        });
        return await res.json();
    }
};
