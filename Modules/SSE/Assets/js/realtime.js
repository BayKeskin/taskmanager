// Modules/SSE/Assets/js/realtime.js

// 15. CANLI SENKRONİZASYON & SERVER-SENT EVENTS (SSE ENGINE)
// ==========================================================================
let sseSource = null;
let sseReconnectTimer = null;
let sseRetryCount = 0;

function initRealtimeSync() {
    const sI18n = window.SSE_I18N || {};
    if (!window.EventSource) {
        console.warn(sI18n.unsupported_browser || 'Server-Sent Events unsupported.');
        const badge = document.getElementById('cuRealtimeStatus');
        if (badge) badge.style.display = 'none';
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const activeProjId = urlParams.get('project_id');
    const base = window.BASE_URL || '';
    const sseEndpoint = base + '/api/sse' + (activeProjId ? '?project_id=' + encodeURIComponent(activeProjId) : '');

    try {
        if (sseSource) {
            sseSource.close();
        }

        sseSource = new EventSource(sseEndpoint);

        sseSource.addEventListener('connected', (e) => {
            sseRetryCount = 0;
            updateRealtimeBadge(true);
        });

        // 1. Görev Durumu Değişti (Kanban & Liste Senkronizasyonu)
        sseSource.addEventListener('task_status_changed', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskStatusChanged(data);
            } catch (err) {
                console.error('SSE task_status_changed parse error:', err);
            }
        });

        // 2. Görev Teslim Edildi
        sseSource.addEventListener('task_submitted', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskSubmitted(data);
            } catch (err) {
                console.error('SSE task_submitted parse error:', err);
            }
        });

        // 3. Yeni Görev Oluşturuldu
        sseSource.addEventListener('task_created', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskCreated(data);
            } catch (err) {
                console.error('SSE task_created parse error:', err);
            }
        });

        // 4. Yeni Yorum / Soru Eklendi
        sseSource.addEventListener('new_comment', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveNewComment(data);
            } catch (err) {
                console.error('SSE new_comment parse error:', err);
            }
        });

        // 5. Yeni Ekip Sohbeti Mesajı (Realtime Chat)
        sseSource.addEventListener('new_chat_message', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (typeof appendMessageToChat === 'function') {
                    appendMessageToChat(data, true);
                    if (typeof scrollToBottom === 'function') scrollToBottom();
                } else {
                    const fallbackUser = sI18n.team_member || 'Team Member';
                    const toastMsg = `💬 <b>${data.user_name || fallbackUser}</b>: ${data.message}`;
                    showToast(toastMsg, 'info');
                }
            } catch (err) {
                console.error('SSE new_chat_message parse error:', err);
            }
        });

        // 5.1 Yeni Birebir Özel Mesaj (Direct Message - DM)
        sseSource.addEventListener('new_direct_message', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (typeof window.handleIncomingDirectMessage === 'function') {
                    window.handleIncomingDirectMessage(data);
                } else if (typeof window.CURRENT_USER_ID !== 'undefined' && data.receiver_id == window.CURRENT_USER_ID) {
                    const sender = data.user_name || (sI18n.a_member || 'User');
                    const msgSnippet = (data.message || (sI18n.attached_file || '')).substring(0, 35);
                    const sentMsg = sI18n.sent_private_message || '';
                    const chatLinkText = sI18n.go_to_chat || '';
                    const base = window.BASE_URL || '';
                    const toastText = `🔒 <b>${sender}</b> ${sentMsg}: "${msgSnippet}" <a href="${base}/chat?user_id=${data.user_id}" style="color:#fff; text-decoration:underline; margin-left:6px;">${chatLinkText}</a>`;
                    showToast(toastText, 'info');
                }
            } catch (err) {
                console.error('SSE new_direct_message parse error:', err);
            }
        });

        // 6. Görev Silindi (Realtime Task Deleted)
        sseSource.addEventListener('task_deleted', (e) => {
            try {
                const data = JSON.parse(e.data);
                handleLiveTaskDeleted(data);
            } catch (err) {
                console.error('SSE task_deleted parse error:', err);
            }
        });

        // 7. Göreve Ek Dosya/Resim Yüklendi (Canlı Senkronizasyon)
        sseSource.addEventListener('task_attachment_added', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && data.attachment && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    currentTaskAttachments = currentTaskAttachments.filter(a => a.id != data.attachment.id);
                    currentTaskAttachments.unshift(data.attachment);
                    renderDrawerAttachments(currentTaskAttachments);
                    const attMsg = sI18n.new_attachment_added || '';
                    const toastText = `📎 ${attMsg}: "${data.attachment.file_name}"`;
                    showToast(toastText, 'info');
                }
            } catch (err) {
                console.error('SSE task_attachment_added parse error:', err);
            }
        });

        // 8. Görevden Ek Dosya/Resim Silindi (Canlı Senkronizasyon)
        sseSource.addEventListener('task_attachment_deleted', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && data.attachment_id && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    currentTaskAttachments = currentTaskAttachments.filter(a => a.id != data.attachment_id);
                    renderDrawerAttachments(currentTaskAttachments);
                }
            } catch (err) {
                console.error('SSE task_attachment_deleted parse error:', err);
            }
        });

        // 9. Görev Yorumu Güncellendi (Realtime Comment Updated)
        sseSource.addEventListener('comment_updated', (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data && typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == data.task_id) {
                    const textEl = document.getElementById(`commentText_${data.comment_id}`);
                    if (textEl) {
                        textEl.innerText = data.comment;
                    }
                    const editedBadge = document.getElementById(`commentEditedBadge_${data.comment_id}`);
                    if (editedBadge) {
                        editedBadge.style.display = 'inline';
                    }
                }
            } catch (err) {
                console.error('SSE comment_updated parse error:', err);
            }
        });

        sseSource.onopen = () => {
            sseRetryCount = 0;
            updateRealtimeBadge(true);
        };

        sseSource.onerror = () => {
            updateRealtimeBadge(false);
            if (sseSource.readyState === EventSource.CLOSED) {
                clearTimeout(sseReconnectTimer);
                const delay = Math.min(30000, 2000 * Math.pow(1.5, sseRetryCount++));
                sseReconnectTimer = setTimeout(() => {
                    initRealtimeSync();
                }, delay);
            }
        };

    } catch (err) {
        console.error('SSE connection error:', err);
        updateRealtimeBadge(false);
    }
}

function updateRealtimeBadge(isConnected) {
    const sI18n = window.SSE_I18N || {};
    const badge = document.getElementById('cuRealtimeStatus');
    const text = document.getElementById('cuRealtimeText');
    if (!badge) return;

    const titleActive = badge.dataset.titleActive || (sI18n.status_active || 'Live Sync Active');
    const titleReconnecting = badge.dataset.titleReconnecting || (sI18n.status_reconnecting || 'Reconnecting...');
    const textLive = badge.dataset.textLive || (sI18n.text_live || 'Live');
    const textConnecting = badge.dataset.textConnecting || (sI18n.text_connecting || 'Connecting');

    if (isConnected) {
        badge.classList.remove('disconnected');
        badge.title = titleActive;
        if (text) text.innerText = textLive;
    } else {
        badge.classList.add('disconnected');
        badge.title = titleReconnecting;
        if (text) text.innerText = textConnecting;
    }
}

// Canlı Görev Durumu Değişimi İşleyicisi
function handleLiveTaskStatusChanged(data) {
    const sI18n = window.SSE_I18N || {};
    const { task_id, task_code, title, old_status, new_status, user_name } = data;

    // A) Kanban Panosunda Kartı Taşı
    const card = document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`);
    if (card) {
        const targetCol = document.querySelector(`.cu-board-cards[data-status="${new_status}"]`);
        if (targetCol && card.parentElement !== targetCol) {
            targetCol.prepend(card);
            card.classList.add('live-pulse-anim');
            setTimeout(() => card.classList.remove('live-pulse-anim'), 1500);
            updateBoardBadges();
        }
    }

    // B) Liste Görünümünde (tasks / index.php) Satırı Taşı
    const taskContainer = document.getElementById(`task-container-${task_id}`);
    if (taskContainer) {
        const targetList = document.getElementById(`taskList-${new_status}`);
        if (targetList && taskContainer.parentElement !== targetList) {
            targetList.prepend(taskContainer);
            taskContainer.classList.add('live-pulse-anim');
            setTimeout(() => taskContainer.classList.remove('live-pulse-anim'), 1500);
            updateListGroupCounts();
        }
    }

    // C) Eğer Task Drawer Açık ve Bu Göreve Aitse Durumu Güncelle
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == task_id) {
        const badge = document.getElementById('drawerStatusBadge');
        if (badge && typeof statusBadgeConfig !== 'undefined') {
            const cfg = statusBadgeConfig[new_status] || { label: (new_status || '').toUpperCase(), color: '#7B68EE' };
            badge.innerHTML = `<span class="cu-status-pill" style="background:${cfg.color};">${cfg.label}</span>`;
        }
    }

    // D) Canlı Bildirim Toast Göster
    const taskLabel = task_code || (sI18n.task || 'Task');
    const teamLabel = user_name || (sI18n.team || 'Team');
    const statusMsg = `⚡ <b>${taskLabel}</b>: ${(new_status || '').toUpperCase()} (${teamLabel})`;
    showToast(statusMsg, 'info');
}

// Canlı Görev Teslimatı
function handleLiveTaskSubmitted(data) {
    const sI18n = window.SSE_I18N || {};
    const { task_id, task_code, title, user_name, notes } = data;
    handleLiveTaskStatusChanged({ ...data, new_status: 'submitted' });
    const taskLabel = task_code || (sI18n.task || 'Task');
    const submitMsg = sI18n.task_submitted_msg || '';
    const noteSnippet = (notes || '').substring(0, 40);
    const submittedToast = `🚀 <b>${taskLabel}</b> ${submitMsg} "${noteSnippet}"`;
    showToast(submittedToast, 'success');
}

// Canlı Görev Silinmesi (Tüm açık ekranlardan anında kaldır)
function handleLiveTaskDeleted(data) {
    const taskId = data.task_id;
    if (!taskId) return;

    // 1. Kanban panosundaki kartı animasyonla sil
    const card = document.querySelector(`.cu-board-card[data-task-id="${taskId}"], .cu-card[data-task-id="${taskId}"], #task-card-${taskId}`);
    if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.85)';
        setTimeout(() => {
            card.remove();
            if (typeof updateBoardBadges === 'function') updateBoardBadges();
        }, 300);
    }

    // 2. Liste görünümündeki satırı sil
    const taskContainer = document.getElementById(`task-container-${taskId}`);
    if (taskContainer) {
        taskContainer.style.transition = 'all 0.3s ease';
        taskContainer.style.opacity = '0';
        setTimeout(() => {
            taskContainer.remove();
            if (typeof updateListGroupCounts === 'function') updateListGroupCounts();
        }, 300);
    }

    // 3. Çekmece veya modal bu göreve aitse kapat
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == taskId) {
        if (typeof closeTaskDrawer === 'function') closeTaskDrawer();
    }
    const modal = document.getElementById('modalTaskDetail');
    if (modal && modal.style.display !== 'none' && window.currentOpenTaskId === taskId) {
        if (typeof closeTaskModal === 'function') closeTaskModal();
    }

    const sI18n = window.SSE_I18N || {};
    const delMsg = `🗑️ <b>${data.task_code || (sI18n.task || 'Task')}</b> ${sI18n.task_deleted_msg || ''}`;
    showToast(delMsg, 'warning');
}

// ==========================================================================

// Canlı Yeni Görev (Kanban & Liste Panolarına Anlık Kart Ekleme)
function handleLiveTaskCreated(data) {
    const sI18n = window.SSE_I18N || {};
    const { task_id, task_code, title, user_name } = data;
    const createdMsg = `➕ <b>${task_code || (sI18n.new_task || 'Task')}</b> ${sI18n.task_created_msg || ''}: "${(title || '').substring(0, 35)}" (${user_name || 'Admin'})`;
    showToast(createdMsg, 'info');

    if (!task_id) return;
    if (document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`)) return;

    const urlParams = new URLSearchParams(window.location.search);
    const activeProjId = urlParams.get('project_id');
    const base = window.BASE_URL || '';

    fetch(`${base}/ajax?action=get_task_card_html&task_id=${task_id}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.task) {
                // Aktif proje filtresi varsa ve görev başka bir projeye aitse panoya ekleme
                if (activeProjId && res.task.project_id != activeProjId) {
                    return;
                }

                // 1. Kanban Board üzerinde 'assigned' sütununa ekle
                const assignedCol = document.querySelector('.cu-board-cards[data-status="assigned"]');
                if (assignedCol && res.card_html) {
                    assignedCol.insertAdjacentHTML('afterbegin', res.card_html);
                    updateBoardBadges();
                }

                // 2. Liste Görünümünde (tasks) 'taskList-assigned' içine ekle
                const assignedList = document.getElementById('taskList-assigned');
                if (assignedList && res.row_html) {
                    const emptyMsg = assignedList.querySelector('.cu-empty-group-msg');
                    if (emptyMsg) emptyMsg.style.display = 'none';
                    assignedList.insertAdjacentHTML('afterbegin', res.row_html);
                    updateListGroupCounts();
                }
            }
        })
        .catch(err => console.error('Live task card fetch error:', err));
}

// Canlı Yeni Yorum (Drawer Açık İse Yorumları Anında Yenile)
function handleLiveNewComment(data) {
    const sI18n = window.SSE_I18N || {};
    const { task_id, task_code, author, comment } = data;
    const commentMsg = `💬 <b>${author}</b> (${task_code || (sI18n.task || 'Task')}): "${(comment || '').substring(0, 45)}"`;
    showToast(commentMsg, 'info');

    // Panodaki kartı da görsel olarak dalgalanma efektiyle vurgula
    const boardCard = document.querySelector(`.cu-board-card[data-task-id="${task_id}"]`);
    if (boardCard) {
        boardCard.classList.add('live-pulse-anim');
        setTimeout(() => boardCard.classList.remove('live-pulse-anim'), 1500);
    }

    // Eğer Task Drawer açık ve bu göreve aitse yorumları ve aktiviteyi anında yenile
    if (typeof currentDrawerTaskId !== 'undefined' && currentDrawerTaskId == task_id) {
        const base = window.BASE_URL || '';
        fetch(`${base}/ajax?action=get_task_detail&id=${task_id}`)
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (res.comments) {
                        renderDrawerComments(res.comments);
                    }
                    if (res.activities) {
                        renderDrawerTimeline(res.activities);
                    }
                }
            }).catch(err => console.error('Live comment drawer refresh error:', err));
    }
}

// Liste Görünümü Grup Sayaçlarını Güncelle
function updateListGroupCounts() {
    const sI18n = window.SSE_I18N || {};
    document.querySelectorAll('.cu-group').forEach(group => {
        const countSpan = group.querySelector('.cu-group-count');
        const rows = group.querySelectorAll('.cu-task-container');
        if (countSpan) {
            countSpan.innerText = `${rows.length} ${sI18n.tasks_suffix || 'tasks'}`;
        }
    });
}

// Sayfa yüklendiğinde Realtime SSE başlat
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRealtimeSync);
} else {
    initRealtimeSync();
}

// ==========================================================================
