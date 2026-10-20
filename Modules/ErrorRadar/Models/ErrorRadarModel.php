<?php
namespace Modules\ErrorRadar\Models;

use Core\BaseModel;

/**
 * ErrorRadarModel - Hata yakalama, parmak izi tekilleştirme ve görev açma veri katmanı
 */
class ErrorRadarModel extends BaseModel
{
    protected string $table = 'tasks';

    /**
     * Hata parmak izine (fingerprint) göre tamamlanmamış (açık) bir görev var mı kontrol eder.
     */
    public function hasActiveErrorTask(int $projectId, string $fingerprint): bool
    {
        $sql = "
            SELECT id FROM tasks 
            WHERE project_id = ? 
              AND status != 'completed' 
              AND (
                  description LIKE ? 
                  OR tags LIKE ?
              )
            LIMIT 1
        ";
        $paramDesc = '%' . $fingerprint . '%';
        $paramTag  = '%' . substr($fingerprint, 0, 16) . '%';
        $rows = $this->query($sql, [$projectId, $paramDesc, $paramTag]);
        return !empty($rows);
    }

    /**
     * Proje koduna göre bir sonraki görev kodunu üretir.
     */
    public function getNextTaskCode(string $projectCode): string
    {
        $lastNum = (int)$this->getPdo()->query("SELECT MAX(id) FROM tasks")->fetchColumn() + 1;
        return $projectCode . '-' . (100 + $lastNum);
    }

    /**
     * Kanban panosunun todo sütununa otomatik hata görevi ekler.
     */
    public function createRadarTask(
        int $projectId,
        string $taskCode,
        string $title,
        string $description,
        int $creatorId = 1
    ): int {
        $sql = "
            INSERT INTO tasks (
                project_id, task_code, title, description,
                tags, priority, status, deadline, time_estimate, time_spent,
                created_by, assigned_to, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                'bug,auto-radar', 'high', 'assigned', NULL, '1h', '0h',
                ?, ?, NOW(), NOW()
            )
        ";
        $this->getPdo()->prepare($sql)->execute([
            $projectId,
            $taskCode,
            $title,
            $description,
            $creatorId,
            $creatorId
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Göreve kullanıcı atar.
     */
    public function assignUser(int $taskId, int $userId): void
    {
        $stmt = $this->getPdo()->prepare("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$taskId, $userId]);
    }

    /**
     * Projeyi ID veya koda göre çeker.
     */
    public function findProject(string|int $projectIdentifier): ?object
    {
        if (is_numeric($projectIdentifier)) {
            $rows = $this->query("SELECT * FROM projects WHERE id = ? LIMIT 1", [(int)$projectIdentifier]);
        } else {
            $rows = $this->query("SELECT * FROM projects WHERE project_code = ? LIMIT 1", [$projectIdentifier]);
        }
        if (!empty($rows)) {
            return (object)$rows[0];
        }

        $fallback = $this->query("SELECT * FROM projects ORDER BY id ASC LIMIT 1");
        return !empty($fallback) ? (object)$fallback[0] : null;
    }
}
