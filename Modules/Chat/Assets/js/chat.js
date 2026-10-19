window.CURRENT_USER_ID = (window.CHAT_APP_DATA && window.CHAT_APP_DATA.currentUser) ? window.CHAT_APP_DATA.currentUser.id : 1;
window.IS_CLIENT_ROLE = (window.CHAT_APP_DATA && window.CHAT_APP_DATA.currentUser) ? window.CHAT_APP_DATA.currentUser.isClient : false;
window.I18N_CHAT = (window.CHAT_APP_DATA && window.CHAT_APP_DATA.i18n) ? window.CHAT_APP_DATA.i18n : {};

const chatBaseUrl = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL.replace(/\/+$/, '') : '';
const currentUserId = window.CURRENT_USER_ID;

let activeDirectUserId = (typeof window.INITIAL_TARGET_USER_ID !== 'undefined' && window.INITIAL_TARGET_USER_ID > 0) ? window.INITIAL_TARGET_USER_ID : null;
let activeDirectUserObj = null;
let lastChatMsgId = 0;
let lastRenderedDate = '';
let selectedChatFile = null;

const messagesContainer = document.getElementById('chatMessagesContainer');
const chatInput = document.getElementById('chatInput');
const btnSend = document.getElementById('btnSendChat');
const chatStatusText = document.getElementById('chatStatusText');
const btnScrollBottom = document.getElementById('btnScrollBottom');

// 1. Konuşmalar Listesini Çek (Sol Panel)
async function loadConversations() {
    try {
        const res = await fetch(`${chatBaseUrl}/ajax?action=get_chat_conversations`);
        const data = await res.json();
        if (data.success && data.conversations) {
            renderConversationsList(data.conversations);
            const totalDmBadge = document.getElementById('totalDmBadge');
            if (totalDmBadge) {
                if (data.total_unread > 0) {
                    totalDmBadge.innerText = data.total_unread;
                    totalDmBadge.style.display = 'inline-block';
                } else {
                    totalDmBadge.style.display = 'none';
                }
            }
        }
    } catch(e) {
        console.error('loadConversations error:', e);
    }
}

// Konuşmalar Listesini Render Et
function renderConversationsList(conversations) {
    const list = document.getElementById('dmMembersList');
    if (!list) return;
    list.innerHTML = '';

    if (!conversations || conversations.length === 0) {
        list.innerHTML = `<div style="text-align:center; padding:16px; font-size:11px; color:var(--text-muted);">${window.I18N_CHAT.no_other_members}</div>`;
        return;
    }

    conversations.forEach(c => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = `dm-btn-${c.id}`;
        btn.className = `cu-chat-member-btn ${activeDirectUserId === c.id ? 'active' : ''}`;
        btn.setAttribute('data-name', (c.name || '').toLowerCase());
        btn.setAttribute('data-title', (c.title || '').toLowerCase());
        btn.onclick = () => selectDirectChat(c.id, c.name, c.avatar_color, c.title);

        const initial = (c.name || 'U').substring(0, 1).toUpperCase();
        const unreadHtml = c.unread_count > 0 ? `<span class="cu-unread-badge">${c.unread_count}</span>` : '';
        const youPrefix = (window.I18N_CHAT?.you_label || '(You)') + ': ';
        const snippet = c.last_message ? `<div style="font-size:10px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;">${c.last_message_by_me ? youPrefix : ''}${escapeHtml(c.last_message)}</div>` : '';

        btn.innerHTML = `
            <div style="position:relative; flex-shrink:0;">
                <div class="cu-avatar" style="width:30px; height:30px; font-size:11px; font-weight:700; background:${c.avatar_color || '#7B68EE'};">
                    ${initial}
                </div>
                <span style="position:absolute; bottom:-1px; right:-1px; width:8px; height:8px; border-radius:50%; background:#00b894; border:2px solid var(--bg-card);" title="${escapeHtml(window.I18N_CHAT?.online || 'Online')}"></span>
            </div>
            <div style="overflow:hidden; flex:1; min-width:0;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:12.5px; font-weight:600; color:var(--text-white); white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                        ${escapeHtml(c.name)}
                    </div>
                    ${unreadHtml}
                </div>
                <div style="font-size:10.5px; color:var(--text-muted); white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                    ${escapeHtml(c.title || c.role || window.I18N_CHAT.default_role || '')}
                </div>
                ${snippet}
            </div>
        `;
        list.appendChild(btn);

        if (activeDirectUserId === c.id) {
            activeDirectUserObj = c;
        }
    });
}

// 2. Genel Sohbeti Seç
function selectGeneralChat() {
    activeDirectUserId = null;
    activeDirectUserObj = null;

    document.querySelectorAll('.cu-chat-channel-btn').forEach(b => b.classList.add('active'));
    document.querySelectorAll('.cu-chat-member-btn').forEach(b => b.classList.remove('active'));

    document.getElementById('chatHeaderAvatar').innerHTML = '#';
    document.getElementById('chatHeaderAvatar').style.background = 'rgba(123,104,238,0.18)';
    document.getElementById('chatHeaderAvatar').style.color = 'var(--brand-primary)';
    document.getElementById('chatHeaderTitle').innerText = '# ' + window.I18N_CHAT.channel_general;
    document.getElementById('chatHeaderBadge').innerText = window.I18N_CHAT.all_team;
    document.getElementById('chatHeaderBadge').style.background = 'rgba(123,104,238,0.15)';
    document.getElementById('chatHeaderBadge').style.color = 'var(--brand-primary)';
    document.getElementById('chatHeaderDesc').innerText = window.I18N_CHAT.general_desc;
    document.getElementById('directPrivacyNotice').style.display = 'none';
    document.getElementById('btnBackToGeneral').style.display = 'none';
    document.getElementById('chatInput').placeholder = window.I18N_CHAT.input_placeholder;

    // Mobilde kenar çubuğunu otomatik kapat
    if (window.innerWidth <= 900) {
        const sb = document.getElementById('cuChatSidebar');
        if (sb) sb.classList.remove('active');
    }

    loadChatHistory();
}

// 3. Birebir Özel Sohbeti Seç (Direct Message)
function selectDirectChat(targetId, targetName, avatarColor, title) {
    activeDirectUserId = targetId;

    // Mobilde kenar çubuğunu otomatik kapat
    if (window.innerWidth <= 900) {
        const sb = document.getElementById('cuChatSidebar');
        if (sb) sb.classList.remove('active');
    }

    document.querySelectorAll('.cu-chat-channel-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.cu-chat-member-btn').forEach(b => {
        b.classList.toggle('active', b.id === `dm-btn-${targetId}`);
    });

    const initial = (targetName || 'U').substring(0, 1).toUpperCase();
    const avatarEl = document.getElementById('chatHeaderAvatar');
    avatarEl.innerHTML = initial;
    avatarEl.style.background = avatarColor || '#7B68EE';
    avatarEl.style.color = '#fff';

    const chatTitle = (window.I18N_CHAT?.private_chat_with || '🔒 Private Chat with {name}').replace('{name}', escapeHtml(targetName));
    document.getElementById('chatHeaderTitle').innerHTML = chatTitle;
    document.getElementById('chatHeaderBadge').innerText = window.I18N_CHAT?.dm_badge || 'Private DM';
    document.getElementById('chatHeaderBadge').style.background = 'rgba(0,184,148,0.15)';
    document.getElementById('chatHeaderBadge').style.color = '#00b894';

    const roleOrTitle = title || window.I18N_CHAT?.default_role || 'Member';
    const chatDesc = (window.I18N_CHAT?.private_chat_desc || '{title} • Only you and {name} can view this.')
        .replace('{title}', roleOrTitle)
        .replace('{name}', targetName);
    document.getElementById('chatHeaderDesc').innerText = chatDesc;
    const isClientRole = window.IS_CLIENT_ROLE || false;
    document.getElementById('directPrivacyNotice').style.display = 'flex';
    document.getElementById('btnBackToGeneral').style.display = isClientRole ? 'none' : 'inline-flex';

    const inputPlaceholder = (window.I18N_CHAT?.private_input_placeholder || 'Write a message to {name}...')
        .replace('{name}', targetName);
    document.getElementById('chatInput').placeholder = inputPlaceholder;

    // Unread sayacını temizle
    const unreadEl = document.querySelector(`#dm-btn-${targetId} .cu-unread-badge`);
    if (unreadEl) unreadEl.remove();

    loadChatHistory();
    chatInput.focus();
}

// 4. Sohbet Geçmişini Çek (Genel veya DM)
function loadChatHistory() {
    chatStatusText.innerText = window.I18N_CHAT?.loading_messages || 'Loading messages...';
    let url = `${chatBaseUrl}/ajax?action=get_chat_messages`;
    if (activeDirectUserId) {
        url += `&receiver_id=${activeDirectUserId}`;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const msgs = data.messages || (data.data && data.data.messages) || [];
            if (data.success) {
                messagesContainer.innerHTML = '';
                lastRenderedDate = '';
                lastChatMsgId = 0;

                if (!msgs || msgs.length === 0) {
                    if (activeDirectUserId) {
                        const targetName = activeDirectUserObj?.name || 'Member';
                        const firstDmDesc = (window.I18N_CHAT?.first_dm_desc || 'Messages remain between you and {name}.')
                            .replace('{name}', escapeHtml(targetName));
                        const dmTitle = (window.I18N_CHAT?.private_chat_with || '🔒 Private Chat with {name}')
                            .replace('{name}', escapeHtml(targetName));
                        messagesContainer.innerHTML = `
                            <div class="empty-state-dm" style="text-align:center; padding:60px 20px; color:var(--text-muted); font-size:13px; margin:auto;">
                                <div style="width:56px; height:56px; border-radius:50%; background:rgba(0,184,148,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px; color:#00b894;">🔒</div>
                                <div style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:6px;">${dmTitle}</div>
                                <div style="color:var(--text-muted); font-size:12.5px; max-width:340px; margin:0 auto; line-height:1.5;">${firstDmDesc}</div>
                            </div>
                        `;
                    } else {
                        messagesContainer.innerHTML = `
                            <div class="empty-state-general" style="text-align:center; padding:60px 20px; color:var(--text-muted); font-size:13px; margin:auto;">
                                <div style="width:56px; height:56px; border-radius:50%; background:rgba(123,104,238,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px; color:var(--brand-primary);">💬</div>
                                <div style="font-size:15px; font-weight:700; color:var(--text-white); margin-bottom:6px;">${escapeHtml(window.I18N_CHAT?.welcome_general_title || 'Welcome')}</div>
                            </div>
                        `;
                    }
                } else {
                    msgs.forEach(msg => {
                        appendMessageToChat(msg, false);
                        if (msg.id > lastChatMsgId) lastChatMsgId = msg.id;
                    });
                    scrollToBottom();
                }
                const msgCountText = (window.I18N_CHAT?.messages_count || '{count} messages').replace('{count}', msgs.length);
                chatStatusText.innerHTML = `<span style="display:inline-flex; align-items:center; gap:6px;"><span style="width:6px; height:6px; border-radius:50%; background:#00b894; display:inline-block;"></span> <span>${msgCountText}</span></span>`;
            } else {
                chatStatusText.innerText = (window.I18N_CHAT?.error_prefix || 'Error: ') + (data.message || window.I18N_CHAT?.load_failed || 'Failed');
            }
        })
        .catch(err => {
            chatStatusText.innerText = (window.I18N_CHAT?.conn_error_prefix || 'Connection error: ') + err.message;
        });
}

// Tarih Ayracı Ekle
function renderDateDividerIfNeeded(msgCreatedAt) {
    if (!msgCreatedAt) return;
    const dateOnly = msgCreatedAt.substring(0, 10);
    if (dateOnly && dateOnly !== lastRenderedDate) {
        lastRenderedDate = dateOnly;
        const divider = document.createElement('div');
        divider.className = 'cu-chat-date-divider';
        divider.style.display = 'flex';
        divider.style.alignItems = 'center';
        divider.style.justifyContent = 'center';
        divider.style.margin = '14px 0 8px 0';
        divider.style.position = 'relative';

        const todayStr = new Date().toISOString().substring(0, 10);
        let label = dateOnly;
        if (dateOnly === todayStr) {
            label = window.I18N_CHAT?.today || 'Today';
        } else {
            const parts = dateOnly.split('-');
            if (parts.length === 3) label = `${parts[2]}.${parts[1]}.${parts[0]}`;
        }

        divider.innerHTML = `
            <div style="height:1px; background:var(--border-subtle); position:absolute; left:0; right:0; top:50%; z-index:1;"></div>
            <span style="position:relative; z-index:2; background:var(--bg-card); padding:2px 14px; border-radius:12px; font-size:11px; font-weight:700; color:var(--text-muted); border:1px solid var(--border-subtle);">
                📅 ${label}
            </span>
        `;
        messagesContainer.appendChild(divider);
    }
}

// 5. Mesajı Ekrana Ekle
function appendMessageToChat(msg, animate = true) {
    if (!msg || (!msg.message && !msg.attachment_url)) return;

    // Boş durum ekranını temizle
    const emptyStates = messagesContainer.querySelectorAll('.empty-state-general, .empty-state-dm');
    emptyStates.forEach(e => e.remove());

    // Zaten varsa ekleme
    if (document.getElementById('chat-msg-' + msg.id)) return;

    if (msg.created_at) {
        renderDateDividerIfNeeded(msg.created_at);
    }

    const isMine = (parseInt(msg.user_id) === currentUserId);
    const msgEl = document.createElement('div');
    msgEl.id = 'chat-msg-' + msg.id;
    msgEl.style.display = 'flex';
    msgEl.style.gap = '12px';
    msgEl.style.alignItems = 'flex-start';
    msgEl.style.padding = isMine ? '2px 8px 2px 0' : '2px 0 2px 6px';
    if (isMine) {
        msgEl.style.flexDirection = 'row-reverse';
    }

    if (animate) {
        msgEl.style.animation = 'fadeInUp 0.25s ease';
    }

    const timeStr = msg.formatted_time || (msg.created_at ? msg.created_at.substring(11, 16) : '');
    const avatarColor = msg.avatar_color || '#7B68EE';
    const defaultUserLabel = window.I18N_CHAT?.user_label || 'User';
    const initial = (msg.user_name || defaultUserLabel || 'U').substring(0, 1).toUpperCase();

    // HTML escape & URL linkification
    let safeText = escapeHtml(msg.message);
    const urlPattern = /(https?:\/\/[^\s<]+)/g;
    safeText = safeText.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:underline; font-weight:600; word-break:break-all;">$1</a>');
    const inspectTooltip = escapeHtml(window.I18N_CHAT?.inspect_task || 'Inspect');
    safeText = safeText.replace(/\b(TSK-\d+|01-\d+)\b/g, `<strong style="text-decoration:underline; cursor:pointer;" title="${inspectTooltip}">$1</strong>`);
    const formattedBody = safeText.replace(/\n/g, '<br>');

    // Ek Dosya / Görsel HTML
    let attachmentHtml = '';
    if (msg.attachment_url) {
        const isImg = /\.(jpg|jpeg|png|gif|webp|svg)($|\?)/i.test(msg.attachment_url);
        if (isImg) {
            const chatImgLabel = escapeHtml(msg.message || window.I18N_CHAT?.chat_image || 'Image');
            const imgAltLabel = escapeHtml(window.I18N_CHAT?.image_alt || 'Image');
            attachmentHtml = `
                <div style="margin-top:8px;">
                    <img src="${msg.attachment_url}" 
                         onclick="openImageLightbox('${msg.attachment_url}', '${chatImgLabel}')" 
                         style="max-width:240px; max-height:160px; border-radius:8px; border:1px solid rgba(255,255,255,0.15); cursor:zoom-in; object-fit:cover; display:block;" 
                         alt="${imgAltLabel}">
                </div>
            `;
        } else {
            const fileName = msg.attachment_url.split('/').pop();
            attachmentHtml = `
                <div style="margin-top:8px;">
                    <a href="${msg.attachment_url}" download target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); padding:5px 10px; border-radius:6px; color:#fff; font-size:11.5px; text-decoration:none;">
                        <span>📎</span> <span>${escapeHtml(fileName)}</span> <span>⬇️</span>
                    </a>
                </div>
            `;
        }
    }

    const bubbleStyle = isMine 
        ? 'background:linear-gradient(135deg, #7B68EE, #6C5CE7); color:#fff; box-shadow:0 3px 10px rgba(123,104,238,0.3); border-radius:14px 4px 14px 14px;' 
        : 'background:var(--bg-base); color:var(--text-primary); border:1px solid var(--border-subtle); box-shadow:0 2px 6px rgba(0,0,0,0.06); border-radius:4px 14px 14px 14px;';

    const youSuffix = isMine ? ` <span style="font-size:10px; opacity:0.85; font-weight:600;">${window.I18N_CHAT?.you_label || '(You)'}</span>` : '';
    const senderDisplay = escapeHtml(msg.user_name || defaultUserLabel);

    msgEl.innerHTML = `
        <div class="cu-avatar" style="width:34px; height:34px; font-size:12px; font-weight:700; background:${avatarColor}; flex-shrink:0; box-shadow:0 2px 6px rgba(0,0,0,0.15);" title="${senderDisplay}">
            ${initial}
        </div>
        <div style="max-width:72%; display:flex; flex-direction:column; align-items:${isMine ? 'flex-end' : 'flex-start'};">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px; font-size:11.5px;">
                <span style="font-weight:700; color:${isMine ? 'var(--brand-primary)' : 'var(--text-white)'};">${senderDisplay}${youSuffix}</span>
                <span style="color:var(--text-muted); font-size:10.5px;">${timeStr}</span>
            </div>
            <div style="padding:10px 15px; font-size:13px; line-height:1.5; word-break:break-word; ${bubbleStyle}">
                ${formattedBody ? `<div>${formattedBody}</div>` : ''}
                ${attachmentHtml}
            </div>
        </div>
    `;

    messagesContainer.appendChild(msgEl);
    if (msg.id > lastChatMsgId) lastChatMsgId = msg.id;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    if (btnScrollBottom) btnScrollBottom.style.display = 'none';
}

// Kaydırma Takibi (En alta git butonu kontrolü)
if (messagesContainer) {
    messagesContainer.addEventListener('scroll', function() {
        const distFromBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
        if (btnScrollBottom) {
            if (distFromBottom > 160) {
                btnScrollBottom.style.display = 'inline-flex';
            } else {
                btnScrollBottom.style.display = 'none';
            }
        }
    });
}

// Metin Alanı Otomatik Büyüme & Karakter Sayacı
function handleChatInputAutoGrow(textarea) {
    textarea.style.height = '46px';
    const newHeight = Math.min(Math.max(textarea.scrollHeight, 46), 120);
    textarea.style.height = newHeight + 'px';
    const len = textarea.value.length;
    const charCounter = document.getElementById('charCount');
    if (charCounter) {
        charCounter.innerText = len > 0 ? `${len} karakter` : '';
    }
}

// Hızlı Emoji Ekleme
function insertEmoji(emoji) {
    const start = chatInput.selectionStart || chatInput.value.length;
    const end = chatInput.selectionEnd || chatInput.value.length;
    chatInput.value = chatInput.value.substring(0, start) + emoji + chatInput.value.substring(end);
    chatInput.focus();
    chatInput.selectionStart = chatInput.selectionEnd = start + emoji.length;
    handleChatInputAutoGrow(chatInput);
}

// Dosya Eki Seçimi
function handleChatFileSelect(input) {
    if (input.files && input.files.length > 0) {
        selectedChatFile = input.files[0];
        const preview = document.getElementById('chatFilePreview');
        const nameEl = document.getElementById('chatFileName');
        if (preview && nameEl) {
            nameEl.innerText = selectedChatFile.name;
            preview.style.display = 'inline-flex';
        }
    }
}

function clearChatFile() {
    selectedChatFile = null;
    const input = document.getElementById('chatFileInput');
    if (input) input.value = '';
    const preview = document.getElementById('chatFilePreview');
    if (preview) preview.style.display = 'none';
}

// Ekip Üyesi Arama & Filtreleme
function filterDirectMembers(val) {
    const q = (val || '').toLowerCase().trim();
    const items = document.querySelectorAll('.cu-chat-member-btn');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const title = el.getAttribute('data-title') || '';
        el.style.display = (!q || name.includes(q) || title.includes(q)) ? 'flex' : 'none';
    });
}

// Mobil Kenar Çubuğu Aç/Kapat
function toggleMembersSidebar() {
    const sidebar = document.getElementById('cuChatSidebar');
    if (sidebar) sidebar.classList.toggle('active');
}

// 6. Mesaj Gönder
function handleSendChatMessage(e) {
    if (e) e.preventDefault();
    const text = chatInput.value.trim();
    if (!text && !selectedChatFile) return;

    btnSend.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send_chat_message');
    fd.append('message', text);
    if (activeDirectUserId) {
        fd.append('receiver_id', activeDirectUserId);
    }
    if (selectedChatFile) {
        fd.append('attachment', selectedChatFile);
    }

    fetch(`${chatBaseUrl}/ajax?action=send_chat_message`, {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btnSend.disabled = false;
        const msgObj = data.chat_message || data.message_item || (data.data && data.data.message) || (typeof data.message === 'object' ? data.message : null);
        if (data.success && msgObj) {
            chatInput.value = '';
            chatInput.style.height = '46px';
            clearChatFile();
            const charCounter = document.getElementById('charCount');
            if (charCounter) charCounter.innerText = '';
            appendMessageToChat(msgObj, true);
            scrollToBottom();
            chatInput.focus();
            loadConversations(); // Sol paneldeki son mesajı güncelle
        } else {
            const errStr = typeof data.message === 'string' ? data.message : (data.error || window.I18N_CHAT?.send_failed || 'Failed to send message.');
            const prefix = window.I18N_CHAT?.error_prefix || 'Error: ';
            window.alert(prefix + errStr);
        }
    })
    .catch(err => {
        btnSend.disabled = false;
        const prefix = window.I18N_CHAT?.server_error_prefix || 'Server error: ';
        window.alert(prefix + err.message);
    });
}

function handleChatInputKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSendChatMessage(e);
    }
}

// Global fonksiyon referansları (SSE erişimi için)
window.appendMessageToChat = appendMessageToChat;
window.scrollToBottom = scrollToBottom;

// 7. Gelen Birebir Özel Mesaj İşleyicisi (SSE)
window.handleIncomingDirectMessage = function(msg) {
    if (!msg) return;

    // Aktif açık olan DM penceresine mi ait?
    const isCurrentConv = (
        (activeDirectUserId == msg.user_id && currentUserId == msg.receiver_id) ||
        (activeDirectUserId == msg.receiver_id && currentUserId == msg.user_id)
    );

    if (isCurrentConv) {
        appendMessageToChat(msg, true);
        scrollToBottom();
    } else if (msg.receiver_id == currentUserId) {
        // Başka bir üyeden gelen okunmamış mesaj
        const sender = msg.sender_name || window.I18N_CHAT?.user_label || 'User';
        const rawContent = msg.message || window.I18N_CHAT?.attachment_fallback || 'File';
        const preview = rawContent.substring(0, 35);
        const toastHtml = (window.I18N_CHAT?.direct_msg_toast || '🔒 <b>{sender}</b>: "{preview}"')
            .replace('{sender}', escapeHtml(sender))
            .replace('{preview}', escapeHtml(preview));
        showToast(toastHtml, 'info');
        loadConversations(); // Unread rozetini tazele
    }
};

// 8. Kesintisiz Çift Katmanlı Senkronizasyon (Background Sync Polling)
function checkIncomingMessages() {
    if (lastChatMsgId <= 0) return;
    let url = `${chatBaseUrl}/ajax?action=get_chat_messages&last_id=` + lastChatMsgId;
    if (activeDirectUserId) {
        url += '&receiver_id=' + activeDirectUserId;
    }
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const msgs = data.messages || (data.data && data.data.messages) || [];
            if (data.success && Array.isArray(msgs) && msgs.length > 0) {
                msgs.forEach(msg => {
                    appendMessageToChat(msg, true);
                });
                scrollToBottom();
            }
        })
        .catch(() => {});
}

// Sayfa yüklendiğinde başlat
document.addEventListener('DOMContentLoaded', function() {
    const chatWrapper = document.querySelector('.cu-chat-wrapper');
    if (!chatWrapper) {
        return; // Sohbet sayfasında değilsek dinleyicileri ve poller'ı çalıştırma
    }

    setInterval(checkIncomingMessages, 2500);

    const initUserId = chatWrapper.getAttribute('data-initial-target-user-id');
    if (initUserId && parseInt(initUserId, 10) > 0) {
        activeDirectUserId = parseInt(initUserId, 10);
    }

    // Mobil menü aç / kapa
    const btnMobileToggle = document.getElementById('btnToggleMembersSidebar');
    if (btnMobileToggle) {
        btnMobileToggle.addEventListener('click', toggleMembersSidebar);
    }
    const btnCloseMobile = document.getElementById('btnCloseSidebarMobile');
    if (btnCloseMobile) {
        btnCloseMobile.addEventListener('click', toggleMembersSidebar);
    }

    // Genel sohbete dönüş
    const btnGeneral = document.getElementById('btnChannelGeneral');
    if (btnGeneral) {
        btnGeneral.addEventListener('click', selectGeneralChat);
    }
    const btnBackGeneral = document.getElementById('btnBackToGeneral');
    if (btnBackGeneral) {
        btnBackGeneral.addEventListener('click', selectGeneralChat);
    }

    // Üye arama
    const searchInput = document.getElementById('memberSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterDirectMembers(this.value);
        });
    }

    // En alta kaydır butonu
    if (btnScrollBottom) {
        btnScrollBottom.addEventListener('click', scrollToBottom);
    }

    // Hızlı emoji butonları
    document.querySelectorAll('.cu-emoji-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            const emoji = this.getAttribute('data-emoji') || this.innerText;
            insertEmoji(emoji);
        });
    });

    // Dosya ekleme tetikleyici
    const btnTriggerFile = document.getElementById('btnTriggerFileInput');
    const fileInput = document.getElementById('chatFileInput');
    if (btnTriggerFile && fileInput) {
        btnTriggerFile.addEventListener('click', function() {
            fileInput.click();
        });
        fileInput.addEventListener('change', function() {
            handleChatFileSelect(this);
        });
    }

    // Dosya temizleme
    const btnClearFile = document.getElementById('btnClearChatFile');
    if (btnClearFile) {
        btnClearFile.addEventListener('click', clearChatFile);
    }

    // Form gönderme
    const chatForm = document.getElementById('chatForm');
    if (chatForm) {
        chatForm.addEventListener('submit', handleSendChatMessage);
    }

    // Textarea otomatik boyutlanma ve tuş dinleme
    if (chatInput) {
        chatInput.addEventListener('input', function() {
            handleChatInputAutoGrow(this);
        });
        chatInput.addEventListener('keydown', handleChatInputKeyDown);
    }

    loadConversations();
    const isClientRole = window.IS_CLIENT_ROLE || false;

    if (activeDirectUserId) {
        // Belirli bir kullanıcı açıldıysa onun DM penceresini yükle
        fetch(`${chatBaseUrl}/ajax?action=get_chat_messages&receiver_id=${activeDirectUserId}`)
            .then(r => r.json())
            .then(data => {
                if (data.target_user) {
                    selectDirectChat(data.target_user.id, data.target_user.name, data.target_user.avatar_color, data.target_user.title);
                } else if (!isClientRole) {
                    selectGeneralChat();
                }
            })
            .catch(() => {
                if (!isClientRole) selectGeneralChat();
            });
    } else if (isClientRole) {
        // Müşteri için: Genel sohbet gizli olduğundan ilk yetkili ekip üyesini otomatik seç
        fetch(`${chatBaseUrl}/ajax?action=get_chat_conversations`)
            .then(r => r.json())
            .then(data => {
                const list = data.conversations || (data.data && data.data.conversations) || [];
                if (list.length > 0) {
                    selectDirectChat(list[0].id, list[0].name, list[0].avatar_color, list[0].title);
                } else {
                    document.getElementById('chatHeaderTitle').innerText = window.I18N_CHAT?.team_private_chat || '🔒 Private Team Chat';
                    document.getElementById('chatHeaderDesc').innerText = window.I18N_CHAT?.team_private_desc || 'Direct communication with your team.';
                    const selectPrompt = escapeHtml(window.I18N_CHAT?.select_member_to_chat || 'Select a member to chat.');
                    messagesContainer.innerHTML = `<div style="text-align:center; padding:50px; color:var(--text-muted); font-size:13px; margin:auto;"><div style="font-size:32px; margin-bottom:12px;">💬</div>${selectPrompt}</div>`;
                }
            })
            .catch(() => {});
    } else {
        selectGeneralChat();
    }
});
