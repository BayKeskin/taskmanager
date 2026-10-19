<?php
namespace Modules\SSE\Services;

use Modules\SSE\Models\SseEventModel;

/**
 * SseBroadcaster - Gerçek zamanlı SSE olaylarını live_events tablosuna yazar ve yayınlar.
 */
class SseBroadcaster
{
    public static function broadcast(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, array $payload): bool
    {
        try {
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $model = new SseEventModel();
            return $model->insertLiveEvent($eventType, $projectId, $taskId, $userId, $jsonPayload);
        } catch (\Throwable $e) {
            error_log("SseBroadcaster error: " . $e->getMessage());
            return false;
        }
    }
}
