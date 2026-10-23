<!-- 3. REVİZYON / AÇIKLAMA İSTEME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalRequestMod">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 class="cu-modal-title-warning">
                <span>📝</span> <?= __('Board::modal_request_mod_title') ?>
            </h3>
            <button type="button" class="cu-modal-close-icon" data-close-modal="modalRequestMod">✕</button>
        </div>
        <form id="formRequestMod">
            <input type="hidden" name="task_id" id="modTaskId">
            <div class="cu-modal-body">
                <p class="cu-modal-lead-desc">
                    <?= __('Board::modal_request_mod_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('Board::modal_request_mod_reason') ?></label>
                    <textarea name="reason" class="cu-form-control" rows="4" placeholder="<?= htmlspecialchars(__('Board::modal_request_mod_reason_ph')) ?>" required></textarea>
                </div>
            </div>
            <div class="cu-modal-footer cu-modal-footer-clean">
                <button type="button" class="cu-btn-sm" data-close-modal="modalRequestMod"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-modify"><?= __('Board::modal_request_mod_submit') ?></button>
            </div>
        </form>
    </div>
</div>

