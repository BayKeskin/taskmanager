<?php
namespace Modules\Mailer\Models;

use Core\BaseModel;

class MailModel extends BaseModel
{
    /**
     * E-posta gönderim kuyruğuna yeni bildirim ekler
     */
    public function queueEmail(string $recipient, string $subject, string $body, ?int $taskId = null): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (?, 1, 'email_sent', ?, NOW())";
        $desc = "Email sent to {$recipient} with subject: {$subject}";
        return $this->execute($sql, [$taskId ?? 0, $desc]);
    }
}
