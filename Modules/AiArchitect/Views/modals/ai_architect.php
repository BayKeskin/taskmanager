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
        <div class="cu-modal-header cu-ai-arch-header">
            <div class="cu-ai-arch-header-left">
                <span class="cu-ai-arch-header-icon">🪄</span>
                <div>
                    <h3 class="cu-ai-arch-header-title"><?= __('AiArchitect::ai_arch_title') ?></h3>
                    <div class="cu-ai-arch-header-subtitle"><?= __('AiArchitect::ai_arch_subtitle') ?></div>
                </div>
            </div>
            <button type="button" onclick="closeModal('modalAiArchitect')" class="cu-ai-arch-close-btn">&times;</button>
        </div>

        <div class="cu-modal-body cu-ai-arch-body">
            <!-- ADIM 1: BRİF GİRİŞ EKRANI -->
            <div id="aiArchStep1">
                <div class="cu-ai-arch-step-grid">
                    <div class="cu-ai-arch-input-col">
                        <div>
                            <label class="cu-ai-arch-label-primary"><?= __('AiArchitect::ai_arch_brief_label') ?></label>
                            <textarea id="aiArchBriefInput" rows="6" class="cu-ai-arch-brief-textarea" placeholder="<?= htmlspecialchars(__('AiArchitect::ai_arch_brief_ph')) ?>"></textarea>
                        </div>

                        <!-- SÜRÜKLE BIRAK WORD / DÖKÜMAN YÜKLEME ALANI -->
                        <div id="aiArchDropzone" class="cu-ai-arch-dropzone"
                             onclick="document.getElementById('aiArchFileInput').click()"
                             ondragover="handleAiArchDragOver(event)"
                             ondragleave="handleAiArchDragLeave(event)"
                             ondrop="handleAiArchFileDrop(event)">
                            <input type="file" id="aiArchFileInput" accept=".docx,.doc,.pdf,.txt,.md,.png,.jpg,.jpeg,.webp" class="cu-hidden-file-input" onchange="handleAiArchFileSelect(this)">
                            <div id="aiArchFilePrompt">
                                <span class="cu-ai-arch-drop-icon">📄 🖼️</span>
                                <div class="cu-ai-arch-drop-title"><?= __('AiArchitect::ai_arch_dropzone_title') ?></div>
                                <div class="cu-ai-arch-drop-desc"><?= __('AiArchitect::ai_arch_dropzone_desc') ?></div>
                            </div>
                            <div id="aiArchFileSelected" class="cu-ai-arch-file-selected" onclick="event.stopPropagation()">
                                <div class="cu-ai-arch-selected-info">
                                    <span class="cu-ai-arch-drop-icon">📎</span>
                                    <div>
                                        <div id="aiArchFileName" class="cu-ai-arch-selected-name"></div>
                                        <div id="aiArchFileSize" class="cu-ai-arch-selected-size"></div>
                                    </div>
                                </div>
                                <button type="button" onclick="clearAiArchFile(event)" class="cu-ai-arch-btn-remove">✕ <?= __('AiArchitect::ai_arch_remove_file') ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="cu-ai-arch-side-box">
                        <div>
                            <label class="cu-ai-arch-label-white"><?= __('AiArchitect::ai_arch_target_project') ?></label>
                            <select id="aiArchProjectSelect" class="cu-ai-arch-select">
                                <?php foreach ($aiArchProjects as $p): ?>
                                    <option value="<?= $p->id ?>" <?= (isset($activeProjectId) && $activeProjectId == $p->id) ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($p->project_code) ?>] <?= htmlspecialchars($p->title) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="cu-ai-arch-guarantee-text">
                                <b><?= __('AiArchitect::ai_arch_guarantee_title') ?></b><br>
                                <?= __('AiArchitect::ai_arch_guarantee_1') ?><br>
                                <?= __('AiArchitect::ai_arch_guarantee_2') ?><br>
                                <?= __('AiArchitect::ai_arch_guarantee_3') ?>
                            </div>
                        </div>
                        <button type="button" id="btnRunAiDecompose" onclick="runAiBriefDecomposition()" class="cu-ai-arch-btn-run">
                            <?= __('AiArchitect::ai_arch_btn_decompose') ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ADIM 2: SÜRÜKLE-BIRAK İNTERAKTİF AĞAÇ ÖNİZLEME EKRANI -->
            <div id="aiArchStep2" class="cu-initially-hidden">
                <div class="cu-ai-arch-tip-banner">
                    <div class="cu-ai-arch-tip-text">
                        <?= __('AiArchitect::ai_arch_drag_tip') ?>
                    </div>
                    <div class="cu-ai-arch-actions-wrap">
                        <button type="button" onclick="toggleAllSubtaskDetails()" class="cu-btn-sm cu-ai-arch-btn-sub">
                            <?= __('AiArchitect::ai_arch_toggle_all_details') ?>
                        </button>
                        <button type="button" onclick="addNewBlankEpic()" class="cu-btn-sm cu-ai-arch-btn-epic">
                            <?= __('AiArchitect::ai_arch_add_epic') ?>
                        </button>
                        <button type="button" onclick="document.getElementById('aiArchStep2').style.display='none'; document.getElementById('aiArchStep1').style.display='block';" class="cu-btn-sm cu-ai-arch-btn-back">
                            <?= __('AiArchitect::ai_arch_back_to_brief') ?>
                        </button>
                    </div>
                </div>

                <!-- DÖKÜMANDAN ÇIKARILAN GÖRSELLER ÖNİZLEME BARI -->
                <div id="aiArchExtractedImagesBar" class="cu-ai-arch-extracted-bar cu-initially-hidden">
                    <div class="cu-ai-arch-ext-title"><?= __('AiArchitect::ai_arch_extracted_images') ?></div>
                    <div id="aiArchExtractedImagesList" class="cu-ai-arch-ext-list"></div>
                </div>

                <div id="aiArchEpicsContainer" class="cu-ai-arch-epics-list"></div>
            </div>
        </div>

        <div class="cu-modal-footer cu-ai-arch-footer">
            <span id="aiArchSummaryBadge" class="cu-ai-arch-badge-text"></span>
            <div class="cu-ai-arch-footer-actions">
                <button type="button" class="cu-btn-sm cu-ai-arch-btn-cancel" onclick="closeModal('modalAiArchitect')"><?= __('AiArchitect::btn_cancel') ?></button>
                <button type="button" id="btnApproveAiArch" onclick="submitAiArchitectTree()" class="cu-ai-arch-btn-approve">
                    <?= __('AiArchitect::ai_arch_btn_approve') ?>
                </button>
            </div>
        </div>
    </div>
</div>
