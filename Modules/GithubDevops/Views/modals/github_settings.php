<!-- 🐙 GITHUB DEVOPS & HESAP AYARLARI MODALI -->
<div class="cu-modal-overlay" id="modalGithubSettings" onclick="if(event.target === this) closeModal('modalGithubSettings')">
    <div class="cu-modal" style="max-width:500px; display:flex; flex-direction:column;">
        <div class="cu-modal-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">🐙</span>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0;"><?= __('GithubDevops::modal_github_title', 'GitHub DevOps & Hesap Ayarları') ?></h3>
            </div>
            <button type="button" onclick="closeModal('modalGithubSettings')" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="cu-modal-body" style="padding:20px;">
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px; line-height:1.5;">
                <?= __('GithubDevops::modal_github_desc', 'GitHub hesabınızı bağlayarak yeni projeler için otomatik Private Repository açılmasını, görevler teslim edildikçe güvenli (http.extraheader) push yapılmasını ve tek tıkla cerrahi geri alma (Rollback) imkanını aktif edebilirsiniz.') ?>
            </p>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('GithubDevops::modal_github_user_label', 'GitHub Kullanıcı Adı veya Organizasyon') ?></label>
                <input type="text" id="ghUsernameInput" class="cu-input" placeholder="<?= htmlspecialchars(__('GithubDevops::modal_github_user_ph', 'Örn: BayKeskin')) ?>" value="" style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white);">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('GithubDevops::modal_github_token_label', 'GitHub Personal Access Token (PAT - repo yetkili)') ?></label>
                <input type="password" id="ghTokenInput" class="cu-input" placeholder="<?= htmlspecialchars(__('GithubDevops::modal_github_token_ph', 'ghp_xxxxxxxxxxxxxxxxxxxx')) ?>" value="" style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white);">
            </div>
        </div>
        <div class="cu-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; padding:14px 20px; border-top:1px solid var(--border-subtle);">
            <button type="button" class="cu-btn-sm cu-btn-secondary" onclick="closeModal('modalGithubSettings')"><?= __('Core::btn_cancel', 'İptal') ?></button>
            <button type="button" class="cu-btn-sm" style="background:#2ea043; color:#fff;" onclick="saveGithubSettings()"><?= __('GithubDevops::modal_github_save_btn', '💾 Kaydet ve Bağla') ?></button>
        </div>
    </div>
</div>
