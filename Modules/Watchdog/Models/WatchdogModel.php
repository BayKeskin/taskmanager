<?php
namespace Modules\Watchdog\Models;

use Core\BaseModel;

class WatchdogModel extends BaseModel
{
    /**
     * Aktif projeleri listeler
     */
    public function getActiveProjects(): array
    {
        $sql = "SELECT id, project_code, title FROM projects WHERE status = 'active'";
        return $this->query($sql) ?: [];
    }

    /**
     * Projeye ait 'in_progress' durumundaki görevleri öncelik sırasına göre getirir
     */
    public function getInProgressTasks(int $projectId): array
    {
        $sql = "
            SELECT * FROM tasks 
            WHERE project_id = ? AND status = 'in_progress'
            ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    WHEN 'low' THEN 4 
                    ELSE 5 
                END ASC,
                updated_at DESC
        ";
        return $this->query($sql, [$projectId]) ?: [];
    }

    /**
     * Görev durumunu 'assigned' olarak günceller
     */
    public function resetTaskToAssigned(int $taskId): bool
    {
        $sql = "UPDATE tasks SET status = 'assigned' WHERE id = ?";
        return $this->execute($sql, [$taskId]) > 0;
    }

    /**
     * Bekçi kurtarma ve sağlık denetim kayıtlarını çeker/ekler
     */
    public function logRecovery(string $serviceName, string $status, string $details): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, 1, 'watchdog_recovery', ?, NOW())";
        $desc = "Watchdog [{$serviceName}] -> {$status}: {$details}";
        return $this->execute($sql, [$desc]);
    }
}
