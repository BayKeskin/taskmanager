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

// 15. KULLANICI PROFİL GÜNCELLEME VE ŞİFRE DEĞİŞTİRME
// ==========================================================================
function openProfileModal() {
    openModal('modalProfileSettings');
}

function closeProfileModal() {
    closeModal('modalProfileSettings');
}

function selectProfileColor(color) {
    document.querySelectorAll('.profile-color-dot').forEach(dot => {
        dot.style.borderColor = (dot.getAttribute('data-color') === color) ? '#fff' : 'transparent';
    });
    const avatar = document.getElementById('profileAvatarPreview');
    if (avatar) avatar.style.background = color;
}

function updateProfileAvatarPreview() {
    const nameInput = document.getElementById('profileInputName');
    const avatar = document.getElementById('profileAvatarPreview');
    const namePreview = document.getElementById('profileNamePreview');
    if (nameInput && avatar && namePreview) {
        const val = nameInput.value.trim();
        namePreview.innerText = val || window.TEAM_I18N?.default_user_name || 'User';
        avatar.innerText = val ? val.charAt(0).toUpperCase() : 'U';
    }
}

function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = '🙈';
    } else {
        input.type = 'password';
        btn.innerText = '👁️';
    }
}

function handleUpdateProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitProfile');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    const updatingTxt = window.TEAM_I18N?.updating_btn || 'Updating...';
    btn.innerHTML = `<span>⏳</span> <span>${updatingTxt}</span>`;

    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'update_profile');

    const newPass = form.querySelector('[name="new_password"]').value;
    const newPassConfirm = form.querySelector('[name="new_password_confirm"]').value;
    const currentPass = form.querySelector('[name="current_password"]').value;

    if (newPass) {
        if (!currentPass) {
            const msg = window.TEAM_I18N?.pwd_current_req_alert || 'Please enter current password.';
            window.alert(msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass.length < 6) {
            const msg = window.TEAM_I18N?.pwd_min_len_alert || 'Password min 6 characters.';
            window.alert(msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass !== newPassConfirm) {
            const msg = window.TEAM_I18N?.pwd_mismatch_alert || 'Passwords do not match.';
            window.alert(msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
    }

    const base = window.BASE_URL || '';
    fetch(base + '/ajax/update_profile', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (data.success) {
            const successMsg = '✅ ' + data.message;
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                window.alert(successMsg);
            }
            closeProfileModal();
            setTimeout(() => {
                location.reload();
            }, 600);
        } else {
            const errMsg = '❌ ' + data.message;
            window.alert(errMsg);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        const prefix = window.TEAM_I18N?.server_error_prefix || 'Server error: ';
        window.alert(prefix + err.message);
    });
}

// ==============================================================================
// 16. EKİP ÜYESİ EKLEME VE YÖNETİMİ
// ==============================================================================
function openNewMemberModal() {
    openModal('modalNewMember');
}

function closeNewMemberModal() {
    closeModal('modalNewMember');
}

function generateRandomPass() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const pwdInput = document.getElementById('memberPassword');
    if (pwdInput) pwdInput.value = pass;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.color-dot').forEach(dot => {
        dot.addEventListener('click', function() {
            document.querySelectorAll('.color-dot').forEach(d => d.style.borderColor = 'transparent');
            this.style.borderColor = '#fff';
        });
    });
    const firstDot = document.querySelector('.color-dot');
    if (firstDot) firstDot.style.borderColor = '#fff';
});

function handleMemberRoleChange(role) {
    const projContainer = document.getElementById('clientProjectContainer');
    const projSelect = document.getElementById('clientProjectSelect');
    const titleInput = document.getElementById('memberTitleInput');
    const i18n = window.TEAM_I18N || {};
    
    if (role === 'client') {
        if (projContainer) projContainer.style.display = 'block';
        if (projSelect) projSelect.required = true;
        if (titleInput && (titleInput.value === (i18n.title_member_default || '') || titleInput.value === '')) {
            titleInput.value = i18n.title_client_default || '';
        }
    } else {
        if (projContainer) projContainer.style.display = 'none';
        if (projSelect) {
            projSelect.required = false;
            projSelect.value = '';
        }
        if (titleInput && titleInput.value === (i18n.title_client_default || '')) {
            titleInput.value = (role === 'admin') ? (i18n.title_admin_default || '') : (i18n.title_member_default || '');
        }
    }
}

function submitNewMember(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitMember');
    const i18n = window.TEAM_I18N || {};
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span><span>' + (i18n.updating_btn || '...') + '</span>';
    }

    const formData = new FormData(e.target);
    formData.append('action', 'create_user');

    const base = window.BASE_URL || '';
    fetch(base + '/ajax/create_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span><span>' + (document.getElementById('memberModalTitle')?.innerText || 'Kaydet') + '</span>';
        }
        if (data.success) {
            const successMsg = data.message || i18n.member_added_toast || '';
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                alert(successMsg);
            }
            setTimeout(() => location.reload(), 600);
        } else {
            const errMsg = data.message || i18n.error_occurred || '';
            if (typeof showToast === 'function') {
                showToast(errMsg, 'error');
            } else {
                alert(errMsg);
            }
        }
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span><span>' + (document.getElementById('memberModalTitle')?.innerText || 'Kaydet') + '</span>';
        }
        alert((i18n.server_error_prefix || '') + err.message);
    });
}

function deleteUser(userId, userName, skipConfirm = false) {
    const i18n = window.TEAM_I18N || {};
    const confirmTemplate = i18n.confirm_delete || ':name';
    if (!skipConfirm && !window.__SKIP_CONFIRM__) {
        if (!confirm(confirmTemplate.replace(':name', userName).replace('{name}', userName))) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);

    const base = window.BASE_URL || '';
    fetch(base + '/ajax/delete_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const successMsg = data.message || i18n.member_deleted_toast || '';
            if (typeof showToast === 'function') {
                showToast(successMsg, 'success');
            } else {
                alert(successMsg);
            }
            setTimeout(() => {
                location.reload();
            }, 400);
        } else {
            const errMsg = data.message || i18n.delete_error_toast || '';
            if (typeof showToast === 'function') {
                showToast(errMsg, 'error');
            } else {
                alert(errMsg);
            }
        }
    })
    .catch(err => {
        alert((i18n.server_error_prefix || '') + err.message);
    });
}

function copyClientInvite(name, email, projectTitle) {
    const i18n = window.TEAM_I18N || {};
    const base = window.BASE_URL || '';
    const loginUrl = window.location.origin + base + '/login';
    const intro = (i18n.invite_body_intro || '').replace(':project', projectTitle);
    const loginLine = (i18n.invite_body_login || '').replace(':url', loginUrl);
    const pwdLine = i18n.invite_body_pwd || '';
    const outro = i18n.invite_body_outro || '';

    const inviteText = `Merhaba ${name},\n\n` +
        `${intro}\n\n` +
        `${loginLine}\n` +
        `✉️ E-Posta: ${email}\n` +
        `${pwdLine}\n\n` +
        `${outro}`;

    const copiedMsg = i18n.invite_copied || '';
    const promptMsg = i18n.invite_prompt || '';

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inviteText).then(() => {
            alert(copiedMsg + '\n\n' + inviteText);
        }).catch(() => {
            prompt(promptMsg, inviteText);
        });
    } else {
        prompt(promptMsg, inviteText);
    }
}

// Global Event Listeners for Team View
function initTeamEventListeners() {
    const btnOpenNew = document.getElementById('btnOpenNewMember');
    if (btnOpenNew) {
        btnOpenNew.addEventListener('click', function() {
            if (typeof openModal === 'function') openModal('modalNewMember');
        });
    }

    // Delegated click handling on document for maximum reliability
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.btn-delete-team-user');
        if (deleteBtn) {
            e.preventDefault();
            const uid = deleteBtn.getAttribute('data-id');
            const uname = deleteBtn.getAttribute('data-name');
            const skipConfirm = e.shiftKey || deleteBtn.hasAttribute('data-confirmed');
            deleteUser(uid, uname, skipConfirm);
            return;
        }

        const inviteBtn = e.target.closest('.btn-invite-client');
        if (inviteBtn) {
            e.preventDefault();
            const name = inviteBtn.getAttribute('data-name');
            const email = inviteBtn.getAttribute('data-email');
            const proj = inviteBtn.getAttribute('data-project');
            copyClientInvite(name, email, proj);
            return;
        }

        const closeBtn = e.target.closest('.btn-close-new-member');
        if (closeBtn) {
            e.preventDefault();
            if (typeof closeModal === 'function') closeModal('modalNewMember');
            return;
        }
    });

    // Close buttons for new member modal
    document.querySelectorAll('.btn-close-new-member').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalNewMember');
        });
    });

    // Random password button
    const btnGenPass = document.getElementById('btnGenRandomPass');
    if (btnGenPass) {
        btnGenPass.addEventListener('click', generateRandomPass);
    }

    // Role select change
    const memberRoleSelect = document.getElementById('memberRoleSelect');
    if (memberRoleSelect) {
        memberRoleSelect.addEventListener('change', function() {
            handleMemberRoleChange(this.value);
        });
    }

    // New member form submit
    const formNewMember = document.getElementById('formNewMember');
    if (formNewMember) {
        formNewMember.addEventListener('submit', submitNewMember);
    }

    // Profile modal handlers
    document.querySelectorAll('.btn-close-profile-modal').forEach(function(btn) {
        btn.addEventListener('click', closeProfileModal);
    });

    const formProfileSettings = document.getElementById('formProfileSettings');
    if (formProfileSettings) {
        formProfileSettings.addEventListener('submit', handleUpdateProfile);
    }

    const profileInputName = document.getElementById('profileInputName');
    if (profileInputName) {
        profileInputName.addEventListener('input', updateProfileAvatarPreview);
    }

    const profileInputTitle = document.getElementById('profileInputTitle');
    if (profileInputTitle) {
        profileInputTitle.addEventListener('input', function() {
            const prev = document.getElementById('profileTitlePreview');
            if (prev) prev.innerText = this.value || (window.TEAM_I18N?.default_user_name || 'User');
        });
    }

    const btnCopyApiToken = document.getElementById('btnCopyApiToken');
    if (btnCopyApiToken) {
        btnCopyApiToken.addEventListener('click', function() {
            const tokenInput = document.getElementById('profileApiTokenInput');
            if (tokenInput && tokenInput.value) {
                navigator.clipboard.writeText(tokenInput.value);
                const toastMsg = window.TEAM_I18N?.token_copied_toast || '';
                if (typeof showToast === 'function') showToast(toastMsg, 'success');
                else alert(toastMsg);
            }
        });
    }

    document.querySelectorAll('.profile-avatar-radio').forEach(function(radio) {
        radio.addEventListener('change', function() {
            selectProfileColor(this.value);
        });
    });

    document.querySelectorAll('.btn-toggle-pass').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            togglePassVisibility(targetId, this);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTeamEventListeners);
} else {
    initTeamEventListeners();
}


