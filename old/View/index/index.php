<?php
// TaskManager Liste Görünümü (List View)
require_once __DIR__ . '/../../Controller/TaskController.php';

$system->title = __('tasks.list_title', 'Görevler - TaskManager');
include __DIR__ . '/../../Components/header.php';

$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$taskController = new TaskController($system);
$taskData = $taskController->listIndex($activeProjectId);
extract($taskData);

// Durumlara Göre Grupla
$groups = [
    'assigned' => ['title' => __('tasks.list_group_assigned', 'YAPILACAK İŞLER (TO DO)'), 'color' => '#4f8ff7', 'tasks' => []],
    'accepted' => ['title' => __('tasks.list_group_accepted', 'KABUL EDİLENLER'), 'color' => '#00b4d8', 'tasks' => []],
    'in_progress' => ['title' => __('tasks.list_group_in_progress', 'ÇALIŞILIYOR (IN PROGRESS)'), 'color' => '#9b5de5', 'tasks' => []],
    'modification_requested' => ['title' => __('tasks.list_group_revision', 'REVİZYON İSTENDİ'), 'color' => '#e67e22', 'tasks' => []],
    'submitted' => ['title' => __('tasks.list_group_submitted', 'İNCELEMEDE (TESLİM EDİLDİ)'), 'color' => '#e056fd', 'tasks' => []],
    'completed' => ['title' => __('tasks.list_group_completed', 'TAMAMLANDI (COMPLETED)'), 'color' => '#00b894', 'tasks' => []],
];

foreach ($allTasks as $t) {
    if (isset($groups[$t->status])) {
        $groups[$t->status]['tasks'][] = $t;
    } else {
        $groups['assigned']['tasks'][] = $t;
    }
}
?>

<!-- ÜST GÖRÜNÜM ÇUBUĞU (VIEW HEADER) -->
<div class="cu-view-header">
    <div class="cu-view-title-wrap" style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📋</span>
        <span style="font-weight:700; font-size:14px; color:var(--text-white);"><?= __('tasks.view_list', 'Liste Görünümü') ?></span>
    </div>

    <div class="cu-filter-bar">
        <?php if ($currentUser->role === 'admin'): ?>
            <button type="button" class="cu-btn-quick-task" onclick="openNewTaskModal()" style="height:32px; padding:0 14px; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
                <span>➕</span> <?= __('tasks.btn_add_task', 'Görev Ekle') ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- TaskManager YAN YANA FİLTRE & SIRALAMA TOOLBAR'I -->
<?php include __DIR__ . '/../../Components/filter_toolbar.php'; ?>

<!-- LİSTE İÇERİK ALANI -->
<div class="cu-content-scroll">
    <!-- PROJE BAŞLIĞI / BİLGİ KARTI -->
    <?php if ($projectInfo): ?>
        <div style="background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px 20px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:40px; height:40px; border-radius:10px; background:<?= htmlspecialchars($projectInfo->color) ?>; display:flex; align-items:center; justify-content:center; font-size:20px;">
                    📁
                </div>
                <div>
                    <h2 style="font-size:16px; font-weight:700; color:var(--text-white);">
                        <?= htmlspecialchars($projectInfo->title) ?> 
                        <span style="font-size:12px; color:var(--text-muted); font-weight:500;">(<?= htmlspecialchars($projectInfo->project_code) ?>)</span>
                    </h2>
                    <p style="font-size:12.5px; color:var(--text-secondary); margin-top:2px;">
                        <?= htmlspecialchars($projectInfo->description ?: __('tasks.no_project_desc', 'Proje açıklaması belirtilmemiş.')) ?>
                    </p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <?php if ($currentUser->role === 'admin'): ?>
                    <?php if ($projectInfo->status === 'completed' || $projectInfo->status === 'archived'): ?>
                        <button type="button" onclick="toggleProjectStatus(<?= $projectInfo->id ?>, 'active')" class="cu-btn-sm" style="background:rgba(123, 104, 238, 0.15); color:var(--brand-primary); border:1px solid rgba(123, 104, 238, 0.3); font-size:11.5px; padding:6px 12px;">
                            <?= __('tasks.btn_restore_active', '↺ Aktif Projelere Geri Yükle') ?>
                        </button>
                        <button type="button" onclick="deleteProjectPermanently(<?= $projectInfo->id ?>)" class="cu-btn-sm" style="background:rgba(231,76,60,0.15); color:#e74c3c; border:1px solid rgba(231,76,60,0.3); font-size:11.5px; padding:6px 12px;">
                            <?= __('tasks.btn_delete_project', '🗑️ Projeyi Sil') ?>
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="toggleProjectStatus(<?= $projectInfo->id ?>, 'completed')" class="cu-btn-sm" style="background:var(--bg-card-hover); color:var(--text-primary); border:1px solid var(--border-subtle); font-size:11.5px; padding:6px 12px;" title="<?= htmlspecialchars(__('tasks.btn_move_archive', 'Projeyi Geçmiş Projelere Taşı')) ?>">
                            <?= __('tasks.btn_move_archive', '📦 Geçmiş Projelere Taşı') ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="/taskmanager/tasks" style="color:var(--text-muted); font-size:12px; text-decoration:none; padding:6px 12px; border:1px solid var(--border-subtle); border-radius:var(--radius-sm);">
                    <?= __('tasks.btn_clear_filter', '✕ Filtreyi Kaldır') ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- DURUM GRUPLARI (TaskManager STATUS ACCORDIONS) -->
    <?php 
    $today = date('Y-m-d');
    foreach ($groups as $statusKey => $group): 
        $taskCount = count($group['tasks']);
    ?>
        <div class="cu-group" id="group-<?= $statusKey ?>">
            <div class="cu-group-header">
                <div class="cu-group-header-left">
                    <span class="cu-group-pill" style="background:<?= $group['color'] ?>;">
                        <?= $group['title'] ?>
                    </span>
                    <span class="cu-group-count" id="count-group-<?= $statusKey ?>"><?= __('tasks.list_task_count', ['count' => $taskCount], "{$taskCount} görev") ?></span>
                </div>
                <?php if ($currentUser->role === 'admin'): ?>
                    <button onclick="openNewTaskModal()" class="cu-btn-sm" style="font-size:11px; padding:2px 8px;">
                        <?= __('tasks.list_quick_add', '+ Hızlı Ekle') ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="cu-task-list" id="taskList-<?= $statusKey ?>">
                <?php if ($taskCount === 0): ?>
                    <div class="cu-empty-group-msg" style="padding:16px; text-align:center; font-size:12px; color:var(--text-muted);">
                        <?= __('tasks.list_empty_group', 'Bu durumda henüz görev bulunmuyor.') ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($group['tasks'] as $t): 
                        $isOverdue = (!empty($t->deadline) && $t->deadline < $today && $t->status !== 'completed');
                        $prLabels = [
                            'urgent' => __('tasks.priority_urgent_badge', '🔴 Acil'),
                            'high'   => __('tasks.priority_high_badge', '🟡 Yüksek'),
                            'normal' => __('tasks.priority_normal_badge', '🔵 Normal'),
                            'low'    => __('tasks.priority_low_badge', '⚪ Düşük'),
                        ];
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
                                 data-deadline="<?= $t->deadline ?: '9999-12-31' ?>"
                                 onclick="openTaskDrawer(<?= $t->id ?>)">
                                
                                <!-- Subtask Toggle Butonu & TSK Kodu -->
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <?php if ($hasSubtasks): ?>
                                        <button type="button" 
                                                onclick="event.stopPropagation(); toggleSubtasksTree(<?= $t->id ?>)"
                                                class="cu-btn-tree-toggle"
                                                id="btnTreeToggle-<?= $t->id ?>"
                                                title="<?= htmlspecialchars(__('app.tasks_toggle_subtasks')) ?>"
                                                style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:11px; padding:2px 4px; border-radius:3px;">
                                            ▶
                                        </button>
                                    <?php else: ?>
                                        <span style="display:inline-block; width:15px;"></span>
                                    <?php endif; ?>

                                    <div class="cu-task-code"><?= htmlspecialchars($t->task_code) ?></div>
                                </div>

                                <!-- Başlık & Proje Rozeti -->
                                <div class="cu-task-title">
                                    <span class="cu-badge-project" style="color:<?= htmlspecialchars($t->project_color ?? '#7B68EE') ?>; margin-right:6px;">
                                        <?= htmlspecialchars($t->project_title ?? 'Genel') ?>
                                    </span>
                                    <?= htmlspecialchars($t->title) ?>
                                    
                                    <?php if ($hasSubtasks): ?>
                                        <span class="cu-nav-badge" style="background:rgba(123, 104, 238, 0.15); color:#7B68EE; font-size:10px; margin-left:6px; font-weight:700;">
                                            <?= __('tasks.list_count_subtasks', ['count' => count($subtasks)], '📂 ' . count($subtasks) . ' alt görev') ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($t->tags)): 
                                        $firstTag = explode(',', $t->tags)[0];
                                    ?>
                                        <span style="font-size:10px; padding:1px 5px; border-radius:3px; background:rgba(123, 104, 238, 0.12); color:#7B68EE; border:1px solid rgba(123, 104, 238, 0.25); font-weight:600; margin-left:6px;">
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
                                    <span><?= htmlspecialchars($t->deadline ?: __('tasks.list_no_date', 'Tarih Yok')) ?></span>
                                </div>

                                <!-- Atanan Çalışanlar (Yan Yana Avatarlar) -->
                                <div class="cu-task-assignee" style="display:flex; align-items:center; gap:4px; max-width:140px; overflow:hidden;">
                                    <?php foreach ($tAssignees as $asg): ?>
                                        <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>; flex-shrink:0;" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                                            <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Aksiyon Butonları -->
                                <div class="cu-row-actions" style="display:flex; gap:6px;">
                                    <button class="cu-btn-sm" style="background:rgba(123, 104, 238, 0.15); color:#7B68EE; border:1px solid rgba(123, 104, 238, 0.3); font-size:11px; padding:3px 8px;" onclick="event.stopPropagation(); expandAndFocusSubtask(<?= $t->id ?>)" title="Alt Görev Aç">
                                        <?= __('tasks.list_btn_subtask', '+ Alt Görev') ?>
                                    </button>
                                    <button class="cu-btn-sm" onclick="event.stopPropagation(); openTaskDrawer(<?= $t->id ?>)">
                                        <?= __('tasks.list_btn_detail', 'Detay ➔') ?>
                                    </button>
                                    <button class="cu-btn-sm" style="background:rgba(231,76,60,0.12); color:#e74c3c; border:1px solid rgba(231,76,60,0.25); font-size:11px; padding:3px 7px;" onclick="event.stopPropagation(); deleteTaskFromList(<?= $t->id ?>)" title="<?= htmlspecialchars(__('tasks.drawer_btn_delete_task', 'Görevi Sil')) ?>">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            <!-- NESTED SUBTASKS TREE (GİRİNTİLİ ALT GÖREVLER) -->
                            <div class="cu-subtasks-tree" id="subtasksTree-<?= $t->id ?>" style="display:none; padding-left:36px; background:rgba(0,0,0,0.15); border-left:2px solid rgba(123, 104, 238, 0.4); margin-left:14px;">
                                <div id="subtasksRows-<?= $t->id ?>">
                                    <?php foreach ($subtasks as $st): ?>
                                        <div class="cu-task-row" style="padding:7px 16px; border-bottom:1px solid var(--border-subtle); font-size:12px; cursor:pointer;" onclick="openTaskDrawer(<?= $st->id ?>)">
                                            <div style="color:var(--text-muted); font-size:11px; font-family:monospace; margin-right:8px;">
                                                └── <strong style="color:var(--brand-primary);"><?= htmlspecialchars($st->task_code) ?></strong>
                                            </div>
                                            <div style="flex:1; color:var(--text-primary); font-weight:500;">
                                                <?= htmlspecialchars($st->title) ?>
                                            </div>
                                            <div>
                                                <span class="cu-priority-badge cu-priority-<?= $st->priority ?>" style="font-size:10px; padding:2px 6px;">
                                                    <?= $prLabels[$st->priority] ?? $st->priority ?>
                                                </span>
                                            </div>
                                            <div class="cu-task-assignee" style="margin-left:12px;">
                                                <div class="cu-avatar" style="width:18px; height:18px; font-size:9px; background:<?= htmlspecialchars($st->assignee_color ?? '#7B68EE') ?>;">
                                                    <?= mb_substr($st->assignee_name ?? 'U', 0, 1) ?>
                                                </div>
                                                <span style="font-size:11px; color:var(--text-secondary);">
                                                    <?= htmlspecialchars(explode(' ', $st->assignee_name ?? __('tasks.unassigned', 'Atanmamış'))[0]) ?>
                                                </span>
                                            </div>
                                            <button type="button" onclick="event.stopPropagation(); deleteSubtask(<?= $st->id ?>)" style="background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:11px; padding:2px 6px; margin-left:8px;" title="Alt Görevi Sil">
                                                ✕
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Hızlı Alt Görev Ekleme Satırı (TaskManager Inline Subtask Creator) -->
                                <div style="padding:8px 16px; display:flex; gap:8px; align-items:center;">
                                    <input type="text" id="quickSubtaskInput-<?= $t->id ?>" class="cu-form-control" placeholder="<?= htmlspecialchars(__('tasks.list_subtask_placeholder', '+ Bu göreve hızlı alt görev ekle... (Enter)')) ?>" style="height:28px; font-size:11.5px; background:rgba(0,0,0,0.25); flex:1;" onkeydown="if(event.key==='Enter') handleQuickAddSubtask(<?= $t->id ?>)">
                                    <button type="button" onclick="handleQuickAddSubtask(<?= $t->id ?>)" class="cu-btn-sm" style="height:28px; font-size:11px; padding:0 10px; background:var(--brand-primary);"><?= __('tasks.btn_add', '+ Ekle') ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../Components/footer.php'; ?>