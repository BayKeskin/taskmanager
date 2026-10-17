<?php

require_once __DIR__ . '/Model.php';

class TaskModel extends Model
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

        return $this->db->multi_record_query($sql, $params) ?: [];
    }

    /**
     * Liste görünümü için ana görevleri çeker
     */
    public function getTasksForList(?int $projectId = null): array
    {
        $sql = "
            SELECT t.*, p.title as project_title, p.color as project_color, 
                   u.name as assignee_name, u.avatar_color as assignee_color
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

        return $this->db->multi_record_query($sql, $params) ?: [];
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

        return $this->db->multi_record_query($sql, $params) ?: [];
    }


    /**
     * Çoklu çalışan atamalarını eşler (task_id => [assignees])
     */
    public function getAssigneesMap(): array
    {
        $assigneesMap = [];
        $allAssigneeRows = $this->db->multi_record_query("
            SELECT ta.task_id, u.id as user_id, u.name, u.avatar_color, u.title
            FROM task_assignees ta
            JOIN users u ON ta.user_id = u.id
            ORDER BY ta.id ASC
        ") ?: [];

        foreach ($allAssigneeRows as $ar) {
            $assigneesMap[$ar->task_id][] = $ar;
        }

        return $assigneesMap;
    }

    /**
     * Görev ek dosya sayılarını çeker
     */
    public function getAttachmentCounts(): array
    {
        return $this->db->pair_query("SELECT task_id, COUNT(*) as cnt FROM task_attachments GROUP BY task_id") ?: [];
    }

    /**
     * Liste görünümü için alt görevleri eşler (parent_task_id => [subtasks])
     */
    public function getSubtaskMap(): array
    {
        $allSubtasks = $this->db->multi_record_query("
            SELECT s.*, u.name as assignee_name, u.avatar_color as assignee_color
            FROM tasks s
            LEFT JOIN users u ON s.assigned_to = u.id
            WHERE s.parent_task_id IS NOT NULL
            ORDER BY s.id ASC
        ") ?: [];

        $subtaskMap = [];
        foreach ($allSubtasks as $sub) {
            $subtaskMap[$sub->parent_task_id][] = $sub;
        }

        return $subtaskMap;
    }

    /**
     * Duruma göre görev sayısını döner
     */
    public function countTasksByStatus(?string $status = null): int
    {
        if ($status === null) {
            return (int)($this->db->count_record("tasks") ?: 0);
        }
        return (int)($this->db->count_record("tasks", ["status"], [$status]) ?: 0);
    }

    /**
     * Gecikmiş (overdue) görevlerin sayısını döner
     */
    public function countOverdueTasks(?string $today = null): int
    {
        $today = $today ?: date('Y-m-d');
        return (int)$this->db->single_query("
            SELECT COUNT(*) FROM tasks 
            WHERE deadline < ? AND status != 'completed'
        ", [$today]);
    }

    /**
     * Bir kullanıcıya atanan görev sayısını döner
     */
    public function countUserTasks(int $userId): int
    {
        return (int)($this->db->count_record("tasks", ["assigned_to"], [$userId]) ?: 0);
    }
}
