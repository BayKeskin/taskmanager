<?php
namespace Modules\SSE\Models;

use Core\BaseModel;

class SseEventModel extends BaseModel
{
    /**
     * En son live_events ID'sini döner
     */
    public function getMaxEventId(): int
    {
        $sql = "SELECT MAX(id) as max_id FROM live_events";
        $row = $this->queryOne($sql);
        return $row ? (int)($row->max_id ?? 0) : 0;
    }

    /**
     * Belirtilen ID'den sonraki olayları getirir
     */
    public function getEventsAfter(int $lastEventId, ?int $projectId = null, int $limit = 25): array
    {
        $params = [$lastEventId];
        $sql = "SELECT id, project_id, task_id, user_id, event_type, payload, created_at 
                FROM live_events 
                WHERE id > ? ";
        if ($projectId) {
            $sql .= "AND (project_id = ? OR project_id IS NULL) ";
            $params[] = $projectId;
        }
        $sql .= "ORDER BY id ASC LIMIT " . (int)$limit;

        return $this->query($sql, $params) ?: [];
    }

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

    /**
     * Canlı SSE olayını live_events tablosuna yazar
     */
    public function insertLiveEvent(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, string $jsonPayload): bool
    {
        $sql = "INSERT INTO live_events (event_type, project_id, task_id, user_id, payload, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        return $this->execute($sql, [$eventType, $projectId, $taskId, $userId, $jsonPayload]) > 0;
    }
}
