<!-- 4. GÖREVİ TESLİM ETME MODALI (KULLANICI) -->
<div class="cu-modal-overlay" id="modalSubmitTask">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 style="font-size:15px; font-weight:700; color:#9b5de5; display:flex; align-items:center; gap:8px;">
                <span>🚀</span> <?= __('app.modal_submit_task_title') ?>
            </h3>
            <button onclick="closeModal('modalSubmitTask')" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">✕</button>
        </div>
        <form id="formSubmitTask" onsubmit="handleSubmitTask(event)">
            <input type="hidden" name="task_id" id="submitTaskId">
            <div class="cu-modal-body">
                <p style="font-size:12.5px; color:var(--text-secondary); margin-bottom:14px;">
                    <?= __('app.modal_submit_task_desc') ?>
                </p>
                <div class="cu-form-group">
                    <label><?= __('app.modal_submit_task_notes') ?></label>
                    <textarea name="notes" class="cu-form-control" rows="3" placeholder="<?= htmlspecialchars(__('app.modal_submit_task_notes_ph')) ?>" required></textarea>
                </div>
                <div class="cu-form-group">
                    <label style="display:flex; justify-content:space-between; align-items:center;">
                        <span>📎 <?= __('app.modal_submit_task_attach') ?></span>
                        <span style="font-size:11px; color:var(--text-muted);"><?= __('app.modal_submit_task_attach_hint') ?></span>
                    </label>

                    <!-- Drag & Drop & Paste Alanı -->
                    <div id="submitTaskDropzone" 
                         ondragover="handleSubmitDropzoneOver(event)" 
                         ondragleave="handleSubmitDropzoneLeave(event)" 
                         ondrop="handleSubmitDropzoneDrop(event)"
                         onclick="document.getElementById('submitTaskFileInput').click()"
                         style="border:2px dashed var(--border-subtle); border-radius:var(--radius-md); padding:16px; text-align:center; cursor:pointer; background:rgba(255,255,255,0.02); transition:all 0.2s;">
                        <input type="file" id="submitTaskFileInput" name="attachment" onchange="handleSubmitFileSelect(this)" style="display:none;" accept="image/*,.pdf,.zip,.rar,.7z,.txt,.doc,.docx,.xls,.xlsx">
                        <div id="submitTaskUploadPrompt">
                            <span style="font-size:24px; display:block; margin-bottom:4px;">☁️</span>
                            <span style="font-size:12.5px; font-weight:600; color:var(--text-white);"><?= __('app.modal_submit_task_drop') ?> <span style="color:var(--brand-primary); text-decoration:underline;"><?= __('app.modal_submit_task_drop_select') ?></span></span>
                            <span style="font-size:11px; color:var(--text-muted); display:block; margin-top:3px;"><?= __('app.modal_submit_task_drop_sub') ?></span>
                        </div>
                        <div id="submitTaskFilePreview" style="display:none; align-items:center; justify-content:center; gap:10px;">
                            <div id="submitTaskPreviewThumb"></div>
                            <div style="text-align:left;">
                                <div id="submitTaskPreviewName" style="font-size:12.5px; font-weight:600; color:var(--text-white);"></div>
                                <div id="submitTaskPreviewSize" style="font-size:11px; color:var(--text-muted);"></div>
                            </div>
                            <button type="button" onclick="clearSubmitTaskFile(event)" style="background:rgba(231,76,60,0.2); border:none; color:#e74c3c; width:24px; height:24px; border-radius:50%; cursor:pointer; font-size:12px; margin-left:10px;">✕</button>
                        </div>
                    </div>
                </div>

                <div class="cu-form-group">
                    <label><?= __('app.modal_submit_task_link') ?></label>
                    <input type="text" name="attachment_url" id="submitTaskAttachmentUrl" class="cu-form-control" placeholder="<?= htmlspecialchars(__('app.modal_submit_task_link_ph')) ?>">
                </div>
            </div>
            <div class="cu-modal-footer" style="padding:14px 20px; background:var(--bg-card-hover); border-top:1px solid var(--border-subtle); display:flex; justify-content:flex-end; gap:10px; align-items:center;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalSubmitTask')"><?= __('app.modal_cancel') ?></button>
                <button type="submit" class="cu-btn-submit"><?= __('app.modal_submit_task_submit') ?></button>
            </div>
        </form>
    </div>
</div>
