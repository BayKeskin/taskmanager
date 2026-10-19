<?php
namespace Modules\Mailer\Models;

use Core\BaseModel;

class MailModel extends BaseModel
{
    /**
     * Settings tablosundan SMTP yapılandırmasını getirir
     */
    public function getSmtpSettings(): ?array
    {
        $sql = "SELECT smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, smtp_from_email, smtp_from_name 
                FROM settings 
                WHERE id = 1 
                LIMIT 1";
        $row = $this->first($sql);
        if (!$row) {
            return null;
        }
        return is_object($row) ? get_object_vars($row) : (array)$row;
    }

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
