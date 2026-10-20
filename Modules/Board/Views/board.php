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
// 3. Durumlara Göre Liste Grupları (List View Groups)
$listGroups = [
    'assigned'               => ['title' => __('Board::list_group_assigned'), 'color' => '#4f8ff7', 'tasks' => []],
    'accepted'               => ['title' => __('Board::list_group_accepted'), 'color' => '#00b4d8', 'tasks' => []],
    'in_progress'            => ['title' => __('Board::list_group_in_progress'), 'color' => '#9b5de5', 'tasks' => []],
    'modification_requested' => ['title' => __('Board::list_group_revision'), 'color' => '#e67e22', 'tasks' => []],
    'submitted'              => ['title' => __('Board::list_group_submitted'), 'color' => '#e056fd', 'tasks' => []],
    'completed'              => ['title' => __('Board::list_group_completed'), 'color' => '#00b894', 'tasks' => []],
];

foreach ($allTasks as $t) {
    if (isset($listGroups[$t->status])) {
        $listGroups[$t->status]['tasks'][] = $t;
    } else {
        $listGroups['assigned']['tasks'][] = $t;
    }
}

$boardQuery = !empty($activeProjectId) ? 'project_id=' . (int)$activeProjectId : '';
$kanbanUrl = baseUrl('board' . (!empty($boardQuery) ? '?' . $boardQuery : ''));
$listUrl = baseUrl('board?' . (!empty($boardQuery) ? $boardQuery . '&view=list' : 'view=list'));
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap cu-view-title-flex">
        <span class="cu-view-header-icon"><?= $currentView === 'list' ? '📋' : '📌' ?></span>
        <span class="cu-view-header-title-text"><?= $isClient ? __('Board::view_client_portal') : ($currentView === 'list' ? __('Board::view_list') : __('Board::view_board')) ?></span>
        
        <?php if (!$isClient): ?>
            <!-- GÖRÜNÜM DEĞİŞTİRİCİ TABLAR (KANBAN / LİSTE) -->
            <div class="cu-view-switcher">
                <a href="<?= htmlspecialchars($kanbanUrl) ?>" class="cu-view-switcher-btn <?= $currentView !== 'list' ? 'active' : '' ?>">
                    <span>📌</span>
                    <span><?= __('Board::view_board') ?></span>
                </a>
                <a href="<?= htmlspecialchars($listUrl) ?>" class="cu-view-switcher-btn <?= $currentView === 'list' ? 'active' : '' ?>">
                    <span>📋</span>
                    <span><?= __('Board::view_list') ?></span>
                </a>
            </div>
        <?php endif; ?>
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
                    <a href="<?= baseUrl('kanban/export-excel?project_id=' . (int)$activeProjectId) ?>" 
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
                <a href="<?= baseUrl('board') ?>" class="cu-btn-clear-filter-link">
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

<?php if ($currentView === 'list'): ?>
    <!-- LİSTE İÇERİK ALANI (LIST VIEW CONTAINER) -->
    <div class="cu-content-scroll">
        <?php 
        $today = date('Y-m-d');
        $prLabels = [
            'urgent' => __('Board::priority_urgent_badge'),
            'high'   => __('Board::priority_high_badge'),
            'normal' => __('Board::priority_normal_badge'),
            'low'    => __('Board::priority_low_badge'),
        ];
        foreach ($listGroups as $statusKey => $group): 
            $taskCount = count($group['tasks']);
        ?>
            <div class="cu-group" id="group-<?= $statusKey ?>">
                <div class="cu-group-header">
                    <div class="cu-group-header-left">
                        <span class="cu-group-pill cu-project-badge-color" data-color="<?= $group['color'] ?>">
                            <?= $group['title'] ?>
                        </span>
                        <span class="cu-group-count" id="count-group-<?= $statusKey ?>">
                            <?= str_replace(':count', (string)$taskCount, __('Board::list_task_count')) ?>
                        </span>
                    </div>
                    <?php if ($currentUser->role === 'admin'): ?>
                        <button type="button" class="cu-btn-sm cu-btn-quick-add-group" data-group-status="<?= $statusKey ?>">
                            <?= __('Board::list_quick_add') ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="cu-task-list" id="taskList-<?= $statusKey ?>">
                    <?php if ($taskCount === 0): ?>
                        <div class="cu-empty-group-msg cu-empty-group-box">
                            <?= __('Board::list_empty_group') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($group['tasks'] as $t): 
                            $isOverdue = (!empty($t->deadline) && $t->deadline < $today && $t->status !== 'completed');
                            $subtasks = $subtaskMap[$t->id] ?? [];
                            $hasSubtasks = count($subtasks) > 0;
                            $tAssignees = $assigneesMap[$t->id] ?? [];
                            if (empty($tAssignees) && !empty($t->assigned_to)) {
                                $tAssignees = [(object)['user_id' => $t->assigned_to, 'name' => $t->assignee_name, 'avatar_color' => $t->assignee_color]];
                            }
                            $assigneeIdsStr = implode(',', array_map(fn($a) => $a->user_id, $tAssignees));
                        ?>
                            <div class="cu-task-container" id="task-container-<?= $t->id ?>">
                                <div class="cu-task-row" 
                                     id="task-row-<?= $t->id ?>"
                                     data-task-id="<?= $t->id ?>"
                                     data-title="<?= htmlspecialchars(mb_strtolower($t->title)) ?>"
                                     data-code="<?= htmlspecialchars(mb_strtolower($t->task_code)) ?>"
                                     data-tags="<?= htmlspecialchars(mb_strtolower($t->tags ?? '')) ?>"
                                     data-priority="<?= $t->priority ?>"
                                     data-assignee-ids="<?= $assigneeIdsStr ?>"
                                     data-assignee-id="<?= $t->assigned_to ?>"
                                     data-deadline="<?= $t->deadline ?: '9999-12-31' ?>">
                                    
                                    <!-- Subtask Toggle Butonu & TSK Kodu -->
                                    <div class="cu-list-task-code-wrap">
                                        <?php if ($hasSubtasks): ?>
                                            <button type="button" 
                                                    class="cu-btn-tree-toggle"
                                                    id="btnTreeToggle-<?= $t->id ?>"
                                                    data-parent-id="<?= $t->id ?>"
                                                    title="<?= htmlspecialchars(__('Board::subtasks')) ?>">
                                                ▶
                                            </button>
                                        <?php else: ?>
                                            <span class="cu-tree-toggle-placeholder"></span>
                                        <?php endif; ?>

                                        <div class="cu-task-code"><?= htmlspecialchars($t->task_code) ?></div>
                                    </div>

                                    <!-- Başlık & Proje Rozeti -->
                                    <div class="cu-task-title">
                                        <span class="cu-badge-project cu-project-badge-color" data-color="<?= htmlspecialchars($t->project_color ?? '#7B68EE') ?>">
                                            <?= htmlspecialchars($t->project_title ?? __('Board::drawer_project_fallback')) ?>
                                        </span>
                                        <?= htmlspecialchars($t->title) ?>
                                        
                                        <?php if ($hasSubtasks): ?>
                                            <span class="cu-nav-badge cu-list-badge-subtasks">
                                                <?= str_replace(':count', (string)count($subtasks), __('Board::list_count_subtasks')) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($t->tags)): 
                                            $firstTag = explode(',', $t->tags)[0];
                                        ?>
                                            <span class="cu-list-tag-badge">
                                                #<?= htmlspecialchars(trim($firstTag)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Öncelik Bayrağı -->
                                    <div>
                                        <span class="cu-priority-badge cu-priority-<?= $t->priority ?>">
                                            <?= $prLabels[$t->priority] ?? $t->priority ?>
                                        </span>
                                    </div>

                                    <!-- Son Teslim Tarihi -->
                                    <div class="cu-deadline <?= $isOverdue ? 'overdue' : '' ?>">
                                        <span><?= $isOverdue ? '⚠️ ' : '📅 ' ?></span>
                                        <span><?= htmlspecialchars($t->deadline ?: __('Board::list_no_date')) ?></span>
                                    </div>

                                    <!-- Atanan Çalışanlar (Yan Yana Avatarlar) -->
                                    <div class="cu-task-assignee cu-list-assignees-avatars">
                                        <?php foreach ($tAssignees as $asg): ?>
                                            <div class="cu-avatar cu-avatar-sm" data-bg="<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                                                <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Aksiyon Butonları -->
                                    <div class="cu-row-actions cu-list-actions-wrap">
                                        <button type="button" class="cu-btn-sm cu-btn-list-subtask" data-parent-id="<?= $t->id ?>" title="<?= htmlspecialchars(__('Board::list_btn_subtask')) ?>">
                                            <?= __('Board::list_btn_subtask') ?>
                                        </button>
                                        <button type="button" class="cu-btn-sm cu-btn-list-detail" data-task-id="<?= $t->id ?>">
                                            <?= __('Board::list_btn_detail') ?>
                                        </button>
                                        <button type="button" class="cu-btn-sm cu-btn-list-delete" data-task-id="<?= $t->id ?>" title="<?= htmlspecialchars(__('Board::delete_task')) ?>">
                                            🗑️
                                        </button>
                                    </div>
                                </div>

                                <!-- NESTED SUBTASKS TREE (GİRİNTİLİ ALT GÖREVLER) -->
                                <div class="cu-subtasks-tree cu-subtasks-tree-wrap" id="subtasksTree-<?= $t->id ?>">
                                    <div id="subtasksRows-<?= $t->id ?>">
                                        <?php foreach ($subtasks as $st): ?>
                                            <div class="cu-task-row cu-subtask-row" data-task-id="<?= $st->id ?>">
                                                <div class="cu-subtask-code-prefix">
                                                    └── <strong class="cu-task-code"><?= htmlspecialchars($st->task_code) ?></strong>
                                                </div>
                                                <div class="cu-subtask-title-cell">
                                                    <?= htmlspecialchars($st->title) ?>
                                                </div>
                                                <div>
                                                    <span class="cu-priority-badge cu-priority-<?= $st->priority ?> cu-subtask-pr-badge">
                                                        <?= $prLabels[$st->priority] ?? $st->priority ?>
                                                    </span>
                                                </div>
                                                <div class="cu-task-assignee cu-subtask-assignee-cell">
                                                    <div class="cu-avatar cu-avatar-sm" data-bg="<?= htmlspecialchars($st->assignee_color ?? '#7B68EE') ?>">
                                                        <?= mb_substr($st->assignee_name ?? 'U', 0, 1) ?>
                                                    </div>
                                                    <span class="cu-project-desc-text">
                                                        <?= htmlspecialchars(explode(' ', $st->assignee_name ?? __('Board::unassigned'))[0]) ?>
                                                    </span>
                                                </div>
                                                <button type="button" class="cu-subtask-delete-btn" data-subtask-id="<?= $st->id ?>" title="<?= htmlspecialchars(__('Board::delete_subtask')) ?>">
                                                    ✕
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Hızlı Alt Görev Ekleme Satırı (Inline Subtask Creator) -->
                                    <div class="cu-quick-subtask-bar">
                                        <input type="text" id="quickSubtaskInput-<?= $t->id ?>" class="cu-form-control cu-quick-subtask-input" placeholder="<?= htmlspecialchars(__('Board::list_subtask_placeholder')) ?>" data-parent-id="<?= $t->id ?>">
                                        <button type="button" class="cu-btn-sm cu-quick-subtask-btn" data-parent-id="<?= $t->id ?>"><?= __('Board::btn_add') ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
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
<?php endif; ?>


<!-- 🐙 GITHUB DEVOPS AYARLARI MODALI -->


<?php include dirname(__DIR__, 3) . '/Core/Views/footer.php'; ?>
