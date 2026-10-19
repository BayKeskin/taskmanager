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
        namePreview.innerText = val || 'Kullanıcı';
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
    btn.innerHTML = '<span>⏳</span> <span>Güncelleniyor...</span>';

    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'update_profile');

    const newPass = form.querySelector('[name="new_password"]').value;
    const newPassConfirm = form.querySelector('[name="new_password_confirm"]').value;
    const currentPass = form.querySelector('[name="current_password"]').value;

    if (newPass) {
        if (!currentPass) {
            alert('Şifrenizi değiştirmek için lütfen mevcut şifrenizi girin.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass.length < 6) {
            alert('Yeni şifreniz en az 6 karakter olmalıdır.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        if (newPass !== newPassConfirm) {
            alert('Yeni şifreler birbiriyle uyuşmuyor.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
    }

    fetch('/taskmanager/ajax/update_profile', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('✅ ' + data.message, 'success');
            } else {
                alert('✅ ' + data.message);
            }
            closeProfileModal();
            setTimeout(() => {
                location.reload();
            }, 600);
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Sunucu hatası: ' + err.message);
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
    
    if (role === 'client') {
        if (projContainer) projContainer.style.display = 'block';
        if (projSelect) projSelect.required = true;
        if (titleInput && (titleInput.value === 'Ekip Üyesi' || titleInput.value === '')) {
            titleInput.value = 'Müşteri / Danışan';
        }
    } else {
        if (projContainer) projContainer.style.display = 'none';
        if (projSelect) {
            projSelect.required = false;
            projSelect.value = '';
        }
        if (titleInput && titleInput.value === 'Müşteri / Danışan') {
            titleInput.value = (role === 'admin') ? 'Yönetici' : 'Ekip Üyesi';
        }
    }
}

function submitNewMember(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitMember');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>⏳</span><span>Ekleniyor...</span>';
    }

    const formData = new FormData(e.target);
    formData.append('action', 'create_user');

    fetch('/taskmanager/ajax/create_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span><span>Kaydet ve Ekle</span>';
        }
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Üye başarıyla eklendi', 'success');
            } else {
                alert('✅ ' + data.message);
            }
            setTimeout(() => location.reload(), 600);
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Hata oluştu', 'error');
            } else {
                alert('❌ Hata: ' + data.message);
            }
        }
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<span>💾</span><span>Kaydet ve Ekle</span>';
        }
        alert('Sunucu hatası oluştu: ' + err.message);
    });
}

function deleteUser(userId, userName) {
    const confirmTemplate = (window.TEAM_I18N && window.TEAM_I18N.confirm_delete) ? window.TEAM_I18N.confirm_delete : ':name isimli ekip üyesini silmek istediğinize emin misiniz?';
    if (!confirm(confirmTemplate.replace(':name', userName).replace('{name}', userName))) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);

    fetch('/taskmanager/ajax/delete_user', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(err => {
        alert('Sunucu hatası: ' + err.message);
    });
}

function copyClientInvite(name, email, projectTitle) {
    const loginUrl = window.location.origin + '/taskmanager/login';
    const inviteText = `Merhaba ${name},\n\n` +
        `"${projectTitle}" projenizin süreçlerini şeffaf bir şekilde takip edebilmeniz ve tamamlanan teslimatları onaylayabilmeniz için TaskManager Müşteri Portalı hesabınız oluşturulmuştur.\n\n` +
        `📌 Giriş Adresi: ${loginUrl}\n` +
        `✉️ E-Posta: ${email}\n` +
        `🔑 Varsayılan Şifre: 123456 (İlk girişinizde güncelleyebilirsiniz)\n\n` +
        `İyi çalışmalar dileriz.`;

    const copiedMsg = (window.TEAM_I18N && window.TEAM_I18N.invite_copied) ? window.TEAM_I18N.invite_copied : '📋 Müşteri davet metni panoya kopyalandı:';
    const promptMsg = (window.TEAM_I18N && window.TEAM_I18N.invite_prompt) ? window.TEAM_I18N.invite_prompt : 'Müşteri davet metni (Kopyalamak için Ctrl+C):';

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
document.addEventListener('DOMContentLoaded', function() {
    const btnOpenNew = document.getElementById('btnOpenNewMember');
    if (btnOpenNew) {
        btnOpenNew.addEventListener('click', function() {
            if (typeof openModal === 'function') openModal('modalNewMember');
        });
    }

    document.querySelectorAll('.btn-invite-client').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const proj = this.getAttribute('data-project');
            copyClientInvite(name, email, proj);
        });
    });

    document.querySelectorAll('.btn-delete-team-user').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const uid = this.getAttribute('data-id');
            const uname = this.getAttribute('data-name');
            deleteUser(uid, uname);
        });
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
            if (prev) prev.innerText = this.value || 'Kullanıcı';
        });
    }

    const btnCopyApiToken = document.getElementById('btnCopyApiToken');
    if (btnCopyApiToken) {
        btnCopyApiToken.addEventListener('click', function() {
            const tokenInput = document.getElementById('profileApiTokenInput');
            if (tokenInput && tokenInput.value) {
                navigator.clipboard.writeText(tokenInput.value);
                if (typeof showToast === 'function') showToast('API Token kopyalandı!', 'success');
                else alert('Token kopyalandı!');
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
});


