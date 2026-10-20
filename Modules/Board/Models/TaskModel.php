<?php
namespace Modules\Board\Models;

use Core\BaseModel;

class TaskModel extends BaseModel
{
    /**
     * Kanban panosu için görevleri çeker
     */
    public function getTasksForBoard(?int $projectId = null): array
    {
        $sql = "
            SELECT t.*, p.title as project_title, p.color as project_color, 
                   u.name as assignee_name, u.avatar_color as assignee_color,
                   (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) as subtask_count,
                   (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id AND sub.status = 'completed') as subtask_completed_count
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.parent_task_id IS NULL
        ";
        $params = [];
        if ($projectId) {
            $sql .= " AND t.project_id = ? ";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY t.id DESC ";

        return $this->query($sql, $params);
    }

    /**
     * Çoklu atanan kullanıcı haritasını çeker
     */
    public function getAssigneesMap(): array
    {
        $sql = "
            SELECT ta.task_id, ta.user_id, u.id, u.name, u.avatar_color 
            FROM task_assignees ta 
            JOIN users u ON ta.user_id = u.id 
            ORDER BY u.name ASC
        ";
        $rows = $this->query($sql);
        $map = [];
        foreach ($rows as $r) {
            $row = (object)$r;
            $map[$row->task_id][] = $row;
        }
        return $map;
    }

    /**
     * Gorevlere ait dosya/ek sayilarini ceker
     */
    public function getAttachmentCounts(): array
    {
        $sql = "SELECT task_id, COUNT(*) as count FROM task_attachments GROUP BY task_id";
        $rows = $this->query($sql);
        $map = [];
        foreach ($rows as $r) {
            $row = (object)$r;
            $map[$row->task_id] = (int)$row->count;
        }
        return $map;
    }

    /**
     * Alt gorev agac haritasini ceker
     */
    public function getSubtaskMap(): array
    {
        $sql = "
            SELECT s.*, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks s
            LEFT JOIN users u ON s.assigned_to = u.id
            WHERE s.parent_task_id IS NOT NULL
            ORDER BY s.id ASC
        ";
        $allSubtasks = $this->query($sql);
        $subtaskMap = [];
        foreach ($allSubtasks as $sub) {
            $subObj = (object)$sub;
            $subtaskMap[$subObj->parent_task_id][] = $subObj;
        }
        return $subtaskMap;
    }

    /**
     * Belirli bir ay ve yıl aralığı veya Gantt için takvim görevlerini çeker
     */
    public function getTasksForCalendar(?string $startDate = null, ?string $endDate = null, ?int $projectId = null): array
    {
        $sql = "
            SELECT t.*, p.title as project_title, p.color as project_color, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND t.deadline >= ? AND t.deadline <= ? ";
            $params[] = $startDate;
            $params[] = $endDate;
        } else {
            $sql .= " AND t.deadline IS NOT NULL ";
        }
        if ($projectId) {
            $sql .= " AND t.project_id = ? ";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY t.deadline ASC, t.id ASC ";

        return $this->query($sql, $params);
    }

    /**
     * ID veya Görev Koduna göre tek bir görev çeker
     */
    public function findByIdOrCode(int|string $taskIdOrCode): ?object
    {
        if (is_numeric($taskIdOrCode)) {
            $rows = $this->query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [(int)$taskIdOrCode]);
        } else {
            $rows = $this->query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$taskIdOrCode]);
        }
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Görev durumunu günceller
     */
    public function updateStatus(int $taskId, string $status): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $taskId]);
    }

    /**
     * Bir üst göreve bağlı tamamlanmamış veya teslim edilmemiş alt görev sayısını döner
     */
    public function getRemainingSubtasksCount(int $parentTaskId): int
    {
        $rows = $this->query("
            SELECT COUNT(*) as cnt FROM tasks 
            WHERE parent_task_id = ? AND status NOT IN ('submitted', 'completed')
        ", [$parentTaskId]);
        return !empty($rows) ? (int)$rows[0]->cnt : 0;
    }

    /**
     * Görev aktivitesi kaydeder
     */
    public function logActivity(int $taskId, int $userId, string $actionType, ?string $oldValue, ?string $newValue, string $comment): bool
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$taskId, $userId, $actionType, $oldValue, $newValue, $comment]);
    }

    /**
     * Görev teslimat kaydı ekler
     */
    public function recordSubmission(int $taskId, int $userId, string $notes, string $attachmentUrl = '', ?string $commitHash = null, ?string $rollbackSql = null, ?string $schemaDiffSummary = null): bool
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, commit_hash, rollback_sql, schema_diff_summary, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        return $stmt->execute([$taskId, $userId, $notes, $attachmentUrl, $commitHash, $rollbackSql, $schemaDiffSummary]);
    }

    /**
     * Görev detayını tüm ilişkili alt verileriyle birlikte derler
     */
    public function getTaskFullDetail(int $taskId, object $currentUser): ?array
    {
        $rows = $this->query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        if (empty($rows)) {
            return null;
        }
        $task = (object)$rows[0];

        // Müşteri / Danışan rolü kontrolü: Yalnızca kendi projesindeki görevleri görebilir
        if ($currentUser->role === 'client' && (int)$task->project_id !== (int)($currentUser->client_project_id ?? 0)) {
            return null;
        }

        // Proje Bilgisi
        $projRows = $this->query("SELECT title, color FROM projects WHERE id = ? LIMIT 1", [(int)$task->project_id]);
        $task->project_title = $projRows[0]->title ?? __('Board::general_project', 'Genel Proje');
        $task->project_color = $projRows[0]->color ?? '#7B68EE';

        // Bağlı olduğu ana görev (Nested Subtask parent context)
        if (!empty($task->parent_task_id)) {
            $pRows = $this->query("SELECT id, task_code, title FROM tasks WHERE id = ? LIMIT 1", [(int)$task->parent_task_id]);
            $task->parent_task = !empty($pRows) ? (object)$pRows[0] : null;
        }

        // Atanan Kullanıcılar (Çoklu Atama Desteği)
        $assignees = $this->query("
            SELECT u.id, u.name, u.email, u.title, u.avatar_color, u.role
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.id ASC
        ", [$taskId]);

        if (empty($assignees) && !empty($task->assigned_to)) {
            $fallbackUser = $this->query("SELECT id, name, email, title, avatar_color, role FROM users WHERE id = ? LIMIT 1", [(int)$task->assigned_to]);
            if (!empty($fallbackUser)) {
                $assignees = [$fallbackUser[0]];
            }
        }
        $task->assignees = $assignees;
        $task->assignee_name = !empty($assignees) ? implode(', ', array_map(fn($a) => $a->name, $assignees)) : __('Board::unassigned');

        // Aktiviteler (Müşteri için ekip içi aktiviteler gizlenir)
        $actSql = "
            SELECT a.*, u.name as user_name 
            FROM task_activities a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.task_id = ?
        ";
        if ($currentUser->role === 'client') {
            $internalTag = "%[" . "\xC4\xB0" . "i]%";
            $internalTagAlt = "%[Ekip %";
            $actSql .= " AND a.comment NOT LIKE '{$internalTagAlt}' AND a.comment NOT LIKE '%🔒%' ";
        }
        $actSql .= " ORDER BY a.id DESC";
        $activities = $this->query($actSql, [$taskId]);

        // Varsa Bekleyen Revizyon Talebi
        $modRows = $this->query("SELECT * FROM task_modifications WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        $modification = !empty($modRows) ? (object)$modRows[0] : null;

        // Varsa Teslimat
        $subRows = $this->query("SELECT * FROM task_submissions WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        $submission = !empty($subRows) ? (object)$subRows[0] : null;

        // Görev Yorumları & Soru-Cevap (TaskManager Comments)
        $commentSql = "
            SELECT c.*, u.name as user_name, u.title as user_title, u.avatar_color, u.role as user_role
            FROM task_comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.task_id = ?
        ";
        if ($currentUser->role === 'client') {
            $commentSql .= " AND c.is_internal = 0 ";
        }
        $commentSql .= " ORDER BY c.id ASC";
        $comments = $this->query($commentSql, [$taskId]);

        // TaskManager Kontrol Listesi (Checklists)
        $checklists = $this->query("SELECT * FROM task_checklists WHERE task_id = ? ORDER BY id ASC", [$taskId]);

        // TaskManager Alt Görevler (Nested Subtasks)
        $subtasks = $this->query("
            SELECT s.*, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks s
            LEFT JOIN users u ON s.assigned_to = u.id
            WHERE s.parent_task_id = ?
            ORDER BY s.id ASC
        ", [$taskId]);

        // TaskManager Bağımlılıklar (Task Dependencies: Waiting On & Blocking)
        $dependencies = $this->query("
            SELECT d.id as dep_id, d.type as dep_type, d.task_id, d.depends_on_task_id,
                   t.title as dep_title, t.task_code as dep_code, t.status as dep_status
            FROM task_dependencies d
            JOIN tasks t ON (CASE WHEN d.task_id = ? THEN d.depends_on_task_id ELSE d.task_id END) = t.id
            WHERE d.task_id = ? OR d.depends_on_task_id = ?
        ", [$taskId, $taskId, $taskId]);

        // Bağımlılık eklenebilecek diğer görevler listesi
        $availableTasks = $this->query("
            SELECT id, task_code, title FROM tasks WHERE id != ? AND parent_task_id IS NULL ORDER BY id DESC LIMIT 20
        ", [$taskId]);

        // Tüm ekip üyeleri listesi (@ ile çalışan seçimi için)
        $allTeamUsers = $this->query("
            SELECT id, name, email, title, avatar_color, role FROM users ORDER BY name ASC
        ");

        // Görev Ekleri & Resimleri (Attachments)
        $attachments = $this->query("
            SELECT a.*, u.name as user_name 
            FROM task_attachments a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.task_id = ? 
            ORDER BY a.id DESC
        ", [$taskId]);

        return [
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
            'attachments' => $attachments
        ];
    }

    /**
     * Sıradaki TSK kodunu üretir
     */
    public function generateNextTaskCode(): string
    {
        $rows = $this->query("SELECT MAX(id) as max_id FROM tasks");
        $lastId = !empty($rows) ? (int)$rows[0]->max_id : 0;
        return 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Yeni görev ekler
     */
    public function createTask(array $data): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO tasks (project_id, task_code, title, description, tags, priority, status, deadline, created_by, assigned_to, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $data['project_id'],
            $data['task_code'],
            $data['title'],
            $data['description'] ?? '',
            $data['tags'] ?? '',
            $data['priority'] ?? 'normal',
            $data['status'] ?? 'assigned',
            $data['deadline'],
            $data['created_by'],
            $data['assigned_to']
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Göreve çoklu çalışan ataması yapar
     */
    public function addAssignees(int $taskId, array $userIds): void
    {
        $stmt = $this->getPdo()->prepare("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $stmt->execute([$taskId, (int)$uid]);
        }
    }

    /**
     * Görev eki kaydeder
     */
    public function addAttachment(int $taskId, int $userId, array $fileData): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $taskId,
            $userId,
            $fileData['file_name'],
            $fileData['file_path'],
            $fileData['file_type'],
            $fileData['file_size']
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Checklist maddesini tamamlandı/tamamlanmadı yapar
     */
    public function toggleChecklistItem(int $itemId, int $isCompleted): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE task_checklists SET is_completed = ? WHERE id = ?");
        return $stmt->execute([$isCompleted, $itemId]);
    }

    /**
     * Yeni checklist maddesi ekler
     */
    public function addChecklistItem(int $taskId, string $title): int
    {
        $stmt = $this->getPdo()->prepare("INSERT INTO task_checklists (task_id, title, is_completed) VALUES (?, ?, 0)");
        $stmt->execute([$taskId, $title]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Checklist maddesi siler
     */
    public function deleteChecklistItem(int $itemId): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM task_checklists WHERE id = ?");
        return $stmt->execute([$itemId]);
    }

    /**
     * Görev alanını dinamik günceller
     */
    public function updateTaskField(int $taskId, string $field, mixed $value): bool
    {
        $allowed = ['title', 'description', 'priority', 'deadline', 'tags', 'time_estimate', 'time_spent'];
        if (!in_array($field, $allowed, true)) {
            return false;
        }
        $stmt = $this->getPdo()->prepare("UPDATE tasks SET `{$field}` = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$value, $taskId]);
    }

    /**
     * Göreve harcanan süreyi kaydeder
     */
    public function logTime(int $taskId, int $userId, int $minutes, string $note = ''): bool
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_time_logs (task_id, user_id, minutes, note, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $ok = $stmt->execute([$taskId, $userId, $minutes, $note]);
        if ($ok) {
            $totalMins = (int)$this->query("SELECT SUM(minutes) as total FROM task_time_logs WHERE task_id = ?", [$taskId])[0]->total ?? 0;
            $h = floor($totalMins / 60);
            $m = $totalMins % 60;
            $spentStr = ($h > 0 ? "{$h}s " : "") . "{$m}d";
            $this->updateTaskField($taskId, 'time_spent', $spentStr);
        }
        return $ok;
    }

    /**
     * Görev bağımlılığı ekler
     */
    public function addDependency(int $taskId, int $dependsOnTaskId, string $type = 'waiting_on'): bool
    {
        $stmt = $this->getPdo()->prepare("
            INSERT IGNORE INTO task_dependencies (task_id, depends_on_task_id, type)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$taskId, $dependsOnTaskId, $type]);
    }

    /**
     * Görev bağımlılığı siler
     */
    public function removeDependency(int $depId): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM task_dependencies WHERE id = ?");
        return $stmt->execute([$depId]);
    }

    /**
     * Alt görev ekler
     */
    public function createSubtask(int $parentTaskId, string $title, string $priority, int $userId, int $assignedTo): ?array
    {
        $parent = $this->findByIdOrCode($parentTaskId);
        if (!$parent) {
            return null;
        }

        $existing = $this->query("SELECT task_code FROM tasks WHERE parent_task_id = ?", [$parentTaskId]);
        $maxIdx = 0;
        foreach ($existing as $es) {
            if (preg_match('/-(\d+)$/', $es->task_code, $m)) {
                $idx = (int)$m[1];
                if ($idx > $maxIdx) $maxIdx = $idx;
            }
        }
        $subCode = $parent->task_code . '-' . ($maxIdx + 1);

        $stmt = $this->getPdo()->prepare("
            INSERT INTO tasks (project_id, parent_task_id, task_code, title, priority, status, deadline, created_by, assigned_to, tags, time_estimate, time_spent, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'assigned', ?, ?, ?, 'Subtask', '2h', '0m', NOW(), NOW())
        ");
        $stmt->execute([
            $parent->project_id, $parentTaskId, $subCode, $title, $priority,
            $parent->deadline, $userId, $assignedTo
        ]);
        $subId = (int)$this->getPdo()->lastInsertId();

        $this->logActivity($parentTaskId, $userId, 'subtask_created', null, 'assigned', __('Board::activity_subtask_created', ['code' => $subCode, 'title' => $title]));

        return ['subtask_id' => $subId, 'task_code' => $subCode, 'title' => $title];
    }

    /**
     * Görevi ve bağlı tüm kayıtlarını temizler
     */
    public function deleteTaskCascading(int $taskId): bool
    {
        $this->query("DELETE FROM task_checklists WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_comments WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_activities WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_dependencies WHERE task_id = ? OR depends_on_task_id = ?", [$taskId, $taskId]);
        $this->query("DELETE FROM task_modifications WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_submissions WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_attachments WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM task_assignees WHERE task_id = ?", [$taskId]);
        $this->query("DELETE FROM tasks WHERE parent_task_id = ?", [$taskId]);
        $stmt = $this->getPdo()->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$taskId]);
    }

    /**
     * Göreve yeni yorum ekler
     */
    public function addComment(int $taskId, int $userId, string $comment, int $isInternal = 0): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_comments (task_id, user_id, comment, is_internal, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$taskId, $userId, $comment, $isInternal]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Yorum günceller
     */
    public function updateComment(int $commentId, string $comment): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE task_comments SET comment = ?, is_edited = 1, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$comment, $commentId]);
    }

    /**
     * Görevden çalışan çıkarır
     */
    public function removeAssignee(int $taskId, int $userId): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM task_assignees WHERE task_id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $remaining = $this->query("SELECT user_id FROM task_assignees WHERE task_id = ? ORDER BY id ASC LIMIT 1", [$taskId]);
        $newPrimary = !empty($remaining) ? (int)$remaining[0]->user_id : null;
        $stmt2 = $this->getPdo()->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
        return $stmt2->execute([$newPrimary, $taskId]);
    }

    /**
     * get_task_card_html için görev ve atanan bilgilerini çeker
     */
    public function getTaskCardData(int $taskId): ?array
    {
        $rows = $this->query("
            SELECT t.*, p.title as project_title, p.color as project_color, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ", [$taskId]);

        if (empty($rows)) {
            return null;
        }
        $task = (object)$rows[0];

        $assignees = $this->query("
            SELECT u.id as user_id, u.name, u.avatar_color, u.title
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = ?
            ORDER BY ta.id ASC
        ", [$taskId]) ?: [];

        if (empty($assignees) && !empty($task->assigned_to)) {
            $assignees = [(object)['user_id' => $task->assigned_to, 'name' => $task->assignee_name, 'avatar_color' => $task->assignee_color]];
        }

        $attCount = (int)($this->query("SELECT COUNT(*) as cnt FROM task_attachments WHERE task_id = ?", [$taskId])[0]->cnt ?? 0);

        return [
            'task' => $task,
            'assignees' => $assignees,
            'att_count' => $attCount
        ];
    }

    /**
     * Revizyon talebini kaydeder
     */
    public function addModificationRequest(int $taskId, int $userId, string $reason): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_modifications (task_id, requested_by, reason, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$taskId, $userId, $reason]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Revizyon talebini çözer/kapatır
     */
    public function resolveModification(int $taskId): bool
    {
        $stmt = $this->getPdo()->prepare("
            UPDATE task_modifications SET status = 'resolved', resolved_at = NOW() WHERE task_id = ? AND status = 'pending'
        ");
        return $stmt->execute([$taskId]);
    }

    /**
     * Teslimatı onaylar ve görevi tamamlandı yapar
     */
    public function approveSubmission(int $taskId): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt1 = $this->getPdo()->prepare("UPDATE tasks SET status = 'completed', completed_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt1->execute([$now, $taskId]);
        $stmt2 = $this->getPdo()->prepare("UPDATE task_submissions SET status = 'approved', reviewed_at = ? WHERE task_id = ?");
        return $stmt2->execute([$now, $taskId]);
    }

    /**
     * Teslimatı reddeder ve düzeltme talebi açar
     */
    public function requestChanges(int $taskId, int $userId, string $reason): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt1 = $this->getPdo()->prepare("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?");
        $stmt1->execute([$taskId]);

        $stmt2 = $this->getPdo()->prepare("UPDATE task_submissions SET status = 'rejected', feedback = ?, reviewed_at = ? WHERE task_id = ?");
        $stmt2->execute([$reason, $now, $taskId]);

        $this->addModificationRequest($taskId, $userId, $reason);
        return true;
    }

    /**
     * Görev ekini siler
     */
    public function deleteTaskAttachment(int $attId): ?object
    {
        $rows = $this->query("SELECT * FROM task_attachments WHERE id = ? LIMIT 1", [$attId]);
        if (empty($rows)) {
            return null;
        }
        $att = (object)$rows[0];
        $stmt = $this->getPdo()->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmt->execute([$attId]);
        return $att;
    }

    /**
     * Göreve yeni çalışan atar
     */
    public function addTaskAssignee(int $taskId, int $userId): ?object
    {
        $userRows = $this->query("SELECT id, name, avatar_color, title, role FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (empty($userRows)) {
            return null;
        }
        $u = (object)$userRows[0];

        $exists = $this->query("SELECT id FROM task_assignees WHERE task_id = ? AND user_id = ? LIMIT 1", [$taskId, $userId]);
        if (!empty($exists)) {
            return null;
        }

        $stmt = $this->getPdo()->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)");
        $stmt->execute([$taskId, $userId]);

        $taskRows = $this->query("SELECT assigned_to FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        if (!empty($taskRows) && (empty($taskRows[0]->assigned_to) || (int)$taskRows[0]->assigned_to === 0)) {
            $stmt2 = $this->getPdo()->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
            $stmt2->execute([$userId, $taskId]);
        }

        return $u;
    }

    /**
     * Kullanıcı bildirimlerini çeker
     */
    public function getUserNotifications(int $userId, int $limit = 15): array
    {
        return $this->query("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY id DESC LIMIT {$limit}
        ", [$userId]);
    }

    /**
     * Bildirimi okundu olarak işaretler
     */
    public function markNotificationAsRead(int $notifId): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notifId]);
    }

    /**
     * Sistem ayarlarını çeker
     */
    public function getSystemSettings(): ?object
    {
        $rows = $this->query("SELECT * FROM settings LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Projedeki diğer aktif AI görevlerini çeker
     */
    public function getRunningAiTasks(int $projectId, int $excludeTaskId): array
    {
        return $this->query("
            SELECT id, task_code, tags FROM tasks 
            WHERE project_id = ? AND status = 'in_progress' AND id != ?
              AND (LOWER(tags) LIKE '%ai%' OR LOWER(tags) LIKE '%otonom%' OR LOWER(tags) LIKE '%agent%' OR LOWER(tags) LIKE '%yapay zeka%' OR LOWER(tags) LIKE '%antigravity%')
        ", [$projectId, $excludeTaskId]);
    }

    /**
     * Görev durumunu günceller ve opsiyonel completed_at ayarlar
     */
    public function updateTaskStatus(int $taskId, string $status, ?string $completedAt = null): bool
    {
        if ($completedAt) {
            $stmt = $this->getPdo()->prepare("UPDATE tasks SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$status, $completedAt, $taskId]);
        }
        $stmt = $this->getPdo()->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $taskId]);
    }

    /**
     * Görev teslimatını kaydeder
     */
    public function addSubmission(int $taskId, int $userId, string $notes, string $attachmentUrl = ''): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$taskId, $userId, $notes, $attachmentUrl]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Görevi proje bilgileriyle çeker
     */
    public function getTaskWithProject(int $taskId): ?object
    {
        $rows = $this->query("SELECT t.*, p.title as project_title FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.id = ? LIMIT 1", [$taskId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Yorumu bağlı olduğu görev ve proje bilgileriyle çeker
     */
    public function getCommentWithTask(int $commentId): ?object
    {
        $rows = $this->query("SELECT c.*, t.project_id, t.task_code, t.title as task_title FROM task_comments c JOIN tasks t ON c.task_id = t.id WHERE c.id = ? LIMIT 1", [$commentId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Görevin alt görevlerinin durum sayısını çeker
     */
    public function countUnfinishedSubtasks(int $parentTaskId): int
    {
        $rows = $this->query("SELECT COUNT(*) as cnt FROM tasks WHERE parent_task_id = ? AND status NOT IN ('submitted', 'completed')", [$parentTaskId]);
        return !empty($rows) ? (int)$rows[0]->cnt : 0;
    }

    /**
     * Görevin çalışan alt görevlerinin sayısını çeker
     */
    public function countRunningSubtasks(int $parentTaskId): int
    {
        $rows = $this->query("SELECT COUNT(*) as cnt FROM tasks WHERE parent_task_id = ? AND status = 'in_progress'", [$parentTaskId]);
        return !empty($rows) ? (int)$rows[0]->cnt : 0;
    }

    /**
     * Görevin son revizyon gerekçesini çeker
     */
    public function getLastModificationReason(int $taskId): string
    {
        $rows = $this->query("SELECT reason FROM task_modifications WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        return !empty($rows) ? (string)$rows[0]->reason : '';
    }

    /**
     * Kullanıcı adını ID'ye göre çeker
     */
    public function getUserNameById(int $userId): ?string
    {
        $rows = $this->query("SELECT name FROM users WHERE id = ? LIMIT 1", [$userId]);
        return !empty($rows) ? (string)$rows[0]->name : null;
    }

    /**
     * Görev etiketlerini çeker
     */
    public function getTaskTags(int $taskId): string
    {
        $rows = $this->query("SELECT tags FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        return !empty($rows) ? (string)($rows[0]->tags ?? '') : '';
    }

    /**
     * Devam eden otonom AI görevini çeker
     */
    public function getInProgressAiTask(int $projectId): ?object
    {
        $sql = "
            SELECT t.* FROM tasks t
            LEFT JOIN tasks p ON t.parent_task_id = p.id
            WHERE t.project_id = ? 
              AND t.status = 'in_progress'
              AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = t.id)
              AND (
                  (
                      LOWER(t.tags) LIKE '%ai%' 
                      OR LOWER(t.tags) LIKE '%otonom%' 
                      OR LOWER(t.tags) LIKE '%agent%'
                      OR LOWER(t.tags) LIKE '%yapay zeka%'
                      OR LOWER(t.tags) LIKE '%antigravity%'
                  )
                  OR (
                      t.parent_task_id IS NOT NULL 
                      AND (
                          LOWER(p.tags) LIKE '%ai%' 
                          OR LOWER(p.tags) LIKE '%otonom%' 
                          OR LOWER(p.tags) LIKE '%agent%'
                          OR LOWER(p.tags) LIKE '%yapay zeka%'
                          OR LOWER(p.tags) LIKE '%antigravity%'
                      )
                  )
              )
            ORDER BY t.id ASC
            LIMIT 1
        ";
        $rows = $this->query($sql, [$projectId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Sıradaki atanmış otonom AI adaylarını çeker
     */
    public function getCandidateAiTasks(int $projectId): array
    {
        $sql = "
            SELECT t.* FROM tasks t
            LEFT JOIN tasks p ON t.parent_task_id = p.id
            WHERE t.project_id = ? 
              AND t.status = 'assigned'
              AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = t.id)
              AND (
                  (
                      LOWER(t.tags) LIKE '%ai%' 
                      OR LOWER(t.tags) LIKE '%otonom%' 
                      OR LOWER(t.tags) LIKE '%agent%'
                      OR LOWER(t.tags) LIKE '%yapay zeka%'
                      OR LOWER(t.tags) LIKE '%antigravity%'
                  )
                  OR (
                      t.parent_task_id IS NOT NULL 
                      AND (
                          LOWER(p.tags) LIKE '%ai%' 
                          OR LOWER(p.tags) LIKE '%otonom%' 
                          OR LOWER(p.tags) LIKE '%agent%'
                          OR LOWER(p.tags) LIKE '%yapay zeka%'
                          OR LOWER(p.tags) LIKE '%antigravity%'
                      )
                  )
              )
            ORDER BY t.parent_task_id ASC, t.id ASC
        ";
        return $this->query($sql, [$projectId]);
    }

    /**
     * Görev durumunu ve güncellenme tarihini yeniler
     */
    public function updateTaskStatusAndTimestamp(int $taskId, string $status): void
    {
        $this->query("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $taskId]);
    }

    /**
     * Üst görev durumunu çeker
     */
    public function getTaskParentRow(int $parentId): ?object
    {
        $rows = $this->query("SELECT id, task_code, title, status FROM tasks WHERE id = ? LIMIT 1", [$parentId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Otonom kuyruk aktivitesi kaydeder
     */
    public function insertAutonomousActivity(int $taskId, string $comment): void
    {
        $this->query("
            INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
            VALUES (?, 1, 'status_change', 'assigned', 'in_progress', ?, NOW())
        ", [$taskId, $comment]);
    }

    /**
     * Tekil görev kaydı çeker
     */
    public function getTaskRowById(int $taskId): ?object
    {
        $rows = $this->query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Görev checklist listesini çeker
     */
    public function getTaskChecklists(int $taskId): array
    {
        return $this->query("
            SELECT id, title, is_completed 
            FROM task_checklists 
            WHERE task_id = ? 
            ORDER BY id ASC
        ", [$taskId]);
    }

    /**
     * Göreve atanan kullanıcı isimlerini çeker
     */
    public function getTaskAssigneeNames(int $taskId): array
    {
        $rows = $this->query("
            SELECT u.name 
            FROM task_assignees ta 
            JOIN users u ON ta.user_id = u.id 
            WHERE ta.task_id = ?
        ", [$taskId]);
        return array_map(fn($a) => $a->name, $rows);
    }

    /**
     * Üst görev mimari bağlamını çeker
     */
    public function getParentTaskContext(int $parentId): ?object
    {
        $rows = $this->query("SELECT task_code, title, description FROM tasks WHERE id = ? LIMIT 1", [$parentId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Görev eklerini çeker
     */
    public function getTaskAttachments(int $taskId): array
    {
        return $this->query("
            SELECT id, file_name, file_path, file_type, file_size 
            FROM task_attachments 
            WHERE task_id = ? 
            ORDER BY id ASC
        ", [$taskId]);
    }

    /**
     * QA Revizyon sayacını 1 artırır ve güncel sayıyı döner
     */
    public function incrementQaRevisionCount(int $taskId): int
    {
        $this->db->exec_query("UPDATE tasks SET qa_revision_count = COALESCE(qa_revision_count, 0) + 1, updated_at = NOW() WHERE id = ?", [$taskId]);
        $rows = $this->query("SELECT qa_revision_count FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        return !empty($rows) ? (int)$rows[0]->qa_revision_count : 1;
    }

    /**
     * Görevin mevcut QA Revizyon sayısını döner
     */
    public function getQaRevisionCount(int $taskId): int
    {
        $rows = $this->query("SELECT qa_revision_count FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        return !empty($rows) ? (int)($rows[0]->qa_revision_count ?? 0) : 0;
    }

    /**
     * QA Revizyon sayacını sıfırlar
     */
    public function resetQaRevisionCount(int $taskId): void
    {
        $this->db->exec_query("UPDATE tasks SET qa_revision_count = 0, updated_at = NOW() WHERE id = ?", [$taskId]);
    }
}


