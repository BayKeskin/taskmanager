<?php
// TaskManager Kanban Panosu (HMVC Board Module View)
$boardController = new \Modules\Board\Controllers\BoardController();

$system->title = __('Board::kanban_board', 'Kanban Panosu - TaskManager');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$boardData = $boardController->index($activeProjectId);
extract($boardData);

// 2. Müşteri İçin Sadeleştirilmiş Kanban Sütunları
if ($isClient) {
    $columns = [
        'in_progress' => ['title' => __('tasks.col_client_dev', 'GELİŞTİRİLİYOR / ÇALIŞILIYOR'), 'color' => '#9b5de5', 'tasks' => []],
        'submitted'   => ['title' => __('tasks.col_client_sub', 'ONAYINIZI BEKLEYENLER (TESLİMATLAR)'), 'color' => '#e056fd', 'tasks' => []],
        'completed'   => ['title' => __('tasks.col_client_comp', 'TAMAMLANAN İŞLER'), 'color' => '#00b894', 'tasks' => []],
    ];
} else {
    $columns = [
        'assigned'               => ['title' => __('tasks.col_assigned', 'YAPILACAK İŞLER'), 'color' => '#4f8ff7', 'tasks' => []],
        'in_progress'            => ['title' => __('tasks.col_in_progress', 'ÇALIŞILIYOR'), 'color' => '#9b5de5', 'tasks' => []],
        'modification_requested' => ['title' => __('tasks.col_modification_requested', 'REVİZYON İSTENDİ'), 'color' => '#e67e22', 'tasks' => []],
        'submitted'              => ['title' => __('tasks.col_submitted', 'İNCELEMEDE'), 'color' => '#e056fd', 'tasks' => []],
        'completed'              => ['title' => __('tasks.col_completed', 'TAMAMLANDI'), 'color' => '#00b894', 'tasks' => []],
    ];
}

foreach ($allTasks as $t) {
    $st = ($t->status === 'accepted') ? 'in_progress' : $t->status;
    if ($isClient) {
        if ($st === 'assigned' || $st === 'created' || $st === 'modification_requested') {
            $columns['in_progress']['tasks'][] = $t;
        } elseif (isset($columns[$st])) {
            $columns[$st]['tasks'][] = $t;
        }
    } else {
        if (isset($columns[$st])) {
            $columns[$st]['tasks'][] = $t;
        } else {
            $columns['assigned']['tasks'][] = $t;
        }
    }
}
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📌</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= $isClient ? __('tasks.view_client_portal', 'Müşteri Portalı (Panom)') : __('tasks.view_board', 'Kanban Board') ?></span>
    </div>

    <div class="cu-filter-bar" style="display:flex; align-items:center; gap:8px;">
        <?php if ($canCreateTask && !$isClient): ?>
            <button type="button" class="cu-btn-sm" onclick="openAiArchitectModal()" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, #7B68EE, #e056fd); border:none; color:#ffffff; font-weight:700; padding:0 14px; font-size:12px; border-radius:6px; cursor:pointer; box-shadow:0 4px 12px rgba(123,104,238,0.35); box-sizing:border-box;" title="<?= htmlspecialchars(__('AiArchitect::tooltip_ai_architect', 'AI Mimar ile Görev Oluştur')) ?>">
                <span>🪄</span> <span><?= __('AiArchitect::btn_ai_architect', 'AI Mimar') ?></span>
            </button>
        <?php endif; ?>

        <!-- 🛠️ ARAÇLAR (TOOLS) DROPDOWN -->
        <div class="cu-tools-dropdown-wrap" style="position:relative; display:inline-block;">
            <button type="button" id="btnKanbanTools" class="cu-btn-sm" onclick="toggleKanbanToolsDropdown(event)" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid var(--border-subtle); color:var(--text-white); font-weight:600; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box; transition:all 0.2s ease;" title="<?= htmlspecialchars(__('Board::kanban_tools_desc', 'Raporlama, DevOps, Dağıtım ve Dışa Aktarma Araçları')) ?>">
                <span>🛠️</span>
                <span><?= __('Board::kanban_tools', 'Araçlar') ?></span>
                <span style="font-size:10px; opacity:0.7; margin-left:2px;">▼</span>
            </button>
            <div id="kanbanToolsMenu" style="display:none; position:absolute; top:calc(100% + 8px); right:0; min-width:260px; background:#1b1d29; border:1px solid rgba(255,255,255,0.14); border-radius:10px; box-shadow:0 14px 35px rgba(0,0,0,0.65); padding:6px; z-index:1050; backdrop-filter:blur(16px);">
                <!-- PDF Hakediş Raporu -->
                <button type="button" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); downloadDeliveryReportPdf(<?= (int)($activeProjectId ?? 0) ?>);">
                    <span style="font-size:16px; width:22px; text-align:center;">📄</span>
                    <span style="flex:1;"><?= __('tasks.btn_pdf_report', 'PDF Hakediş Raporu') ?></span>
                </button>

                <!-- AI Standup Özeti -->
                <button type="button" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); openAiStandupModal();">
                    <span style="font-size:16px; width:22px; text-align:center;">🤖</span>
                    <span style="flex:1;"><?= __('tasks.btn_ai_standup', 'AI Standup Özeti') ?></span>
                </button>

                <?php if (!empty($activeProjectId)): ?>
                    <!-- Excel'e Aktar -->
                    <a href="/taskmanager/kanban/export-excel?project_id=<?= (int)$activeProjectId ?>" 
                       id="btnExportExcel" 
                       onclick="closeKanbanToolsDropdown()" 
                       target="_blank"
                       class="cu-tools-item"
                       style="color:#2ecc71 !important;">
                        <span style="font-size:16px; width:22px; text-align:center;">📊</span>
                        <span style="flex:1; font-weight:600;"><?= __('Board::kanban_export_excel', 'Excel\'e Aktar') ?></span>
                    </a>
                <?php endif; ?>

                <?php if (!$isClient): 
                    \Modules\GithubDevops\Services\GitService::ensureGithubAndRollbackSchema();
                    $ghCreds = \Modules\GithubDevops\Services\GitService::getGithubCredentials();
                ?>
                    <div style="height:1px; background:rgba(255,255,255,0.08); margin:5px 0;"></div>

                    <!-- GitHub Ayarları -->
                    <button type="button" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); openGithubSettingsModal();">
                        <span style="font-size:16px; width:22px; text-align:center;">🐙</span>
                        <span style="flex:1;"><?= !empty($ghCreds['username']) ? htmlspecialchars($ghCreds['username']) : __('Board::btn_connect_github', 'GitHub Bağla') ?></span>
                        <span style="font-size:10px; color:var(--text-muted); background:rgba(255,255,255,0.06); padding:2px 6px; border-radius:4px;">DevOps</span>
                    </button>

                    <?php if (!empty($activeProjectId)): ?>
                        <!-- GitHub Sync -->
                        <button type="button" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); syncProjectToGithub(<?= (int)$activeProjectId ?>);" style="color:#3fb950 !important;">
                            <span style="font-size:16px; width:22px; text-align:center;">🔄</span>
                            <span style="flex:1; font-weight:600;"><?= __('Board::btn_sync_github', 'GitHub Sync') ?></span>
                        </button>

                        <!-- Canlı Dağıtım (Deploy Wizard) -->
                        <button type="button" id="btnOpenDeployWizard" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); openDeployWizard(<?= (int)$activeProjectId ?>);" style="color:#00cec9 !important;">
                            <span style="font-size:16px; width:22px; text-align:center;">🚀</span>
                            <span style="flex:1; font-weight:700;"><?= __('Board::deploy_btn_wizard', 'Canlı Dağıtım') ?></span>
                        </button>

                        <!-- MCP Tools -->
                        <button type="button" id="btnOpenProjectMcp" class="cu-tools-item" onclick="closeKanbanToolsDropdown(); openProjectMcpModal(<?= (int)$activeProjectId ?>);" style="color:#f39c12 !important;">
                            <span style="font-size:16px; width:22px; text-align:center;">🧩</span>
                            <span style="flex:1; font-weight:600;"><?= __('Board::mcp_btn_open', 'MCP Tools') ?></span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canCreateTask): ?>
            <button type="button" class="cu-btn-quick-task" onclick="openNewTaskModal()" style="height:32px; padding:0 14px; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
                <span>➕</span> <?= __('tasks.btn_add_task', 'Görev Ekle') ?>
            </button>
        <?php endif; ?>
    </div>
</div>


<!-- TaskManager YAN YANA FİLTRE & SIRALAMA TOOLBAR'I -->
<?php include dirname(__DIR__, 3) . '/Core/Views/filter_toolbar.php'; ?>

<!-- PROJE BİLGİ KARTI (FİLTRELİ İSE) -->
<?php if ($projectInfo): ?>
    <div style="background:var(--bg-card); border-bottom:1px solid var(--border-subtle); padding:12px 24px; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:32px; height:32px; border-radius:8px; background:<?= htmlspecialchars($projectInfo->color) ?>; display:flex; align-items:center; justify-content:center; font-size:16px;">
                📁
            </div>
            <div>
                <h2 style="font-size:14px; font-weight:700; color:var(--text-white); margin:0;">
                    <?= htmlspecialchars($projectInfo->title) ?> 
                    <span style="font-size:11px; color:var(--text-muted); font-weight:500;">(<?= htmlspecialchars($projectInfo->project_code) ?>)</span>
                </h2>
                <p style="font-size:12px; color:var(--text-secondary); margin:2px 0 0 0;">
                    <?= htmlspecialchars($projectInfo->description ?: __('tasks.no_project_desc', 'Proje açıklaması belirtilmemiş.')) ?>
                </p>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <?php if ($currentUser->role === 'admin'): ?>
                <?php if ($projectInfo->status === 'completed' || $projectInfo->status === 'archived'): ?>
                    <button type="button" onclick="toggleProjectStatus(<?= $projectInfo->id ?>, 'active')" class="cu-btn-sm" style="background:rgba(123, 104, 238, 0.15); color:var(--brand-primary); border:1px solid rgba(123, 104, 238, 0.3); font-size:11.5px; padding:4px 10px;">
                        <?= __('tasks.btn_restore_active', '↺ Aktif Projelere Geri Yükle') ?>
                    </button>
                    <button type="button" onclick="deleteProjectPermanently(<?= $projectInfo->id ?>)" class="cu-btn-sm" style="background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3); font-size:11.5px; padding:4px 10px;">
                        <?= __('tasks.btn_delete_project', '🗑️ Projeyi Sil') ?>
                    </button>
                <?php else: ?>
                    <button type="button" onclick="toggleProjectStatus(<?= $projectInfo->id ?>, 'completed')" class="cu-btn-sm" style="background:var(--bg-card-hover); color:var(--text-primary); border:1px solid var(--border-subtle); font-size:11.5px; padding:4px 10px;" title="<?= htmlspecialchars(__('tasks.tooltip_move_archive', 'Projeyi Geçmiş Projelere Taşı')) ?>">
                        <?= __('tasks.btn_move_archive', '📦 Geçmiş Projelere Taşı') ?>
                    </button>
                <?php endif; ?>
                <a href="/taskmanager/board" style="color:var(--text-muted); font-size:11.5px; text-decoration:none; padding:4px 10px; border:1px solid var(--border-subtle); border-radius:var(--radius-sm);">
                    <?= __('tasks.btn_clear_filter', '✕ Filtreyi Kaldır') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- MOBİLDE ACCORDION GÖREVLERİN ÜZERİNDE YENİ GÖREV BUTONU -->
<?php if (!$isClient): ?>
    <div class="cu-mobile-quick-task-bar">
        <button type="button" class="cu-btn-quick-task" onclick="openNewTaskModal()">
            <span>➕</span>
            <span><?= __('tasks.btn_new_task', 'Yeni Görev Ekle') ?></span>
        </button>
    </div>
<?php endif; ?>

<div class="cu-board-container">
    <?php 
    $today = date('Y-m-d');
    $prLabels = [
        'urgent' => __('tasks.priority_urgent_badge', '🔴 Acil'),
        'high'   => __('tasks.priority_high_badge', '🟡 Yüksek'),
        'normal' => __('tasks.priority_normal_badge', '🔵 Normal'),
        'low'    => __('tasks.priority_low_badge', '⚪ Düşük'),
    ];
    foreach ($columns as $cKey => $col): 
        $cCount = count($col['tasks']);
    ?>
        <div class="cu-board-col active" data-col-status="<?= $cKey ?>">
            <div class="cu-board-col-header" onclick="toggleColAccordion(this)">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $col['color'] ?>;"></span>
                    <span style="font-size:12px; font-weight:700; color:var(--text-white); letter-spacing:0.4px;"><?= $col['title'] ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span class="cu-nav-badge"><?= $cCount ?></span>
                    <span class="cu-col-accordion-icon">▼</span>
                </div>
            </div>

            <div class="cu-board-cards" 
                 data-status="<?= $cKey ?>"
                 <?= !$isClient ? 'ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, \''.$cKey.'\')"' : '' ?>>
                <?php foreach ($col['tasks'] as $task): 
                    $isOverdue = (!empty($task->deadline) && $task->deadline < $today && $task->status !== 'completed');
                    $tAssignees = $assigneesMap[$task->id] ?? [];
                    if (empty($tAssignees) && !empty($task->assigned_to)) {
                        $tAssignees = [(object)['user_id' => $task->assigned_to, 'name' => $task->assignee_name, 'avatar_color' => $task->assignee_color]];
                    }
                    $assigneeIdsStr = implode(',', array_map(fn($a) => $a->user_id, $tAssignees));
                ?>
                    <div class="cu-board-card" 
                         draggable="<?= $isClient ? 'false' : 'true' ?>"
                         data-task-id="<?= $task->id ?>"
                         data-title="<?= htmlspecialchars(mb_strtolower($task->title)) ?>"
                         data-code="<?= htmlspecialchars(mb_strtolower($task->task_code)) ?>"
                         data-tags="<?= htmlspecialchars(mb_strtolower($task->tags ?? '')) ?>"
                         data-priority="<?= $task->priority ?>"
                         data-assignee-ids="<?= $assigneeIdsStr ?>"
                         data-assignee-id="<?= $task->assigned_to ?>"
                         data-deadline="<?= $task->deadline ?: '9999-12-31' ?>"
                         <?= !$isClient ? 'ondragstart="handleDragStart(event, '.$task->id.')" ondragend="handleDragEnd(event)"' : '' ?>
                         onclick="openTaskDrawer(<?= $task->id ?>)">
                        <div style="display:flex; align-items:center; justify-content:space-between;">
                            <span class="cu-task-code"><?= htmlspecialchars($task->task_code) ?></span>
                            <span class="cu-badge-project" style="color:<?= htmlspecialchars($task->project_color ?? '#7B68EE') ?>;">
                                <?= htmlspecialchars($task->project_title ?? __('tasks.project', 'Proje')) ?>
                            </span>
                        </div>

                        <div class="cu-board-card-title">
                            <?= htmlspecialchars($task->title) ?>
                        </div>

                        <?php
                        $isAiTask = false;
                        if (!empty($task->tags)) {
                            $rawTags = array_map('trim', explode(',', mb_strtolower($task->tags)));
                            foreach ($rawTags as $tg) {
                                if (in_array($tg, ['ai', '#ai', 'otonom', 'agent', 'yapay zeka'])) {
                                    $isAiTask = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <div style="margin: 6px 0; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span class="cu-priority-badge cu-priority-<?= $task->priority ?>" style="font-size:10.5px; padding:2px 6px;">
                                <?= $prLabels[$task->priority] ?? $task->priority ?>
                            </span>
                            <?php if ($isAiTask && !$isClient): ?>
                                <span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(155, 93, 229, 0.2); border:1px solid rgba(155, 93, 229, 0.45); color:#d8b4fe; font-weight:700; display:inline-flex; align-items:center; gap:3px;" title="<?= htmlspecialchars(__('tasks.ai_autonomous_title', '🤖 Otonom Kuyruk: Sırası gelince yapay zeka otomatik başlar')) ?>">
                                    <span>🤖</span><span><?= __('tasks.ai_autonomous_badge', 'AI Otonom') ?></span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="cu-board-card-footer">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="cu-deadline <?= $isOverdue ? 'overdue' : '' ?>" style="font-size:11px;">
                                    <span><?= $isOverdue ? '⚠️ ' : '📅 ' ?></span>
                                    <span><?= htmlspecialchars($task->deadline ?: __('tasks.no_deadline')) ?></span>
                                </div>
                                <?php $attCount = $attachmentCounts[$task->id] ?? 0; ?>
                                <?php if ($attCount > 0): ?>
                                    <span style="font-size:10.5px; color:var(--text-muted); display:inline-flex; align-items:center; gap:2px; font-weight:600;" title="<?= htmlspecialchars(__('tasks.attachment_count_title', ['count' => $attCount])) ?>">
                                        <span>📎</span><span><?= $attCount ?></span>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($task->subtask_count)): ?>
                                    <span style="font-size:10.5px; color:var(--brand-primary); background:rgba(123, 104, 238, 0.12); padding:1px 6px; border-radius:4px; font-weight:600; display:inline-flex; align-items:center; gap:3px;" title="<?= htmlspecialchars(__('tasks.subtasks')) ?>">
                                        <span>↳</span><span><?= (int)$task->subtask_completed_count ?>/<?= (int)$task->subtask_count ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Yan Yana Dizilen Çalışan Avatarları -->
                            <div class="cu-card-avatars" style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                <?php foreach ($tAssignees as $asg): ?>
                                    <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>;" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                                        <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- MÜŞTERİ ONAY / DÜZELTME AKSİYONLARI -->
                        <?php if ($isClient && $cKey === 'submitted'): ?>
                            <div style="display:flex; gap:6px; margin-top:10px; border-top:1px solid rgba(255,255,255,0.08); padding-top:8px;" onclick="event.stopPropagation();">
                                <button type="button" onclick="clientApproveTask(<?= $task->id ?>)" style="flex:1; background:#00b894; color:#fff; font-size:11px; padding:6px; border:none; border-radius:4px; cursor:pointer; font-weight:700; display:flex; align-items:center; justify-content:center; gap:4px;" title="<?= htmlspecialchars(__('tasks.btn_approve', 'Teslimatı Onayla ve Tamamla')) ?>">
                                    <span>✅</span><span><?= __('tasks.btn_approve', 'Onayla') ?></span>
                                </button>
                                <button type="button" onclick="clientRequestChanges(<?= $task->id ?>)" style="flex:1; background:#e67e22; color:#fff; font-size:11px; padding:6px; border:none; border-radius:4px; cursor:pointer; font-weight:700; display:flex; align-items:center; justify-content:center; gap:4px;" title="<?= htmlspecialchars(__('tasks.btn_request_changes_short', 'Düzeltme / Revizyon İste')) ?>">
                                    <span>⚠️</span><span><?= __('tasks.btn_request_changes_short', 'Düzeltme İste') ?></span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
window.BOARD_CONTEXT = {
    projectId: <?= json_encode($activeProjectId ? (string)$activeProjectId : null) ?>,
    userEmail: <?= json_encode($currentUser->email ?? '') ?>
};
window.BOARD_I18N = {
    approve_confirm: <?= json_encode(__('tasks.client_approve_confirm', 'Bu görevin teslimatını onaylayıp tamamlandı olarak işaretlemek istiyor musunuz?')) ?>,
    approve_success: <?= json_encode(__('tasks.client_approve_success', '✅ Teslimat başarıyla onaylandı ve görev tamamlandı!')) ?>,
    request_prompt: <?= json_encode(__('tasks.client_request_reason_prompt', 'Lütfen yapılmasını istediğiniz düzeltme veya revizyon notunu yazınız:')) ?>,
    request_success: <?= json_encode(__('tasks.client_request_success', '✅ Düzeltme talebiniz geliştirici ekibe iletildi!')) ?>,
    analyzing: <?= json_encode(__('tasks.ai_standup_analyzing', 'Yapay zeka son 24 saatin standup özetini analiz ediyor...')) ?>,
    period: <?= json_encode(__('tasks.ai_standup_period_label', 'Dönem Aralığı')) ?>,
    last_24h: <?= json_encode(__('tasks.ai_standup_last_24h', 'Son 24 Saat')) ?>,
    comp_stat: <?= json_encode(__('tasks.ai_standup_completed_stat', '✅ :count Tamamlanan / Teslim')) ?>,
    in_prog_stat: <?= json_encode(__('tasks.ai_standup_in_progress_stat', '⚡ :count Aktif Çalışılan')) ?>,
    delayed_stat: <?= json_encode(__('tasks.ai_standup_delayed_stat', '⚠️ :count Riskli / Geciken')) ?>,
    email_prompt: <?= json_encode(__('tasks.ai_standup_email_prompt', 'Bültenin gönderileceği e-posta adresini giriniz:')) ?>,
    email_success: <?= json_encode(__('tasks.ai_standup_email_success', '✅ Standup e-posta bülteni başarıyla gönderildi: ')) ?>,
    err_fetch: <?= json_encode(__('tasks.ai_standup_err_fetch', 'Özet alınamadı: ')) ?>,
    err_conn: <?= json_encode(__('tasks.ai_standup_err_conn', 'Bağlantı hatası: ')) ?>,
    server_error: <?= json_encode(__('tasks.server_error', 'Sunucu hatası: ')) ?>,
    error_prefix: <?= json_encode(__('tasks.error_prefix', '❌ Hata: ')) ?>
};
</script>

<!-- GÜNLÜK AI STANDUP MODALI -->
<?php include __DIR__ . '/modals/ai_standup.php'; ?>

<!-- 🐙 GITHUB DEVOPS AYARLARI MODALI -->


<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>

