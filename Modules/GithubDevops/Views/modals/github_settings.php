<!-- 🐙 GITHUB DEVOPS & HESAP AYARLARI MODALI -->
<div class="cu-modal-overlay" id="modalGithubSettings">
    <div class="cu-modal gh-modal-dialog">
        <div class="cu-modal-header gh-modal-header">
            <div class="gh-header-left">
                <span class="gh-header-icon">🐙</span>
                <h3 class="gh-modal-title"><?= __('GithubDevops::modal_github_title') ?></h3>
            </div>
            <button type="button" class="gh-close-btn" id="btnGhCloseTop" title="<?= htmlspecialchars(__('GithubDevops::btn_cancel')) ?>">&times;</button>
        </div>
        <div class="cu-modal-body gh-modal-body">
            <p class="gh-desc-text">
                <?= __('GithubDevops::modal_github_desc') ?>
            </p>
            <div class="form-group gh-form-group">
                <label class="gh-label" for="ghUsernameInput"><?= __('GithubDevops::modal_github_user_label') ?></label>
                <input type="text" id="ghUsernameInput" class="cu-input gh-input-field" placeholder="<?= htmlspecialchars(__('GithubDevops::modal_github_user_ph')) ?>" value="" autocomplete="off">
            </div>
            <div class="form-group gh-form-group">
                <label class="gh-label" for="ghTokenInput"><?= __('GithubDevops::modal_github_token_label') ?></label>
                <div class="gh-input-wrapper">
                    <input type="password" id="ghTokenInput" class="cu-input gh-input-field gh-input-password" placeholder="<?= htmlspecialchars(__('GithubDevops::modal_github_token_ph')) ?>" value="" autocomplete="off">
                    <button type="button" id="btnGhToggleToken" class="gh-btn-toggle-pwd" title="<?= htmlspecialchars(__('GithubDevops::toggle_token_title')) ?>">👁️</button>
                </div>
            </div>
        </div>
        <div class="cu-modal-footer gh-modal-footer">
            <button type="button" class="cu-btn-sm cu-btn-secondary" id="btnGhCancel"><?= __('GithubDevops::btn_cancel') ?></button>
            <button type="button" class="cu-btn-sm gh-save-btn" id="btnGhSave"><?= __('GithubDevops::modal_github_save_btn') ?></button>
        </div>
    </div>
</div>
