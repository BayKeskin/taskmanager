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
            SELECT ta.task_id, u.id, u.name, u.avatar_color 
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
        $map = [];
        foreach ($rows as $r) {
            $row = (object)$r;
            $map[$row->task_id] = (int)$row->count;
        }
        return $map;
    }
}
