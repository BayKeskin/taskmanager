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
     * Görevlere ait dosya/ek sayılarını çeker
     */
    public function getAttachmentCounts(): array
    {
        $sql = "SELECT task_id, COUNT(*) as count FROM task_attachments GROUP BY task_id";
        $rows = $this->query($sql);
        foreach ($rows as $r) {
            $row = (object)$r;
            $map[$row->task_id] = (int)$row->count;
        }
        return $map;
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
}
