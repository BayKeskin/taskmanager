<!-- 🪄 AI PROJE MİMARI & SÜRÜKLE-BIRAK GÖREV AYRIŞTIRMA MODALI -->
<?php
$aiArchProjects = $projects ?? [];
if (empty($aiArchProjects)) {
    $pm = new \Modules\Projects\Models\ProjectModel();
    $aiArchProjects = $pm->getProjects();
}
?>
<div class="cu-modal-overlay" id="modalAiArchitect" onclick="if(event.target === this) closeModal('modalAiArchitect')">
    <div class="cu-modal cu-ai-arch-modal">
        <div class="cu-modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-subtle); padding:16px 22px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:24px;">🪄</span>
                <div>
                    <h3 style="font-size:16px; font-weight:800; color:var(--text-white); margin:0;"><?= __('app.ai_arch_title') ?></h3>
                    <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px;"><?= __('app.ai_arch_subtitle') ?></div>
                </div>
            </div>
            <button type="button" onclick="closeModal('modalAiArchitect')" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">&times;</button>
        </div>

        <div class="cu-modal-body" style="flex:1; overflow-y:auto; padding:20px;">
            <!-- ADIM 1: BRİF GİRİŞ EKRANI -->
            <div id="aiArchStep1">
                <div class="cu-ai-arch-step-grid">
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-secondary); display:block; margin-bottom:6px;"><?= __('app.ai_arch_brief_label') ?></label>
                            <textarea id="aiArchBriefInput" rows="6" class="cu-ai-arch-brief-textarea" placeholder="<?= htmlspecialchars(__('app.ai_arch_brief_ph')) ?>"></textarea>
                        </div>

                        <!-- SÜRÜKLE BIRAK WORD / DÖKÜMAN YÜKLEME ALANI -->
                        <div id="aiArchDropzone" class="cu-ai-arch-dropzone"
                             onclick="document.getElementById('aiArchFileInput').click()"
                             ondragover="handleAiArchDragOver(event)"
                             ondragleave="handleAiArchDragLeave(event)"
                             ondrop="handleAiArchFileDrop(event)">
                            <input type="file" id="aiArchFileInput" accept=".docx,.doc,.pdf,.txt,.md,.png,.jpg,.jpeg,.webp" style="display:none;" onchange="handleAiArchFileSelect(this)">
                            <div id="aiArchFilePrompt">
                                <span style="font-size:22px;">📄 🖼️</span>
                                <div style="font-size:12.5px; font-weight:700; color:var(--text-white); margin-top:4px;"><?= __('app.ai_arch_dropzone_title') ?></div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= __('app.ai_arch_dropzone_desc') ?></div>
                            </div>
                            <div id="aiArchFileSelected" class="cu-ai-arch-file-selected" onclick="event.stopPropagation()">
                                <div style="display:flex; align-items:center; gap:8px; text-align:left;">
                                    <span style="font-size:18px;">📎</span>
                                    <div>
                                        <div id="aiArchFileName" style="font-size:12px; font-weight:700; color:var(--text-white);"></div>
                                        <div id="aiArchFileSize" style="font-size:10.5px; color:#00b894;"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="clearAiArchFile(event)" style="background:rgba(255,71,87,0.2); border:none; color:#ff4757; border-radius:4px; padding:4px 8px; font-size:11px; font-weight:700; cursor:pointer;">✕ <?= __('app.ai_arch_remove_file') ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="cu-ai-arch-side-box">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-white); display:block; margin-bottom:6px;"><?= __('app.ai_arch_target_project') ?></label>
                            <select id="aiArchProjectSelect" style="width:100%; background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white); padding:8px; font-size:12.5px; margin-bottom:12px;">
                                <?php foreach ($aiArchProjects as $p): ?>
                                    <option value="<?= $p->id ?>" <?= (isset($activeProjectId) && $activeProjectId == $p->id) ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="font-size:11.5px; color:var(--text-secondary); line-height:1.5;">
                                <b><?= __('app.ai_arch_guarantee_title') ?></b><br>
                                <?= __('app.ai_arch_guarantee_1') ?><br>
                                <?= __('app.ai_arch_guarantee_2') ?><br>
                                <?= __('app.ai_arch_guarantee_3') ?>
                            </div>
                        </div>
                        <button type="button" id="btnRunAiDecompose" onclick="runAiBriefDecomposition()" class="cu-ai-arch-btn-run">
                            <?= __('app.ai_arch_btn_decompose') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ADIM 2: SÜRÜKLE-BIRAK İNTERAKTİF AĞAÇ ÖNİZLEME EKRANI -->
            <div id="aiArchStep2" style="display:none;">
                <div class="cu-ai-arch-tip-banner">
                    <div style="font-size:12.5px; color:var(--text-white); line-height:1.4;">
                        <?= __('app.ai_arch_drag_tip') ?>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" onclick="toggleAllSubtaskDetails()" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border:1px solid var(--border-subtle); color:var(--text-white); font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer;">
                            <?= __('app.ai_arch_toggle_all_details') ?>
                        </button>
                        <button type="button" onclick="addNewBlankEpic()" class="cu-btn-sm" style="background:rgba(123,104,238,0.2); border:1px solid #7B68EE; color:#fff; font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600;">
                            <?= __('app.ai_arch_add_epic') ?>
                        </button>
                        <button type="button" onclick="document.getElementById('aiArchStep2').style.display='none'; document.getElementById('aiArchStep1').style.display='block';" class="cu-btn-sm" style="background:rgba(255,255,255,0.08); border:1px solid var(--border-subtle); color:var(--text-secondary); font-size:11.5px; padding:6px 12px; border-radius:6px; cursor:pointer;">
                            <?= __('app.ai_arch_back_to_brief') ?>
                        </button>
                    </div>
                </div>

                <!-- DÖKÜMANDAN ÇIKARILAN GÖRSELLER ÖNİZLEME BARI -->
                <div id="aiArchExtractedImagesBar" class="cu-ai-arch-extracted-bar" style="display:none;">
                    <div style="font-size:11.5px; font-weight:700; color:#d8b4fe; margin-bottom:8px;"><?= __('app.ai_arch_extracted_images') ?></div>
                    <div id="aiArchExtractedImagesList" style="display:flex; gap:10px; overflow-x:auto; padding-bottom:4px;"></div>
                </div>

                <div id="aiArchEpicsContainer" style="display:flex; flex-direction:column; gap:16px;"></div>
            </div>
        </div>

        <div class="cu-modal-footer" style="display:flex; justify-content:space-between; align-items:center; padding:14px 22px; border-top:1px solid var(--border-subtle);">
            <span id="aiArchSummaryBadge" style="font-size:12px; color:var(--text-muted); font-weight:600;"></span>
            <div style="display:flex; gap:10px;">
                <button type="button" class="cu-btn-sm" onclick="closeModal('modalAiArchitect')" style="background:rgba(255,255,255,0.06); color:var(--text-secondary);"><?= __('app.btn_cancel') ?></button>
                <button type="button" id="btnApproveAiArch" onclick="submitAiArchitectTree()" style="display:none; background:#00b894; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:13px; font-weight:700; cursor:pointer;">
                    <?= __('app.ai_arch_btn_approve') ?>
                </button>
            </div>
        </div>
    </div>
</div>
