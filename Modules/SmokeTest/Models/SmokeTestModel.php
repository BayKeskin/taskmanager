<?php
namespace Modules\SmokeTest\Models;

use Core\BaseModel;

class SmokeTestModel extends BaseModel
{
    /**
     * Otonom smoke test sonucunu denetim günlüğüne yazar
     */
    public function logTestRun(string $suiteName, bool $isSuccess, string $summary): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, 1, 'smoke_test', ?, NOW())";
        $status = $isSuccess ? 'PASS' : 'FAIL';
        $desc = "SmokeTest [{$suiteName}] -> {$status}: {$summary}";
        return (int)$this->db->exec_query($sql, [$desc]);
    }

    /**
     * Tablo varlığını sorgular
     */
    public function checkTableExists(string $tableName): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $res = $this->query("SHOW TABLES LIKE '{$safeName}'");
        return !empty($res);
    }

    /**
     * İlk müşteri kullanıcısını getirir
     */
    public function getClientUser(): ?object
    {
        $rows = $this->query("SELECT * FROM users WHERE role = 'client' LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Projeye ait görev listesini getirir
     */
    public function getTasksByProjectId(int $projectId): array
    {
        return $this->query("SELECT id FROM tasks WHERE project_id = ?", [$projectId]) ?: [];
    }

    /**
     * Test sohbet mesajı ekler
     */
    public function insertChatMessage(int $userId, string $message): int
    {
        return (int)$this->db->insert_return_count("chat_messages", ["user_id", "message"], [$userId, $message]);
    }

    /**
     * Sohbet mesajını ID ile sorgular
     */
    public function getChatMessageById(int $messageId): ?object
    {
        $rows = $this->query("SELECT * FROM chat_messages WHERE id = ?", [$messageId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }
}
