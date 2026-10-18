<?php
namespace Modules\Telegram\Models;

use Core\BaseModel;

class TelegramModel extends BaseModel
{
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
