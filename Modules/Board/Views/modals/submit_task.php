<!-- 4. GÖREVİ TESLİM ETME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalSubmitTask">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 class="cu-modal-title-purple">
                <span>🚀</span> <?= __('Board::modal_submit_task_title') ?>
            </h3>
            <button type="button" class="cu-modal-close-icon" data-close-modal="modalSubmitTask">✕</button>
        </div>
        <form id="formSubmitTask">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div class="cu-modal-body">
                <p class="cu-modal-lead-desc">
                    <?= __('Board::modal_submit_task_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('Board::modal_submit_task_notes') ?></label>
                    <textarea name="notes" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('Board::modal_submit_task_notes_ph')) ?>" required></textarea>
                </div>
                <div class="cu-form-group">
                    <label class="cu-form-label-between">
                        <span>📎 <?= __('Board::modal_submit_task_attach') ?></span>
                        <span class="cu-label-hint"><?= __('Board::modal_submit_task_attach_hint') ?></span>
                    </label>

                    <!-- Drag & Drop & Paste Alanı -->
                    <div id="submitTaskDropzone" class="cu-submit-task-dropzone">
                        <input type="file" id="submitTaskFileInput" name="attachment" class="cu-hidden-file-input" accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                        <div id="submitTaskUploadPrompt">
                            <span class="cu-dropzone-cloud-icon">☁️</span>
                            <span class="cu-dropzone-main-text"><?= __('Board::modal_submit_task_drop') ?> <span class="cu-dropzone-link-text"><?= __('Board::modal_submit_task_drop_select') ?></span></span>
                            <span class="cu-dropzone-sub-text"><?= __('Board::modal_submit_task_drop_sub') ?></span>
                        </div>
                        <div id="submitTaskFilePreview" class="cu-submit-task-preview">
                            <div id="submitTaskPreviewThumb"></div>
                            <div class="cu-preview-info">
                                <div id="submitTaskPreviewName" class="cu-preview-name"></div>
                                <div id="submitTaskPreviewSize" class="cu-preview-size"></div>
                            </div>
                            <button type="button" id="btnClearSubmitTaskFile" class="cu-btn-clear-preview">✕</button>
                        </div>
                    </div>
                </div>

                <div class="cu-form-group">
                    <label><?= __('Board::modal_submit_task_link') ?></label>
                    <input type="text" name="attachment_url" id="submitTaskAttachmentUrl" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Board::modal_submit_task_link_ph')) ?>">
                </div>
            </div>
            <div class="cu-modal-footer cu-modal-footer-clean">
                <button type="button" class="cu-btn-sm" data-close-modal="modalSubmitTask"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-submit"><?= __('Board::modal_submit_task_submit') ?></button>
            </div>
        </form>
    </div>
</div>
