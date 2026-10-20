/**
 * Modules/Calendar/Assets/js/calendar.js
 * Takvim ve Gantt Görünümü İstemci Mantığı (Event Delegation & Drag-Drop)
 */

let draggedGanttTaskId = null;
let draggedCalendarTaskId = null;

function handleGanttDragStart(e, taskId) {
    draggedGanttTaskId = taskId;
    if (e.dataTransfer) {
        e.dataTransfer.setData('text/plain', taskId);
        e.dataTransfer.effectAllowed = 'move';
    }
}

function handleGanttDragOver(e) {
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
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

function handleCalendarDragStart(e, taskId) {
    draggedCalendarTaskId = taskId;
    if (e.dataTransfer) {
        e.dataTransfer.setData('text/plain', taskId);
        e.dataTransfer.effectAllowed = 'move';
    }
}

function handleCalendarDragOver(e) {
    e.preventDefault();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
    }
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

    const base = (window.BASE_URL || '').replace(/\/+$/, '');
    fetch(`${base}/ajax/update_task_field`, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(res => {
        const successMsg = (window.CALENDAR_DATA && window.CALENDAR_DATA.lang && window.CALENDAR_DATA.lang.drag_success) 
            ? window.CALENDAR_DATA.lang.drag_success 
            : '';
        const failMsg = (window.CALENDAR_DATA && window.CALENDAR_DATA.lang && window.CALENDAR_DATA.lang.update_failed) 
            ? window.CALENDAR_DATA.lang.update_failed 
            : '';

        if (res.success) {
            if (typeof showToast === 'function') {
                showToast(successMsg + newDate, 'success');
            } else {
                alert(successMsg + newDate);
            }
            setTimeout(() => location.reload(), 400);
        } else {
            const errText = res.message || failMsg;
            if (typeof showToast === 'function') {
                showToast(errText, 'error');
            } else {
                alert(errText);
            }
        }
    })
    .catch(err => {
        const serverErr = (window.CALENDAR_DATA && window.CALENDAR_DATA.lang && window.CALENDAR_DATA.lang.server_error) 
            ? window.CALENDAR_DATA.lang.server_error 
            : '';
        alert(serverErr + err.message);
    });
}

// Event Delegation & Init Setup
document.addEventListener('DOMContentLoaded', function() {
    // 1. Dinamik Çubuk Konumlandırma & Renkleri data attribute'lardan CSS style'a uygula
    document.querySelectorAll('.gantt-task-bar').forEach(function(bar) {
        const left = bar.getAttribute('data-bar-left');
        const width = bar.getAttribute('data-bar-width');
        const bg = bar.getAttribute('data-bar-bg');
        if (left) bar.style.left = left + 'px';
        if (width) bar.style.width = width + 'px';
        if (bg) bar.style.background = bg;
    });

    // 2. Dinamik Kenarlık & Avatar Renkleri
    document.querySelectorAll('.cu-dynamic-border').forEach(function(el) {
        const borderLeft = el.getAttribute('data-border-left-color');
        if (borderLeft) el.style.borderLeftColor = borderLeft;
    });

    document.querySelectorAll('.cu-dynamic-color').forEach(function(el) {
        const col = el.getAttribute('data-color');
        if (col) el.style.color = col;
    });

    document.querySelectorAll('.cu-dynamic-bg').forEach(function(el) {
        const bg = el.getAttribute('data-bg');
        if (bg) el.style.background = bg;
    });

    // 3. Görev Çekmecesi Açma Tıklama Dinleyicileri (Click Delegation)
    document.addEventListener('click', function(e) {
        const taskTrigger = e.target.closest('[data-open-task-id]');
        if (taskTrigger) {
            const taskId = taskTrigger.getAttribute('data-open-task-id');
            if (taskId && typeof window.openTaskDrawer === 'function') {
                e.preventDefault();
                window.openTaskDrawer(taskId);
            }
        }
    });

    // 4. Gantt Drag & Drop Event Delegation
    document.addEventListener('dragstart', function(e) {
        const ganttBar = e.target.closest('.gantt-task-bar');
        if (ganttBar) {
            const taskId = ganttBar.getAttribute('data-task-id');
            handleGanttDragStart(e, taskId);
            return;
        }

        const calPill = e.target.closest('.cal-task-pill');
        if (calPill) {
            const taskId = calPill.getAttribute('data-task-id');
            handleCalendarDragStart(e, taskId);
            return;
        }
    });

    document.addEventListener('dragover', function(e) {
        const dropCell = e.target.closest('.gantt-drop-cell');
        if (dropCell) {
            handleGanttDragOver(e);
            return;
        }

        const calDay = e.target.closest('.cal-day-cell');
        if (calDay) {
            handleCalendarDragOver(e);
            return;
        }
    });

    document.addEventListener('dragleave', function(e) {
        const dropCell = e.target.closest('.gantt-drop-cell');
        if (dropCell) {
            handleGanttDragLeave(e);
            return;
        }

        const calDay = e.target.closest('.cal-day-cell');
        if (calDay) {
            handleCalendarDragLeave(e);
            return;
        }
    });

    document.addEventListener('drop', function(e) {
        const dropCell = e.target.closest('.gantt-drop-cell');
        if (dropCell) {
            const taskId = dropCell.getAttribute('data-task-id');
            const targetDate = dropCell.getAttribute('data-target-date');
            handleGanttDrop(e, taskId, targetDate);
            return;
        }

        const calDay = e.target.closest('.cal-day-cell');
        if (calDay) {
            const targetDate = calDay.getAttribute('data-date');
            handleCalendarDrop(e, targetDate);
            return;
        }
    });
});
