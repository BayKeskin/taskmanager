<!-- 1. YENİ GÖREV EKLEME MODALI -->
<div class="cu-modal-overlay" id="modalNewTask">
    <div class="cu-modal">
        <div class="cu-modal-header">
            <h3 class="cu-modal-title-white">
                <span>➕</span> <?= __('Board::modal_new_task_title') ?>
            </h3>
            <button type="button" class="cu-modal-close-icon" data-close-modal="modalNewTask">✕</button>
        </div>
        <form id="formNewTask">
            <div class="cu-modal-body">
                <div class="cu-form-group">
                    <label><?= __('Board::modal_project_space') ?></label>
                    <select name="project_id" id="modalNewTaskProjectSelect" class="cu-form-control" required>
                        <?php 
                        $curProjId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : ($activeProjectId ?? null);
                        foreach ($allProjects as $p): 
                            $isSelected = ($curProjId && (int)$p->id === (int)$curProjId) ? 'selected' : '';
                        ?>
                            <option value="<?= $p->id ?>" <?= $isSelected ?>><?= htmlspecialchars($p->title) ?> (<?= $p->project_code ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cu-form-group">
                    <label><?= __('Board::modal_task_title') ?></label>
                    <input type="text" name="title" id="modalNewTaskTitleInput" class="cu-form-control" placeholder="<?= htmlspecialchars(__('Board::modal_task_title_placeholder')) ?>" required>
                </div>

                <!-- 🪄 AI GÖREV MİMARI (TASK ARCHITECT PANEL) -->
                <div class="cu-form-group cu-ai-architect-card-wrap">
                    <div class="cu-ai-architect-card-header" id="btnToggleAiArchitectPanel">
                        <div class="cu-ai-architect-header-left">
                            <span class="cu-ai-architect-wand">🪄</span>
                            <span class="cu-ai-architect-badge-text">
                                <?= __('Board::modal_ai_architect_badge') ?>
                            </span>
                            <span class="cu-ai-architect-sub-hint d-none d-sm-inline">
                                — <?= __('Board::modal_ai_architect_title') ?>
                            </span>
                        </div>
                        <div class="cu-ai-architect-header-right">
                            <span id="aiArchitectToggleIcon" class="cu-ai-architect-caret">▼</span>
                        </div>
                    </div>

                    <!-- Açılır Panel Gövdesi -->
                    <div id="aiArchitectPanelBody" class="cu-ai-architect-body-panel">
                        <p class="cu-ai-architect-p-desc">
                            <?= __('Board::modal_ai_architect_desc') ?>
                        </p>

                        <!-- Kısa Talimat Alanı (Zorunlu) -->
                        <div class="cu-field-spacing-sm">
                            <label class="cu-ai-prompt-label">
                                <?= __('Board::modal_ai_architect_prompt_label') ?> <span class="cu-req-star">*</span>
                            </label>
                            <textarea id="aiArchitectInstruction" class="cu-form-control cu-ai-prompt-input" rows="2" placeholder="<?= htmlspecialchars(__('Board::modal_ai_architect_prompt_ph')) ?>"></textarea>
                        </div>

                        <!-- Opsiyonel Ekler (Görsel ve Kod Dosyası) -->
                        <div class="cu-field-spacing-sm">
                            <div class="cu-ref-header-flex">
                                <label class="cu-ref-label">
                                    <?= __('Board::modal_ai_architect_ref_label') ?>
                                </label>
                                <span class="cu-ref-hint"><?= __('Board::modal_ai_architect_ref_hint') ?></span>
                            </div>

                            <input type="file" id="aiArchitectFileInput" multiple accept="image/*,.php,.js,.blade.php,.sql,.html,.css,.json,.txt,.py" class="cu-hidden-file-input">

                            <!-- Sürükle Bırak / Tıkla Alanı -->
                            <div id="aiArchitectDropzone" class="cu-ai-architect-dropzone">
                                <span class="cu-dropzone-icon-pair">📁 📸</span>
                                <span class="cu-dropzone-select-label"><?= __('Board::ai_dropzone_select_label') ?></span>
                                <span class="cu-dropzone-paste-label"><?= __('Board::ai_dropzone_paste_label') ?></span>
                            </div>

                            <!-- Seçilen Referans Dosyalar Listesi -->
                            <div id="aiArchitectFilesPreview" class="cu-files-preview-flex"></div>
                        </div>

                        <!-- AI Üret Butonu -->
                        <div class="cu-btn-align-right">
                            <button type="button" id="btnRunAiArchitect" class="cu-btn-sm cu-btn-run-ai">
                                <span>🪄</span> <span id="btnRunAiArchitectText"><?= __('Board::modal_ai_architect_btn') ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="cu-form-group">
                    <div class="cu-form-label-between">
                        <label class="cu-mb-0"><?= __('Board::modal_desc_instructions') ?></label>
                        <span class="cu-label-hint"><?= __('tasks.click_to_edit') ?></span>
                    </div>
                    <textarea name="description" id="modalNewTaskDescription" class="cu-form-control" rows="5" placeholder="<?= htmlspecialchars(__('Board::modal_desc_placeholder')) ?>"></textarea>
                </div>

                <div class="cu-form-grid-2">
                    <div class="cu-form-group">
                        <label><?= __('Board::modal_priority') ?></label>
                        <select name="priority" class="cu-form-control">
                            <option value="urgent"><?= __('tasks.priority_urgent') ?></option>
                            <option value="high"><?= __('tasks.priority_high') ?></option>
                            <option value="normal" selected><?= __('tasks.priority_normal') ?></option>
                            <option value="low"><?= __('tasks.priority_low') ?></option>
                        </select>
                    </div>
                    <div class="cu-form-group">
                        <label><?= __('Board::modal_deadline') ?></label>
                        <input type="date" name="deadline" class="cu-form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>
                </div>

                <!-- ETİKETLER (TAGS) & AI OTONOM SEÇİMİ -->
                <div class="cu-form-group">
                    <div class="cu-form-label-between">
                        <label class="cu-mb-0"><?= __('Board::modal_tags') ?></label>
                        <span class="cu-label-hint"><?= __('Board::modal_tags_hint') ?></span>
                    </div>
                    <div class="cu-tag-input-row">
                        <input type="text" name="tags" id="modalNewTaskTagsInput" class="cu-form-control cu-flex-1" placeholder="<?= htmlspecialchars(__('Board::modal_tags_placeholder')) ?>">
                        <?php if ($canUseAiTasks): ?>
                            <button type="button" id="btnToggleAiTag" class="cu-btn-toggle-ai" title="<?= htmlspecialchars(__('Board::modal_ai_btn_title')) ?>">
                                <span>🤖</span> <span id="btnToggleAiTagText"><?= __('Board::modal_ai_btn') ?></span>
                            </button>
                        <?php else: ?>
                            <div class="cu-ai-locked-badge" title="<?= htmlspecialchars(__('Board::modal_ai_admin_only_title')) ?>">
                                <span>🔒</span> <span><?= __('Board::modal_ai_admin_only') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Hızlı Etiket Önerileri -->
                    <div class="cu-quick-tags-wrap">
                        <span class="cu-quick-tags-label"><?= __('Board::modal_quick_add_tags') ?></span>
                        <button type="button" class="cu-tag-pill" data-quick-tag="frontend">#frontend</button>
                        <button type="button" class="cu-tag-pill" data-quick-tag="backend">#backend</button>
                        <button type="button" class="cu-tag-pill" data-quick-tag="api">#api</button>
                        <button type="button" class="cu-tag-pill" data-quick-tag="bug">#bug</button>
                        <button type="button" class="cu-tag-pill" data-quick-tag="ui">#ui</button>
                    </div>
                </div>

                <!-- TaskManager ÇOKLU ÇALIŞAN ATAMA (@ MENTION SEÇİCİ) -->
                <div class="cu-form-group">
                    <div class="cu-form-label-between">
                        <label class="cu-mb-0"><?= __('Board::modal_assignees') ?></label>
                        <span class="cu-label-hint"><?= __('Board::modal_assignees_hint') ?></span>
                    </div>

                    <!-- Yan yana dizilen seçili çalışanlar alanı -->
                    <div id="modalSelectedAssignees" class="cu-selected-assignees-box">
                        <!-- JS ile dinamik chipler eklenecek -->
                    </div>

                    <!-- @ ile Arama Kutusu ve Popover Menü -->
                    <div class="cu-pos-relative">
                        <div class="cu-input-prefix-wrapper">
                            <span class="cu-mention-at">@</span>
                            <input type="text" id="modalAssigneeInput" class="cu-form-control cu-mention-input" placeholder="<?= htmlspecialchars(__('Board::modal_assignees_placeholder')) ?>" autocomplete="off">
                        </div>

                        <!-- @ Açılır Menüsü (Dropdown) -->
                        <div id="modalAssigneeDropdown" class="cu-assignee-dropdown-popover">
                            <!-- Dinamik kullanıcı listesi -->
                        </div>
                    </div>

                    <!-- Form Gönderimi için Gizli Inputlar -->
                    <div id="modalAssigneeHiddenInputs"></div>
                </div>

                <!-- GÖREV RESİMLERİ & EKLERİ (TASK IMAGES & ATTACHMENTS) -->
                <div class="cu-form-group cu-mb-0">
                    <div class="cu-form-label-between">
                        <label class="cu-attachments-label">
                            <span>🖼️</span> <?= __('Board::modal_attachments') ?>
                        </label>
                        <span class="cu-label-hint"><?= __('Board::modal_attachments_hint') ?></span>
                    </div>

                    <!-- Gizli File Input -->
                    <input type="file" id="modalNewTaskFileInput" name="attachments[]" multiple accept="image/*,.pdf,.zip,.rar,.7z,.txt,.docx,.xlsx,.sql,.php,.js,.html,.css,.json" class="cu-hidden-file-input">

                    <!-- Dropzone Alanı -->
                    <div id="modalNewTaskDropzone" class="cu-new-task-dropzone">
                        <div class="cu-dropzone-camera-icon">📸 📤</div>
                        <div class="cu-dropzone-header-title"><?= __('Board::modal_dropzone_title') ?></div>
                        <div class="cu-dropzone-subtitle"><?= __('Board::modal_dropzone_sub') ?></div>
                    </div>

                    <!-- Seçilen / Yapıştırılan Dosyaların Önizleme Listesi -->
                    <div id="modalNewTaskFilesPreview" class="cu-files-preview-wrap"></div>
                </div>
            </div>
            <div class="cu-modal-footer cu-modal-footer-clean">
                <button type="button" class="cu-btn-sm" data-close-modal="modalNewTask"><?= __('Board::modal_cancel') ?></button>
                <button type="submit" class="cu-btn-quick-task"><?= __('Board::modal_create_task') ?></button>
            </div>
        </form>
    </div>
</div>
