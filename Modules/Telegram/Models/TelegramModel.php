<?php
namespace Modules\Telegram\Models;

use Core\BaseModel;

class TelegramModel extends BaseModel
{
    /**
     * Telegram ayarlarını döner
     */
    public function getTelegramSettings(): ?array
    {
        $sql = "SELECT telegram_bot_token, telegram_chat_id, telegram_notifications_enabled FROM settings WHERE id = 1";
        return $this->row($sql);
    }

    /**
     * Bekleyen ve aktif görevleri listeler
     */
    public function getActiveTasks(int $limit = 10): array
    {
        $sql = "SELECT id, task_code, title, status, priority, deadline 
                FROM tasks 
                WHERE status IN ('assigned', 'in_progress', 'modification_requested')
                ORDER BY id DESC LIMIT " . (int)$limit;
        return $this->query($sql) ?: [];
    }

    /**
     * En son görev ID'sini döner
     */
    public function getLastTaskId(): int
    {
        $sql = "SELECT MAX(id) as max_id FROM tasks";
        $row = $this->row($sql);
        return (int)($row['max_id'] ?? 0);
    }

    /**
     * Yeni görev ekler
     */
    public function createTask(array $data): int
    {
        $sql = "INSERT INTO tasks (project_id, task_code, title, description, priority, status, created_by, assigned_to, deadline, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        return $this->execute($sql, [
            $data['project_id'],
            $data['task_code'],
            $data['title'],
            $data['description'],
            $data['priority'] ?? 'normal',
            $data['status'] ?? 'assigned',
            $data['created_by'] ?? 1,
            $data['assigned_to'] ?? 1,
            $data['deadline'] ?? date('Y-m-d', strtotime('+3 days')),
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Görev kodu ile görevi bulur
     */
    public function findByTaskCode(string $taskCode): ?array
    {
        $sql = "SELECT * FROM tasks WHERE task_code = ? LIMIT 1";
        return $this->row($sql, [$taskCode]);
    }

    /**
     * Görev durumunu günceller
     */
    public function updateTaskStatus(int $id, string $newStatus): bool
    {
        $sql = "UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->execute($sql, [$newStatus, $id]) > 0;
    }

    /**
     * Aktivite günlüğü ekler
     */
    public function logActivity(int $taskId, int $userId, string $actionType, ?string $oldVal, ?string $newVal, string $comment): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        return $this->execute($sql, [$taskId, $userId, $actionType, $oldVal, $newVal, $comment]);
    }

    /**
     * Telegram bildirim günlüğünü kaydeder
     */
    public function logTelegramAlert(string $chatId, string $message): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, 1, 'telegram_alert', ?, NOW())";
        $desc = "Telegram alert sent to {$chatId}: " . substr($message, 0, 120);
        return $this->execute($sql, [$desc]);
    }
}
