// Modules/Docs/Assets/js/docs.js
window.TaskManagerDocs = {
    loadDoc: async function(docId) {
        const res = await fetch(`/taskmanager/api/docs/${docId}`);
        return await res.json();
    }
};
