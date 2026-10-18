/**
 * Modules/Calendar/Assets/js/gantt-drag.js
 * Gantt Çizelgesi Sürükle-Bırak İstemci Mantığı
 */
window.handleGanttDragOver = function(e) {
    e.preventDefault();
    e.currentTarget.style.background = 'rgba(123, 104, 238, 0.2)';
};

window.handleGanttDragLeave = function(e) {
    e.currentTarget.style.background = '';
};

window.handleGanttDrop = function(e, taskId, targetDate) {
    e.preventDefault();
    e.currentTarget.style.background = '';
    console.log("Updating task deadline to", targetDate, "for task", taskId);
};
