<?php
namespace Modules\SSE\Models;

use Core\BaseModel;

class SseEventModel extends BaseModel
{
    /**
     * Canlı SSE olay kaydı oluşturur
     */
    public function logBroadcast(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, array $payload = []): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (?, ?, 'sse_broadcast', ?, NOW())";
        $desc = "SSE Event: {$eventType} for project " . ($projectId ?? 0);
        return $this->execute($sql, [$taskId ?? 0, $userId ?? 1, $desc]);
    }
}
