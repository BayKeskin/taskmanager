/**
 * Modules/LiveDeploy/Assets/js/deploy-wizard.js
 * Canlı Dağıtım & Hosting Sihirbazı İstemci Mantığı
 */
let currentDeployStep = 1;
const deployI18n = window.DEPLOY_I18N || {};
const deployBaseUrl = (window.BASE_URL || '').replace(/\/+$/, '');

window.openDeployWizard = function(projectId) {
    if (!projectId) {
        const msg = deployI18n.select_project_first || 'Please select a project first.';
        if (typeof showToast === 'function') showToast(msg, 'error');
        return;
    }

    const projIdInput = document.getElementById('deployWizardProjId');
    if (projIdInput) projIdInput.value = projectId;

    currentDeployStep = 1;
    showDeployStep(1);
    if (typeof openModal === 'function') openModal('modalDeployWizard');

    fetch(deployBaseUrl + '/ajax?action=get_project&id=' + projectId)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const p = res.data;
                if (p.workspace_path) {
                    loadDeployGitDiff(projectId);
                }
            }
        })
        .catch(err => console.error(deployI18n.err_project_info_failed || 'Project info error:', err));
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
        statusSpan.innerHTML = deployI18n.status_verifying || 'Verifying...';
        statusSpan.style.color = 'var(--text-muted)';
    }

    const fd = new FormData();
    fd.append('panel_type', document.getElementById('deployPanelType')?.value || 'standard');
    fd.append('host', document.getElementById('deployFtpHost')?.value || '');
    fd.append('username', document.getElementById('deployFtpUser')?.value || '');
    fd.append('password', document.getElementById('deployFtpPass')?.value || '');
    fd.append('port', document.getElementById('deployFtpPort')?.value || '21');

    fetch(deployBaseUrl + '/ajax?action=test_hosting_panel', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (statusSpan) {
                if (res.success) {
                    statusSpan.innerHTML = '✅ ' + (res.message || deployI18n.status_conn_success || 'Success');
                    statusSpan.style.color = '#00b894';
                } else {
                    statusSpan.innerHTML = '❌ ' + (res.message || deployI18n.status_conn_failed || 'Error');
                    statusSpan.style.color = '#e74c3c';
                }
            }
        })
        .catch(err => {
            if (statusSpan) {
                statusSpan.innerHTML = '❌ ' + (deployI18n.status_server_error || 'Server error: ') + err.message;
                statusSpan.style.color = '#e74c3c';
            }
        });
};

window.loadDeployGitDiff = function(projectId) {
    const badge = document.getElementById('deployDiffBadge');
    const details = document.getElementById('deployDiffDetails');
    if (badge) badge.innerText = deployI18n.badge_calculating || 'Calculating...';

    const fd = new FormData();
    fd.append('project_id', projectId);

    fetch(deployBaseUrl + '/ajax?action=delta_diff', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data.diff;
                const migs = res.data.pending_migrations || [];
                if (badge) {
                    badge.innerText = d.is_full ? (deployI18n.badge_initial_deploy || 'Initial') : (deployI18n.badge_delta_sync || 'Delta');
                }
                if (details) {
                    const uploadMsg = (deployI18n.files_to_upload || 'Files: {added} new, {modified} modified')
                        .replace('{added}', d.added.length)
                        .replace('{modified}', d.modified.length);
                    const deleteMsg = (deployI18n.files_to_delete || 'Delete: {deleted}')
                        .replace('{deleted}', d.deleted.length);
                    const migMsg = (deployI18n.migrations_to_run || 'Migrations: {count}')
                        .replace('{count}', migs.length);
                    details.innerHTML = `
                        <div>${uploadMsg}</div>
                        <div>${deleteMsg}</div>
                        <div>${migMsg}</div>
                    `;
                }
            } else {
                if (badge) badge.innerText = deployI18n.badge_no_diff || 'No diff';
            }
        })
        .catch(err => console.error(deployI18n.err_delta_diff_failed || 'Delta error:', err));
};

window.startLiveDeployment = function() {
    const pId = document.getElementById('deployWizardProjId')?.value;
    const logBox = document.getElementById('deployLiveLogBox');
    const title = document.getElementById('deployStatusTitle');
    const desc = document.getElementById('deployStatusDesc');
    const btnAction = document.getElementById('btnDeployAction');

    if (btnAction) {
        btnAction.disabled = true;
        btnAction.innerText = deployI18n.btn_deploying || 'Deploying...';
    }

    if (logBox) {
        logBox.classList.remove('deploy-modal-hidden');
        logBox.style.display = 'block';
        logBox.innerHTML = '<div>' + (deployI18n.log_step1 || '[1/4] Preparing Git diff...') + '</div>';
    }

    const liveDomain = document.getElementById('deployLiveDomain')?.value || '';
    const fd = new FormData();
    fd.append('project_id', pId);
    fd.append('deployment_type', 'delta');
    fd.append('live_domain', liveDomain);
    fd.append('details[live_domain]', liveDomain);

    fetch(deployBaseUrl + '/ajax?action=record_deployment', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (logBox) {
                    logBox.innerHTML += '<div>' + (deployI18n.log_step2 || '[2/4] Files transferred.') + '</div>';
                    logBox.innerHTML += '<div>' + (deployI18n.log_step3 || '[3/4] Migrations applied.') + '</div>';
                    const step4 = (deployI18n.log_step4 || '[4/4] Deployment recorded: ID #{id}').replace('{id}', res.data.deployment_id);
                    logBox.innerHTML += '<div>' + step4 + '</div>';
                }
                if (title) title.innerText = deployI18n.title_deploy_completed || 'Deployment Completed';
                if (desc) desc.innerText = deployI18n.desc_deploy_completed || 'Site updated. Running healthcheck...';
                if (btnAction) {
                    btnAction.innerText = deployI18n.btn_deploy_done || 'Done';
                }
                triggerLiveHealthcheck(liveDomain);
            } else {
                if (logBox) logBox.innerHTML += '<div style="color:#e74c3c;">[ERROR] ' + res.message + '</div>';
                if (btnAction) {
                    btnAction.disabled = false;
                    btnAction.innerText = deployI18n.btn_retry || 'Retry';
                }
            }
        })
        .catch(err => {
            if (logBox) logBox.innerHTML += '<div style="color:#e74c3c;">[ERROR] ' + err.message + '</div>';
            if (btnAction) {
                btnAction.disabled = false;
                btnAction.innerText = deployI18n.btn_retry || 'Retry';
            }
        });
};

window.triggerLiveHealthcheck = function(liveUrl) {
    const badge = document.getElementById('deployHealthBadge');
    if (badge) {
        badge.innerHTML = '<span>🟡</span> <span>' + (deployI18n.badge_health_checking || 'Checking...') + '</span>';
        badge.style.color = '#f39c12';
        badge.style.borderColor = 'rgba(243,156,18,0.4)';
    }

    const fd = new FormData();
    fd.append('live_url', liveUrl || document.getElementById('deployLiveDomain')?.value || '');
    fd.append('project_id', document.getElementById('deployWizardProjId')?.value || '');

    fetch(deployBaseUrl + '/ajax?action=live_healthcheck', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.result) {
                const rData = res.data.result;
                if (badge) {
                    const healthSuccess = (deployI18n.badge_health_success || 'Live & Healthy (HTTP {code}, {time}ms)')
                        .replace('{code}', rData.http_code)
                        .replace('{time}', rData.response_time_ms);
                    badge.innerHTML = `<span>🟢</span> <span>${healthSuccess}</span>`;
                    badge.style.color = '#00b894';
                    badge.style.borderColor = 'rgba(0,184,148,0.5)';
                    badge.style.background = 'rgba(0,184,148,0.1)';
                }
            } else {
                if (badge) {
                    const code = res.data?.result?.http_code || 0;
                    const healthErr = (deployI18n.badge_health_error || 'Health Error (HTTP {code})').replace('{code}', code);
                    badge.innerHTML = `<span>🔴</span> <span>${healthErr}</span>`;
                    badge.style.color = '#e74c3c';
                    badge.style.borderColor = 'rgba(231,76,60,0.5)';
                    badge.style.background = 'rgba(231,76,60,0.1)';
                }
            }
        })
        .catch(err => {
            if (badge) {
                badge.innerHTML = '<span>🔴</span> <span>' + (deployI18n.badge_health_conn_error || 'Connection Error') + '</span>';
                badge.style.color = '#e74c3c';
            }
        });
};

window.executeDeployRollback = function() {
    const confirmPrompt = deployI18n.deploy_rollback_confirm || 'Revert to previous stable version?';
    if (!window.confirm(confirmPrompt)) {
        return;
    }

    const pId = document.getElementById('deployWizardProjId')?.value;
    const logBox = document.getElementById('deployLiveLogBox');
    if (logBox) {
        logBox.classList.remove('deploy-modal-hidden');
        logBox.style.display = 'block';
        logBox.innerHTML += '<div>' + (deployI18n.log_rollback_start || '[ROLLBACK] Rollback initiated...') + '</div>';
    }

    const fd = new FormData();
    fd.append('project_id', pId);

    fetch(deployBaseUrl + '/ajax?action=rollback_deployment', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (logBox) {
                    logBox.innerHTML += '<div style="color:#2ecc71;">[ROLLBACK] ' + res.message + '</div>';
                }
                const msgSuccess = '✅ ' + (deployI18n.msg_rollback_done || 'Rollback complete.');
                if (typeof showToast === 'function') showToast(msgSuccess, 'success');
                triggerLiveHealthcheck(document.getElementById('deployLiveDomain')?.value);
            } else {
                if (logBox) {
                    logBox.innerHTML += '<div style="color:#e74c3c;">[ROLLBACK ERROR] ' + res.message + '</div>';
                }
                const msgErr = '❌ ' + res.message;
                if (typeof showToast === 'function') showToast(msgErr, 'error');
            }
        })
        .catch(err => {
            const errLog = (deployI18n.err_rollback_msg || 'Rollback error: ') + err.message;
            if (typeof showToast === 'function') showToast(errLog, 'error');
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
