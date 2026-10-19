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
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at) 
                VALUES (0, 1, 'watchdog_recovery', ?, NOW())";
        $desc = "Watchdog [{$serviceName}] -> {$status}: {$details}";
        return $this->execute($sql, [$desc]);
    }


    /**
     * Belirli dakikadan uzun süredir güncellenmeyen 'in_progress' durumundaki görevleri bulur
     */
    public function getStaleInProgressTasks(int $minutesThreshold = 25): array
    {
        $sql = "
            SELECT t.*, p.project_code, p.title as project_title 
            FROM tasks t
            LEFT JOIN projects p ON p.id = t.project_id
            WHERE t.status = 'in_progress' 
              AND (t.updated_at IS NULL OR t.updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE))
            ORDER BY t.updated_at ASC
        ";
        return $this->query($sql, [$minutesThreshold]) ?: [];
    }

    /**
     * Tıkanmış görevi 'assigned' durumuna çeker ve not ekler
     */
    public function resetStaleTaskToAssigned(int $taskId, string $reason = 'Watchdog Stale Task Recovery'): bool
    {
        $sql = "UPDATE tasks SET status = 'assigned', updated_at = NOW() WHERE id = ?";
        $res = $this->execute($sql, [$taskId]);
        if ($res > 0) {
            $this->logRecovery('StaleRecovery', 'recovered', "Task ID {$taskId} recovered: {$reason}");
            return true;
        }
        return false;
    }
}

