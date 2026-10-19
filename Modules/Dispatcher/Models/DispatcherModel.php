<?php
namespace Modules\Dispatcher\Models;

use Core\BaseModel;

class DispatcherModel extends BaseModel
{
    /**
     * Otonom kuyrukta bekleyen AI görevlerini çeker
     */
    public function getQueuedAiTasks(?int $projectId = null): array
    {
        $sql = "SELECT t.id, t.project_id, t.task_code, t.title, t.tags, t.status, t.priority,
                       p.project_code, p.title as project_title, p.workspace_path 
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE t.status = 'assigned'
                  AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = t.id)
                  AND (
                      LOWER(t.tags) LIKE '%ai%' 
                      OR LOWER(t.tags) LIKE '%otonom%' 
                      OR LOWER(t.tags) LIKE '%agent%'
                      OR LOWER(t.tags) LIKE '%yapay zeka%'
                      OR LOWER(t.tags) LIKE '%antigravity%'
                  )";
        $params = [];
        if ($projectId) {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY t.id ASC";
        return $this->query($sql, $params);
    }

    /**
     * Çalışma alanı tanımlı projeleri çeker
     */
    public function getProjectsWithWorkspace(): array
    {
        return $this->query("SELECT project_code, workspace_path FROM projects WHERE workspace_path IS NOT NULL AND workspace_path != ''");
    }

    /**
     * Projenin agent_rules alanını çeker
     */
    public function getProjectAgentRules(int $projectId): string
    {
        $rows = $this->query("SELECT agent_rules FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        return !empty($rows[0]['agent_rules']) ? (string)$rows[0]['agent_rules'] : '';
    }

    /**
     * Projenin ajan konuşma ID'sini (conversation_id) günceller
     */
    public function updateAgentConversationId(int $projectId, string $conversationId): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE projects SET agent_conversation_id = ? WHERE id = ?");
        return $stmt->execute([$conversationId, $projectId]);
    }

    /**
     * SQLite3 dosyasından anahtar veya tek değer okur
     */
    public function querySqliteSingleValue(\SQLite3 $sqliteDb, string $table, string $column, string $whereCol, string $whereVal): ?string
    {
        $stmt = $sqliteDb->prepare("SELECT `{$column}` FROM `{$table}` WHERE `{$whereCol}` = :val LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bindValue(':val', $whereVal, SQLITE3_TEXT);
        $result = $stmt->execute();
        if (!$result) {
            return null;
        }
        $row = $result->fetchArray(SQLITE3_NUM);
        return isset($row[0]) ? (string)$row[0] : null;
    }
}
