<?php
namespace Modules\Dispatcher\Models;

use Core\BaseModel;

class DispatcherModel extends BaseModel
{
    /**
     * Otonom kuyrukta bekleyen AI görevlerini çeker
     */
    public function getQueuedAiTasks(?int $projectId = null): array
    {
        $sql = "SELECT t.*, p.project_code, p.title as project_title, p.workspace_path 
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE t.status = 'assigned' AND t.is_ai = 1";
        $params = [];
        if ($projectId) {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY t.id ASC";
        return $this->query($sql, $params);
    }
}
