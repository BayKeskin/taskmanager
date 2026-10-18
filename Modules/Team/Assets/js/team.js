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
