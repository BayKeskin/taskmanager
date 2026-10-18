<!-- 🚀 CANLI DAĞITIM & HOSTİNG SİHİRBAZI MODALI (TSK-1085) -->
<div class="cu-modal-overlay" id="modalDeployWizard" onclick="if(event.target === this) closeModal('modalDeployWizard')">
    <div class="cu-modal" style="max-width:760px; max-height:calc(100vh - 40px); height:auto; display:flex; flex-direction:column; overflow:hidden;">
        <div class="cu-modal-header" style="flex-shrink:0; background:linear-gradient(135deg, rgba(0,184,148,0.1), rgba(9,132,227,0.15));">
            <div>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); display:flex; align-items:center; gap:8px; margin:0;">
                    <span>🚀</span> <?= __('LiveDeploy::deploy_wizard_title') ?>
                </h3>
                <p style="font-size:11.5px; color:var(--text-muted); margin:4px 0 0 0;">
                    <?= __('LiveDeploy::deploy_wizard_desc') ?>
                </p>
            </div>
            <button onclick="closeModal('modalDeployWizard')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px;">✕</button>
        </div>

        <!-- SİHİRBAZ ADIM GÖSTERGESİ (STEPPER) -->
        <div style="display:flex; border-bottom:1px solid var(--border-subtle); background:var(--bg-card); padding:10px 20px; gap:8px;">
            <div id="deployStepTab1" class="deploy-step-tab active" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(0,184,148,0.15); border:1px solid rgba(0,184,148,0.4); color:#00cec9; font-size:12px; font-weight:700;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:#00cec9; color:#000; align-items:center; justify-content:center; font-size:11px;">1</span>
                <span><?= __('LiveDeploy::deploy_step1_title') ?></span>
            </div>
            <div id="deployStepTab2" class="deploy-step-tab" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); color:var(--text-muted); font-size:12px; font-weight:600;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:var(--border-subtle); color:var(--text-white); align-items:center; justify-content:center; font-size:11px;">2</span>
                <span><?= __('LiveDeploy::deploy_step2_title') ?></span>
            </div>
            <div id="deployStepTab3" class="deploy-step-tab" style="flex:1; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); color:var(--text-muted); font-size:12px; font-weight:600;">
                <span style="display:inline-flex; width:20px; height:20px; border-radius:50%; background:var(--border-subtle); color:var(--text-white); align-items:center; justify-content:center; font-size:11px;">3</span>
                <span><?= __('LiveDeploy::deploy_step3_title') ?></span>
            </div>
        </div>

        <div class="cu-modal-body" style="overflow-y:auto; flex:1 1 auto; padding:20px;">
            <input type="hidden" id="deployWizardProjId" value="">

            <!-- ADIM 1: HOSTİNG & PANEL/FTP GİRİŞİ -->
            <div id="deployStepContent1" class="deploy-step-content">
                <div class="cu-form-group" style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:700; color:var(--text-white); margin-bottom:6px;"><?= __('LiveDeploy::hosting_adapter_title') ?></label>
                    <select id="deployPanelType" class="cu-form-control" onchange="toggleDeployPanelFields()" style="background:var(--bg-main); color:var(--text-white);">
                        <option value="standard"><?= __('LiveDeploy::hosting_panel_standard') ?></option>
                        <option value="cpanel"><?= __('LiveDeploy::hosting_panel_cpanel') ?></option>
                        <option value="plesk"><?= __('LiveDeploy::hosting_panel_plesk') ?></option>
                        <option value="cyberpanel"><?= __('LiveDeploy::hosting_panel_cyberpanel') ?></option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;"><?= __('LiveDeploy::deploy_target_domain') ?></label>
                        <input type="url" id="deployLiveDomain" class="cu-form-control" placeholder="https://ornekproje.com" value="">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / SSH Host</label>
                        <input type="text" id="deployFtpHost" class="cu-form-control" placeholder="ftp.ornekproje.com" value="">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 100px; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / Panel Kullanıcı</label>
                        <input type="text" id="deployFtpUser" class="cu-form-control" placeholder="cpanel_user">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">FTP / Panel Şifre</label>
                        <input type="password" id="deployFtpPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Port</label>
                        <input type="number" id="deployFtpPort" class="cu-form-control" value="21">
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding:10px 14px; background:rgba(255,255,255,0.03); border:1px solid var(--border-subtle); border-radius:6px;">
                    <span id="deployPanelTestStatus" style="font-size:12px; color:var(--text-muted);">
                        ⚡ Giriş bilgilerinizi doğrulayın.
                    </span>
                    <button type="button" class="cu-btn-sm" onclick="testDeployPanelConnection()" style="background:rgba(123,104,238,0.2); color:#a29bfe; border:1px solid rgba(123,104,238,0.4);">
                        <?= __('LiveDeploy::deploy_btn_test_panel') ?>
                    </button>
                </div>
            </div>

            <!-- ADIM 2: CANLI DOMAİN VE VERİTABANI TEYİDİ -->
            <div id="deployStepContent2" class="deploy-step-content" style="display:none;">
                <div style="background:rgba(9,132,227,0.08); border:1px solid rgba(9,132,227,0.3); border-radius:6px; padding:12px 14px; margin-bottom:14px; font-size:12px; color:var(--text-white);">
                    <b>ℹ️ Canlı İzolasyon Koruması:</b> Yerel kodlarınız ve yerel veri tabanınız kesinlikle bozulmaz. Canlıya aktarılırken URL ve DB ayarları canlı sunucuya özel dönüştürülür.
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Host</label>
                        <input type="text" id="deployDbHost" class="cu-form-control" value="localhost">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı Veritabanı Adı</label>
                        <input type="text" id="deployDbName" class="cu-form-control" placeholder="ornek_db">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Kullanıcı Adı</label>
                        <input type="text" id="deployDbUser" class="cu-form-control" placeholder="ornek_user">
                    </div>
                    <div class="cu-form-group">
                        <label style="font-size:11.5px;">Canlı DB Şifre</label>
                        <input type="password" id="deployDbPass" class="cu-form-control" placeholder="••••••••">
                    </div>
                </div>

                <!-- Git Diff Özeti -->
                <div id="deployDiffSummaryBox" style="background:rgba(0,0,0,0.25); border:1px solid var(--border-subtle); border-radius:6px; padding:12px; font-size:12px;">
                    <div style="font-weight:700; color:var(--text-white); margin-bottom:6px; display:flex; justify-content:space-between;">
                        <span>📦 Senkronizasyon Kapsamı (Git Diff)</span>
                        <span id="deployDiffBadge" style="color:#00cec9;">Hesaplanıyor...</span>
                    </div>
                    <div id="deployDiffDetails" style="font-size:11.5px; color:var(--text-secondary); line-height:1.5;">
                        Değişiklikler yükleniyor...
                    </div>
                </div>
            </div>

            <!-- ADIM 3: TEK TIKLA DAĞITIM, SAĞLIK KONTROLÜ VE ROLLBACK -->
            <div id="deployStepContent3" class="deploy-step-content" style="display:none;">
                <div style="text-align:center; padding:16px 0;">
                    <div id="deployHealthBadge" style="display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:20px; font-size:13px; font-weight:700; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-muted); margin-bottom:14px;">
                        <span>🩺</span> <span>Sağlık durumu bekleniyor</span>
                    </div>
                    <h4 id="deployStatusTitle" style="font-size:15px; font-weight:700; color:var(--text-white); margin:0 0 6px 0;">Dağıtıma Hazır</h4>
                    <p id="deployStatusDesc" style="font-size:12px; color:var(--text-muted); margin:0 auto; max-width:500px;">
                        Tek tıkla dosyalarınızı yükleyin, yeni şema farklarını canlı veritabanında çalıştırın.
                    </p>
                </div>

                <div id="deployLiveLogBox" style="background:#0d1117; border:1px solid #30363d; border-radius:6px; padding:12px; font-family:monospace; font-size:11px; color:#58a6ff; max-height:150px; overflow-y:auto; margin-bottom:14px; display:none;">
                </div>

                <!-- Cerrahi Rollback Kutusu -->
                <div id="deployRollbackBox" style="background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.3); border-radius:6px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-size:12px; font-weight:700; color:#e74c3c;">⏪ Cerrahi Geri Alma (Rollback)</div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">Terslik durumunda sunucuyu ve DB'yi önceki stabil commit'e döndürün.</div>
                    </div>
                    <button type="button" class="cu-btn-sm" onclick="executeDeployRollback()" style="background:#e74c3c; color:#fff; font-weight:700; border:none; padding:6px 14px; border-radius:6px; cursor:pointer;">
                        <?= __('LiveDeploy::deploy_btn_rollback') ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="cu-modal-footer" style="flex-shrink:0; padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:space-between; align-items:center;">
            <button type="button" id="btnDeployPrev" class="cu-btn-sm cu-btn-secondary" onclick="prevDeployStep()" style="display:none;">
                <?= __('LiveDeploy::deploy_btn_prev') ?>
            </button>
            <div style="display:flex; gap:10px; margin-left:auto;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalDeployWizard')"><?= __('Board::btn_cancel', 'İptal') ?></button>
                <button type="button" id="btnDeployNext" class="cu-btn-sm" style="background:var(--brand-primary); color:#fff; font-weight:600;" onclick="nextDeployStep()">
                    <?= __('LiveDeploy::deploy_btn_next') ?>
                </button>
                <button type="button" id="btnDeployAction" class="cu-btn-sm" style="background:#00b894; color:#fff; font-weight:700; display:none;" onclick="startLiveDeployment()">
                    <?= __('LiveDeploy::deploy_btn_deploy_now') ?>
                </button>
            </div>
        </div>
    </div>
</div>
