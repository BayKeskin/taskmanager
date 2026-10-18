<?php
namespace Modules\SSE\Services;

use Database;

/**
 * SseBroadcaster - Gerçek zamanlı SSE olaylarını live_events tablosuna yazar ve yayınlar.
 */
class SseBroadcaster
{
    public static function broadcast(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, array $payload): bool
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once dirname(__DIR__, 3) . '/Model/DataLayer.php';
                $db = new Database();
            }
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->exec_query("
                INSERT INTO live_events (event_type, project_id, task_id, user_id, payload, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$eventType, $projectId, $taskId, $userId, $jsonPayload]);
            return true;
        } catch (\Throwable $e) {
            error_log("SseBroadcaster error: " . $e->getMessage());
            return false;
        }
    }
}
