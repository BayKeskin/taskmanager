// Modules/Projects/Assets/js/projects.js
window.TaskManagerProjects = {
    openSettings: function(projectId) {
        window.location.href = `/taskmanager/settings?project_id=${projectId}`;
    }
};
