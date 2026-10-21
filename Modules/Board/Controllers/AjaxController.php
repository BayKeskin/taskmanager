<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/Core/System.php';

// Dil motorunu başlat (URL parametresi ?lang=, $_POST['lang'] veya kullanıcı tercihi)
Language::init();

$taskModel = new \Modules\Board\Models\TaskModel();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$currentUser = $_SESSION['user'] ?? (object)['id' => 1, 'name' => 'Admin User', 'role' => 'admin'];

// 🚀 HMVC MODÜLER AJAX YÖNLENDİRİCİSİ (Core\AjaxRouter)
// Eğer gelen eylem (action) Projects, Team, Settings, Docs, Chat, Mcp, LiveDeploy vb. bir modüle aitse
// ilgili modülün kendi Controller'ı çalıştırılır ve işlem tamamlanır.
if (!empty($action) && class_exists('Core\AjaxRouter') && \Core\AjaxRouter::dispatch($action)) {
    exit;
}

function jsonResponse($success, $message = '', $data = []) {
    $payload = ['success' => $success, 'message' => $message];
    if (!empty($data) && is_array($data)) {
        $payload = array_merge($payload, $data);
        if (!isset($payload['data'])) {
            $payload['data'] = $data;
        }
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $payload = \Modules\Board\Services\TaskLifecycleService::sanitizeUtf8Deep($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            $json = json_encode([
                'success' => false,
                'message' => 'JSON encode error: ' . json_last_error_msg()
            ]);
        }
    }
    echo $json;
    exit;
}

switch ($action) {
    // 0. KANBAN GÖREVLERİ EXCEL DIŞA AKTARIMI
    case 'export_excel':
    case 'kanban_export_excel':
        $ec = new \Modules\ExcelExport\Controllers\ExcelExportController();
        $projId = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
        $ec->export($projId, $currentUser);
        exit;
        break;

    // 1. GÖREV DETAYI ALMA
    case 'get_task_detail':
        $taskId = (int)($_GET['id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, __('Board::invalid_task_id'));
        }

        $detail = $taskModel->getTaskFullDetail($taskId, $currentUser);
        if (!$detail) {
            jsonResponse(false, __('Board::task_not_found'));
        }

        $detail['currentUser'] = $currentUser;
        jsonResponse(true, '', $detail);
        break;

    // 1.1 GÖREV KART / SATIR HTML ÇIKTISI (REALTIME SSE İÇİN)
    case 'get_task_card_html':
        $taskId = (int)($_GET['task_id'] ?? ($_GET['id'] ?? 0));
        if ($taskId <= 0) {
            jsonResponse(false, __('Board::invalid_task_id'));
        }

        $cardData = $taskModel->getTaskCardData($taskId);
        if (!$cardData || empty($cardData['task'])) {
            jsonResponse(false, __('Board::task_not_found'));
        }

        $task = $cardData['task'];
        if ($currentUser->role === 'client' && (int)$task->project_id !== (int)($currentUser->client_project_id ?? 0)) {
            jsonResponse(false, __('Board::unauthorized_access'));
        }

        $assignees = $cardData['assignees'];
        $attCount = $cardData['att_count'];
        $assigneeIdsStr = implode(',', array_map(fn($a) => $a->user_id ?? $a->id, $assignees));

        $today = date('Y-m-d');
        $prLabels = [
            'urgent' => __('Board::priority_urgent_badge'),
            'high'   => __('Board::priority_high_badge'),
            'normal' => __('Board::priority_normal_badge'),
            'low'    => __('Board::priority_low_badge')
        ];
        $isOverdue = (!empty($task->deadline) && $task->deadline < $today && $task->status !== 'completed');
        $isAiTask = \Modules\Board\Services\TaskLifecycleService::isAiTask($task);

        // 1. Kanban Kartı HTML
        ob_start();
        ?>
        <div class="cu-board-card live-pulse-anim" 
             draggable="true"
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

            <div class="cu-card-badges-wrap">
                <span class="cu-priority-badge cu-priority-<?= $task->priority ?> cu-priority-pill">
                    <?= $prLabels[$task->priority] ?? $task->priority ?>
                </span>
                <?php if ($isAiTask): ?>
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
                    <?php if ($attCount > 0): ?>
                        <span class="cu-card-attachment-badge" title="<?= $attCount ?>">
                            <span>📎</span><span><?= $attCount ?></span>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="cu-card-avatars cu-avatars-wrap">
                    <?php foreach ($assignees as $asg): ?>
                        <div class="cu-avatar cu-avatar-sm" data-bg="<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                            <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        $cardHtml = ob_get_clean();

        // 2. Liste Görünümü Satırı HTML
        ob_start();
        ?>
        <div class="cu-task-container live-pulse-anim" id="task-container-<?= $task->id ?>">
            <div class="cu-task-row" 
                 id="task-row-<?= $task->id ?>"
                 data-task-id="<?= $task->id ?>"
                 data-title="<?= htmlspecialchars(mb_strtolower($task->title)) ?>"
                 data-code="<?= htmlspecialchars(mb_strtolower($task->task_code)) ?>"
                 data-tags="<?= htmlspecialchars(mb_strtolower($task->tags ?? '')) ?>"
                 data-priority="<?= $task->priority ?>"
                 data-assignee-ids="<?= $assigneeIdsStr ?>"
                 data-assignee-id="<?= $task->assigned_to ?>"
                 data-deadline="<?= $task->deadline ?: '9999-12-31' ?>">
                
                <div class="cu-task-code-wrapper">
                    <span class="cu-row-spacer"></span>
                    <div class="cu-task-code"><?= htmlspecialchars($task->task_code) ?></div>
                </div>

                <div class="cu-task-title">
                    <span class="cu-badge-project cu-project-badge-color cu-project-badge-spacing" data-color="<?= htmlspecialchars($task->project_color ?? '#7B68EE') ?>">
                        <?= htmlspecialchars($task->project_title ?? __('Board::drawer_project_fallback')) ?>
                    </span>
                    <?= htmlspecialchars($task->title) ?>
                    <?php if ($isAiTask): ?>
                        <span class="cu-ai-pill-badge">
                            🤖 AI
                        </span>
                    <?php endif; ?>
                </div>

                <div>
                    <span class="cu-priority-badge cu-priority-<?= $task->priority ?>">
                        <?= $prLabels[$task->priority] ?? $task->priority ?>
                    </span>
                </div>

                <div class="cu-deadline <?= $isOverdue ? 'overdue' : '' ?>">
                    <span><?= $isOverdue ? '⚠️ ' : '📅 ' ?></span>
                    <span><?= htmlspecialchars($task->deadline ?: __('Board::none')) ?></span>
                </div>

                <div class="cu-task-assignee cu-task-assignee-list">
                    <?php foreach ($assignees as $asg): ?>
                        <div class="cu-avatar cu-avatar-sm" data-bg="<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>" title="<?= htmlspecialchars($asg->name ?? '') ?>">
                            <?= mb_substr($asg->name ?? 'U', 0, 1) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        $rowHtml = ob_get_clean();

        jsonResponse(true, '', [
            'task' => $task,
            'card_html' => $cardHtml,
            'row_html' => $rowHtml
        ]);
        break;

    // 2. YENİ GÖREV OLUŞTURMA
    case 'create_task':
        $sysSettings = $taskModel->getSystemSettings();
        $employeeCanCreate = (int)($sysSettings->employee_can_create_tasks ?? 1);
        $employeeCanAi = (int)($sysSettings->employee_can_ai_tasks ?? 0);

        if ($currentUser->role === 'client') {
            jsonResponse(false, __('Board::client_cannot_create_task'));
        }

        if ($currentUser->role !== 'admin' && !$employeeCanCreate) {
            jsonResponse(false, __('Board::employee_create_task_disabled'));
        }

        $projectId = (int)($_POST['project_id'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $deadline = $_POST['deadline'] ?? date('Y-m-d', strtotime('+7 days'));
        $tags = trim($_POST['tags'] ?? '');

        if ($currentUser->role !== 'admin' && !$employeeCanAi) {
            $filteredTags = array_filter(explode(',', $tags), function($t) {
                $clean = trim(mb_strtolower($t));
                return !in_array($clean, ['ai', '#ai', 'otonom', 'agent', 'yapay zeka']);
            });
            $tags = implode(', ', array_map('trim', $filteredTags));
        }

        $rawAssigned = $_POST['assigned_to'] ?? [];
        if (is_array($rawAssigned)) {
            $assignedIds = array_values(array_unique(array_filter(array_map('intval', $rawAssigned))));
        } else {
            $assignedIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$rawAssigned)))));
        }
        if (empty($assignedIds)) {
            $assignedIds = [(int)$currentUser->id];
        }
        $primaryAssigned = $assignedIds[0];

        if (empty($title)) {
            jsonResponse(false, __('Board::task_title_required'));
        }

        $taskCode = $taskModel->generateNextTaskCode();

        $newTaskId = $taskModel->createTask([
            'project_id'  => $projectId,
            'task_code'   => $taskCode,
            'title'       => $title,
            'description' => $description,
            'tags'        => $tags,
            'priority'    => $priority,
            'status'      => 'assigned',
            'deadline'    => $deadline,
            'created_by'  => $currentUser->id,
            'assigned_to' => $primaryAssigned
        ]);

        if ($newTaskId > 0) {
            $taskModel->addAssignees($newTaskId, $assignedIds);
            $taskModel->logActivity($newTaskId, $currentUser->id, 'created', null, 'assigned', __('Board::activity_task_created'));

            // Ek dosyalar
            if (!empty($_FILES['attachments'])) {
                $rawFiles = $_FILES['attachments'];
                if (is_array($rawFiles['name'])) {
                    for ($i = 0; $i < count($rawFiles['name']); $i++) {
                        if (!empty($rawFiles['tmp_name'][$i]) && $rawFiles['error'][$i] === UPLOAD_ERR_OK) {
                            $singleFile = [
                                'name'     => $rawFiles['name'][$i],
                                'type'     => $rawFiles['type'][$i],
                                'tmp_name' => $rawFiles['tmp_name'][$i],
                                'error'    => $rawFiles['error'][$i],
                                'size'     => $rawFiles['size'][$i],
                            ];
                            $upRes = \Modules\Storage\Services\StorageService::upload($singleFile, 'tasks');
                            if ($upRes['success']) {
                                $taskModel->addAttachment($newTaskId, $currentUser->id, $upRes);
                            }
                        }
                    }
                }
            } elseif (!empty($_FILES['attachment']) && !empty($_FILES['attachment']['tmp_name'])) {
                $upRes = \Modules\Storage\Services\StorageService::upload($_FILES['attachment'], 'tasks');
                if ($upRes['success']) {
                    $taskModel->addAttachment($newTaskId, $currentUser->id, $upRes);
                }
            }

            \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', $projectId, $newTaskId, (int)$currentUser->id, [
                'task_id'     => $newTaskId,
                'task_code'   => $taskCode,
                'title'       => $title,
                'status'      => 'assigned',
                'user_name'   => $currentUser->name
            ]);

            if (\Modules\Board\Services\TaskLifecycleService::isAiTask($tags)) {
                \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable($projectId);
            }

            jsonResponse(true, __('Board::task_created_success'), ['task_id' => $newTaskId, 'task_code' => $taskCode]);
        } else {
            jsonResponse(false, __('Board::task_create_error'));
        }
        break;

    // 2.1 AI GÖREV MİMARI: KISA TALEBİ TEKNİK ŞARTNAME & PROMPT'A DÖNÜŞTÜR
    case 'refine_task_specification':
        $aiController = new \Modules\AiArchitect\Controllers\AiArchitectController();
        $aiController->refineSpecification();
        exit;
        break;

    // 4. İŞ AKIŞI AKSİYONU (WORKFLOW STATE TRANSITIONS)
    case 'workflow_action':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $wAction = $_POST['workflow_action'] ?? '';

        if ($taskId <= 0 || empty($wAction)) {
            jsonResponse(false, __('Board::invalid_params'));
        }

        $task = $taskModel->findByIdOrCode($taskId);
        if (!$task) {
            jsonResponse(false, __('Board::task_not_found'));
        }

        switch ($wAction) {
            // A) KULLANICI GÖREVİ KABUL EDER
            case 'accept':
                if (\Modules\Board\Services\TaskLifecycleService::isAiTask($task)) {
                    $otherRunning = $taskModel->getRunningAiTasks((int)$task->project_id, $taskId);
                    foreach ($otherRunning as $other) {
                        $taskModel->updateTaskStatus((int)$other->id, 'assigned');
                        $taskModel->logActivity((int)$other->id, $currentUser->id, 'status_change', 'in_progress', 'assigned', __('Board::activity_ai_task_paused', ['code' => $task->task_code]));
                        \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, (int)$other->id, (int)$currentUser->id, [
                            'task_id'    => (int)$other->id,
                            'task_code'  => $other->task_code,
                            'old_status' => 'in_progress',
                            'new_status' => 'assigned'
                        ]);
                    }
                }

                $taskModel->updateTaskStatus($taskId, 'in_progress');
                $taskModel->logActivity($taskId, $currentUser->id, 'accepted', 'assigned', 'in_progress', __('Board::activity_user_accepted', ['user' => $currentUser->name]));

                if (\Modules\Board\Services\TaskLifecycleService::isAiTask($task)) {
                    \Modules\Board\Services\TaskLifecycleService::triggerTaskStartedWebhook($taskId, 'assigned', ['triggered_by' => $currentUser->name]);
                }

                \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'assigned',
                    'new_status'  => 'in_progress',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                jsonResponse(true, __('Board::task_accepted_success'));
                break;

            // B) KULLANICI REVİZYON / AÇIKLAMA İSTER
            case 'request_modification':
                $reason = trim($_POST['reason'] ?? '');
                if (empty($reason)) {
                    jsonResponse(false, __('Board::mod_reason_required'));
                }

                $taskModel->updateTaskStatus($taskId, 'modification_requested');
                $taskModel->addModificationRequest($taskId, $currentUser->id, $reason);
                $taskModel->logActivity($taskId, $currentUser->id, 'modification_requested', $task->status, 'modification_requested', __('Board::activity_mod_requested', ['user' => $currentUser->name, 'reason' => $reason]));

                \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => $task->status,
                    'new_status'  => 'modification_requested',
                    'reason'      => $reason,
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                jsonResponse(true, __('Board::revision_requested_success'));
                break;

            // C) ADMİN REVİZYON TALEBİNİ YANITLAR & TEKRAR ATAR
            case 'resolve_modification':
                if ($currentUser->role !== 'admin') {
                    jsonResponse(false, __('Board::unauthorized_access'));
                }

                $taskModel->updateTaskStatus($taskId, 'assigned');
                $taskModel->resolveModification($taskId);
                $taskModel->logActivity($taskId, $currentUser->id, 'modification_resolved', 'modification_requested', 'assigned', __('Board::activity_mod_resolved', ['user' => $currentUser->name]));

                jsonResponse(true, __('Board::revision_resolved_success'));
                break;

            // D) KULLANICI GÖREVİ TESLİM EDER
            case 'submit_task':
                $notes = trim($_POST['notes'] ?? '');
                $attachmentUrl = trim($_POST['attachment_url'] ?? '');

                if (empty($notes)) {
                    jsonResponse(false, __('Board::submission_notes_required'));
                }

                $file = $_FILES['attachment'] ?? ($_FILES['file'] ?? null);
                if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
                    $upRes = \Modules\Storage\Services\StorageService::upload($file, 'submissions');
                    if (!$upRes['success']) {
                        jsonResponse(false, $upRes['error']);
                    }
                    $attachmentUrl = $upRes['file_path'];
                    $taskModel->addAttachment($taskId, $currentUser->id, $upRes);
                }

                $taskModel->updateTaskStatus($taskId, 'submitted');
                $taskModel->addSubmission($taskId, $currentUser->id, $notes, $attachmentUrl);
                $taskModel->logActivity($taskId, $currentUser->id, 'submitted', $task->status, 'submitted', __('Board::activity_user_submitted', ['user' => $currentUser->name, 'notes' => $notes]) . ($attachmentUrl ? " (Ek: {$attachmentUrl})" : ""));

                \Modules\SSE\Services\SseBroadcaster::broadcast('task_submitted', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'        => $taskId,
                    'task_code'      => $task->task_code,
                    'title'          => $task->title,
                    'old_status'     => $task->status,
                    'new_status'     => 'submitted',
                    'notes'          => $notes,
                    'attachment_url' => $attachmentUrl,
                    'user_id'        => (int)$currentUser->id,
                    'user_name'      => $currentUser->name
                ]);

                $attRows = $taskModel->getTaskAttachments($taskId);
                $bDir = dirname(__DIR__, 3);
                $apiConf = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
                $bUrl = rtrim($apiConf['base_url'], '/');
                $atts = [];
                foreach ($attRows as $ar) {
                    $rPath = str_replace('\\', '/', (string)$ar->file_path);
                    $rlPath = ltrim(preg_replace('#^/?(?:[a-zA-Z0-9_-]+/)?(Public/.*)$#i', '$1', $rPath), '/');
                    $cAbsPath = str_replace('\\', '/', $bDir . '/' . $rlPath);
                    $atts[] = [
                        'id'        => (int)$ar->id,
                        'file_name' => $ar->file_name,
                        'file_type' => $ar->file_type,
                        'file_size' => (int)$ar->file_size,
                        'file_path' => $ar->file_path,
                        'abs_path'  => $cAbsPath,
                        'file_uri'  => 'file:///' . ltrim($cAbsPath, '/'),
                        'url'       => $bUrl . '/' . $rlPath,
                        'exists'    => file_exists($cAbsPath),
                    ];
                }

                \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.submitted', [
                    'task_id'          => $taskId,
                    'task_code'        => $task->task_code,
                    'title'            => $task->title,
                    'description'      => (string)($task->description ?? ''),
                    'attachments'      => $atts,
                    'project_id'       => (int)$task->project_id,
                    'submission_notes' => $notes
                ]);

                jsonResponse(true, __('Board::task_submitted_success'), [
                    'attachment_url' => $attachmentUrl
                ]);
                break;

            // E) ADMİN VEYA MÜŞTERİ TESLİMATI ONAYLAR
            case 'approve':
                if ($currentUser->role !== 'admin' && $currentUser->role !== 'client') {
                    jsonResponse(false, __('Board::unauthorized_access'));
                }
                if ($currentUser->role === 'client' && (int)$task->project_id !== (int)($currentUser->client_project_id ?? 0)) {
                    jsonResponse(false, __('Board::only_own_project_approve'));
                }

                $taskModel->approveSubmission($taskId);
                $userLabel = ($currentUser->role === 'client') ? __('Board::role_client') : __('Board::role_admin');
                $taskModel->logActivity($taskId, $currentUser->id, 'completed', 'submitted', 'completed', __('Board::activity_delivery_approved', ['role' => $userLabel, 'user' => $currentUser->name]));

                \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'submitted',
                    'new_status'  => 'completed',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$taskId);

                jsonResponse(true, __('Board::task_approved_success'));
                break;

            // F) ADMİN VEYA MÜŞTERİ DÜZELTME / DEĞİŞİKLİK İSTER
            case 'request_changes':
                if ($currentUser->role !== 'admin' && $currentUser->role !== 'client') {
                    jsonResponse(false, __('Board::unauthorized_access'));
                }
                if ($currentUser->role === 'client' && (int)$task->project_id !== (int)($currentUser->client_project_id ?? 0)) {
                    jsonResponse(false, __('Board::only_own_project_changes'));
                }

                $reason = trim($_POST['feedback'] ?? $_POST['reason'] ?? $_POST['notes'] ?? __('Board::default_revision_reason'));
                $taskModel->requestChanges($taskId, $currentUser->id, $reason);

                $userLabel = ($currentUser->role === 'client') ? __('Board::role_client') : __('Board::role_admin');
                $taskModel->logActivity($taskId, $currentUser->id, 'modification_requested', 'submitted', 'modification_requested', __('Board::activity_delivery_changes_requested', ['role' => $userLabel, 'user' => $currentUser->name, 'reason' => $reason]));

                \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'submitted',
                    'new_status'  => 'modification_requested',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                jsonResponse(true, __('Board::task_changes_requested_success'));
                break;

            default:
                jsonResponse(false, __('Board::unknown_workflow_action'));
        }
        break;

    // 5. GÖREVE YORUM EKLEME
    case 'add_comment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $commentText = trim($_POST['comment'] ?? '');

        if ($taskId <= 0 || empty($commentText)) {
            jsonResponse(false, __('Board::comment_empty_error'));
        }

        $task = $taskModel->getTaskWithProject($taskId);
        if (!$task) {
            jsonResponse(false, __('Board::task_not_found'));
        }

        if ($currentUser->role === 'client' && (int)$task->project_id !== (int)($currentUser->client_project_id ?? 0)) {
            jsonResponse(false, __('Board::comment_unauthorized'));
        }

        $isInternal = ($currentUser->role !== 'client' && !empty($_POST['is_internal'])) ? 1 : 0;
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');

        $file = $_FILES['attachment'] ?? ($_FILES['file'] ?? null);
        if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $upRes = \Modules\Storage\Services\StorageService::upload($file, 'comments');
            if (!$upRes['success']) {
                jsonResponse(false, $upRes['error']);
            }
            $attachmentUrl = $upRes['file_path'];
            $taskModel->addAttachment($taskId, $currentUser->id, $upRes);
        }

        $commentId = $taskModel->addComment($taskId, $currentUser->id, $commentText, $isInternal);
        if ($commentId > 0) {
            $internalTagStr = $isInternal ? ("🔒 [" . __('Board::internal_team_badge') . "] ") : "";
            $taskModel->logActivity($taskId, $currentUser->id, 'comment', null, null, __('Board::activity_comment_added', ['user' => $currentUser->name, 'tag' => $internalTagStr, 'comment' => $commentText]) . ($attachmentUrl ? " (Ek: {$attachmentUrl})" : ""));

            \Modules\SSE\Services\SseBroadcaster::broadcast('new_comment', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                'comment_id'     => $commentId,
                'task_id'        => $taskId,
                'task_code'      => $task->task_code ?? 'TSK',
                'title'          => $task->title ?? __('Board::default_task_title'),
                'author'         => $currentUser->name,
                'comment'        => $commentText,
                'attachment_url' => $attachmentUrl,
                'is_internal'    => $isInternal
            ]);

            jsonResponse(true, __('Board::comment_added_success'), [
                'comment_id'     => $commentId,
                'attachment_url' => $attachmentUrl
            ]);
        }
        jsonResponse(false, __('Board::comment_error'));
        break;

    // 5.1 GÖREV YORUMUNU DÜZENLEME
    case 'update_comment':
        $commentId = (int)($_POST['comment_id'] ?? ($_POST['id'] ?? 0));
        $newText = trim($_POST['comment'] ?? ($_POST['text'] ?? ''));

        if ($commentId <= 0 || empty($newText)) {
            jsonResponse(false, __('Board::comment_update_empty'));
        }

        $comm = $taskModel->getCommentWithTask($commentId);
        if (!$comm) {
            jsonResponse(false, __('Board::comment_not_found'));
        }

        $isOwner = ((int)$comm->user_id === (int)$currentUser->id);
        $isAdmin = ($currentUser->role === 'admin');
        if (!$isOwner && !$isAdmin) {
            jsonResponse(false, __('Board::comment_update_unauthorized'));
        }

        $updated = $taskModel->updateComment($commentId, $newText);
        if ($updated) {
            $taskModel->logActivity((int)$comm->task_id, (int)$currentUser->id, 'comment_edit', null, null, __('Board::activity_comment_edited', ['user' => $currentUser->name, 'comment' => $newText]));

            \Modules\SSE\Services\SseBroadcaster::broadcast('comment_updated', (int)$comm->project_id, (int)$comm->task_id, (int)$currentUser->id, [
                'comment_id' => $commentId,
                'task_id'    => (int)$comm->task_id,
                'comment'    => $newText,
                'is_edited'  => 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'editor'     => $currentUser->name
            ]);

            jsonResponse(true, __('Board::comment_updated_success'), [
                'comment_id' => $commentId,
                'comment'    => $newText,
                'is_edited'  => 1
            ]);
        }
        jsonResponse(false, __('Board::comment_update_error'));
        break;

    // 6. KANBAN SÜRÜKLE & BIRAK DURUM GÜNCELLEME
    case 'update_task_status':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['assigned', 'accepted', 'in_progress', 'modification_requested', 'submitted', 'under_review', 'completed'];

        if ($taskId <= 0 || !in_array($newStatus, $allowed)) {
            jsonResponse(false, __('Board::invalid_params'));
        }

        $task = $taskModel->findByIdOrCode($taskId);
        if (!$task) {
            jsonResponse(false, __('Board::task_not_found'));
        }
        $oldStatus = $task->status;

        if ($newStatus === 'completed' && $currentUser->role !== 'admin') {
            jsonResponse(false, __('Board::only_admin_can_complete'));
        }

        if (in_array($newStatus, ['submitted', 'completed'], true)) {
            $uncompletedSubs = $taskModel->countUnfinishedSubtasks($taskId);
            if ($uncompletedSubs > 0) {
                jsonResponse(false, __('Board::unfinished_subtasks_error', ['count' => $uncompletedSubs]));
            }
        }

        if (in_array($newStatus, ['assigned', 'created'], true)) {
            $runningSubs = $taskModel->countRunningSubtasks($taskId);
            if ($runningSubs > 0) {
                jsonResponse(false, __('Board::running_subtasks_error'));
            }
        }

        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        $taskModel->updateTaskStatus($taskId, $newStatus, $completedAt);

        // Alt görev senkronizasyonları
        if (!empty($task->parent_task_id) && $newStatus === 'in_progress') {
            $pId = (int)$task->parent_task_id;
            $parentTask = $taskModel->findByIdOrCode($pId);
            if ($parentTask && $parentTask->status !== 'in_progress') {
                $taskModel->updateTaskStatus($pId, 'in_progress');
                \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $pId, (int)$currentUser->id, [
                    'task_id'    => $pId,
                    'task_code'  => $parentTask->task_code,
                    'title'      => $parentTask->title,
                    'old_status' => $parentTask->status,
                    'new_status' => 'in_progress',
                    'user_name'  => 'System'
                ]);
            }
        }

        if (!empty($task->parent_task_id) && in_array($newStatus, ['submitted', 'completed'], true)) {
            $pId = (int)$task->parent_task_id;
            if ($taskModel->countUnfinishedSubtasks($pId) === 0) {
                $parentTask = $taskModel->findByIdOrCode($pId);
                if ($parentTask && !in_array($parentTask->status, ['submitted', 'completed'], true)) {
                    $oldPStatus = $parentTask->status;
                    $taskModel->updateTaskStatus($pId, 'submitted');
                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $pId, (int)$currentUser->id, [
                        'task_id'    => $pId,
                        'task_code'  => $parentTask->task_code,
                        'title'      => $parentTask->title,
                        'old_status' => $oldPStatus,
                        'new_status' => 'submitted',
                        'user_name'  => 'System Rollup'
                    ]);
                }
            }
        }

        $taskModel->logActivity($taskId, $currentUser->id, 'status_change', $oldStatus, $newStatus, __('Board::activity_status_changed', ['user' => $currentUser->name, 'old' => $oldStatus, 'new' => $newStatus]));

        \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
            'task_id'     => $taskId,
            'task_code'   => $task->task_code,
            'title'       => $task->title,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'user_id'     => (int)$currentUser->id,
            'user_name'   => $currentUser->name
        ]);

        $isCurrentTaskAi = \Modules\Board\Services\TaskLifecycleService::isAiTask($task);
        if ($newStatus === 'in_progress' && $oldStatus !== 'in_progress') {
            if ($isCurrentTaskAi) {
                $otherRunning = $taskModel->getRunningAiTasks((int)$task->project_id, $taskId);
                foreach ($otherRunning as $other) {
                    $taskModel->updateTaskStatus((int)$other->id, 'assigned');
                    $taskModel->logActivity((int)$other->id, $currentUser->id, 'status_change', 'in_progress', 'assigned', __('Board::activity_ai_task_requeued', ['code' => $task->task_code]));
                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', (int)$task->project_id, (int)$other->id, (int)$currentUser->id, [
                        'task_id'    => (int)$other->id,
                        'task_code'  => $other->task_code,
                        'old_status' => 'in_progress',
                        'new_status' => 'assigned'
                    ]);
                }

                if ($oldStatus === 'modification_requested') {
                    $revReason = $taskModel->getLastModificationReason($taskId) ?: __('Board::default_revision_reason');
                    \Modules\Board\Services\TaskLifecycleService::triggerTaskRevisionWebhook($taskId, $revReason, ['triggered_by' => $currentUser->name]);
                } else {
                    \Modules\Board\Services\TaskLifecycleService::triggerTaskStartedWebhook($taskId, $oldStatus, ['triggered_by' => $currentUser->name]);
                }
            }
        } elseif ($newStatus === 'submitted') {
            if ($isCurrentTaskAi) {
                $attRows2 = $taskModel->getTaskAttachments($taskId);
                $bDir2 = dirname(__DIR__, 3);
                $apiConf2 = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
                $bUrl2 = rtrim($apiConf2['base_url'], '/');
                $atts2 = [];
                foreach ($attRows2 as $ar2) {
                    $rPath2 = str_replace('\\', '/', (string)$ar2->file_path);
                    $rlPath2 = ltrim(preg_replace('#^/?(?:[a-zA-Z0-9_-]+/)?(Public/.*)$#i', '$1', $rPath2), '/');
                    $cAbsPath2 = str_replace('\\', '/', $bDir2 . '/' . $rlPath2);
                    $atts2[] = [
                        'id'        => (int)$ar2->id,
                        'file_name' => $ar2->file_name,
                        'file_type' => $ar2->file_type,
                        'file_size' => (int)$ar2->file_size,
                        'file_path' => $ar2->file_path,
                        'abs_path'  => $cAbsPath2,
                        'file_uri'  => 'file:///' . ltrim($cAbsPath2, '/'),
                        'url'       => $bUrl2 . '/' . $rlPath2,
                        'exists'    => file_exists($cAbsPath2),
                    ];
                }

                \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.submitted', [
                    'task_id'          => $taskId,
                    'task_code'        => $task->task_code,
                    'title'            => $task->title,
                    'description'      => (string)($task->description ?? ''),
                    'attachments'      => $atts2,
                    'project_id'       => (int)$task->project_id,
                    'submission_notes' => 'Moved to submitted via Board'
                ]);
            } else {
                \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$taskId);
            }
        } elseif ($newStatus === 'completed') {
            \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$taskId);
        } elseif ($oldStatus === 'in_progress' && $newStatus === 'assigned' && $isCurrentTaskAi) {
            \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.requeued', [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'project_id' => (int)$task->project_id
            ]);
        }

        jsonResponse(true, __('Board::task_status_updated'), ['new_status' => $newStatus]);
        break;

    // 8. CHECKLIST DURUM DEĞİŞTİRME (TOGGLE)
    case 'toggle_checklist':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $isCompleted = (int)($_POST['is_completed'] ?? 0);
        if ($itemId <= 0) {
            jsonResponse(false, __('Board::invalid_item_id'));
        }
        $taskModel->toggleChecklistItem($itemId, $isCompleted);
        jsonResponse(true, __('Board::checklist_updated'), ['is_completed' => $isCompleted]);
        break;

    // 9. YENİ CHECKLIST MADDESİ EKLEME
    case 'add_checklist_item':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($taskId <= 0 || empty($title)) {
            jsonResponse(false, __('Board::checklist_item_empty'));
        }
        $newId = $taskModel->addChecklistItem($taskId, $title);
        if ($newId > 0) {
            jsonResponse(true, __('Board::checklist_added'), ['item_id' => $newId, 'title' => $title]);
        }
        jsonResponse(false, __('Board::checklist_add_error'));
        break;

    // 10. SATIR İÇİ ALAN GÜNCELLEME (INLINE EDITING)
    case 'update_task_field':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');
        $allowedFields = ['title', 'description', 'priority', 'deadline', 'tags', 'time_estimate', 'time_spent'];

        if (!in_array($field, $allowedFields) || $taskId <= 0) {
            jsonResponse(false, __('Board::invalid_field_or_task'));
        }

        if ($field === 'tags' && stripos($value, 'ai') !== false && ($currentUser->role ?? '') !== 'admin') {
            $sys = $taskModel->getSystemSettings();
            $canAi = (int)($sys->employee_can_ai_tasks ?? 0);
            if ($canAi !== 1) {
                jsonResponse(false, __('Board::employee_ai_tag_forbidden'));
            }
        }

        $ok = $taskModel->updateTaskField($taskId, $field, $value);
        if ($ok) {
            $taskModel->logActivity($taskId, $currentUser->id, 'field_update', null, null, __('Board::activity_field_updated', ['user' => $currentUser->name, 'field' => $field, 'value' => $value]));
            jsonResponse(true, __('Board::field_updated'), ['field' => $field, 'value' => $value]);
        }
        jsonResponse(false, __('Board::invalid_field_or_task'));
        break;

    // 10.1 CANLI SÜRE TAKİBİ
    case 'track_time':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $minutesAdded = (int)($_POST['minutes'] ?? 0);
        if ($taskId <= 0 || $minutesAdded <= 0) {
            jsonResponse(false, __('Board::invalid_task_or_time'));
        }

        $ok = $taskModel->logTime($taskId, $currentUser->id, $minutesAdded);
        if ($ok) {
            $taskModel->logActivity($taskId, $currentUser->id, 'time_tracked', null, null, __('Board::activity_time_tracked', ['user' => $currentUser->name, 'minutes' => $minutesAdded]));
            jsonResponse(true, __('Board::time_tracked_success'));
        }
        jsonResponse(false, __('Board::invalid_task_or_time'));
        break;

    // 10.2 GÖREV BAĞIMLILIĞI EKLEME
    case 'add_dependency':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $dependsOnTaskId = (int)($_POST['depends_on_task_id'] ?? 0);
        $depType = $_POST['type'] ?? 'waiting_on';

        if ($taskId <= 0 || $dependsOnTaskId <= 0 || $taskId === $dependsOnTaskId) {
            jsonResponse(false, __('Board::invalid_task_dependency'));
        }

        $ok = $taskModel->addDependency($taskId, $dependsOnTaskId, $depType);
        if ($ok) {
            $taskModel->logActivity($taskId, $currentUser->id, 'dependency_added', null, null, __('Board::activity_dependency_added', ['user' => $currentUser->name]));
            jsonResponse(true, __('Board::dependency_added_success'));
        }
        jsonResponse(false, __('Board::dependency_already_exists'));
        break;

    // 10.3 BAĞIMLILIK SİLME
    case 'remove_dependency':
        $depId = (int)($_POST['dep_id'] ?? 0);
        if ($depId <= 0) {
            jsonResponse(false, __('Board::invalid_dependency_id'));
        }
        $taskModel->removeDependency($depId);
        jsonResponse(true, __('Board::dependency_removed_success'));
        break;

    // 10.4 GÖREVE RESİM / EK DOSYA YÜKLEME
    case 'upload_task_attachment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, __('Board::invalid_task_id'));
        }

        if (empty($_FILES['attachment']) && empty($_FILES['file'])) {
            jsonResponse(false, __('Board::no_file_selected'));
        }

        $file = $_FILES['attachment'] ?? $_FILES['file'];
        $uploadResult = \Modules\Storage\Services\StorageService::upload($file, 'tasks');

        if (!$uploadResult['success']) {
            jsonResponse(false, $uploadResult['error'] ?? __('Board::file_upload_error'));
        }

        $attId = $taskModel->addAttachment($taskId, $currentUser->id, $uploadResult);
        $taskModel->logActivity($taskId, $currentUser->id, 'attachment_uploaded', null, null, __('Board::activity_attachment_uploaded', ['user' => $currentUser->name, 'file' => $uploadResult['file_name']]));

        $task = $taskModel->findByIdOrCode($taskId);
        $projId = $task ? (int)$task->project_id : 1;

        $attachmentPayload = [
            'id'        => $attId,
            'task_id'   => $taskId,
            'file_name' => $uploadResult['file_name'],
            'file_path' => $uploadResult['file_path'],
            'file_size' => $uploadResult['file_size'],
            'file_type' => $uploadResult['file_type'],
            'storage'   => $uploadResult['storage'] ?? 'local',
            'is_image'  => $uploadResult['is_image'] ?? false,
            'created_at'=> date('Y-m-d H:i:s'),
            'user_name' => $currentUser->name
        ];

        \Modules\SSE\Services\SseBroadcaster::broadcast('task_attachment_added', $projId, $taskId, (int)$currentUser->id, [
            'task_id'    => $taskId,
            'attachment' => $attachmentPayload
        ]);

        jsonResponse(true, __('Board::attachment_uploaded_success'), [
            'data'       => $attachmentPayload,
            'attachment' => $attachmentPayload,
            'id'         => $attId,
            ...$attachmentPayload
        ]);
        break;

    // 10.5 GÖREV EK DOSYASI SİLME
    case 'delete_task_attachment':
        $attId = (int)($_POST['attachment_id'] ?? 0);
        if ($attId <= 0) {
            jsonResponse(false, __('Board::invalid_attachment_id'));
        }

        $att = $taskModel->deleteTaskAttachment($attId);
        if (!$att) {
            jsonResponse(false, __('Board::attachment_not_found'));
        }

        \Modules\Storage\Services\StorageService::delete($att->file_path);
        $taskModel->logActivity((int)$att->task_id, $currentUser->id, 'attachment_deleted', null, null, __('Board::activity_attachment_deleted', ['user' => $currentUser->name, 'file' => $att->file_name]));

        $task = $taskModel->findByIdOrCode((int)$att->task_id);
        $projId = $task ? (int)$task->project_id : 1;

        \Modules\SSE\Services\SseBroadcaster::broadcast('task_attachment_deleted', $projId, (int)$att->task_id, (int)$currentUser->id, [
            'task_id'       => (int)$att->task_id,
            'attachment_id' => $attId
        ]);

        jsonResponse(true, __('Board::attachment_deleted_success'), [
            'attachment_id' => $attId,
            'data'          => ['attachment_id' => $attId]
        ]);
        break;

    // 10.6 EVRENSEL DOSYA / EKRAN GÖRÜNTÜSÜ YÜKLEYİCİ
    case 'upload_attachment':
        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            jsonResponse(false, __('Board::no_file_selected'));
        }
        $file = $_FILES['file'] ?? $_FILES['attachment'];
        $context = preg_replace('/[^a-z0-9_-]/i', '', $_POST['context'] ?? 'tasks') ?: 'tasks';
        $taskId = (int)($_POST['task_id'] ?? 0);

        $uploadResult = \Modules\Storage\Services\StorageService::upload($file, $context);
        if (!$uploadResult['success']) {
            jsonResponse(false, $uploadResult['error'] ?? __('Board::file_upload_error'));
        }

        $recordId = 0;
        if ($context === 'tasks' && $taskId > 0) {
            $recordId = $taskModel->addAttachment($taskId, $currentUser->id, $uploadResult);
        }

        $attRecordPayload = [
            'id'        => $recordId,
            'file_name' => $uploadResult['file_name'],
            'file_path' => $uploadResult['file_path'],
            'file_size' => $uploadResult['file_size'],
            'file_type' => $uploadResult['file_type'],
            'storage'   => $uploadResult['storage'] ?? 'local',
            'is_image'  => $uploadResult['is_image'] ?? false,
            'user_name' => $currentUser->name
        ];

        jsonResponse(true, __('Board::attachment_uploaded_success'), [
            'data'       => $attRecordPayload,
            'attachment' => $attRecordPayload,
            ...$attRecordPayload
        ]);
        break;

    // 11. ALT GÖREV OLUŞTURMA (SUBTASKS)
    case 'create_subtask':
        $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $assignedTo = (int)($_POST['assigned_to'] ?? $currentUser->id);

        if ($parentTaskId <= 0 || empty($title)) {
            jsonResponse(false, __('Board::subtask_required_fields'));
        }

        $subRes = $taskModel->createSubtask($parentTaskId, $title, $priority, (int)$currentUser->id, $assignedTo);
        if ($subRes) {
            jsonResponse(true, __('Board::subtask_created_success'), $subRes);
        }
        jsonResponse(false, __('Board::subtask_create_error'));
        break;

    // 12. BİLDİRİMLERİ GETİR
    case 'get_notifications':
        $notifs = $taskModel->getUserNotifications((int)$currentUser->id, 15);
        jsonResponse(true, '', ['notifications' => $notifs]);
        break;

    // 13. BİLDİRİMİ OKUNDU İŞARETLE
    case 'mark_notification_read':
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $taskModel->markNotificationAsRead($notifId);
            jsonResponse(true, __('Board::notification_read'));
        }
        jsonResponse(false, __('Board::invalid_notification_id'));
        break;

    // 18. GÖREV SİL
    case 'delete_task':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, __('Board::invalid_task_id'));
        }

        $task = $taskModel->findByIdOrCode($taskId);
        if (!$task) {
            jsonResponse(false, __('Board::task_not_found'));
        }

        $deleted = $taskModel->deleteTaskCascading($taskId);
        if ($deleted) {
            \Modules\SSE\Services\SseBroadcaster::broadcast('task_deleted', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'title'      => $task->title,
                'project_id' => (int)$task->project_id
            ]);
            jsonResponse(true, __('Board::task_deleted_success'));
        }
        jsonResponse(false, __('Board::task_delete_error'));
        break;

    // 19. KONTROL LİSTESİ MADDESİ SİL
    case 'delete_checklist_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            jsonResponse(false, __('Board::invalid_item_id'));
        }
        $taskModel->deleteChecklistItem($itemId);
        jsonResponse(true, __('Board::checklist_item_deleted'));
        break;

    // 22. GÖREVE ÇALIŞAN EKLE
    case 'add_task_assignee':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($taskId <= 0 || $userId <= 0) {
            jsonResponse(false, __('Board::invalid_params'));
        }

        $u = $taskModel->addTaskAssignee($taskId, $userId);
        if (!$u) {
            jsonResponse(false, __('Board::assignee_already_added'));
        }

        $taskModel->logActivity($taskId, $currentUser->id, 'assignee_added', null, null, __('Board::activity_assignee_added', ['user' => $currentUser->name, 'target' => $u->name]));

        jsonResponse(true, __('Board::assignee_added_success', ['name' => $u->name]), ['user' => $u]);
        break;

    // 23. GÖREVDEN ÇALIŞAN ÇIKAR
    case 'remove_task_assignee':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($taskId <= 0 || $userId <= 0) {
            jsonResponse(false, __('Board::invalid_params'));
        }

        $uName = $taskModel->getUserNameById($userId) ?: __('Board::default_user');

        $taskModel->removeAssignee($taskId, $userId);
        $taskModel->logActivity($taskId, $currentUser->id, 'assignee_removed', null, null, __('Board::activity_assignee_removed', ['user' => $currentUser->name, 'target' => $uName]));

        jsonResponse(true, __('Board::assignee_removed_success', ['name' => $uName]));
        break;

    default:
        jsonResponse(false, __('Board::invalid_ajax_action'));
}
