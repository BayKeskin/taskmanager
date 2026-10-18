// Modules/Team/Assets/js/team.js
window.TaskManagerTeam = {
    filterByRole: function(role) {
        document.querySelectorAll('.cu-member-card').forEach(el => {
            if (!role || role === 'all' || el.dataset.role === role) {
                el.style.display = 'flex';
            } else {
                el.style.display = 'none';
            }
        });
    }
};
