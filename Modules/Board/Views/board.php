<?php
// TaskManager Kanban Panosu (HMVC Board Module View)
$boardController = new \Modules\Board\Controllers\BoardController();
$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$boardData = $boardController->index($activeProjectId);
extract($boardData);

$system->title = __('Board::kanban_board');
include dirname(__DIR__, 3) . '/Core/Views/header.php';

// 2. Müşteri İçin Sadeleştirilmiş Kanban Sütunları
if ($isClient) {
    $columns = [
        'in_progress' => ['title' => __('Board::col_client_dev'), 'color' => '#9b5de5', 'tasks' => []],
        'submitted'   => ['title' => __('Board::col_client_sub'), 'color' => '#e056fd', 'tasks' => []],
        'completed'   => ['title' => __('Board::col_client_comp'), 'color' => '#00b894', 'tasks' => []],
    ];
} else {
    $columns = [
        'assigned'               => ['title' => __('Board::col_assigned'), 'color' => '#4f8ff7', 'tasks' => []],
        'in_progress'            => ['title' => __('Board::col_in_progress'), 'color' => '#9b5de5', 'tasks' => []],
        'modification_requested' => ['title' => __('Board::col_modification_requested'), 'color' => '#e67e22', 'tasks' => []],
        'submitted'              => ['title' => __('Board::col_submitted'), 'color' => '#e056fd', 'tasks' => []],
        'completed'              => ['title' => __('Board::col_completed'), 'color' => '#00b894', 'tasks' => []],
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
    <div class="cu-view-title-wrap cu-view-title-flex">
        <span class="cu-view-header-icon">📌</span>
        <span class="cu-view-header-title-text"><?= $isClient ? __('Board::view_client_portal') : __('Board::view_board') ?></span>
    </div>

    <div class="cu-filter-bar cu-filter-bar-flex">
        <?php if ($canCreateTask && !$isClient): ?>
            <button type="button" class="cu-btn-sm cu-btn-ai-architect" id="btnOpenAiArchitectModal" title="<?= htmlspecialchars(__('AiArchitect::tooltip_ai_architect')) ?>">
                <span>🪄</span> <span><?= __('AiArchitect::btn_ai_architect') ?></span>
            </button>
        <?php endif; ?>

        <!-- 🛠️ ARAÇLAR (TOOLS) DROPDOWN -->
        <div class="cu-tools-dropdown-wrap cu-tools-dropdown-pos">
            <button type="button" id="btnKanbanTools" class="cu-btn-sm cu-btn-tools-trigger" title="<?= htmlspecialchars(__('Board::kanban_tools_desc')) ?>">
                <span>🛠️</span>
                <span><?= __('Board::kanban_tools') ?></span>
                <span class="cu-caret-down-icon">▼</span>
            </button>
            <div id="kanbanToolsMenu" class="cu-tools-dropdown-menu">
                <!-- PDF Hakediş Raporu -->
                <button type="button" class="cu-tools-item" id="btnDownloadDeliveryPdf" data-project-id="<?= (int)($activeProjectId ?? 0) ?>">
                    <span class="cu-tool-icon">📄</span>
                    <span class="cu-flex-1"><?= __('Board::btn_pdf_report') ?></span>
                </button>

                <!-- AI Standup Özeti -->
                <button type="button" class="cu-tools-item" id="btnOpenAiStandupModal">
                    <span class="cu-tool-icon">🤖</span>
                    <span class="cu-flex-1"><?= __('Board::btn_ai_standup') ?></span>
                </button>

                <?php if (!empty($activeProjectId)): ?>
                    <!-- Excel'e Aktar -->
                    <a href="/taskmanager/kanban/export-excel?project_id=<?= (int)$activeProjectId ?>" 
                       id="btnExportExcel" 
                       target="_blank"
                       class="cu-tools-item cu-tools-item-excel">
                        <span class="cu-tool-icon">📊</span>
                        <span class="cu-tool-text-bold"><?= __('Board::kanban_export_excel') ?></span>
                    </a>
                <?php endif; ?>

                <?php if (!$isClient): ?>
                    <div class="cu-dropdown-divider"></div>

                    <!-- GitHub Ayarları -->
                    <button type="button" class="cu-tools-item" id="btnOpenGithubSettings">
                        <span class="cu-tool-icon">🐙</span>
                        <span class="cu-flex-1"><?= !empty($ghCreds['username']) ? htmlspecialchars($ghCreds['username']) : __('Board::btn_connect_github') ?></span>
                        <span class="cu-devops-badge">DevOps</span>
                    </button>

                    <?php if (!empty($activeProjectId)): ?>
                        <!-- GitHub Sync -->
                        <button type="button" class="cu-tools-item cu-tools-item-github" id="btnSyncGithub" data-project-id="<?= (int)$activeProjectId ?>">
                            <span class="cu-tool-icon">🔄</span>
                            <span class="cu-tool-text-bold"><?= __('Board::btn_sync_github') ?></span>
                        </button>

                        <!-- Canlı Dağıtım (Deploy Wizard) -->
                        <button type="button" id="btnOpenDeployWizard" class="cu-tools-item cu-tools-item-deploy" data-project-id="<?= (int)$activeProjectId ?>">
                            <span class="cu-tool-icon">🚀</span>
                            <span class="cu-tool-text-deploy"><?= __('Board::deploy_btn_wizard') ?></span>
                        </button>

                        <!-- MCP Tools -->
                        <button type="button" id="btnOpenProjectMcp" class="cu-tools-item cu-tools-item-mcp" data-project-id="<?= (int)$activeProjectId ?>">
                            <span class="cu-tool-icon">🧩</span>
                            <span class="cu-tool-text-bold"><?= __('Board::mcp_btn_open') ?></span>
                        </button>
                    <?php endif; ?>

                    <div class="cu-dropdown-divider"></div>

                    <!-- Smoke Test (Sistem Sağlık Taraması) -->
                    <button type="button" id="btnOpenSmokeTestModal" class="cu-tools-item cu-tools-item-smoketest">
                        <span class="cu-tool-icon">🔍</span>
                        <span class="cu-tool-text-bold"><?= __('SmokeTest::btn_run_smoketest') ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>


        <?php if ($canCreateTask): ?>
            <button type="button" class="cu-btn-quick-task cu-btn-add-task-header" id="btnOpenNewTaskModal">
                <span>➕</span> <?= __('Board::btn_add_task') ?>
            </button>
        <?php endif; ?>
    </div>
</div>


<!-- TaskManager YAN YANA FİLTRE & SIRALAMA TOOLBAR'I -->
<?php include dirname(__DIR__, 3) . '/Core/Views/filter_toolbar.php'; ?>

<!-- PROJE BİLGİ KARTI (FİLTRELİ İSE) -->
<?php if ($projectInfo): ?>
    <div class="cu-project-info-card">
        <div class="cu-project-info-left">
            <div class="cu-project-avatar-box cu-project-badge-color" data-color="<?= htmlspecialchars($projectInfo->color) ?>">
                📁
            </div>
            <div>
                <h2 class="cu-project-title-heading">
                    <?= htmlspecialchars($projectInfo->title) ?> 
                    <span class="cu-project-code-muted">(<?= htmlspecialchars($projectInfo->project_code) ?>)</span>
                </h2>
                <p class="cu-project-desc-text">
                    <?= htmlspecialchars($projectInfo->description ?: __('Board::no_project_desc')) ?>
                </p>
            </div>
        </div>
        <div class="cu-project-info-actions">
            <?php if ($currentUser->role === 'admin'): ?>
                <?php if ($projectInfo->status === 'completed' || $projectInfo->status === 'archived'): ?>
                    <button type="button" class="cu-btn-sm cu-btn-restore-project" data-project-id="<?= $projectInfo->id ?>">
                        <?= __('Board::btn_restore_active') ?>
                    </button>
                    <button type="button" class="cu-btn-sm cu-btn-delete-project" data-project-id="<?= $projectInfo->id ?>">
                        <?= __('Board::btn_delete_project') ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="cu-btn-sm cu-btn-archive-project" data-project-id="<?= $projectInfo->id ?>" title="<?= htmlspecialchars(__('Board::tooltip_move_archive')) ?>">
                        <?= __('Board::btn_move_archive') ?>
                    </button>
                <?php endif; ?>
                <a href="/taskmanager/board" class="cu-btn-clear-filter-link">
                    <?= __('Board::btn_clear_filter') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- MOBİLDE ACCORDION GÖREVLERİN ÜZERİNDE YENİ GÖREV BUTONU -->
<?php if (!$isClient): ?>
    <div class="cu-mobile-quick-task-bar">
        <button type="button" class="cu-btn-quick-task" id="btnMobileQuickTask">
            <span>➕</span>
            <span><?= __('Board::btn_new_task') ?></span>
        </button>
    </div>
<?php endif; ?>

<div class="cu-board-container">
    <?php 
    $today = date('Y-m-d');
    $prLabels = [
        'urgent' => __('Board::priority_urgent_badge'),
        'high'   => __('Board::priority_high_badge'),
        'normal' => __('Board::priority_normal_badge'),
        'low'    => __('Board::priority_low_badge'),
    ];
    foreach ($columns as $cKey => $col): 
        $cCount = count($col['tasks']);
    ?>
        <div class="cu-board-col active" data-col-status="<?= $cKey ?>">
            <div class="cu-board-col-header cu-col-toggle-header">
                <div class="cu-col-header-left">
                    <span class="cu-col-status-dot cu-project-badge-color" data-color="<?= $col['color'] ?>"></span>
                    <span class="cu-col-title-label"><?= $col['title'] ?></span>
                </div>
                <div class="cu-col-header-right">
                    <span class="cu-nav-badge"><?= $cCount ?></span>
                    <span class="cu-col-accordion-icon">▼</span>
                </div>
            </div>

            <div class="cu-board-cards" 
                 data-status="<?= $cKey ?>">
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
                         data-deadline="<?= $task->deadline ?: '9999-12-31' ?>">
                        <div class="cu-card-header-flex">
                            <span class="cu-task-code"><?= htmlspecialchars($task->task_code) ?></span>
                            <span class="cu-badge-project cu-project-badge-color" data-color="<?= htmlspecialchars($task->project_color ?? '#7B68EE') ?>">
                                <?= htmlspecialchars($task->project_title ?? __('Board::drawer_project_fallback')) ?>
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
                        <div class="cu-card-badges-wrap">
                            <span class="cu-priority-badge cu-priority-<?= $task->priority ?> cu-priority-pill">
                                <?= $prLabels[$task->priority] ?? $task->priority ?>
                            </span>
                            <?php if ($isAiTask && !$isClient): ?>
                                <span class="cu-ai-queue-badge" title="<?= htmlspecialchars(__('Board::ai_autonomous_badge')) ?>">
                                    <span>🤖</span><span><?= __('Board::ai_autonomous_badge') ?></span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="cu-board-card-footer">
                            <div class="cu-card-footer-meta">
                                <div class="cu-deadline <?= $isOverdue ? 'overdue' : '' ?> cu-deadline-font">
                                    <span><?= $isOverdue ? '⚠️ ' : '📅 ' ?></span>
                                    <span><?= htmlspecialchars($task->deadline ?: __('Board::none')) ?></span>
                                </div>
                                <?php $attCount = $attachmentCounts[$task->id] ?? 0; ?>
                                <?php if ($attCount > 0): ?>
                                    <span class="cu-card-attachment-badge" title="<?= $attCount ?>">
                                        <span>📎</span><span><?= $attCount ?></span>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($task->subtask_count)): ?>
                                    <span class="cu-card-subtask-badge" title="<?= htmlspecialchars(__('Board::subtasks')) ?>">
                                        <span>↳</span><span><?= (int)$task->subtask_completed_count ?>/<?= (int)$task->subtask_count ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Yan Yana Dizilen Çalışan Avatarları -->
                            <div class="cu-card-avatars cu-avatars-wrap">
                                <?php foreach ($tAssignees as $asg): ?>
                                    <div class="cu-avatar cu-avatar-sm" data-bg="<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                                        <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- MÜŞTERİ ONAY / DÜZELTME AKSİYONLARI -->
                        <?php if ($isClient && $cKey === 'submitted'): ?>
                            <div class="cu-client-actions-bar">
                                <button type="button" class="cu-btn-client-approve" data-task-id="<?= $task->id ?>" title="<?= htmlspecialchars(__('Board::client_approve_btn')) ?>">
                                    <span>✅</span><span><?= __('Board::client_approve_btn') ?></span>
                                </button>
                                <button type="button" class="cu-btn-client-mod" data-task-id="<?= $task->id ?>" title="<?= htmlspecialchars(__('Board::client_request_changes_btn')) ?>">
                                    <span>⚠️</span><span><?= __('Board::client_request_changes_btn') ?></span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 🐙 GITHUB DEVOPS AYARLARI MODALI -->


<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
