<?php
namespace Modules\ExcelExport\Models;

use Core\BaseModel;

/**
 * ExcelExportModel - Dışa aktarma veri katmanı
 */
class ExcelExportModel extends BaseModel
{
    protected string $table = 'tasks';

    /**
     * Dışa aktarma için proje görevlerini eksiksiz çeker
     */
    public function getTasksForExport(int $projectId): array
    {
        $sql = "
            SELECT t.id, t.task_code, t.title, t.description, t.status, t.priority,
                   t.deadline, t.created_at, t.updated_at,
                   u_assignee.name as assignee_name,
                   u_creator.name as creator_name,
                   (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id) as subtask_count,
                   (SELECT COUNT(*) FROM tasks sub WHERE sub.parent_task_id = t.id AND sub.status = 'completed') as subtask_completed_count
            FROM tasks t
            LEFT JOIN users u_assignee ON t.assigned_to = u_assignee.id
            LEFT JOIN users u_creator ON t.created_by = u_creator.id
            WHERE t.project_id = ? AND t.parent_task_id IS NULL
            ORDER BY t.id DESC
        ";
        return $this->query($sql, [$projectId]);
    }
}
