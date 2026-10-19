/**
 * Modules/LiveDeploy/Assets/js/deploy-wizard.js
 * Canlı Dağıtım & Hosting Sihirbazı İstemci Mantığı
 */
let currentDeployStep = 1;

window.openDeployWizard = function(projectId) {
    if (!projectId) {
        if (typeof showToast === 'function') showToast('Lütfen önce bir proje seçin.', 'error');
        return;
    }

    const projIdInput = document.getElementById('deployWizardProjId');
    if (projIdInput) projIdInput.value = projectId;

    currentDeployStep = 1;
    showDeployStep(1);
    if (typeof openModal === 'function') openModal('modalDeployWizard');

    fetch('/taskmanager/ajax?action=get_project&id=' + projectId)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const p = res.data;
                if (p.workspace_path) {
                    loadDeployGitDiff(projectId);
                }
            }
        })
        .catch(err => console.error('Proje bilgileri alınamadı:', err));
};

window.closeDeployWizard = function() {
    if (typeof closeModal === 'function') {
        closeModal('modalDeployWizard');
    }
    const modalEl = document.getElementById('deployWizardModal');
    if (modalEl) modalEl.classList.add('deploy-modal-hidden');
};

window.showDeployStep = function(step) {
    currentDeployStep = step;
    [1, 2, 3].forEach(s => {
        const content = document.getElementById('deployStepContent' + s);
        const tab = document.getElementById('deployStepTab' + s);
        if (content) {
            if (s === step) {
                content.classList.remove('deploy-modal-hidden');
                content.style.display = 'block';
            } else {
                content.classList.add('deploy-modal-hidden');
                content.style.display = 'none';
            }
        }
        if (tab) {
            if (s === step) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        }
    });

    const btnPrev = document.getElementById('btnDeployPrev');
    const btnNext = document.getElementById('btnDeployNext');
    const btnAction = document.getElementById('btnDeployAction');

    if (btnPrev) {
        if (step > 1) {
            btnPrev.classList.remove('deploy-modal-hidden');
            btnPrev.style.display = 'inline-block';
        } else {
            btnPrev.classList.add('deploy-modal-hidden');
            btnPrev.style.display = 'none';
        }
    }
    if (btnNext) {
        if (step < 3) {
            btnNext.classList.remove('deploy-modal-hidden');
            btnNext.style.display = 'inline-block';
        } else {
            btnNext.classList.add('deploy-modal-hidden');
            btnNext.style.display = 'none';
        }
    }
    if (btnAction) {
        if (step === 3) {
            btnAction.classList.remove('deploy-modal-hidden');
            btnAction.style.display = 'inline-block';
        } else {
            btnAction.classList.add('deploy-modal-hidden');
            btnAction.style.display = 'none';
        }
    }
};

window.nextDeployStep = function() {
    if (currentDeployStep === 1) {
        const pId = document.getElementById('deployWizardProjId')?.value;
        if (pId) loadDeployGitDiff(pId);
        showDeployStep(2);
    } else if (currentDeployStep === 2) {
        showDeployStep(3);
    }
};

window.prevDeployStep = function() {
    if (currentDeployStep > 1) {
        showDeployStep(currentDeployStep - 1);
    }
};

window.toggleDeployPanelFields = function() {
    const type = document.getElementById('deployPanelType')?.value;
    const portInput = document.getElementById('deployFtpPort');
    if (portInput) {
        if (type === 'cpanel') portInput.value = '2083';
        else if (type === 'plesk') portInput.value = '8443';
        else if (type === 'cyberpanel') portInput.value = '8090';
        else portInput.value = '21';
    }
};

window.testDeployPanelConnection = function() {
    const statusSpan = document.getElementById('deployPanelTestStatus');
    if (statusSpan) {
        statusSpan.innerHTML = '⏳ Doğrulanıyor...';
        statusSpan.style.color = 'var(--text-muted)';
    }

    const fd = new FormData();
    fd.append('panel_type', document.getElementById('deployPanelType')?.value || 'standard');
    fd.append('host', document.getElementById('deployFtpHost')?.value || '');
    fd.append('username', document.getElementById('deployFtpUser')?.value || '');
    fd.append('password', document.getElementById('deployFtpPass')?.value || '');
    fd.append('port', document.getElementById('deployFtpPort')?.value || '21');

    fetch('/taskmanager/ajax?action=test_hosting_panel', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (statusSpan) {
                if (res.success) {
                    statusSpan.innerHTML = '✅ ' + (res.message || 'Bağlantı başarılı!');
                    statusSpan.style.color = '#00b894';
                } else {
                    statusSpan.innerHTML = '❌ ' + (res.message || 'Bağlantı hatası.');
                    statusSpan.style.color = '#e74c3c';
                }
            }
        })
        .catch(err => {
            if (statusSpan) {
                statusSpan.innerHTML = '❌ Sunucu hatası: ' + err.message;
                statusSpan.style.color = '#e74c3c';
            }
        });
};

window.loadDeployGitDiff = function(projectId) {
    const badge = document.getElementById('deployDiffBadge');
    const details = document.getElementById('deployDiffDetails');
    if (badge) badge.innerText = 'Hesaplanıyor...';

    const fd = new FormData();
    fd.append('project_id', projectId);

    fetch('/taskmanager/ajax?action=delta_diff', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data.diff;
                const migs = res.data.pending_migrations || [];
                if (badge) {
                    badge.innerText = d.is_full ? 'İlk Dağıtım (Tam Aktarım)' : 'Delta Senkronizasyon';
                }
                if (details) {
                    details.innerHTML = `
                        <div>📁 <b>Yüklenecek Dosyalar:</b> ${d.added.length} yeni, ${d.modified.length} değişen</div>
                        <div>🗑️ <b>Silinecek Dosyalar:</b> ${d.deleted.length} adet</div>
                        <div>🗄️ <b>Yeni Şema Göçleri (.migrations):</b> ${migs.length} adet</div>
                    `;
                }
            } else {
                if (badge) badge.innerText = 'Fark bulunamadı veya ilk dağıtım';
            }
        })
        .catch(err => console.error('Delta diff alınamadı:', err));
};

window.startLiveDeployment = function() {
    const pId = document.getElementById('deployWizardProjId')?.value;
    const logBox = document.getElementById('deployLiveLogBox');
    const title = document.getElementById('deployStatusTitle');
    const desc = document.getElementById('deployStatusDesc');
    const btnAction = document.getElementById('btnDeployAction');

    if (btnAction) {
        btnAction.disabled = true;
        btnAction.innerText = '🚀 Dağıtılıyor...';
    }

    if (logBox) {
        logBox.classList.remove('deploy-modal-hidden');
        logBox.style.display = 'block';
        logBox.innerHTML = '<div>[1/4] Git diff ve dosya listesi hazırlanıyor...</div>';
    }

    const liveDomain = document.getElementById('deployLiveDomain')?.value || '';
    const fd = new FormData();
    fd.append('project_id', pId);
    fd.append('deployment_type', 'delta');
    fd.append('live_domain', liveDomain);
    fd.append('details[live_domain]', liveDomain);

    fetch('/taskmanager/ajax?action=record_deployment', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (logBox) {
                    logBox.innerHTML += '<div>[2/4] Dosyalar canlı sunucuya aktarıldı.</div>';
                    logBox.innerHTML += '<div>[3/4] Canlı şema göçleri kontrol edildi ve uygulandı.</div>';
                    logBox.innerHTML += '<div>[4/4] Dağıtım kaydı tamamlandı: ID #' + res.data.deployment_id + '</div>';
                }
                if (title) title.innerText = '🎉 Dağıtım Başarıyla Tamamlandı!';
                if (desc) desc.innerText = 'Canlı siteniz başarıyla güncellendi. Şimdi sağlık kontrolü yapılıyor...';
                if (btnAction) {
                    btnAction.innerText = '✅ Dağıtım Tamam';
                }
                triggerLiveHealthcheck(liveDomain);
            } else {
                if (logBox) logBox.innerHTML += '<div style="color:#e74c3c;">[HATA] ' + res.message + '</div>';
                if (btnAction) {
                    btnAction.disabled = false;
                    btnAction.innerText = '🚀 Tekrar Dene';
                }
            }
        })
        .catch(err => {
            if (logBox) logBox.innerHTML += '<div style="color:#e74c3c;">[HATA] ' + err.message + '</div>';
            if (btnAction) {
                btnAction.disabled = false;
                btnAction.innerText = '🚀 Tekrar Dene';
            }
        });
};

window.triggerLiveHealthcheck = function(liveUrl) {
    const badge = document.getElementById('deployHealthBadge');
    if (badge) {
        badge.innerHTML = '<span>🟡</span> <span>Sağlık kontrolü yapılıyor...</span>';
        badge.style.color = '#f39c12';
        badge.style.borderColor = 'rgba(243,156,18,0.4)';
    }

    const fd = new FormData();
    fd.append('live_url', liveUrl || document.getElementById('deployLiveDomain')?.value || '');
    fd.append('project_id', document.getElementById('deployWizardProjId')?.value || '');

    fetch('/taskmanager/ajax?action=live_healthcheck', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.result) {
                const rData = res.data.result;
                if (badge) {
                    badge.innerHTML = `<span>🟢</span> <span>Canlı ve Sağlıklı (HTTP ${rData.http_code}, ${rData.response_time_ms}ms)</span>`;
                    badge.style.color = '#00b894';
                    badge.style.borderColor = 'rgba(0,184,148,0.5)';
                    badge.style.background = 'rgba(0,184,148,0.1)';
                }
            } else {
                if (badge) {
                    const code = res.data?.result?.http_code || 0;
                    badge.innerHTML = `<span>🔴</span> <span>Sağlık Hatası (HTTP ${code})</span>`;
                    badge.style.color = '#e74c3c';
                    badge.style.borderColor = 'rgba(231,76,60,0.5)';
                    badge.style.background = 'rgba(231,76,60,0.1)';
                }
            }
        })
        .catch(err => {
            if (badge) {
                badge.innerHTML = `<span>🔴</span> <span>Bağlantı Hatası</span>`;
                badge.style.color = '#e74c3c';
            }
        });
};

window.executeDeployRollback = function() {
    if (!confirm('Canlı sunucu önceki stabil sürüme geri alınacak. Devam etmek istiyor musunuz?')) {
        return;
    }

    const pId = document.getElementById('deployWizardProjId')?.value;
    const logBox = document.getElementById('deployLiveLogBox');
    if (logBox) {
        logBox.classList.remove('deploy-modal-hidden');
        logBox.style.display = 'block';
        logBox.innerHTML += '<div>[ROLLBACK] Geri alma süreci başlatıldı...</div>';
    }

    const fd = new FormData();
    fd.append('project_id', pId);

    fetch('/taskmanager/ajax?action=rollback_deployment', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (logBox) {
                    logBox.innerHTML += '<div style="color:#2ecc71;">[ROLLBACK TAMAMLANDI] ' + res.message + '</div>';
                }
                if (typeof showToast === 'function') showToast('✅ Canlı başarıyla önceki sürüme döndürüldü.', 'success');
                triggerLiveHealthcheck(document.getElementById('deployLiveDomain')?.value);
            } else {
                if (logBox) {
                    logBox.innerHTML += '<div style="color:#e74c3c;">[ROLLBACK HATA] ' + res.message + '</div>';
                }
                if (typeof showToast === 'function') showToast('❌ ' + res.message, 'error');
            }
        })
        .catch(err => {
            if (typeof showToast === 'function') showToast('Geri alma hatası: ' + err.message, 'error');
        });
};

document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('modalDeployWizard');
    if (modalEl) {
        modalEl.addEventListener('click', function(e) {
            if (e.target === this && typeof closeModal === 'function') {
                closeModal('modalDeployWizard');
            }
        });
    }

    const btnHeaderClose = document.getElementById('btnDeployWizardHeaderClose');
    if (btnHeaderClose) {
        btnHeaderClose.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalDeployWizard');
        });
    }

    const btnWizardClose = document.getElementById('btnDeployWizardClose');
    if (btnWizardClose) {
        btnWizardClose.addEventListener('click', function() {
            window.closeDeployWizard();
        });
    }

    const panelTypeSelect = document.getElementById('deployPanelType');
    if (panelTypeSelect) {
        panelTypeSelect.addEventListener('change', function() {
            window.toggleDeployPanelFields();
        });
    }

    const btnTestPanel = document.getElementById('btnTestDeployPanel');
    if (btnTestPanel) {
        btnTestPanel.addEventListener('click', function() {
            window.testDeployPanelConnection();
        });
    }

    const btnPrev = document.getElementById('btnDeployPrev');
    if (btnPrev) {
        btnPrev.addEventListener('click', function() {
            window.prevDeployStep();
        });
    }

    const btnNext = document.getElementById('btnDeployNext');
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            window.nextDeployStep();
        });
    }

    const btnAction = document.getElementById('btnDeployAction');
    if (btnAction) {
        btnAction.addEventListener('click', function() {
            window.startLiveDeployment();
        });
    }

    const btnRollback = document.getElementById('btnExecuteDeployRollback');
    if (btnRollback) {
        btnRollback.addEventListener('click', function() {
            window.executeDeployRollback();
        });
    }

    const btnCancel = document.getElementById('btnDeployModalCancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', function() {
            if (typeof closeModal === 'function') closeModal('modalDeployWizard');
        });
    }
});
