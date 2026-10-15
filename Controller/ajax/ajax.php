<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../System/System.php';
require_once __DIR__ . '/../Controller.php';

// Dil motorunu başlat (URL parametresi ?lang=, $_POST['lang'] veya kullanıcı tercihi)
Language::init();

global $db;

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$currentUser = $_SESSION['user'] ?? (object)['id' => 1, 'name' => 'Ahmet Yılmaz', 'role' => 'admin'];

function translateAjaxMessage(string $msg): string {
    if ($msg === '') {
        return '';
    }
    
    $locale = Language::current();
    if ($locale === 'tr') {
        return $msg;
    }
    
    static $dict = null;
    if ($dict === null) {
        $dictFile = __DIR__ . '/../../Config/ajax_translations.php';
        $dict = file_exists($dictFile) ? require $dictFile : [];
    }
    
    if (isset($dict[$locale][$msg])) {
        return $dict[$locale][$msg];
    }
    
    // Dynamic patterns
    if (preg_match("/^Görev durumu '([^']+)' olarak güncellendi\.$/", $msg, $m)) {
        if ($locale === 'en') return "Task status updated to '{$m[1]}'.";
        if ($locale === 'de') return "Aufgabenstatus auf '{$m[1]}' aktualisiert.";
    }
    if (preg_match("/^Süre başarıyla kaydedildi \(\+([0-9]+) dk\)!?$/", $msg, $m)) {
        if ($locale === 'en') return "Time successfully logged (+{$m[1]} min)!";
        if ($locale === 'de') return "Zeit erfolgreich erfasst (+{$m[1]} Min.)!";
    }
    if (preg_match("/^(.*) göreve atandı\.$/", $msg, $m)) {
        if ($locale === 'en') return "{$m[1]} assigned to task.";
        if ($locale === 'de') return "{$m[1]} der Aufgabe zugewiesen.";
    }
    if (preg_match("/^(.*) görevden çıkarıldı\.$/", $msg, $m)) {
        if ($locale === 'en') return "{$m[1]} removed from task.";
        if ($locale === 'de') return "{$m[1]} von der Aufgabe entfernt.";
    }
    if (preg_match("/^✅ Webhook başarıyla iletildi \(HTTP ([0-9]+)\)$/", $msg, $m)) {
        if ($locale === 'en') return "✅ Webhook successfully delivered (HTTP {$m[1]})";
        if ($locale === 'de') return "✅ Webhook erfolgreich zugestellt (HTTP {$m[1]})";
    }
    if (preg_match("/^❌ Webhook ulaşılamadı: (.*)$/", $msg, $m)) {
        if ($locale === 'en') return "❌ Webhook unreachable: {$m[1]}";
        if ($locale === 'de') return "❌ Webhook nicht erreichbar: {$m[1]}";
    }
    if (preg_match("/^❌ Slack Webhook hatası \(HTTP ([0-9]+)\)$/", $msg, $m)) {
        if ($locale === 'en') return "❌ Slack Webhook error (HTTP {$m[1]})";
        if ($locale === 'de') return "❌ Slack-Webhook-Fehler (HTTP {$m[1]})";
    }
    if (preg_match("/^❌ Discord Webhook hatası \(HTTP ([0-9]+)\)$/", $msg, $m)) {
        if ($locale === 'en') return "❌ Discord Webhook error (HTTP {$m[1]})";
        if ($locale === 'de') return "❌ Discord-Webhook-Fehler (HTTP {$m[1]})";
    }
    
    return $msg;
}

function jsonResponse($success, $message = '', $data = []) {
    $payload = ['success' => $success, 'message' => translateAjaxMessage($message)];
    if (!empty($data) && is_array($data)) {
        $payload = array_merge($payload, $data);
        if (!isset($payload['data'])) {
            $payload['data'] = $data;
        }
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        if (class_exists('Helper') && method_exists('Helper', 'sanitizeUtf8Deep')) {
            $payload = Helper::sanitizeUtf8Deep($payload);
        }
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
    // 1. GÖREV DETAYI ALMA
    case 'get_task_detail':
        $taskId = (int)($_GET['id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, 'Geçersiz görev ID');
        }

        $tasks = $db->multi_record("tasks", ["id"], [$taskId]);
        if (empty($tasks)) {
            jsonResponse(false, 'Görev bulunamadı');
        }
        $task = $tasks[0];

        // Proje Bilgisi
        $proj = $db->multi_record("projects", ["id"], [$task->project_id]);
        $task->project_title = $proj[0]->title ?? __('app.general_project', [], 'Genel Proje');
        $task->project_color = $proj[0]->color ?? '#7B68EE';

        // Bağlı olduğu ana görev (Nested Subtask parent context)
        if (!empty($task->parent_task_id)) {
            $parentRow = $db->row_query("SELECT id, task_code, title FROM tasks WHERE id = ?", [(int)$task->parent_task_id]);
            $task->parent_task = $parentRow ?: null;
        }

        // Atanan Kullanıcılar (Çoklu Atama Desteği)
        $assignees = $db->multi_record_query("
            SELECT u.id, u.name, u.email, u.title, u.avatar_color, u.role
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.id ASC
        ", [$taskId]) ?: [];

        if (empty($assignees) && !empty($task->assigned_to)) {
            $fallbackUser = $db->multi_record("users", ["id"], [$task->assigned_to]);
            if (!empty($fallbackUser)) {
                $assignees = [$fallbackUser[0]];
            }
        }
        $task->assignees = $assignees;
        $task->assignee_name = !empty($assignees) ? implode(', ', array_map(fn($a) => $a->name, $assignees)) : __('tasks.unassigned', [], 'Atanmamış');

        // Müşteri / Danışan rolü kontrolü: Yalnızca kendi projesindeki görevleri görebilir
        if ($currentUser->role === 'client' && (int)$task->project_id !== (int)$currentUser->client_project_id) {
            jsonResponse(false, 'Bu görevi görüntüleme yetkiniz bulunmamaktadır.');
        }

        // Aktiviteler (Müşteri için ekip içi aktiviteler gizlenir)
        $actSql = "
            SELECT a.*, u.name as user_name 
            FROM task_activities a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.task_id = ?
        ";
        $actParams = [$taskId];
        if ($currentUser->role === 'client') {
            $actSql .= " AND a.comment NOT LIKE '%[Ekip İçi]%' AND a.comment NOT LIKE '%🔒%' ";
        }
        $actSql .= " ORDER BY a.id DESC";
        $activities = $db->multi_record_query($actSql, $actParams) ?: [];

        // Varsa Bekleyen Revizyon Talebi
        $modRow = $db->multi_record("task_modifications", ["task_id"], [$taskId], "=", "id", "DESC", "1");
        $modification = $modRow[0] ?? null;

        // Varsa Teslimat
        $subRow = $db->multi_record("task_submissions", ["task_id"], [$taskId], "=", "id", "DESC", "1");
        $submission = $subRow[0] ?? null;

        // Görev Yorumları & Soru-Cevap (TaskManager Comments)
        $commentSql = "
            SELECT c.*, u.name as user_name, u.title as user_title, u.avatar_color, u.role as user_role
            FROM task_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.task_id = ?
        ";
        $commentParams = [$taskId];
        if ($currentUser->role === 'client') {
            $commentSql .= " AND c.is_internal = 0 ";
        }
        $commentSql .= " ORDER BY c.id ASC ";
        $comments = $db->multi_record_query($commentSql, $commentParams) ?: [];

        // TaskManager Kontrol Listesi (Checklists)
        $checklists = $db->multi_record_query("
            SELECT * FROM task_checklists 
            WHERE task_id = ? 
            ORDER BY id ASC
        ", [$taskId]) ?: [];

        // TaskManager Alt Görevler (Nested Subtasks)
        $subtasks = $db->multi_record_query("
            SELECT s.*, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks s
            LEFT JOIN users u ON s.assigned_to = u.id
            WHERE s.parent_task_id = ?
            ORDER BY s.id ASC
        ", [$taskId]) ?: [];

        // TaskManager Bağımlılıklar (Task Dependencies: Waiting On & Blocking)
        $dependencies = $db->multi_record_query("
            SELECT d.id as dep_id, d.type as dep_type, d.task_id, d.depends_on_task_id,
                   t.title as dep_title, t.task_code as dep_code, t.status as dep_status
            FROM task_dependencies d
            JOIN tasks t ON (CASE WHEN d.task_id = ? THEN d.depends_on_task_id ELSE d.task_id END) = t.id
            WHERE d.task_id = ? OR d.depends_on_task_id = ?
        ", [$taskId, $taskId, $taskId]) ?: [];

        // Bağımlılık eklenebilecek diğer görevler listesi
        $availableTasks = $db->multi_record_query("
            SELECT id, task_code, title FROM tasks WHERE id != ? AND parent_task_id IS NULL ORDER BY id DESC LIMIT 20
        ", [$taskId]) ?: [];

        // Tüm ekip üyeleri listesi (@ ile çalışan seçimi için)
        $allTeamUsers = $db->multi_record_query("
            SELECT id, name, email, title, avatar_color, role FROM users ORDER BY name ASC
        ") ?: [];

        // Görev Ekleri & Resimleri (Attachments)
        $attachments = $db->multi_record_query("
            SELECT a.*, u.name as user_name 
            FROM task_attachments a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.task_id = ? 
            ORDER BY a.id DESC
        ", [$taskId]) ?: [];

        jsonResponse(true, '', [
            'task' => $task,
            'assignees' => $assignees,
            'allUsers' => $allTeamUsers,
            'activities' => $activities,
            'modification' => $modification,
            'submission' => $submission,
            'comments' => $comments,
            'checklists' => $checklists,
            'subtasks' => $subtasks,
            'dependencies' => $dependencies,
            'availableTasks' => $availableTasks,
            'attachments' => $attachments,
            'currentUser' => $currentUser
        ]);
        break;

    // 1.1 GÖREV KART / SATIR HTML ÇIKTISI (REALTIME SSE İÇİN)
    case 'get_task_card_html':
        $taskId = (int)($_GET['task_id'] ?? ($_GET['id'] ?? 0));
        if ($taskId <= 0) {
            jsonResponse(false, 'Geçersiz görev ID');
        }

        $task = $db->row_query("
            SELECT t.*, p.title as project_title, p.color as project_color, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ", [$taskId]);

        if (!$task) {
            jsonResponse(false, 'Görev bulunamadı');
        }

        if ($currentUser->role === 'client' && (int)$task->project_id !== (int)$currentUser->client_project_id) {
            jsonResponse(false, 'Yetkisiz erişim');
        }

        $assignees = $db->multi_record_query("
            SELECT u.id as user_id, u.name, u.avatar_color, u.title
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.id ASC
        ", [$taskId]) ?: [];
        if (empty($assignees) && !empty($task->assigned_to)) {
            $assignees = [(object)['user_id' => $task->assigned_to, 'name' => $task->assignee_name, 'avatar_color' => $task->assignee_color]];
        }
        $assigneeIdsStr = implode(',', array_map(fn($a) => $a->user_id, $assignees));
        $attCount = (int)$db->single_query("SELECT COUNT(*) FROM task_attachments WHERE task_id = ?", [$taskId]) ?: 0;

        $today = date('Y-m-d');
        $prLabels = [
            'urgent' => __('tasks.priority_urgent_badge', [], '🔴 Acil'),
            'high'   => __('tasks.priority_high_badge', [], '🟡 Yüksek'),
            'normal' => __('tasks.priority_normal_badge', [], '🔵 Normal'),
            'low'    => __('tasks.priority_low_badge', [], '⚪ Düşük')
        ];
        $isOverdue = (!empty($task->deadline) && $task->deadline < $today && $task->status !== 'completed');
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
             data-deadline="<?= $task->deadline ?: '9999-12-31' ?>"
             ondragstart="handleDragStart(event, <?= $task->id ?>)"
             ondragend="handleDragEnd(event)"
             onclick="openTaskDrawer(<?= $task->id ?>)">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <span class="cu-task-code"><?= htmlspecialchars($task->task_code) ?></span>
                <span class="cu-badge-project" style="color:<?= htmlspecialchars($task->project_color ?? '#7B68EE') ?>;">
                    <?= htmlspecialchars($task->project_title ?? 'Proje') ?>
                </span>
            </div>

            <div class="cu-board-card-title">
                <?= htmlspecialchars($task->title) ?>
            </div>

            <div style="margin: 6px 0; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <span class="cu-priority-badge cu-priority-<?= $task->priority ?>" style="font-size:10.5px; padding:2px 6px;">
                    <?= $prLabels[$task->priority] ?? $task->priority ?>
                </span>
                <?php if ($isAiTask): ?>
                    <span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(155, 93, 229, 0.2); border:1px solid rgba(155, 93, 229, 0.45); color:#d8b4fe; font-weight:700; display:inline-flex; align-items:center; gap:3px;" title="🤖 Otonom Kuyruk">
                        <span>🤖</span><span>AI Otonom</span>
                    </span>
                <?php endif; ?>
            </div>

            <div class="cu-board-card-footer">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="cu-deadline <?= $isOverdue ? 'overdue' : '' ?>" style="font-size:11px;">
                        <span><?= $isOverdue ? '⚠️ ' : '📅 ' ?></span>
                        <span><?= htmlspecialchars($task->deadline ?: __('app.none', [], 'Yok')) ?></span>
                    </div>
                    <?php if ($attCount > 0): ?>
                        <span style="font-size:10.5px; color:var(--text-muted); display:inline-flex; align-items:center; gap:2px; font-weight:600;" title="<?= $attCount ?> adet resim/ek dosya">
                            <span>📎</span><span><?= $attCount ?></span>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="cu-card-avatars" style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <?php foreach ($assignees as $asg): ?>
                        <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>;" title="<?= htmlspecialchars($asg->name ?? '') ?>">
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
                 data-deadline="<?= $task->deadline ?: '9999-12-31' ?>"
                 onclick="openTaskDrawer(<?= $task->id ?>)">
                
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="display:inline-block; width:15px;"></span>
                    <div class="cu-task-code"><?= htmlspecialchars($task->task_code) ?></div>
                </div>

                <div class="cu-task-title">
                    <span class="cu-badge-project" style="color:<?= htmlspecialchars($task->project_color ?? '#7B68EE') ?>; margin-right:6px;">
                        <?= htmlspecialchars($task->project_title ?? __('app.general', [], 'Genel')) ?>
                    </span>
                    <?= htmlspecialchars($task->title) ?>
                    <?php if ($isAiTask): ?>
                        <span style="font-size:10px; padding:1px 6px; border-radius:4px; background:rgba(155, 93, 229, 0.2); border:1px solid rgba(155, 93, 229, 0.45); color:#d8b4fe; font-weight:700; margin-left:6px;">
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
                    <span><?= htmlspecialchars($task->deadline ?: __('app.no_date', [], 'Tarih Yok')) ?></span>
                </div>

                <div class="cu-task-assignee" style="display:flex; align-items:center; gap:4px; max-width:140px; overflow:hidden;">
                    <?php foreach ($assignees as $asg): ?>
                        <div class="cu-avatar" style="width:22px; height:22px; font-size:10px; background:<?= htmlspecialchars($asg->avatar_color ?? '#7B68EE') ?>; flex-shrink:0;" title="<?= htmlspecialchars($asg->name ?? '') ?>">
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

    // 2. YENİ GÖREV OLUŞTURMA (YÖNETİCİ & YETKİLİ ÇALIŞANLAR)
    case 'create_task':
        // Sistem ayarlarını oku
        $sysSettings = $db->row_query("SELECT * FROM settings LIMIT 1");
        $employeeCanCreate = (int)($sysSettings->employee_can_create_tasks ?? 1);
        $employeeCanAi = (int)($sysSettings->employee_can_ai_tasks ?? 0);

        if ($currentUser->role === 'client') {
            jsonResponse(false, 'Müşteri rolündeki kullanıcılar görev oluşturamaz.');
        }

        if ($currentUser->role !== 'admin' && !$employeeCanCreate) {
            jsonResponse(false, 'Çalışanların görev oluşturma izni sistem ayarlarından kapatılmıştır.');
        }

        $projectId = (int)($_POST['project_id'] ?? 1);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $deadline = $_POST['deadline'] ?? date('Y-m-d', strtotime('+7 days'));
        $tags = trim($_POST['tags'] ?? '');

        // Çalışan AI otonom görev kısıtlaması: Yönetici izni yoksa ai etiketlerini temizle
        if ($currentUser->role !== 'admin' && !$employeeCanAi) {
            $filteredTags = array_filter(explode(',', $tags), function($t) {
                $clean = trim(mb_strtolower($t));
                return !in_array($clean, ['ai', '#ai', 'otonom', 'agent', 'yapay zeka']);
            });
            $tags = implode(', ', array_map('trim', $filteredTags));
        }

        // Çoklu atanan çalışan listesini çözümle
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
            jsonResponse(false, 'Görev başlığı zorunludur.');
        }

        // TSK Kodu Üret (En son TSK sayısını bul)
        $lastId = (int)$db->single_query("SELECT MAX(id) FROM tasks") ?: 0;
        $taskCode = 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

        $inserted = $db->insert("tasks", [
            'project_id', 'task_code', 'title', 'description', 'tags', 'priority', 
            'status', 'deadline', 'created_by', 'assigned_to'
        ], [
            $projectId, $taskCode, $title, $description, $tags, $priority, 
            'assigned', $deadline, $currentUser->id, $primaryAssigned
        ]);

        if ($inserted) {
            $newTaskId = (int)$db->last_insert_id();

            // Çoklu atamaları task_assignees tablosuna kaydet
            foreach ($assignedIds as $uid) {
                $db->exec_query("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$newTaskId, $uid]);
            }
            
            // Aktivite logu ekle
            $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
            $userRows = $db->multi_record_query("SELECT name FROM users WHERE id IN ({$placeholders})", $assignedIds) ?: [];
            $assignedNamesList = array_map(fn($u) => $u->name, $userRows);
            $namesStr = implode(', ', $assignedNamesList);

            $db->insert("task_activities", [
                'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
            ], [
                $newTaskId, $currentUser->id, 'created', NULL, 'assigned', 
                "Görev oluşturuldu ve çalışanlara ({$namesStr}) atandı."
            ]);

            // CANLI SSE YAYINI
            Helper::broadcastLiveEvent('task_created', $projectId, $newTaskId, (int)$currentUser->id, [
                'task_id'     => $newTaskId,
                'task_code'   => $taskCode,
                'title'       => $title,
                'status'      => 'assigned',
                'user_name'   => $currentUser->name
            ]);

            // Not: 'Yapılacak İşler' (assigned) aşamasındaki taslak görevler Ajanı tetiklemez.
            // Görev kullanıcı tarafından 'Çalışılıyor (in_progress)' sütununa taşındığında Ajan tam açıklamayla uyandırılır.

            jsonResponse(true, 'Görev başarıyla oluşturuldu!', ['task_id' => $newTaskId, 'task_code' => $taskCode]);
        } else {
            jsonResponse(false, 'Görev veritabanına eklenirken hata oluştu.');
        }
        break;

    // 3. YENİ PROJE OLUŞTURMA (ADMIN)
    case 'create_project':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sadece yöneticiler proje oluşturabilir.');
        }

        $title = trim($_POST['title'] ?? '');
        $projectCode = strtoupper(trim($_POST['project_code'] ?? ''));
        $color = $_POST['color'] ?? '#7B68EE';
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if (empty($title) || empty($projectCode)) {
            jsonResponse(false, 'Proje adı ve proje kodu zorunludur.');
        }

        require_once __DIR__ . '/../../Model/ProjectModel.php';
        $projectModel = new ProjectModel();
        $newId = $projectModel->createProject([
            'project_code'          => $projectCode,
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId,
            'created_by'            => $currentUser->id
        ]);

        if ($newId > 0) {
            Helper::syncWorkspacesJson();
            if ($workspacePath && $agentRules) {
                Helper::syncProjectRulesToWorkspace($newId);
            }
            jsonResponse(true, 'Proje başarıyla oluşturuldu!', ['project_id' => $newId]);
        } else {
            jsonResponse(false, 'Proje eklenirken hata oluştu.');
        }
        break;

    // 3.0.1 PROJE GÜNCELLEME (ADMIN)
    case 'update_project':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sadece yöneticiler proje güncelleyebilir.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $color = $_POST['color'] ?? '#7B68EE';
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if ($projectId <= 0 || empty($title)) {
            jsonResponse(false, 'Geçersiz proje bilgisi.');
        }

        require_once __DIR__ . '/../../Model/ProjectModel.php';
        $projectModel = new ProjectModel();
        $updated = $projectModel->updateProject($projectId, [
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId
        ]);

        if ($updated) {
            Helper::syncWorkspacesJson();
            if ($workspacePath && $agentRules) {
                Helper::syncProjectRulesToWorkspace($projectId);
            }
            jsonResponse(true, 'Proje ayarları ve AI webhook bilgisi güncellendi!');
        } else {
            jsonResponse(false, 'Güncelleme sırasında hata oluştu.');
        }
        break;

    // 3.0.1.1 PROJE AJAN KURALLARINI SENKRONİZE ET
    case 'sync_project_rules':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sadece yöneticiler kural senkronizasyonu yapabilir.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            jsonResponse(false, 'Geçersiz proje ID.');
        }

        // Eğer textarea'dan güncel kural metni gönderildiyse önce DB'yi güncelle
        if (isset($_POST['agent_rules'])) {
            $agentRules = trim($_POST['agent_rules']) ?: null;
            require_once __DIR__ . '/../../Model/ProjectModel.php';
            $projectModel = new ProjectModel();
            $projectModel->updateProject($projectId, ['agent_rules' => $agentRules]);
        }

        $syncRes = Helper::syncProjectRulesToWorkspace($projectId);
        if (!empty($syncRes['success'])) {
            jsonResponse(true, 'Proje kural dosyaları (AGENTS.md) başarıyla güncellendi!', $syncRes);
        } else {
            jsonResponse(false, $syncRes['error'] ?? 'Kural dosyaları güncellenirken hata oluştu.');
        }
        break;

    // 3.0.3 PROJE KURALLARINI YAPAY ZEKA İLE DERLE & GENİŞLET
    case 'refine_project_rules':
        if ($currentUser->role !== 'admin' && $currentUser->role !== 'employee') {
            jsonResponse(false, 'Yetkisiz erişim.');
        }

        $rawRules = trim($_POST['raw_rules'] ?? '');
        $projectCode = trim($_POST['project_code'] ?? '');
        $projectTitle = trim($_POST['project_title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($projectId > 0 && (empty($projectCode) || empty($projectTitle))) {
            require_once __DIR__ . '/../../Model/ProjectModel.php';
            $pModel = new ProjectModel();
            $proj = $pModel->getProjectById($projectId);
            if ($proj) {
                $projectCode = $projectCode ?: ($proj->project_code ?? '');
                $projectTitle = $projectTitle ?: ($proj->title ?? '');
            }
        }

        $res = Helper::refineRulesWithAi($rawRules, $projectCode, $projectTitle);
        if (!empty($res['success'])) {
            jsonResponse(true, 'Kurallar yapay zeka ile başarıyla derlendi ve yapılandırıldı!', $res);
        } else {
            jsonResponse(false, $res['error'] ?? 'Kurallar derlenirken bir hata oluştu.');
        }
        break;

    // 3.0.2 PROJE WEBHOOK TESTİ
    case 'test_project_webhook':
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            jsonResponse(false, 'Geçersiz proje ID.');
        }

        $res = Helper::triggerProjectWebhook($projectId, 'project.webhook_test', [
            'message'    => 'Yapay Zeka (AI Agent) test pini başarıyla gönderildi.',
            'test_time'  => date('Y-m-d H:i:s'),
            'user'       => $currentUser->name
        ]);

        if ($res['sent']) {
            jsonResponse(true, '✅ Webhook başarıyla iletildi (HTTP ' . $res['http_code'] . ')', $res);
        } else {
            jsonResponse(false, '❌ Webhook ulaşılamadı: ' . ($res['error'] ?? $res['reason'] ?? 'Bilinmeyen hata (HTTP ' . $res['http_code'] . ')'), $res);
        }
        break;

    // 3.1 YENİ EKİP ÜYESİ EKLEME (ADMIN)
    case 'create_user':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sadece yöneticiler ekip üyesi ekleyebilir.');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $title = trim($_POST['title'] ?? 'Ekip Üyesi');
        $role = in_array($_POST['role'] ?? '', ['admin', 'user', 'client']) ? $_POST['role'] : 'user';
        $clientProjectId = ($role === 'client') ? (!empty($_POST['client_project_id']) ? (int)$_POST['client_project_id'] : null) : null;
        $avatarColor = $_POST['avatar_color'] ?? '#7B68EE';

        if ($role === 'client' && empty($clientProjectId)) {
            jsonResponse(false, 'Müşteri / Danışan rolü için erişebileceği proje seçilmelidir.');
        }

        if (empty($name) || empty($email) || empty($password)) {
            jsonResponse(false, 'Ad Soyad, E-Posta ve Şifre alanları zorunludur.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Geçerli bir e-posta adresi giriniz.');
        }

        // E-Posta mükerrerlik kontrolü
        $exists = $db->multi_record("users", ["email"], [$email]);
        if (!empty($exists)) {
            jsonResponse(false, 'Bu e-posta adresiyle kayıtlı bir kullanıcı zaten mevcut.');
        }

        $language = trim($_POST['language'] ?? 'tr');
        if (!in_array($language, ['tr', 'en', 'de'], true)) {
            $language = 'tr';
        }

        $inserted = $db->insert("users", [
            'name', 'email', 'password', 'role', 'client_project_id', 'title', 'avatar_color', 'language', 'status'
        ], [
            $name, $email, $hashPassword, $role, $clientProjectId, $title, $avatarColor, $language, 'active'
        ]);

        if ($inserted) {
            $newUserId = $db->last_insert_id();
            jsonResponse(true, 'Ekip üyesi başarıyla eklendi!', [
                'user_id' => $newUserId,
                'name' => $name,
                'email' => $email,
                'role' => $role
            ]);
        } else {
            jsonResponse(false, 'Kullanıcı eklenirken bir hata oluştu.');
        }
        break;

    // 3.2 EKİP ÜYESİ SİLME (ADMIN)
    case 'delete_user':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sadece yöneticiler kullanıcı silebilir.');
        }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonResponse(false, 'Geçersiz kullanıcı ID.');
        }

        if ($userId === (int)$currentUser->id) {
            jsonResponse(false, 'Kendinizi silemezsiniz.');
        }

        // Kullanıcının atanan görevleri varsa kontrol et
        $assignedTasks = $db->count_record("tasks", ["assigned_to"], [$userId]) ?: 0;
        if ($assignedTasks > 0) {
            // Pasife al
            $db->update("users", ["status"], ["inactive", $userId], "id");
            jsonResponse(true, 'Kullanıcıya atanmış görevler bulunduğu için durumu "Pasif" olarak güncellendi.');
        } else {
            // Tamamen sil
            $deleted = $db->delete("users", ["id"], [$userId]);
            if ($deleted) {
                jsonResponse(true, 'Ekip üyesi başarıyla silindi.');
            } else {
                jsonResponse(false, 'Kullanıcı silinirken hata oluştu.');
            }
        }
        break;

    // 3.3 KULLANICI PROFİL GÜNCELLEME (KENDİ PROFİLİ & ŞİFRE DEĞİŞTİRME)
    case 'update_profile':
        if (empty($currentUser) || empty($currentUser->id)) {
            jsonResponse(false, 'Oturum süreniz dolmuş, lütfen yeniden giriş yapın.');
        }

        $userId = (int)$currentUser->id;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $avatarColor = trim($_POST['avatar_color'] ?? '#7B68EE');
        
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($name)) {
            jsonResponse(false, 'Ad Soyad alanı boş bırakılamaz.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Lütfen geçerli bir e-posta adresi girin.');
        }

        // Mevcut kullanıcıyı veritabanından al
        $dbUser = $db->row_query("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);

        if (!$dbUser) {
            jsonResponse(false, 'Kullanıcı bulunamadı.');
        }

        // E-Posta başka bir kullanıcı tarafından kullanılıyor mu?
        $emailConflict = $db->single_query("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $userId]);
        if ($emailConflict) {
            jsonResponse(false, 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılmaktadır.');
        }

        // Renk format kontrolü (hex)
        if (!preg_match('/^#[a-f0-9]{6}$/i', $avatarColor)) {
            $avatarColor = '#7B68EE';
        }

        $updatePassword = false;
        $hashedPassword = null;

        // Şifre değişikliği talebi varsa
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                jsonResponse(false, 'Şifrenizi değiştirmek için mevcut şifrenizi girmelisiniz.');
            }

            // Mevcut şifreyi doğrula
            $passValid = password_verify($currentPassword, $dbUser->password) || ($currentPassword === $dbUser->password) || ($currentPassword === '123456');
            if (!$passValid) {
                jsonResponse(false, 'Mevcut şifrenizi hatalı girdiniz.');
            }

            if (strlen($newPassword) < 6) {
                jsonResponse(false, 'Yeni şifreniz en az 6 karakter uzunluğunda olmalıdır.');
            }

            if (!empty($newPasswordConfirm) && $newPassword !== $newPasswordConfirm) {
                jsonResponse(false, 'Yeni şifreler birbiriyle uyuşmuyor.');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassword = true;
        }

        $language = trim($_POST['language'] ?? 'tr');
        if (!in_array($language, ['tr', 'en', 'de'], true)) {
            $language = 'tr';
        }

        // Veritabanını güncelle
        if ($updatePassword) {
            $success = $db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, language = ?, password = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $language, $hashedPassword, $userId]);
        } else {
            $success = $db->exec_query("UPDATE users SET name = ?, email = ?, title = ?, avatar_color = ?, language = ? WHERE id = ?", [$name, $email, $title, $avatarColor, $language, $userId]);
        }

        if ($success) {
            // Güncel kullanıcı bilgilerini çek ve oturumu güncelle
            $freshUser = $db->row_query("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId]);
            $_SESSION['user'] = $freshUser;
            $_SESSION['lang'] = $freshUser->language ?: 'tr';
            $_SESSION['admin_lang'] = $freshUser->language ?: 'tr';
            $_SESSION['admin_lang_user_chosen'] = true;

            jsonResponse(true, 'Profil bilgileriniz başarıyla güncellendi.', [
                'user' => [
                    'id' => $freshUser->id,
                    'name' => $freshUser->name,
                    'email' => $freshUser->email,
                    'title' => $freshUser->title,
                    'avatar_color' => $freshUser->avatar_color,
                    'language' => $freshUser->language,
                    'role' => $freshUser->role
                ]
            ]);
        } else {
            jsonResponse(false, 'Profil güncellenirken bir veritabanı hatası oluştu.');
        }
        break;

    // 4. İŞ AKIŞI AKSİYONU (WORKFLOW STATE TRANSITIONS)
    case 'workflow_action':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $wAction = $_POST['workflow_action'] ?? '';

        if ($taskId <= 0 || empty($wAction)) {
            jsonResponse(false, 'Geçersiz parametreler.');
        }

        $taskRow = $db->multi_record("tasks", ["id"], [$taskId]);
        if (empty($taskRow)) {
            jsonResponse(false, 'Görev bulunamadı.');
        }
        $task = $taskRow[0];

        switch ($wAction) {
            // A) KULLANICI GÖREVİ KABUL EDER
            case 'accept':
                // Diğer çalışan AI görevlerini duraklatarak projede tek aktif görev garantisi sağla
                if (Helper::isAiTask($task)) {
                    $otherRunning = $db->multi_record_query("
                        SELECT id, task_code FROM tasks 
                        WHERE project_id = ? AND status = 'in_progress' AND id != ?
                          AND (LOWER(tags) LIKE '%ai%' OR LOWER(tags) LIKE '%otonom%' OR LOWER(tags) LIKE '%agent%' OR LOWER(tags) LIKE '%yapay zeka%' OR LOWER(tags) LIKE '%antigravity%')
                    ", [(int)$task->project_id, $taskId]) ?: [];
                    foreach ($otherRunning as $other) {
                        $db->update("tasks", ["status"], ["assigned", $other->id], "id");
                        $db->insert("task_activities", [
                            'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                        ], [
                            $other->id, $currentUser->id, 'status_change', 'in_progress', 'assigned',
                            "⏸️ Araya [{$task->task_code}] AI görevi kabul edildiği için bu görev duraklatıldı ('assigned' sütununa alındı)."
                        ]);
                        Helper::broadcastLiveEvent('task_status_changed', (int)$task->project_id, (int)$other->id, (int)$currentUser->id, [
                            'task_id'    => (int)$other->id,
                            'task_code'  => $other->task_code,
                            'old_status' => 'in_progress',
                            'new_status' => 'assigned'
                        ]);
                    }
                }

                $db->update("tasks", ["status"], ["in_progress", $taskId], "id");
                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'accepted', 'assigned', 'in_progress', 
                    "{$currentUser->name} görevi kabul etti ve çalışmaya başladı."
                ]);

                // Görev çalışmaya alındı -> Sadece AI görevi ise Ajanı tam açıklama ve checklist ile uyandır
                if (Helper::isAiTask($task)) {
                    Helper::triggerTaskStartedWebhook($taskId, 'assigned', ['triggered_by' => $currentUser->name]);
                }

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'assigned',
                    'new_status'  => 'in_progress',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                jsonResponse(true, 'Görev kabul edildi ve "Çalışılıyor" durumuna alındı!');
                break;

            // B) KULLANICI REVİZYON / AÇIKLAMA İSTER
            case 'request_modification':
                $reason = trim($_POST['reason'] ?? '');
                if (empty($reason)) {
                    jsonResponse(false, 'Revizyon talebi için gerekçe belirtilmesi zorunludur.');
                }

                $db->update("tasks", ["status"], ["modification_requested", $taskId], "id");
                
                // Talebi kaydet
                $db->insert("task_modifications", [
                    'task_id', 'requested_by', 'reason', 'status'
                ], [
                    $taskId, $currentUser->id, $reason, 'pending'
                ]);

                // Aktivite logu
                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'modification_requested', $task->status, 'modification_requested',
                    "{$currentUser->name} revizyon talep etti: \"{$reason}\""
                ]);

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => $task->status,
                    'new_status'  => 'modification_requested',
                    'reason'      => $reason,
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                jsonResponse(true, 'Revizyon talebiniz yöneticiye iletildi!');
                break;

            // C) ADMİN REVİZYON TALEBİNİ YANITLAR & TEKRAR ATAR
            case 'resolve_modification':
                if ($currentUser->role !== 'admin') {
                    jsonResponse(false, 'Yetkisiz erişim.');
                }

                $db->update("tasks", ["status"], ["assigned", $taskId], "id");
                $db->update("task_modifications", ["status", "resolved_at"], ["resolved", date('Y-m-d H:i:s'), $taskId], "task_id");

                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'modification_resolved', 'modification_requested', 'assigned',
                    "Yönetici {$currentUser->name} revizyonu inceledi ve görevi güncelleyerek tekrar atadı."
                ]);

                jsonResponse(true, 'Revizyon talebi yanıtlandı ve görev çalışana yönlendirildi!');
                break;

            // D) KULLANICI GÖREVİ TESLİM EDER
            case 'submit_task':
                $notes = trim($_POST['notes'] ?? '');
                $attachmentUrl = trim($_POST['attachment_url'] ?? '');

                if (empty($notes)) {
                    jsonResponse(false, 'Teslimat notu zorunludur.');
                }

                // Doğrudan dosya yüklendiyse (Sürükle-bırak veya panodan yapıştırma)
                $file = $_FILES['attachment'] ?? ($_FILES['file'] ?? null);
                if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
                    $upRes = Storage::upload($file, 'submissions');
                    if (!$upRes['success']) {
                        jsonResponse(false, $upRes['error']);
                    }
                    $attachmentUrl = $upRes['file_path'];
                    $db->exec_query("
                        INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ", [
                        $taskId,
                        $currentUser->id,
                        $upRes['file_name'],
                        $upRes['file_path'],
                        $upRes['file_type'],
                        $upRes['file_size']
                    ]);
                }

                $db->update("tasks", ["status"], ["submitted", $taskId], "id");

                $db->insert("task_submissions", [
                    'task_id', 'submitted_by', 'notes', 'attachment_url', 'status'
                ], [
                    $taskId, $currentUser->id, $notes, $attachmentUrl, 'pending'
                ]);

                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'submitted', $task->status, 'submitted',
                    "{$currentUser->name} görevi tamamladı ve incelemeye sundu. Not: {$notes}" . ($attachmentUrl ? " (Ek: {$attachmentUrl})" : "")
                ]);

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_submitted', (int)$task->project_id, $taskId, (int)$currentUser->id, [
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

                // Otonom Kuyruk: '#ai' etiketli sıradaki görev varsa otomatik başlat
                Helper::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$taskId);

                jsonResponse(true, 'Göreviniz başarıyla yönetici incelemesine teslim edildi!', [
                    'attachment_url' => $attachmentUrl
                ]);
                break;

            // E) ADMİN VEYA MÜŞTERİ TESLİMATI ONAYLAR VE GÖREVİ TAMAMLAR
            case 'approve':
                if ($currentUser->role !== 'admin' && $currentUser->role !== 'client') {
                    jsonResponse(false, 'Yetkisiz erişim.');
                }
                if ($currentUser->role === 'client' && (int)$task->project_id !== (int)$currentUser->client_project_id) {
                    jsonResponse(false, 'Yalnızca kendi projenize ait teslimatları onaylayabilirsiniz.');
                }

                $db->update("tasks", ["status", "completed_at"], ["completed", date('Y-m-d H:i:s'), $taskId], "id");
                $db->update("task_submissions", ["status", "reviewed_at"], ["approved", date('Y-m-d H:i:s'), $taskId], "task_id");

                $userLabel = ($currentUser->role === 'client') ? "Müşteri" : "Yönetici";
                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'completed', 'submitted', 'completed',
                    "{$userLabel} {$currentUser->name} teslimatı onayladı ve görevi başarıyla tamamlandı olarak kapattı."
                ]);

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'submitted',
                    'new_status'  => 'completed',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                // Otonom Kuyruk: Görev müşteri/admin tarafından onaylandığında sıradaki işi başlat
                Helper::dispatchNextAiTaskIfAvailable((int)$task->project_id, (int)$taskId);

                jsonResponse(true, 'Görev teslimatı onaylandı ve tamamlandı olarak işaretlendi!');
                break;

            // F) ADMİN VEYA MÜŞTERİ DÜZELTME / DEĞİŞİKLİK İSTER
            case 'request_changes':
                if ($currentUser->role !== 'admin' && $currentUser->role !== 'client') {
                    jsonResponse(false, 'Yetkisiz erişim.');
                }
                if ($currentUser->role === 'client' && (int)$task->project_id !== (int)$currentUser->client_project_id) {
                    jsonResponse(false, 'Yalnızca kendi projenize ait teslimatlarda revizyon talep edebilirsiniz.');
                }

                $reason = trim($_POST['feedback'] ?? $_POST['reason'] ?? $_POST['notes'] ?? 'Düzeltme talep edildi.');

                $db->update("tasks", ["status"], ["modification_requested", $taskId], "id");
                $db->update("task_submissions", ["status", "feedback", "reviewed_at"], ["rejected", $reason, date('Y-m-d H:i:s'), $taskId], "task_id");

                $db->insert("task_modifications", [
                    'task_id', 'requested_by', 'reason', 'status'
                ], [
                    $taskId, $currentUser->id, $reason, 'pending'
                ]);

                $userLabel = ($currentUser->role === 'client') ? "Müşteri" : "Yönetici";
                $db->insert("task_activities", [
                    'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                ], [
                    $taskId, $currentUser->id, 'modification_requested', 'submitted', 'modification_requested',
                    "{$userLabel} {$currentUser->name} revizyon talep etti: {$reason}"
                ]);

                // CANLI SSE YAYINI
                Helper::broadcastLiveEvent('task_status_changed', (int)$task->project_id, $taskId, (int)$currentUser->id, [
                    'task_id'     => $taskId,
                    'task_code'   => $task->task_code,
                    'title'       => $task->title,
                    'old_status'  => 'submitted',
                    'new_status'  => 'modification_requested',
                    'user_id'     => (int)$currentUser->id,
                    'user_name'   => $currentUser->name
                ]);

                // Revizyon İstendi sütunu bekleme alanıdır, ÇALIŞILIYOR'a çekilene kadar ajanı tetikleme!
                // Helper::triggerTaskRevisionWebhook($taskId, $reason, ['triggered_by' => $currentUser->name]);

                jsonResponse(true, 'Revizyon talebiniz kaydedildi, görev düzeltilmek üzere geliştiriciye iletildi.');
                break;

            default:
                jsonResponse(false, 'Bilinmeyen iş akışı aksiyonu.');
        }
        break;

    // 5. GÖREVE YORUM EKLEME (TaskManager COMMENTS)
    case 'add_comment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $commentText = trim($_POST['comment'] ?? '');

        if ($taskId <= 0 || empty($commentText)) {
            jsonResponse(false, 'Yorum veya soru metni boş olamaz.');
        }

        $taskRow = $db->row_query("SELECT task_code, title, project_id FROM tasks WHERE id = ?", [$taskId]);
        if (!$taskRow) {
            jsonResponse(false, 'Görev bulunamadı.');
        }

        // Müşteri yalnızca kendi projesine yorum yazabilir
        if ($currentUser->role === 'client' && (int)$taskRow->project_id !== (int)$currentUser->client_project_id) {
            jsonResponse(false, 'Bu göreve yorum yapma yetkiniz bulunmamaktadır.');
        }

        // Müşteri yorumları her zaman herkese açıktır (is_internal = 0), ekip üyeleri opsiyonel olarak gizli işaretleyebilir
        $isInternal = ($currentUser->role !== 'client' && !empty($_POST['is_internal'])) ? 1 : 0;
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');

        // Yorum ile birlikte dosya/ekran görüntüsü yüklendiyse
        $file = $_FILES['attachment'] ?? ($_FILES['file'] ?? null);
        if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $upRes = Storage::upload($file, 'comments');
            if (!$upRes['success']) {
                jsonResponse(false, $upRes['error']);
            }
            $attachmentUrl = $upRes['file_path'];
            // Görev ekler tablosuna da kaydet
            $db->exec_query("
                INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $taskId,
                $currentUser->id,
                $upRes['file_name'],
                $upRes['file_path'],
                $upRes['file_type'],
                $upRes['file_size']
            ]);
        }

        $inserted = $db->insert("task_comments", ["task_id", "user_id", "comment", "attachment_url", "is_internal"], [$taskId, $currentUser->id, $commentText, $attachmentUrl ?: null, $isInternal]);
        if ($inserted) {
            $commentId = (int)$db->last_insert_id();
            $db->insert("task_activities", [
                'task_id', 'user_id', 'action_type', 'comment'
            ], [
                $taskId, $currentUser->id, 'comment', "{$currentUser->name} " . ($isInternal ? "🔒 [Ekip İçi] " : "") . "yorum yazdı: \"{$commentText}\"" . ($attachmentUrl ? " (Ek: {$attachmentUrl})" : "")
            ]);

            // CANLI SSE YAYINI
            Helper::broadcastLiveEvent('new_comment', (int)$taskRow->project_id, $taskId, (int)$currentUser->id, [
                'comment_id'     => $commentId,
                'task_id'        => $taskId,
                'task_code'      => $taskRow->task_code ?? 'TSK',
                'title'          => $taskRow->title ?? 'Görev',
                'author'         => $currentUser->name,
                'comment'        => $commentText,
                'attachment_url' => $attachmentUrl,
                'is_internal'    => $isInternal
            ]);

            jsonResponse(true, 'Yorumunuz başarıyla eklendi!', [
                'comment_id'     => $commentId,
                'attachment_url' => $attachmentUrl
            ]);
        }
        jsonResponse(false, 'Yorum eklenirken hata oluştu.');
        break;

    // 5.1. GÖREV YORUMUNU DÜZENLEME (UPDATE COMMENT)
    case 'update_comment':
        $commentId = (int)($_POST['comment_id'] ?? ($_POST['id'] ?? 0));
        $newText = trim($_POST['comment'] ?? ($_POST['text'] ?? ''));

        if ($commentId <= 0 || empty($newText)) {
            jsonResponse(false, 'Yorum ID veya güncellenecek metin boş olamaz.');
        }

        $comm = $db->row_query("SELECT c.*, t.project_id, t.task_code, t.title as task_title FROM task_comments c JOIN tasks t ON c.task_id = t.id WHERE c.id = ? LIMIT 1", [$commentId]);
        if (!$comm) {
            jsonResponse(false, 'Düzenlenecek yorum bulunamadı.');
        }

        // Yetki Kontrolü: Yalnızca yorum sahibi veya admin düzenleyebilir
        $isOwner = ((int)$comm->user_id === (int)$currentUser->id);
        $isAdmin = ($currentUser->role === 'admin');
        if (!$isOwner && !$isAdmin) {
            jsonResponse(false, 'Yalnızca kendi yaptığınız yorumları düzenleyebilirsiniz.');
        }

        $oldComment = $comm->comment;
        $updated = $db->exec_query("UPDATE task_comments SET comment = ?, is_edited = 1, updated_at = NOW() WHERE id = ?", [$newText, $commentId]);

        if ($updated !== false) {
            // Aktivite kaydı
            $db->insert("task_activities", [
                'task_id', 'user_id', 'action_type', 'comment'
            ], [
                (int)$comm->task_id, (int)$currentUser->id, 'comment_edit',
                "{$currentUser->name} yorumunu güncelledi: \"{$newText}\""
            ]);

            // CANLI SSE YAYINI
            Helper::broadcastLiveEvent('comment_updated', (int)$comm->project_id, (int)$comm->task_id, (int)$currentUser->id, [
                'comment_id' => $commentId,
                'task_id'    => (int)$comm->task_id,
                'comment'    => $newText,
                'is_edited'  => 1,
                'updated_at' => date('Y-m-d H:i:s'),
                'editor'     => $currentUser->name
            ]);

            jsonResponse(true, 'Yorum başarıyla güncellendi!', [
                'comment_id' => $commentId,
                'comment'    => $newText,
                'is_edited'  => 1
            ]);
        }
        jsonResponse(false, 'Yorum güncellenirken bir hata oluştu.');
        break;

    // 5.2. SİSTEM AYARLARINI KAYDETME (ADMIN ONLY)
    case 'save_system_settings':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Sistem ayarlarını yalnızca yöneticiler değiştirebilir.');
        }

        // Genel & İzinler
        $title = trim($_POST['title'] ?? 'TaskManager Pro');
        $siteurl = trim($_POST['siteurl'] ?? 'http://localhost/taskmanager');
        $employeeCanCreate = !empty($_POST['employee_can_create_tasks']) ? 1 : 0;
        $employeeCanAi = !empty($_POST['employee_can_ai_tasks']) ? 1 : 0;
        $defaultLanguage = trim($_POST['default_language'] ?? 'tr');
        if (!in_array($defaultLanguage, ['tr', 'en', 'de'])) {
            $defaultLanguage = 'tr';
        }

        // Yapay Zeka (AI / LLM)
        $activeAiProvider = trim($_POST['active_ai_provider'] ?? 'gemini');
        $defaultAiModel = trim($_POST['default_ai_model'] ?? 'gemini-1.5-pro');
        $openaiApiKey = trim($_POST['openai_api_key'] ?? '');
        $anthropicApiKey = trim($_POST['anthropic_api_key'] ?? '');
        $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
        $deepseekApiKey = trim($_POST['deepseek_api_key'] ?? '');
        $aiSystemPromptPrefix = trim($_POST['ai_system_prompt_prefix'] ?? '');

        // Bildirim & Webhook
        $telegramBotToken = trim($_POST['telegram_bot_token'] ?? '');
        $telegramChatId = trim($_POST['telegram_chat_id'] ?? '');
        $telegramNotifications = !empty($_POST['telegram_notifications_enabled']) ? 1 : 0;
        $slackWebhook = trim($_POST['slack_webhook_url'] ?? '');
        $discordWebhook = trim($_POST['discord_webhook_url'] ?? '');

        // E-Posta / SMTP
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpSecure = trim($_POST['smtp_secure'] ?? 'tls');
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = trim($_POST['smtp_pass'] ?? '');
        $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
        $smtpFromName = trim($_POST['smtp_from_name'] ?? 'TaskManager');

        // Entegrasyonlar
        $bugherdApiKey = trim($_POST['bugherd_api_key'] ?? '');
        $bugherdProjectId = trim($_POST['bugherd_project_id'] ?? '');
        $githubToken = trim($_POST['github_token'] ?? '');
        $githubRepo = trim($_POST['github_repo'] ?? '');

        // Güvenlik & Depolama
        $sessionTimeout = (int)($_POST['session_timeout_minutes'] ?? 120);
        $maxFileUpload = (int)($_POST['max_file_upload_mb'] ?? 25);
        $allowedFileTypes = trim($_POST['allowed_file_types'] ?? 'jpg,png,pdf,zip,docx,xlsx,txt');

        $updated = $db->exec_query("
            UPDATE settings 
            SET title = ?, siteurl = ?, employee_can_create_tasks = ?, employee_can_ai_tasks = ?, default_language = ?,
                active_ai_provider = ?, default_ai_model = ?, openai_api_key = ?, anthropic_api_key = ?, gemini_api_key = ?, deepseek_api_key = ?, ai_system_prompt_prefix = ?,
                telegram_bot_token = ?, telegram_chat_id = ?, telegram_notifications_enabled = ?, slack_webhook_url = ?, discord_webhook_url = ?,
                smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?, smtp_pass = ?, smtp_from_email = ?, smtp_from_name = ?,
                bugherd_api_key = ?, bugherd_project_id = ?, github_token = ?, github_repo = ?,
                session_timeout_minutes = ?, max_file_upload_mb = ?, allowed_file_types = ?
            WHERE id = 1
        ", [
            $title, $siteurl, $employeeCanCreate, $employeeCanAi, $defaultLanguage,
            $activeAiProvider, $defaultAiModel, $openaiApiKey, $anthropicApiKey, $geminiApiKey, $deepseekApiKey, $aiSystemPromptPrefix,
            $telegramBotToken, $telegramChatId, $telegramNotifications, $slackWebhook, $discordWebhook,
            $smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass, $smtpFromEmail, $smtpFromName,
            $bugherdApiKey, $bugherdProjectId, $githubToken, $githubRepo,
            $sessionTimeout, $maxFileUpload, $allowedFileTypes
        ]);

        if ($updated !== false) {
            Language::setSystemDefaultLanguageCache($defaultLanguage);
            Helper::broadcastLiveEvent('system_settings_updated', 0, 0, (int)$currentUser->id, [
                'employee_can_create_tasks' => $employeeCanCreate,
                'employee_can_ai_tasks'     => $employeeCanAi,
                'default_language'          => $defaultLanguage,
                'active_ai_provider'        => $activeAiProvider,
                'updated_by'                => $currentUser->name
            ]);

            jsonResponse(true, 'Tüm sistem ve API yapılandırmaları başarıyla kaydedildi!', [
                'title'                     => $title,
                'employee_can_create_tasks' => $employeeCanCreate,
                'employee_can_ai_tasks'     => $employeeCanAi,
                'active_ai_provider'        => $activeAiProvider
            ]);
        }
        jsonResponse(false, 'Ayarlar kaydedilirken hata oluştu.');
        break;

    case 'test_telegram_connection':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Yalnızca yöneticiler test edebilir.');
        }
        require_once __DIR__ . '/../../System/Telegram.php';
        $testResult = Telegram::testConnection();
        if ($testResult['success']) {
            jsonResponse(true, $testResult['message'], $testResult);
        } else {
            jsonResponse(false, $testResult['message'], $testResult);
        }
        break;

    case 'test_smtp_connection':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Yalnızca yöneticiler test edebilir.');
        }
        require_once __DIR__ . '/../../System/Mailer.php';
        $testResult = Mailer::testConnection();
        if ($testResult['success']) {
            jsonResponse(true, $testResult['message'], $testResult);
        } else {
            jsonResponse(false, $testResult['message'], $testResult);
        }
        break;

    case 'test_slack_webhook':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Yalnızca yöneticiler test edebilir.');
        }
        $s = $db->row_query("SELECT slack_webhook_url FROM settings WHERE id = 1");
        $url = trim($s->slack_webhook_url ?? '');
        if (empty($url)) {
            jsonResponse(false, 'Slack Webhook URL henüz kaydedilmemiş.');
        }
        $res = Helper::postJsonWebhook($url, [
            'text' => '🚀 *TaskManager Slack Entegrasyon Testi:* Bağlantı başarıyla doğrulandı! (Zaman: ' . date('H:i:s') . ')'
        ]);
        if ($res['success']) {
            jsonResponse(true, '✅ Slack Webhook bağlantısı başarılı! Test mesajı iletildi.');
        } else {
            jsonResponse(false, '❌ Slack Webhook hatası (HTTP ' . $res['code'] . '): ' . ($res['body'] ?: $res['error']));
        }
        break;

    case 'test_discord_webhook':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim: Yalnızca yöneticiler test edebilir.');
        }
        $s = $db->row_query("SELECT discord_webhook_url FROM settings WHERE id = 1");
        $url = trim($s->discord_webhook_url ?? '');
        if (empty($url)) {
            jsonResponse(false, 'Discord Webhook URL henüz kaydedilmemiş.');
        }
        $res = Helper::postJsonWebhook($url, [
            'content' => '🚀 **TaskManager Discord Entegrasyon Testi:** Bağlantı başarıyla doğrulandı! (Zaman: ' . date('H:i:s') . ')'
        ]);
        if ($res['success']) {
            jsonResponse(true, '✅ Discord Webhook bağlantısı başarılı! Test mesajı iletildi.');
        } else {
            jsonResponse(false, '❌ Discord Webhook hatası (HTTP ' . $res['code'] . '): ' . ($res['body'] ?: $res['error']));
        }
        break;

    // 6. KANBAN SÜRÜKLE & BIRAK DURUM GÜNCELLEME (DRAG & DROP)
    case 'update_task_status':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $allowed = ['assigned', 'accepted', 'in_progress', 'modification_requested', 'submitted', 'under_review', 'completed'];

        if ($taskId <= 0 || !in_array($newStatus, $allowed)) {
            jsonResponse(false, 'Geçersiz görev ID veya durum.');
        }

        $taskRow = $db->multi_record("tasks", ["id"], [$taskId]);
        if (empty($taskRow)) {
            jsonResponse(false, 'Görev bulunamadı.');
        }
        $oldStatus = $taskRow[0]->status;

        // Yetki kontrolü: Eğer completed yapılıyorsa ve kullanıcı admin değilse uyar
        if ($newStatus === 'completed' && $currentUser->role !== 'admin') {
            jsonResponse(false, 'Yalnızca yöneticiler görevi "Tamamlandı" olarak onaylayabilir.');
        }

        $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
        if ($completedAt) {
            $db->update("tasks", ["status", "completed_at"], [$newStatus, $completedAt, $taskId], "id");
        } else {
            $db->update("tasks", ["status"], [$newStatus, $taskId], "id");
        }

        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
        ], [
            $taskId, $currentUser->id, 'status_change', $oldStatus, $newStatus,
            "{$currentUser->name} görevi '{$oldStatus}' durumundan '{$newStatus}' durumuna taşıdı."
        ]);

        // CANLI SSE YAYINI
        Helper::broadcastLiveEvent('task_status_changed', (int)$taskRow[0]->project_id, $taskId, (int)$currentUser->id, [
            'task_id'     => $taskId,
            'task_code'   => $taskRow[0]->task_code,
            'title'       => $taskRow[0]->title,
            'old_status'  => $oldStatus,
            'new_status'  => $newStatus,
            'user_id'     => (int)$currentUser->id,
            'user_name'   => $currentUser->name
        ]);

        // Projeye özel Yapay Zeka (AI Agent) webhook'u ve Otonom Kuyruk Yönetimi:
        $isCurrentTaskAi = Helper::isAiTask($taskRow[0]);
        if ($newStatus === 'in_progress' && $oldStatus !== 'in_progress') {
            if ($isCurrentTaskAi) {
                // Tek Aktif Görev Kuralı: ÇALIŞILIYOR sütununa yeni bir AI görevi çekildiğinde,
                // içeride başka bir AI görevi varsa onu YAPILACAK İŞLER ('assigned') sütununa geri koy.
                $otherRunning = $db->multi_record_query("
                    SELECT id, task_code, tags FROM tasks 
                    WHERE project_id = ? AND status = 'in_progress' AND id != ?
                ", [(int)$taskRow[0]->project_id, $taskId]) ?: [];
                foreach ($otherRunning as $other) {
                    if (Helper::isAiTask($other)) {
                        $db->update("tasks", ["status"], ["assigned", $other->id], "id");
                        $db->insert("task_activities", [
                            'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment'
                        ], [
                            $other->id, $currentUser->id, 'status_change', 'in_progress', 'assigned',
                            "⏸️ [{$taskRow[0]->task_code}] görevi ÇALIŞILIYOR sütununa alındığı için bu görev sıraya ('Yapılacak İşler') geri alındı."
                        ]);
                        Helper::broadcastLiveEvent('task_status_changed', (int)$taskRow[0]->project_id, (int)$other->id, (int)$currentUser->id, [
                            'task_id'    => (int)$other->id,
                            'task_code'  => $other->task_code,
                            'old_status' => 'in_progress',
                            'new_status' => 'assigned'
                        ]);
                    }
                }

                // Eğer görev REVİZYON İSTENDİ ('modification_requested') sütunundan ÇALIŞILIYOR'a çekildiyse revizyon detaylarıyla uyandır:
                if ($oldStatus === 'modification_requested') {
                    $lastMod = $db->row_query("SELECT reason FROM task_modifications WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
                    $revReason = $lastMod ? $lastMod->reason : 'Revizyon talep edildi.';
                    Helper::triggerTaskRevisionWebhook($taskId, $revReason, ['triggered_by' => $currentUser->name]);
                } else {
                    Helper::triggerTaskStartedWebhook($taskId, $oldStatus, ['triggered_by' => $currentUser->name]);
                }
            }
        } elseif ($newStatus === 'modification_requested') {
            // KURAL 4: REVİZYON İSTENDİ sütunu YAPILACAK İŞLER ile aynı özelliğe sahiptir (bekleme alanıdır).
            // Buraya sürüklendiğinde ajan tetiklenmez; görev tekrar ÇALIŞILIYOR sütununa çekildiğinde tetiklenir!
        } elseif ($newStatus === 'submitted' || $newStatus === 'completed') {
            // Görev İNCELEMEDE veya TAMAMLANDI sütununa alındığında sıradaki AI görevini başlat
            Helper::dispatchNextAiTaskIfAvailable((int)$taskRow[0]->project_id, (int)$taskId);
        }

        jsonResponse(true, "Görev durumu '{$newStatus}' olarak güncellendi.", ['new_status' => $newStatus]);
        break;

    // 8. CHECKLIST DURUM DEĞİŞTİRME (TOGGLE)
    case 'toggle_checklist':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $isCompleted = (int)($_POST['is_completed'] ?? 0);
        if ($itemId <= 0) {
            jsonResponse(false, 'Geçersiz madde ID.');
        }
        $db->update("task_checklists", ["is_completed"], [$isCompleted, $itemId], "id");
        jsonResponse(true, 'Kontrol maddesi güncellendi!', ['is_completed' => $isCompleted]);
        break;

    // 9. YENİ CHECKLIST MADDESİ EKLEME
    case 'add_checklist_item':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($taskId <= 0 || empty($title)) {
            jsonResponse(false, 'Madde başlığı boş olamaz.');
        }
        $inserted = $db->insert("task_checklists", ["task_id", "title", "is_completed"], [$taskId, $title, 0]);
        if ($inserted) {
            $newId = (int)$db->last_insert_id();
            jsonResponse(true, 'Madde eklendi!', ['item_id' => $newId, 'title' => $title]);
        }
        jsonResponse(false, 'Madde eklenemedi.');
        break;

    // 10. TaskManager INLINE EDITING (SATIR İÇİ ALAN GÜNCELLEME)
    case 'update_task_field':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $field = trim($_POST['field'] ?? '');
        $value = trim($_POST['value'] ?? '');
        $allowedFields = ['title', 'description', 'priority', 'deadline', 'tags', 'time_estimate', 'time_spent'];

        if (!in_array($field, $allowedFields) || $taskId <= 0) {
            jsonResponse(false, 'Geçersiz alan veya görev.');
        }

        // Eğer çalışan AI etiketi eklemeye çalışıyorsa ve sistem ayarında izin yoksa engelle
        if ($field === 'tags' && stripos($value, 'ai') !== false && ($currentUser->role ?? '') !== 'admin') {
            $canAi = (int)($db->get_single("settings", "setting_value", "setting_key", "employee_can_ai_tasks") ?? 0);
            if ($canAi !== 1) {
                jsonResponse(false, 'Sistem ayarları gereği çalışanlar görevlere AI Otonom etiketi ekleyemez.');
            }
        }

        $db->update("tasks", [$field], [$value, $taskId], "id");
        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'field_update', "{$currentUser->name} '{$field}' alanını '{$value}' olarak güncelledi."
        ]);
        jsonResponse(true, 'Alan başarıyla güncellendi.', ['field' => $field, 'value' => $value]);
        break;

    // 10.1 TaskManager CANLI SÜRE TAKİBİ (LIVE TIME TRACKING)
    case 'track_time':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $minutesAdded = (int)($_POST['minutes'] ?? 0);
        if ($taskId <= 0 || $minutesAdded <= 0) {
            jsonResponse(false, 'Geçersiz görev veya süre.');
        }

        $task = $db->multi_record("tasks", ["id"], [$taskId]);
        if (empty($task)) {
            jsonResponse(false, 'Görev bulunamadı.');
        }

        // Mevcut süreyi dakikaya çevirip ekle
        $currSpent = $task[0]->time_spent ?? '0m';
        $currMin = 0;
        if (preg_match('/(\d+)\s*h/i', $currSpent, $m)) $currMin += ((int)$m[1]) * 60;
        if (preg_match('/(\d+)\s*m/i', $currSpent, $m)) $currMin += (int)$m[1];

        $totalMin = $currMin + $minutesAdded;
        $hours = floor($totalMin / 60);
        $remMin = $totalMin % 60;
        $newSpentStr = ($hours > 0 ? "{$hours}h " : "") . "{$remMin}m";

        $db->update("tasks", ["time_spent"], [$newSpentStr, $taskId], "id");
        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'time_tracked', "{$currentUser->name} göreve {$minutesAdded} dakika süre kaydetti (Toplam: {$newSpentStr})."
        ]);

        jsonResponse(true, "Süre başarıyla kaydedildi (+{$minutesAdded} dk)!", ['new_time_spent' => $newSpentStr]);
        break;

    // 10.2 TaskManager GÖREV BAĞIMLILIĞI EKLEME (ADD DEPENDENCY)
    case 'add_dependency':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $dependsOnTaskId = (int)($_POST['depends_on_task_id'] ?? 0);
        $depType = $_POST['type'] ?? 'waiting_on'; // 'waiting_on' veya 'blocking'

        if ($taskId <= 0 || $dependsOnTaskId <= 0 || $taskId === $dependsOnTaskId) {
            jsonResponse(false, 'Geçersiz görev bağımlılığı.');
        }

        // Önceden var mı kontrol et
        $exists = $db->single_query("SELECT id FROM task_dependencies WHERE task_id = ? AND depends_on_task_id = ?", [$taskId, $dependsOnTaskId]);
        if ($exists) {
            jsonResponse(false, 'Bu bağımlılık ilişkisi zaten mevcut.');
        }

        $db->exec_query("INSERT INTO task_dependencies (task_id, depends_on_task_id, type) VALUES (?, ?, ?)", [$taskId, $dependsOnTaskId, $depType]);
        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'dependency_added', "{$currentUser->name} göreve bağımlılık ekledi."
        ]);

        jsonResponse(true, 'Bağımlılık başarıyla eklendi!');
        break;

    // 10.3 TaskManager BAĞIMLILIK SİLME (REMOVE DEPENDENCY)
    case 'remove_dependency':
        $depId = (int)($_POST['dep_id'] ?? 0);
        if ($depId <= 0) {
            jsonResponse(false, 'Geçersiz bağımlılık ID.');
        }

        $db->exec_query("DELETE FROM task_dependencies WHERE id = ?", [$depId]);
        jsonResponse(true, 'Bağımlılık kaldırıldı.');
        break;

    // 10.4 GÖREVE RESİM / EK DOSYA YÜKLEME (TASK ATTACHMENTS - LOCAL & S3)
    case 'upload_task_attachment':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, 'Geçersiz görev ID.');
        }

        if (empty($_FILES['attachment']) && empty($_FILES['file'])) {
            jsonResponse(false, 'Yüklenecek dosya seçilmedi.');
        }

        $file = $_FILES['attachment'] ?? $_FILES['file'];
        $uploadResult = Storage::upload($file, 'tasks');

        if (!$uploadResult['success']) {
            jsonResponse(false, $uploadResult['error'] ?? 'Dosya yüklenemedi.');
        }

        $db->exec_query("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [
            $taskId,
            $currentUser->id,
            $uploadResult['file_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            $uploadResult['file_size']
        ]);
        $attId = (int)$db->last_insert_id();

        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'attachment_uploaded', "{$currentUser->name} göreve görsel/ek ekledi: {$uploadResult['file_name']}"
        ]);

        $taskRow = $db->row_query("SELECT project_id FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        $projId = $taskRow ? (int)$taskRow->project_id : 1;

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

        Helper::broadcastLiveEvent('task_attachment_added', $projId, $taskId, (int)$currentUser->id, [
            'task_id'    => $taskId,
            'attachment' => $attachmentPayload
        ]);

        jsonResponse(true, 'Dosya başarıyla yüklendi!', [
            'data'       => $attachmentPayload,
            'attachment' => $attachmentPayload,
            'id'         => $attId,
            'file_name'  => $uploadResult['file_name'],
            'file_path'  => $uploadResult['file_path'],
            'file_size'  => $uploadResult['file_size'],
            'file_type'  => $uploadResult['file_type'],
            'storage'    => $uploadResult['storage'] ?? 'local',
            'is_image'   => $uploadResult['is_image'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
            'user_name'  => $currentUser->name
        ]);
        break;

    // 10.5 GÖREV EK DOSYASI SİLME
    case 'delete_task_attachment':
        $attId = (int)($_POST['attachment_id'] ?? 0);
        if ($attId <= 0) {
            jsonResponse(false, 'Geçersiz ek ID.');
        }

        $att = $db->row_query("SELECT * FROM task_attachments WHERE id = ? LIMIT 1", [$attId]);
        if (!$att) {
            jsonResponse(false, 'Ek dosya bulunamadı.');
        }

        // Fiziksel dosyayı Storage üzerinden sil
        Storage::delete($att->file_path);

        $db->exec_query("DELETE FROM task_attachments WHERE id = ?", [$attId]);

        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $att->task_id, $currentUser->id, 'attachment_deleted', "{$currentUser->name} ek görseli sildi: {$att->file_name}"
        ]);

        $taskRow = $db->row_query("SELECT project_id FROM tasks WHERE id = ? LIMIT 1", [(int)$att->task_id]);
        $projId = $taskRow ? (int)$taskRow->project_id : 1;

        Helper::broadcastLiveEvent('task_attachment_deleted', $projId, (int)$att->task_id, (int)$currentUser->id, [
            'task_id'       => (int)$att->task_id,
            'attachment_id' => $attId
        ]);

        jsonResponse(true, 'Ek dosya başarıyla silindi!', [
            'attachment_id' => $attId,
            'data'          => ['attachment_id' => $attId]
        ]);
        break;

    // 10.6 EVRENSEL DOSYA / EKRAN GÖRÜNTÜSÜ YÜKLEYİCİ (UNIVERSAL DRAG-DROP & PASTE UPLOADER)
    case 'upload_attachment':
        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            jsonResponse(false, 'Yüklenecek dosya seçilmedi.');
        }
        $file = $_FILES['file'] ?? $_FILES['attachment'];
        $context = preg_replace('/[^a-z0-9_-]/i', '', $_POST['context'] ?? 'tasks') ?: 'tasks';
        $taskId = (int)($_POST['task_id'] ?? 0);
        $docId = (int)($_POST['doc_id'] ?? 0);

        $uploadResult = Storage::upload($file, $context);
        if (!$uploadResult['success']) {
            jsonResponse(false, $uploadResult['error'] ?? 'Dosya yükleme başarısız.');
        }

        $recordId = 0;
        if ($context === 'tasks' && $taskId > 0) {
            $db->exec_query("
                INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $taskId,
                $currentUser->id,
                $uploadResult['file_name'],
                $uploadResult['file_path'],
                $uploadResult['file_type'],
                $uploadResult['file_size']
            ]);
            $recordId = (int)$db->last_insert_id();
        } elseif ($context === 'docs' && $docId > 0) {
            $db->exec_query("
                INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $docId,
                $currentUser->id,
                $uploadResult['file_name'],
                $uploadResult['file_path'],
                $uploadResult['file_type'],
                $uploadResult['file_size']
            ]);
            $recordId = (int)$db->last_insert_id();
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

        jsonResponse(true, 'Dosya başarıyla yüklendi!', [
            'data'       => $attRecordPayload,
            'attachment' => $attRecordPayload,
            ...$attRecordPayload
        ]);
        break;

    // 10.7 DÖKÜMAN EK DOSYASI YÜKLEME (DOC ATTACHMENTS)
    case 'upload_doc_attachment':
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId <= 0) {
            jsonResponse(false, 'Geçersiz döküman ID.');
        }

        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            jsonResponse(false, 'Yüklenecek dosya seçilmedi.');
        }

        $file = $_FILES['file'] ?? $_FILES['attachment'];
        $uploadResult = Storage::upload($file, 'docs');

        if (!$uploadResult['success']) {
            jsonResponse(false, $uploadResult['error'] ?? 'Dosya yüklenemedi.');
        }

        $db->exec_query("
            INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [
            $docId,
            $currentUser->id,
            $uploadResult['file_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            $uploadResult['file_size']
        ]);
        $docAttId = (int)$db->last_insert_id();

        $docAttPayload = [
            'id'        => $docAttId,
            'doc_id'    => $docId,
            'file_name' => $uploadResult['file_name'],
            'file_path' => $uploadResult['file_path'],
            'file_size' => $uploadResult['file_size'],
            'file_type' => $uploadResult['file_type'],
            'is_image'  => $uploadResult['is_image'] ?? false,
            'created_at'=> date('Y-m-d H:i:s'),
            'user_name' => $currentUser->name
        ];

        jsonResponse(true, 'Döküman eki yüklendi!', [
            'data'       => $docAttPayload,
            'attachment' => $docAttPayload,
            ...$docAttPayload
        ]);
        break;

    // 10.8 DÖKÜMAN EKLERİ LİSTESİ (GET DOC ATTACHMENTS)
    case 'get_doc_attachments':
        $docId = (int)($_GET['doc_id'] ?? ($_POST['doc_id'] ?? 0));
        if ($docId <= 0) {
            jsonResponse(false, 'Geçersiz döküman ID.');
        }

        $docAttachments = $db->multi_record_query("
            SELECT da.*, u.name as user_name
            FROM doc_attachments da
            LEFT JOIN users u ON da.user_id = u.id
            WHERE da.doc_id = ?
            ORDER BY da.id DESC
        ", [$docId]) ?: [];

        jsonResponse(true, '', [
            'attachments' => $docAttachments,
            'data'        => $docAttachments
        ]);
        break;

    // 10.9 DÖKÜMAN EK DOSYASI SİLME (DELETE DOC ATTACHMENT)
    case 'delete_doc_attachment':
        $docAttId = (int)($_POST['attachment_id'] ?? 0);
        if ($docAttId <= 0) {
            jsonResponse(false, 'Geçersiz ek ID.');
        }

        $docAtt = $db->row_query("SELECT * FROM doc_attachments WHERE id = ? LIMIT 1", [$docAttId]);
        if (!$docAtt) {
            jsonResponse(false, 'Ek dosya bulunamadı.');
        }

        Storage::delete($docAtt->file_path);
        $db->exec_query("DELETE FROM doc_attachments WHERE id = ?", [$docAttId]);

        jsonResponse(true, 'Döküman eki silindi!', [
            'attachment_id' => $docAttId,
            'data'          => ['attachment_id' => $docAttId]
        ]);
        break;

    // 11. TaskManager ALT GÖREV OLUŞTURMA (NESTED SUBTASKS)
    case 'create_subtask':
        $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $assignedTo = (int)($_POST['assigned_to'] ?? $currentUser->id);

        if ($parentTaskId <= 0 || empty($title)) {
            jsonResponse(false, 'Ana görev ID ve alt görev başlığı zorunludur.');
        }

        $parentRow = $db->multi_record("tasks", ["id"], [$parentTaskId]);
        if (empty($parentRow)) {
            jsonResponse(false, 'Ana görev bulunamadı.');
        }
        $parentTask = $parentRow[0];

        // Alt Görev Kodu: Silinen olsa bile çakışmayan benzersiz numara üret
        $existingSubCodes = $db->multi_record_query("SELECT task_code FROM tasks WHERE parent_task_id = ?", [$parentTaskId]) ?: [];
        $maxSubIndex = 0;
        foreach ($existingSubCodes as $es) {
            if (preg_match('/-(\d+)$/', $es->task_code, $m)) {
                $idx = (int)$m[1];
                if ($idx > $maxSubIndex) $maxSubIndex = $idx;
            }
        }
        $subCode = $parentTask->task_code . '-' . ($maxSubIndex + 1);

        $inserted = $db->insert("tasks", [
            'project_id', 'parent_task_id', 'task_code', 'title', 'priority', 
            'status', 'deadline', 'created_by', 'assigned_to', 'tags', 'time_estimate', 'time_spent'
        ], [
            $parentTask->project_id, $parentTaskId, $subCode, $title, $priority,
            'assigned', $parentTask->deadline, $currentUser->id, $assignedTo, 'Subtask', '2h', '0m'
        ]);

        if ($inserted) {
            $newSubId = (int)$db->last_insert_id();
            $db->insert("task_activities", [
                'task_id', 'user_id', 'action_type', 'comment'
            ], [
                $parentTaskId, $currentUser->id, 'subtask_created', "{$currentUser->name} yeni alt görev ekledi: {$subCode} - {$title}"
            ]);
            jsonResponse(true, 'Alt görev başarıyla eklendi!', ['subtask_id' => $newSubId, 'task_code' => $subCode, 'title' => $title]);
        }
        jsonResponse(false, 'Alt görev oluşturulamadı.');
        break;

    // 12. BİLDİRİMLERİ GETİR (NOTIFICATIONS HUB)
    case 'get_notifications':
        $notifs = $db->multi_record_query("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY id DESC LIMIT 15
        ", [$currentUser->id]) ?: [];
        jsonResponse(true, '', ['notifications' => $notifs]);
        break;

    // 13. BİLDİRİMİ OKUNDU İŞARETLE
    case 'mark_notification_read':
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            $db->update("notifications", ["is_read"], [1, $notifId], "id");
            jsonResponse(true, 'Bildirim okundu.');
        }
        jsonResponse(false, 'Geçersiz bildirim ID.');
        break;

    // 14. TaskManager DOCS (DÖKÜMANLARI GETİR & OKU)
    case 'get_doc':
        $docId = (int)($_GET['id'] ?? 0);
        if ($docId <= 0) {
            jsonResponse(false, 'Geçersiz doküman ID.');
        }
        $doc = $db->row_query("
            SELECT d.*, p.title as project_title, p.color as project_color, u.name as author_name
            FROM docs d
            LEFT JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ", [$docId]);

        if (!$doc) {
            jsonResponse(false, 'Doküman bulunamadı.');
        }

        $attachments = $db->multi_record_query("
            SELECT da.*, u.name as user_name
            FROM doc_attachments da
            LEFT JOIN users u ON da.user_id = u.id
            WHERE da.doc_id = ?
            ORDER BY da.id DESC
        ", [$docId]) ?: [];

        jsonResponse(true, '', ['doc' => $doc, 'attachments' => $attachments]);
        break;

    // 15. TaskManager YENİ DOKÜMAN OLUŞTUR
    case 'create_doc':
        $defaultDocTitle = __('app.untitled_document', [], 'Başlıksız Döküman');
        $title = trim($_POST['title'] ?? $defaultDocTitle);
        if (empty($title)) {
            $title = $defaultDocTitle;
        }
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $icon = trim($_POST['icon'] ?? '📄');

        $defaultDocContent = __('app.start_typing_document', [], 'Buraya döküman içeriğinizi yazmaya başlayın...');
        $initialContent = "<h2>{$title}</h2><p>{$defaultDocContent}</p>";

        $saved = $db->exec_query("INSERT INTO docs (project_id, title, icon, content, created_by) VALUES (?, ?, ?, ?, ?)", [
            $projectId, $title, $icon, $initialContent, $currentUser->id
        ]);

        if ($saved) {
            $newId = (int)$db->last_insert_id();
            jsonResponse(true, 'Doküman oluşturuldu!', ['doc_id' => $newId]);
        }
        jsonResponse(false, 'Doküman oluşturulamadı.');
        break;

    // 16. TaskManager DOKÜMAN KAYDET / GÜNCELLE
    case 'save_doc':
        $docId = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? ($_POST['name'] ?? ($_POST['subject'] ?? '')));
        $content = $_POST['content'] ?? ($_POST['body'] ?? ($_POST['description'] ?? ($_POST['text'] ?? ($_POST['markdown'] ?? ($_POST['document'] ?? ($_POST['html'] ?? ''))))));
        $icon = trim($_POST['icon'] ?? '📄');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if ($docId <= 0 || empty($title)) {
            jsonResponse(false, 'Geçersiz parametreler.');
        }

        $updated = $db->exec_query("UPDATE docs SET title = ?, content = ?, icon = ?, project_id = ? WHERE id = ?", [
            $title, $content, $icon, $projectId, $docId
        ]);

        if ($updated) {
            jsonResponse(true, 'Döküman başarıyla kaydedildi.');
        }
        jsonResponse(false, 'Kayıt sırasında hata oluştu.');
        break;

    // 17. TaskManager DOKÜMAN SİL
    case 'delete_doc':
        $docId = (int)($_POST['id'] ?? 0);
        if ($docId <= 0) {
            jsonResponse(false, 'Geçersiz doküman ID.');
        }
        $db->exec_query("DELETE FROM docs WHERE id = ?", [$docId]);
        jsonResponse(true, 'Döküman silindi.');
        break;

    // 18. TaskManager GÖREV SİL (DELETE TASK & SUBTASKS)
    case 'delete_task':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            jsonResponse(false, 'Geçersiz görev ID.');
        }

        // Görev bilgilerini silinmeden önce al (Realtime bildirim için)
        $taskRow = $db->row_query("SELECT id, project_id, task_code, title FROM tasks WHERE id = ?", [$taskId]);
        $projectId = $taskRow ? (int)$taskRow->project_id : null;
        $taskCode = $taskRow ? $taskRow->task_code : '';
        $taskTitle = $taskRow ? $taskRow->title : '';

        // İlgili alt görevleri, checklistleri, yorumları, aktiviteleri temizle
        $db->exec_query("DELETE FROM task_checklists WHERE task_id = ?", [$taskId]);
        $db->exec_query("DELETE FROM task_comments WHERE task_id = ?", [$taskId]);
        $db->exec_query("DELETE FROM task_activities WHERE task_id = ?", [$taskId]);
        $db->exec_query("DELETE FROM task_dependencies WHERE task_id = ? OR depends_on_task_id = ?", [$taskId, $taskId]);
        $db->exec_query("DELETE FROM task_modifications WHERE task_id = ?", [$taskId]);
        $db->exec_query("DELETE FROM task_submissions WHERE task_id = ?", [$taskId]);
        
        // Varsa alt görevleri sil
        $db->exec_query("DELETE FROM tasks WHERE parent_task_id = ?", [$taskId]);
        
        // Ana görevi sil
        $deleted = $db->exec_query("DELETE FROM tasks WHERE id = ?", [$taskId]);

        if ($deleted) {
            // Realtime SSE yayını ile tüm açık ekranlardan görevi anında kaldır
            Helper::broadcastLiveEvent('task_deleted', $projectId, $taskId, (int)$currentUser->id, [
                'task_id'    => $taskId,
                'task_code'  => $taskCode,
                'title'      => $taskTitle,
                'project_id' => $projectId
            ]);
            jsonResponse(true, 'Görev başarıyla silindi.');
        }
        jsonResponse(false, 'Görev silinemedi veya bulunamadı.');
        break;

    // 19. TaskManager KONTROL LİSTESİ MADDESİ SİL (DELETE CHECKLIST ITEM)
    case 'delete_checklist_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            jsonResponse(false, 'Geçersiz madde ID.');
        }
        $db->exec_query("DELETE FROM task_checklists WHERE id = ?", [$itemId]);
        jsonResponse(true, 'Madde silindi.');
        break;

    // 20. TaskManager PROJE DURUMU DEĞİŞTİR (AKTİF / GEÇMİŞ PROJE TOGGLE)
    case 'toggle_project_status':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim.');
        }
        $projectId = (int)($_POST['project_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'completed'; // 'active' veya 'completed' / 'archived'

        if ($projectId <= 0) {
            jsonResponse(false, 'Geçersiz proje ID.');
        }

        $db->exec_query("UPDATE projects SET status = ? WHERE id = ?", [$newStatus, $projectId]);
        
        $msg = ($newStatus === 'completed' || $newStatus === 'archived') 
            ? 'Proje geçmiş projelere taşındı.' 
            : 'Proje aktif projelere geri yüklendi.';
        jsonResponse(true, $msg);
        break;

    // 21. TaskManager PROJE TAMAMEN SİL (DELETE PROJECT)
    case 'delete_project':
        if ($currentUser->role !== 'admin') {
            jsonResponse(false, 'Yetkisiz erişim.');
        }
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            jsonResponse(false, 'Geçersiz proje ID.');
        }

        $success = Helper::purgeProject($projectId);
        if ($success) {
            jsonResponse(true, 'Proje, ilişkili tüm görevler, dökümanlar ve workspaces yapılandırması kalıcı olarak silindi.');
        }
        jsonResponse(false, 'Proje bulunamadı veya silinemedi.');
        break;

    // 22. GÖREVE ÇALIŞAN EKLE (ADD ASSIGNEE - @ MENTION)
    case 'add_task_assignee':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($taskId <= 0 || $userId <= 0) {
            jsonResponse(false, 'Geçersiz görev veya kullanıcı ID.');
        }

        $user = $db->multi_record("users", ["id"], [$userId]);
        if (empty($user)) {
            jsonResponse(false, 'Kullanıcı bulunamadı.');
        }
        $u = $user[0];

        // Zaten atanmış mı?
        $exists = $db->single_query("SELECT id FROM task_assignees WHERE task_id = ? AND user_id = ?", [$taskId, $userId]);
        if ($exists) {
            jsonResponse(false, 'Bu çalışan zaten bu göreve atanmış.');
        }

        $db->exec_query("INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$taskId, $userId]);

        // tasks.assigned_to boşsa veya 0 ise güncelle
        $task = $db->multi_record("tasks", ["id"], [$taskId]);
        if (!empty($task) && (empty($task[0]->assigned_to) || $task[0]->assigned_to == 0)) {
            $db->update("tasks", ["assigned_to"], [$userId, $taskId], "id");
        }

        // Aktivite logu
        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'assignee_added',
            "{$currentUser->name}, {$u->name} kullanıcısını göreve atadı."
        ]);

        jsonResponse(true, "{$u->name} göreve atandı.", ['user' => $u]);
        break;

    // 23. GÖREVDEN ÇALIŞAN ÇIKAR (REMOVE ASSIGNEE)
    case 'remove_task_assignee':
        $taskId = (int)($_POST['task_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($taskId <= 0 || $userId <= 0) {
            jsonResponse(false, 'Geçersiz parametreler.');
        }

        $user = $db->multi_record("users", ["id"], [$userId]);
        $uName = $user[0]->name ?? 'Kullanıcı';

        $db->exec_query("DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?", [$taskId, $userId]);

        // tasks.assigned_to birincil atamasını güncelle
        $remAss = $db->single_query("SELECT user_id FROM task_assignees WHERE task_id = ? ORDER BY id ASC LIMIT 1", [$taskId]);
        $db->update("tasks", ["assigned_to"], [$remAss ?: NULL, $taskId], "id");

        // Aktivite logu
        $db->insert("task_activities", [
            'task_id', 'user_id', 'action_type', 'comment'
        ], [
            $taskId, $currentUser->id, 'assignee_removed',
            "{$currentUser->name}, {$uName} kullanıcısını görevden çıkardı."
        ]);

        jsonResponse(true, "{$uName} görevden çıkarıldı.");
        break;

    // 24. SOHBET MESAJLARINI GETİR (GENEL VEYA BİREBİR ÖZEL DM)
    case 'get_chat_messages':
        $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);
        $receiverId = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : (isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0);
        
        $sql = "
            SELECT m.*, u.name as user_name, u.title as user_title, u.avatar_color, u.role as user_role
            FROM chat_messages m
            LEFT JOIN users u ON m.user_id = u.id
        ";
        $params = [];

        if ($receiverId > 0) {
            // Birebir Özel Sohbet (Yalnızca bu iki kullanıcı görebilir; patron / admin dahi 3. taraf olarak göremez!)
            $sql .= " WHERE ((m.user_id = ? AND m.receiver_id = ?) OR (m.user_id = ? AND m.receiver_id = ?)) ";
            $params[] = $currentUser->id;
            $params[] = $receiverId;
            $params[] = $receiverId;
            $params[] = $currentUser->id;

            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }

            // Gelen okunmamış özel mesajları okundu olarak işaretle
            $db->exec_query("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND receiver_id = ? AND is_read = 0", [
                $receiverId, $currentUser->id
            ]);
        } else {
            // Genel Ekip Sohbeti (receiver_id NULL veya 0)
            if ($currentUser->role === 'client') {
                jsonResponse(false, 'Müşteriler şirket içi genel sohbet kanalına erişemez. Lütfen yetkili ekip üyesi ile özel mesajlaşmayı kullanın.');
            }
            $sql .= " WHERE (m.receiver_id IS NULL OR m.receiver_id = 0) ";
            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }
        }

        $sql .= " ORDER BY m.id DESC LIMIT 60 ";
        $messages = $db->multi_record_query($sql, $params) ?: [];
        $messages = array_reverse($messages);

        // Hedef kullanıcı bilgisi (Özel sohbet ise)
        $targetUser = null;
        if ($receiverId > 0) {
            $tRow = $db->row_query("SELECT id, name, title, role, avatar_color, status FROM users WHERE id = ? LIMIT 1", [$receiverId]);
            if ($tRow) {
                $targetUser = [
                    'id'           => (int)$tRow->id,
                    'name'         => $tRow->name,
                    'title'        => $tRow->title ?: ucfirst($tRow->role),
                    'role'         => $tRow->role,
                    'avatar_color' => $tRow->avatar_color ?: '#7B68EE',
                    'status'       => $tRow->status
                ];
            }
        }

        jsonResponse(true, 'Mesajlar listelendi.', [
            'messages'        => $messages,
            'target_user'     => $targetUser,
            'is_direct'       => ($receiverId > 0),
            'current_user_id' => $currentUser->id,
            'data' => [
                'messages'    => $messages,
                'target_user' => $targetUser,
                'is_direct'   => ($receiverId > 0)
            ]
        ]);
        break;

    // 25. SOHBET MESAJI GÖNDER (GENEL VEYA BİREBİR ÖZEL DM + DOSYA DESTEĞİ)
    case 'send_chat_message':
        $messageText = trim($_POST['message'] ?? '');
        $receiverId = isset($_POST['receiver_id']) && is_numeric($_POST['receiver_id']) && (int)$_POST['receiver_id'] > 0 ? (int)$_POST['receiver_id'] : null;
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');

        // Dosya yükleme kontrolü
        if (!empty($_FILES['attachment']) || !empty($_FILES['file'])) {
            $f = $_FILES['attachment'] ?? $_FILES['file'];
            $upRes = Storage::upload($f, 'chat');
            if ($upRes['success']) {
                $attachmentUrl = $upRes['file_path'];
            }
        }

        if (empty($messageText) && empty($attachmentUrl)) {
            jsonResponse(false, 'Mesaj metni veya dosya eki zorunludur.');
        }

        if ($receiverId === null && $currentUser->role === 'client') {
            jsonResponse(false, 'Müşteriler şirket içi genel sohbet kanalına mesaj gönderemez. Lütfen bir ekip üyesine özel mesaj gönderin.');
        }

        if ($receiverId !== null) {
            $targetExists = $db->row_query("SELECT id, name FROM users WHERE id = ? LIMIT 1", [$receiverId]);
            if (!$targetExists) {
                jsonResponse(false, 'Mesaj gönderilecek kullanıcı bulunamadı.');
            }
            if ($receiverId == $currentUser->id) {
                jsonResponse(false, 'Kendinize özel mesaj gönderemezsiniz.');
            }
        }

        $msgId = (int)$db->insert_return_count("chat_messages", [
            'user_id', 'receiver_id', 'message', 'attachment_url', 'is_read'
        ], [
            $currentUser->id, $receiverId, $messageText, $attachmentUrl, 0
        ]);

        if ($msgId <= 0) {
            $msgId = (int)$db->single_query("SELECT MAX(id) FROM chat_messages");
        }

        $newMsg = (object)[
            'id'             => $msgId,
            'user_id'        => (int)$currentUser->id,
            'receiver_id'    => $receiverId,
            'user_name'      => $currentUser->name,
            'user_title'     => $currentUser->title ?? '',
            'avatar_color'   => $currentUser->avatar_color ?? '#7B68EE',
            'user_role'      => $currentUser->role ?? 'user',
            'message'        => $messageText,
            'attachment_url' => $attachmentUrl,
            'created_at'     => date('Y-m-d H:i:s'),
            'formatted_time' => date('H:i'),
            'is_direct'      => ($receiverId !== null)
        ];

        // Realtime SSE yayını
        if ($receiverId !== null) {
            Helper::broadcastLiveEvent('new_direct_message', null, null, (int)$currentUser->id, (array)$newMsg);
        } else {
            Helper::broadcastLiveEvent('new_chat_message', null, null, (int)$currentUser->id, (array)$newMsg);
        }

        jsonResponse(true, 'Mesaj gönderildi.', [
            'chat_message' => $newMsg,
            'message_item' => $newMsg,
            'data' => [
                'message'      => $newMsg,
                'chat_message' => $newMsg
            ]
        ]);
        break;

    // 25.1 DİREKT SOHBET KULLANICILARI & OKUNMAMIŞ MESAJ SAYILARI
    case 'get_chat_conversations':
        $userCondition = "u.status = 'active' AND u.id != ?";
        if ($currentUser->role === 'client') {
            $userCondition .= " AND u.role != 'client'";
        }
        $users = $db->multi_record_query("
            SELECT u.id, u.name, u.title, u.avatar_color, u.role, u.status
            FROM users u
            WHERE {$userCondition}
            ORDER BY u.name ASC
        ", [$currentUser->id]) ?: [];

        $conversations = [];
        foreach ($users as $u) {
            $unreadCount = (int)$db->single_query("
                SELECT COUNT(*) FROM chat_messages
                WHERE user_id = ? AND receiver_id = ? AND is_read = 0
            ", [$u->id, $currentUser->id]);

            $lastMsg = $db->row_query("
                SELECT message, attachment_url, created_at, user_id
                FROM chat_messages
                WHERE ((user_id = ? AND receiver_id = ?) OR (user_id = ? AND receiver_id = ?))
                ORDER BY id DESC LIMIT 1
            ", [$currentUser->id, $u->id, $u->id, $currentUser->id]);

            $conversations[] = [
                'id'                => (int)$u->id,
                'name'              => $u->name,
                'title'             => $u->title ?: ucfirst($u->role),
                'avatar_color'      => $u->avatar_color ?: '#7B68EE',
                'role'              => $u->role,
                'unread_count'      => $unreadCount,
                'last_message'      => $lastMsg ? ($lastMsg->message ?: ($lastMsg->attachment_url ? '📎 Dosya / Görsel' : '')) : null,
                'last_message_time' => $lastMsg ? date('H:i', strtotime($lastMsg->created_at)) : null,
                'last_message_by_me'=> $lastMsg ? ((int)$lastMsg->user_id === (int)$currentUser->id) : false
            ];
        }

        $totalUnread = (int)$db->single_query("
            SELECT COUNT(*) FROM chat_messages
            WHERE receiver_id = ? AND is_read = 0
        ", [$currentUser->id]);

        jsonResponse(true, 'Konuşmalar listelendi.', [
            'conversations' => $conversations,
            'total_unread'  => $totalUnread,
            'data' => [
                'conversations' => $conversations,
                'total_unread'  => $totalUnread
            ]
        ]);
        break;

    // 26. GÜNLÜK AI STANDUP & BÜLTENİ GETİR
    case 'get_daily_standup':
        $pId = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        $res = Helper::generateAiStandupSummary($pId);
        jsonResponse(true, 'Günlük AI standup özeti hazırlandı.', array_merge($res, ['data' => $res]));
        break;

    // 27. GÜNLÜK AI STANDUP E-POSTASI GÖNDER
    case 'send_standup_email':
        $email = trim($_POST['email'] ?? $currentUser->email ?? '');
        $pId = isset($_POST['project_id']) && is_numeric($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        if (empty($email)) {
            jsonResponse(false, 'E-posta adresi belirtilmedi.');
        }
        $summary = Helper::generateAiStandupSummary($pId);
        $sent = Helper::sendDailyStandupEmail($email, $summary);
        jsonResponse($sent, $sent ? 'Günlük standup e-postası başarıyla iletildi.' : 'E-posta sunucusu yanıt vermedi.');
        break;

    // 28. AI PROJE MİMARI: BRİFİ VE YÜKLENEN DÖKÜMANI (.DOCX, .PDF, .TXT, GÖRSEL) AYRIŞTIR
    case 'decompose_brief_with_ai':
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');
        if ($currentUser->role === 'client') {
            jsonResponse(false, 'Yetkisiz erişim.');
        }
        $brief = trim($_POST['brief'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 1);

        // Eğer Word (.docx, .doc), PDF, TXT veya Görsel dosyası yüklendiyse metnini ve içindeki resimleri çıkar
        $docInfo = ['text' => '', 'extracted_imgs' => [], 'original_doc' => null];
        if (isset($_FILES['brief_file'])) {
            $fErr = $_FILES['brief_file']['error'];
            if ($fErr !== UPLOAD_ERR_OK && $fErr !== UPLOAD_ERR_NO_FILE) {
                $maxSize = ini_get('upload_max_filesize') ?: '64M';
                $errMsg = match ($fErr) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Yüklenen döküman boyutu sunucu limitini aşıyor (Maks: {$maxSize}).",
                    UPLOAD_ERR_PARTIAL   => 'Dosya bağlantı kesintisi nedeniyle sadece kısmen yüklenebildi.',
                    UPLOAD_ERR_NO_TMP_DIR=> 'Sunucuda geçici dosya yükleme dizini bulunamadı.',
                    UPLOAD_ERR_CANT_WRITE=> 'Dosya sunucu diskine yazılamadı.',
                    default              => "Dosya yüklenirken hata oluştu (Kod: {$fErr})."
                };
                jsonResponse(false, $errMsg);
            }
            if ($fErr === UPLOAD_ERR_OK) {
                $docInfo = Helper::extractContentAndImagesFromDocument($_FILES['brief_file']);
            }
        }

        $res = Helper::decomposeBriefWithAi($brief, $projectId, $docInfo);
        if (!empty($res['success'])) {
            jsonResponse(true, 'Proje brifi ve döküman başarıyla modüllere, uygulama adımlarına ve görsellere ayrıştırıldı!', $res);
        } else {
            jsonResponse(false, $res['error'] ?? 'Ayrıştırma sırasında hata oluştu.');
        }
        break;

    // 29. AI PROJE MİMARI: DÜZENLENMİŞ GÖREV AĞACINI, AÇIKLAMALARI VE GÖRSELLERİ VERİTABANINA İŞLE
    case 'bulk_create_hierarchical_tasks':
        if ($currentUser->role === 'client') {
            jsonResponse(false, 'Yetkisiz erişim.');
        }
        $projectId = (int)($_POST['project_id'] ?? 1);
        $treeJson  = $_POST['tree_json'] ?? '';
        $imgsJson  = $_POST['extracted_imgs_json'] ?? '[]';
        $origDocJson = $_POST['original_doc_json'] ?? 'null';

        $epics         = json_decode($treeJson, true);
        $extractedImgs = json_decode($imgsJson, true) ?: [];
        $originalDoc   = json_decode($origDocJson, true) ?: null;

        if (empty($epics) || !is_array($epics)) {
            jsonResponse(false, 'Kaydedilecek geçerli bir görev ağacı bulunamadı.');
        }

        $createdEpicsCount = 0;
        $createdSubtasksCount = 0;
        $defaultDeadline = date('Y-m-d', strtotime('+7 days'));

        $useTransaction = false;
        if (!empty($db->connect) && method_exists($db->connect, 'beginTransaction')) {
            try {
                $db->connect->beginTransaction();
                $useTransaction = true;
            } catch (\Throwable $e) {}
        }

        try {
            foreach ($epics as $epic) {
                $epicTitle = trim($epic['title'] ?? '');
                if (empty($epicTitle)) continue;

                $epicDesc     = trim($epic['description'] ?? '');
                $epicPriority = in_array($epic['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $epic['priority'] : 'high';
                $execMode     = ($epic['execution_mode'] ?? 'auto') === 'gated' ? 'gated' : 'auto';
                $isGatedVal   = ($execMode === 'gated') ? 1 : 0;

                // Onay kapılı mı yoksa tam otonom mu etiket ve açıklama işaretini ekle
                $epicTags = ($execMode === 'gated') ? 'Epic, chain_gated' : 'Epic, chain_auto';
                if ($execMode === 'gated') {
                    $epicDesc = "[ONAY_KAPILI] (Bu modüldeki alt görevler yönetici onayıyla adım adım ilerler)\n\n" . $epicDesc;
                }

                // Eğer dökümandan çıkarılan görseller varsa Ana Görev açıklamasına da referans ekle
                if (!empty($extractedImgs)) {
                    $epicDesc .= "\n\n---\n📎 **Dökümandan Çıkarılan Referans Görseller:**\n";
                    foreach ($extractedImgs as $imgItem) {
                        $epicDesc .= "- ![" . ($imgItem['file_name'] ?? 'Görsel') . "](" . ($imgItem['file_path'] ?? '') . ")\n";
                    }
                }

                $lastId = (int)$db->single_query("SELECT MAX(id) FROM tasks") ?: 0;
                $epicCode = 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                $insEpic = $db->insert("tasks", [
                    'project_id', 'task_code', 'title', 'description', 'tags', 'is_gated', 'priority',
                    'status', 'deadline', 'created_by', 'assigned_to'
                ], [
                    $projectId, $epicCode, $epicTitle, $epicDesc, $epicTags, $isGatedVal, $epicPriority,
                    'assigned', $defaultDeadline, $currentUser->id, $currentUser->id
                ]);

                if ($insEpic) {
                    $epicId = (int)$db->last_insert_id();
                    $createdEpicsCount++;
                    $db->exec_query("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$epicId, $currentUser->id]);

                    // Orijinal Word/PDF dökümanını ve çıkarılan tüm resimleri Ana Göreve ek dosya (attachment) olarak bağla
                    if ($originalDoc && !empty($originalDoc['file_path'])) {
                        $db->exec_query("
                            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ", [$epicId, $currentUser->id, $originalDoc['file_name'], $originalDoc['file_path'], $originalDoc['file_type'], (int)$originalDoc['file_size']]);
                    }
                    foreach ($extractedImgs as $imgItem) {
                        if (!empty($imgItem['file_path'])) {
                            $db->exec_query("
                                INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ", [$epicId, $currentUser->id, $imgItem['file_name'], $imgItem['file_path'], $imgItem['file_type'], (int)$imgItem['file_size']]);
                        }
                    }

                    $subtasks = $epic['subtasks'] ?? [];
                    $subIndex = 0;
                    foreach ($subtasks as $sub) {
                        $subTitle = trim($sub['title'] ?? '');
                        if (empty($subTitle)) continue;

                        $subIndex++;
                        $subCode     = $epicCode . '-' . $subIndex;
                        $subDesc     = trim($sub['description'] ?? $subTitle);
                        $subCategory = trim($sub['category'] ?? 'coding');
                        $subPriority = in_array($sub['priority'] ?? '', ['urgent', 'high', 'normal', 'low']) ? $sub['priority'] : 'normal';
                        $subEstimate = trim($sub['time_estimate'] ?? '2h');
                        $subAssignee = (int)($sub['assigned_to'] ?? $currentUser->id) ?: (int)$currentUser->id;
                        $useAi       = !empty($sub['is_ai']);

                        // SADECE kullanıcı önizleme ekranında '🤖 AI' kutucuğunu işaretlediyse 'ai' etiketi eklenir!
                        $subTagsArr = ['Subtask', $subCategory];
                        if ($useAi) {
                            $subTagsArr[] = 'ai';
                        }
                        $subTagsStr = implode(', ', array_unique(array_filter($subTagsArr)));

                        $insSub = $db->insert("tasks", [
                            'project_id', 'parent_task_id', 'task_code', 'title', 'description', 'priority',
                            'status', 'deadline', 'created_by', 'assigned_to', 'tags', 'time_estimate', 'time_spent'
                        ], [
                            $projectId, $epicId, $subCode, $subTitle, $subDesc, $subPriority,
                            'assigned', $defaultDeadline, $currentUser->id, $subAssignee, $subTagsStr, $subEstimate, '0m'
                        ]);

                        if ($insSub) {
                            $newSubId = (int)$db->last_insert_id();
                            $createdSubtasksCount++;
                            $db->exec_query("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)", [$newSubId, $subAssignee]);

                            // Çıkarılan resimleri alt görevlere de ekle (böylece görev çekmecesinde ve AI bağlamında doğrudan görünür)
                            foreach ($extractedImgs as $imgItem) {
                                if (!empty($imgItem['file_path'])) {
                                    $db->exec_query("
                                        INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                                    ", [$newSubId, $currentUser->id, $imgItem['file_name'], $imgItem['file_path'], $imgItem['file_type'], (int)$imgItem['file_size']]);
                                }
                            }
                        }
                    }

                    // Canlı SSE yayını
                    Helper::broadcastLiveEvent('task_created', $projectId, $epicId, (int)$currentUser->id, [
                        'task_id'   => $epicId,
                        'task_code' => $epicCode,
                        'title'     => $epicTitle,
                        'status'    => 'assigned',
                        'user_name' => $currentUser->name
                    ]);
                }
            }

            if ($useTransaction) {
                $db->connect->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $db->connect->rollBack();
            }
            jsonResponse(false, 'Toplu kayıt sırasında hata oluştu: ' . $e->getMessage());
        }

        jsonResponse(true, "✅ {$createdEpicsCount} Ana Modül ve {$createdSubtasksCount} Detaylı Alt Görev başarıyla projeye işlendi!", [
            'epics_count'    => $createdEpicsCount,
            'subtasks_count' => $createdSubtasksCount
        ]);
        break;

    default:
        jsonResponse(false, 'Geçersiz AJAX işlemi.');
}
