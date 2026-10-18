let draggedGanttTaskId = null;

function handleGanttDragStart(e, taskId) {
    draggedGanttTaskId = taskId;
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed = 'move';
}

function handleGanttDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-hover');
}

function handleGanttDragLeave(e) {
    e.currentTarget.classList.remove('drag-hover');
}

function handleGanttDrop(e, taskId, newDate) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-hover');
    const tId = draggedGanttTaskId || taskId;
    if (!tId || !newDate) return;

    updateTaskDeadlineAjax(tId, newDate);
}

// Standart Takvim Drag-Drop
let draggedCalendarTaskId = null;

function handleCalendarDragStart(e, taskId) {
    draggedCalendarTaskId = taskId;
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed = 'move';
}

function handleCalendarDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-hover');
}

function handleCalendarDragLeave(e) {
    e.currentTarget.classList.remove('drag-hover');
}

function handleCalendarDrop(e, newDate) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-hover');
    if (!draggedCalendarTaskId || !newDate) return;

    updateTaskDeadlineAjax(draggedCalendarTaskId, newDate);
}

// Ortak AJAX Teslim Tarihi Güncelleyici
function updateTaskDeadlineAjax(taskId, newDate) {
    const fd = new FormData();
    fd.append('action', 'update_task_field');
    fd.append('task_id', taskId);
    fd.append('field', 'deadline');
    fd.append('value', newDate);

    fetch('/taskmanager/ajax/update_task_field', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (typeof showToast === 'function') {
                showToast('<?= __('tasks.gantt_drag_success') ?>' + newDate, 'success');
            } else {
                alert('<?= __('tasks.gantt_drag_success') ?>' + newDate);
            }
            setTimeout(() => location.reload(), 400);
        } else {
            alert('❌ ' + (res.message || 'Tarih güncellenemedi.'));
        }
    })
    .catch(err => alert('Sunucu hatası: ' + err.message));
}
