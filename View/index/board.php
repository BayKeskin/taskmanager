<?php
// TaskManager Kanban Panosu (Board View)
require_once __DIR__ . '/../../Controller/BoardController.php';

$system->title = __('tasks.board_title', 'Kanban Panosu - TaskManager');
include __DIR__ . '/../../Components/header.php';

$activeProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$boardController = new BoardController($system);
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

    <div class="cu-filter-bar">
        <?php if ($canCreateTask && !$isClient): ?>
            <button type="button" class="cu-btn-sm" onclick="openAiArchitectModal()" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, #7B68EE, #e056fd); border:none; color:#ffffff; font-weight:700; padding:0 14px; font-size:12px; border-radius:6px; cursor:pointer; box-shadow:0 4px 12px rgba(123,104,238,0.35); box-sizing:border-box;" title="<?= htmlspecialchars(__('app.tooltip_ai_architect')) ?>">
                <span>🪄</span> <span><?= __('app.btn_ai_architect') ?></span>
            </button>
        <?php endif; ?>
        <button type="button" class="cu-btn-sm" onclick="openAiStandupModal()" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, rgba(123,104,238,0.2), rgba(0,184,148,0.2)); border:1px solid rgba(123,104,238,0.4); color:var(--text-white); font-weight:600; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box;" title="<?= htmlspecialchars(__('tasks.tooltip_ai_standup', 'Son 24 saatin LLM özetini ve standup bültenini görüntüle')) ?>">
            <span>🤖</span> <span><?= __('tasks.btn_ai_standup', 'AI Standup Özeti') ?></span>
        </button>
        <?php if (!$isClient): 
            Helper::ensureGithubAndRollbackSchema();
            $ghCreds = Helper::getGithubCredentials();
        ?>
            <button type="button" class="cu-btn-sm" onclick="openGithubSettingsModal()" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.18); color:var(--text-white); font-weight:600; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box;" title="<?= htmlspecialchars(__('app.modal_github_title', 'GitHub Otomasyon & Hesap Ayarları')) ?>">
                <span>🐙</span> <span><?= !empty($ghCreds['username']) ? htmlspecialchars($ghCreds['username']) : __('app.btn_connect_github', 'GitHub Bağla') ?></span>
            </button>
                <button type="button" class="cu-btn-sm" onclick="syncProjectToGithub(<?= (int)$activeProjectId ?>)" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:rgba(46,160,67,0.15); border:1px solid rgba(46,160,67,0.4); color:#3fb950; font-weight:600; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box;" title="<?= htmlspecialchars(__('app.btn_sync_github', 'Bu Projeyi GitHub ile Eşitle')) ?>">
                    <span>🐙</span> <span><?= __('app.btn_sync_github', 'GitHub Sync') ?></span>
                </button>
                <button type="button" id="btnOpenDeployWizard" class="cu-btn-sm" onclick="openDeployWizard(<?= (int)$activeProjectId ?>)" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, rgba(0,184,148,0.2), rgba(9,132,227,0.25)); border:1px solid rgba(0,184,148,0.5); color:#00cec9; font-weight:700; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box; box-shadow:0 2px 8px rgba(0,184,148,0.2);" title="<?= htmlspecialchars(__('app.deploy_wizard_desc')) ?>">
                    <span>🚀</span> <span><?= __('app.deploy_btn_wizard') ?></span>
                </button>
                <button type="button" id="btnOpenProjectMcp" class="cu-btn-sm" onclick="openProjectMcpModal(<?= (int)$activeProjectId ?>)" style="height:32px; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(135deg, rgba(230,126,34,0.15), rgba(243,156,18,0.25)); border:1px solid rgba(243,156,18,0.45); color:#f39c12; font-weight:600; padding:0 12px; font-size:12px; border-radius:6px; cursor:pointer; box-sizing:border-box;" title="<?= htmlspecialchars(__('app.mcp_marketplace_desc')) ?>">
                    <span>🧩</span> <span><?= __('app.mcp_btn_open') ?></span>
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canCreateTask): ?>
            <button type="button" class="cu-btn-quick-task" onclick="openNewTaskModal()" style="height:32px; padding:0 14px; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; box-sizing:border-box;">
                <span>➕</span> <?= __('tasks.btn_add_task', 'Görev Ekle') ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- TaskManager YAN YANA FİLTRE & SIRALAMA TOOLBAR'I -->
<?php include __DIR__ . '/../../Components/filter_toolbar.php'; ?>

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

function clientApproveTask(taskId) {
    if (!confirm(window.BOARD_I18N.approve_confirm)) return;
    const fd = new FormData();
    fd.append('action', 'task_workflow');
    fd.append('workflow_action', 'approve');
    fd.append('task_id', taskId);
    fetch('/taskmanager/ajax/task_workflow', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(window.BOARD_I18N.approve_success);
                location.reload();
            } else {
                alert(window.BOARD_I18N.error_prefix + res.message);
            }
        })
        .catch(err => alert(window.BOARD_I18N.server_error + err.message));
}

function clientRequestChanges(taskId) {
    const reason = prompt(window.BOARD_I18N.request_prompt);
    if (!reason || !reason.trim()) return;
    const fd = new FormData();
    fd.append('action', 'task_workflow');
    fd.append('workflow_action', 'request_changes');
    fd.append('task_id', taskId);
    fd.append('feedback', reason.trim());
    fetch('/taskmanager/ajax/task_workflow', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(window.BOARD_I18N.request_success);
                location.reload();
            } else {
                alert(window.BOARD_I18N.error_prefix + res.message);
            }
        })
        .catch(err => alert(window.BOARD_I18N.server_error + err.message));
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

function openAiStandupModal() {
    const modal = document.getElementById('modalAiStandup');
    const content = document.getElementById('aiStandupContent');
    if (!modal || !content) return;
    openModal('modalAiStandup');
    content.innerHTML = `<div style="padding:40px; text-align:center; color:var(--text-muted);"><div style="font-size:32px; margin-bottom:12px;">🤖</div><strong>${window.BOARD_I18N.analyzing}</strong></div>`;

    fetch('/taskmanager/ajax/get_daily_standup<?= $activeProjectId ? "?project_id=".$activeProjectId : "" ?>')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const d = (res.data || res);
                const raw = d.raw_data || {};
                const period = raw.period || d.date || window.BOARD_I18N.last_24h;
                const compCount = raw.completed_count !== undefined ? raw.completed_count : 0;
                const inProgCount = raw.in_progress_count !== undefined ? raw.in_progress_count : 0;
                const delayedCount = raw.delayed_count !== undefined ? raw.delayed_count : 0;
                const summaryText = d.summary || '';

                let normalizedText = (summaryText || '').replace(/\r\n/g, '\n');
                let formattedSummary = escapeHtml(normalizedText)
                    .replace(/^\s*###\s*(.*$)/gim, '<div style="font-size:13px; font-weight:700; color:var(--brand-primary); margin-top:16px; margin-bottom:8px; border-bottom:1px solid var(--border-subtle); padding-bottom:4px;">$1</div>')
                    .replace(/^\s*#\s*(.*$)/gim, '<div style="font-size:15px; font-weight:800; color:var(--text-white); margin-bottom:10px;">$1</div>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong style="color:var(--text-white);">$1</strong>')
                    .replace(/_(.*?)_/g, '<em style="color:var(--text-muted);">$1</em>')
                    .replace(/^\s*-\s*(.*$)/gim, '<div style="margin-left:8px; margin-bottom:6px; display:flex; align-items:flex-start; gap:8px;"><span style="color:var(--brand-primary);">•</span><span>$1</span></div>');

                const compLabel = window.BOARD_I18N.comp_stat.replace(':count', compCount);
                const inProgLabel = window.BOARD_I18N.in_prog_stat.replace(':count', inProgCount);
                const delayedLabel = window.BOARD_I18N.delayed_stat.replace(':count', delayedCount);

                content.innerHTML = `
                    <div style="background:rgba(123,104,238,0.08); border:1px solid rgba(123,104,238,0.25); border-radius:8px; padding:16px; margin-bottom:18px;">
                        <div style="font-size:11px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">${window.BOARD_I18N.period}</div>
                        <div style="font-size:13px; color:var(--text-white); font-weight:600;">📅 ${period}</div>
                        <div style="display:flex; gap:12px; margin-top:12px; flex-wrap:wrap;">
                            <div style="background:rgba(0,184,148,0.15); border:1px solid rgba(0,184,148,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#00b894; font-weight:700;">${compLabel}</div>
                            <div style="background:rgba(155,93,229,0.15); border:1px solid rgba(155,93,229,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#9b5de5; font-weight:700;">${inProgLabel}</div>
                            <div style="background:rgba(231,76,60,0.15); border:1px solid rgba(231,76,60,0.3); border-radius:6px; padding:6px 12px; font-size:12px; color:#e74c3c; font-weight:700;">${delayedLabel}</div>
                        </div>
                    </div>
                    <div style="background:var(--bg-base); border:1px solid var(--border-subtle); border-radius:8px; padding:18px; font-size:13px; line-height:1.6; color:var(--text-primary); max-height:360px; overflow-y:auto;">
                        ${formattedSummary}
                    </div>
                `;
            } else {
                content.innerHTML = '<div style="padding:30px; text-align:center; color:#e74c3c;">' + window.BOARD_I18N.err_fetch + (res.message || '') + '</div>';
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="padding:30px; text-align:center; color:#e74c3c;">' + window.BOARD_I18N.err_conn + err.message + '</div>';
        });
}

function sendStandupEmail() {
    const email = prompt(window.BOARD_I18N.email_prompt, '<?= htmlspecialchars($currentUser->email ?? '') ?>');
    if (!email || !email.trim()) return;

    const fd = new FormData();
    fd.append('action', 'send_standup_email');
    fd.append('email', email.trim());
    <?php if ($activeProjectId): ?>
        fd.append('project_id', '<?= (int)$activeProjectId ?>');
    <?php endif; ?>

    fetch('/taskmanager/ajax/send_standup_email', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert(window.BOARD_I18N.email_success + email);
            } else {
                alert(window.BOARD_I18N.error_prefix + res.message);
            }
        })
        .catch(err => alert(window.BOARD_I18N.server_error + err.message));
}
</script>

<!-- GÜNLÜK AI STANDUP MODALI -->
<div class="cu-modal-overlay" id="modalAiStandup" onclick="if(event.target === this) closeModal('modalAiStandup')">
    <div class="cu-modal" style="max-width:680px; max-height:90vh; display:flex; flex-direction:column;">
        <div class="cu-modal-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">🤖</span>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0;"><?= __('tasks.ai_standup_modal_title', 'Günlük AI Standup & Görev Özeti') ?></h3>
            </div>
            <button type="button" onclick="closeModal('modalAiStandup')" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="cu-modal-body" id="aiStandupContent" style="flex:1; overflow-y:auto; padding:20px;">
        </div>
        <div class="cu-modal-footer" style="display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid var(--border-subtle);">
            <button type="button" class="cu-btn-sm cu-btn-secondary" onclick="sendStandupEmail()" style="display:flex; align-items:center; gap:6px;">
                <span>📧</span> <span><?= __('tasks.ai_standup_send_email_btn', 'E-Posta Bülteni Gönder') ?></span>
            </button>
            <button type="button" class="cu-btn-sm" onclick="closeModal('modalAiStandup')">
                <?= __('tasks.modal_close', 'Kapat') ?>
            </button>
        </div>
    </div>
</div>

<!-- 🐙 GITHUB DEVOPS AYARLARI MODALI -->
<div class="cu-modal-overlay" id="modalGithubSettings" onclick="if(event.target === this) closeModal('modalGithubSettings')">
    <div class="cu-modal" style="max-width:500px; display:flex; flex-direction:column;">
        <div class="cu-modal-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">🐙</span>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-white); margin:0;"><?= __('app.modal_github_title', 'GitHub DevOps & Hesap Ayarları') ?></h3>
            </div>
            <button type="button" onclick="closeModal('modalGithubSettings')" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <div class="cu-modal-body" style="padding:20px;">
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px; line-height:1.5;">
                <?= __('app.modal_github_desc', 'GitHub hesabınızı bağlayarak yeni projeler için otomatik Private Repository açılmasını, görevler teslim edildikçe güvenli (http.extraheader) push yapılmasını ve tek tıkla cerrahi geri alma (Rollback) imkanını aktif edebilirsiniz.') ?>
            </p>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_github_user_label', 'GitHub Kullanıcı Adı veya Organizasyon') ?></label>
                <input type="text" id="ghUsernameInput" class="cu-input" placeholder="<?= htmlspecialchars(__('app.modal_github_user_ph', 'Örn: BayKeskin')) ?>" value="<?= htmlspecialchars($ghCreds['username'] ?? '') ?>" style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white);">
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--text-secondary); margin-bottom:6px;"><?= __('app.modal_github_token_label', 'GitHub Personal Access Token (PAT - repo yetkili)') ?></label>
                <input type="password" id="ghTokenInput" class="cu-input" placeholder="<?= htmlspecialchars(__('app.modal_github_token_ph', 'ghp_xxxxxxxxxxxxxxxxxxxx')) ?>" value="<?= htmlspecialchars($ghCreds['token'] ?? '') ?>" style="width:100%; padding:8px 12px; background:var(--bg-main); border:1px solid var(--border-subtle); border-radius:6px; color:var(--text-white);">
            </div>
        </div>
        <div class="cu-modal-footer" style="display:flex; justify-content:flex-end; gap:10px; padding:14px 20px; border-top:1px solid var(--border-subtle);">
            <button type="button" class="cu-btn-sm cu-btn-secondary" onclick="closeModal('modalGithubSettings')"><?= __('app.btn_cancel', 'İptal') ?></button>
            <button type="button" class="cu-btn-sm" style="background:#2ea043; color:#fff;" onclick="saveGithubSettings()"><?= __('app.modal_github_save_btn', '💾 Kaydet ve Bağla') ?></button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../Components/footer.php'; ?>

