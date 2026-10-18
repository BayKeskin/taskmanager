<?php
namespace Modules\VisualPins\Models;

use Core\BaseModel;

/**
 * PinModel - Görsel pin kayıt ve ilişki veri katmanı
 */
class PinModel extends BaseModel
{
    protected string $table = 'tasks';

    /**
     * Görsel pin görevini veritabanına kaydeder
     */
    public function createPinTask(
        int $projectId,
        string $taskCode,
        string $title,
        string $fullDesc,
        string $priority,
        int $creatorId
    ): int {
        $sql = "
            INSERT INTO tasks (
                project_id, task_code, title, description,
                tags, priority, status, deadline, time_estimate, time_spent,
                created_by, assigned_to, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                'visual_pin,bugherd_alternative', ?, 'assigned', NULL, '2h', '0h',
                ?, ?, NOW(), NOW()
            )
        ";
        $this->getPdo()->prepare($sql)->execute([
            $projectId, $taskCode, $title, $fullDesc,
            $priority, $creatorId, $creatorId
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Göreve atanan kişiyi ekler
     */
    public function assignUser(int $taskId, int $userId): void
    {
        $stmt = $this->getPdo()->prepare("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$taskId, $userId]);
    }

    /**
     * Ekran görüntüsü dosyasını task_attachments tablosuna iliştirir
     */
    public function attachScreenshot(int $taskId, int $userId, string $fileName, string $filePath, string $mimeType, int $fileSize): void
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$taskId, $userId, $fileName, $filePath, $mimeType, $fileSize]);
    }

    /**
     * Sonraki task code numarasını belirler
     */
    public function getNextTaskCode(string $projectCode): string
    {
        $lastNum = (int)$this->getPdo()->query("SELECT MAX(id) FROM tasks")->fetchColumn() + 1;
        return $projectCode . '-' . (100 + $lastNum);
    }
}
