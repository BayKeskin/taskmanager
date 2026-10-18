<!-- 3. REVİZYON / AÇIKLAMA İSTEME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalRequestMod">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 style="font-size:15px; font-weight:700; color:#e67e22; display:flex; align-items:center; gap:8px;">
                <span>📝</span> <?= __('app.modal_request_mod_title') ?>
            </h3>
            <button onclick="closeModal('modalRequestMod')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formRequestMod" onsubmit="handleRequestMod(event)">
            <input type="hidden" name="task_id" id="modTaskId">
            <div class="cu-modal-body">
                <p style="font-size:12.5px; color:var(--text-secondary); margin-bottom:14px;">
                    <?= __('app.modal_request_mod_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('app.modal_request_mod_reason') ?></label>
                    <textarea name="reason" class="cu-form-control" rows="4" placeholder="<?= htmlspecialchars(__('app.modal_request_mod_reason_ph')) ?>" required></textarea>
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalRequestMod')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-modify"><?= __('app.modal_request_mod_submit') ?></button>
            </div>
        </form>
    </div>
</div>
