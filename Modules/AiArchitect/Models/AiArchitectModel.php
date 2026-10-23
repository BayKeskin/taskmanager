<?php
namespace Modules\AiArchitect\Models;

use Core\BaseModel;

class AiArchitectModel extends BaseModel
{
    /**
     * Projeye ait brief ve mimari ayrıştırma geçmişini kaydeder veya getirir
     */
    public function getProjectAiBriefs(int $projectId): array
    {
        $sql = "SELECT * FROM docs WHERE project_id = ? AND category = 'specification' ORDER BY id DESC";
        return $this->query($sql, [$projectId]);
    }

    /**
     * AI tarafından üretilen teknik şartnameyi döküman olarak kaydeder
     */
    public function saveSpecificationDoc(int $projectId, string $title, string $content, int $userId): int
    {
        $sql = "INSERT INTO docs (project_id, title, content, author_id, category, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'specification', NOW(), NOW())";
        return $this->execute($sql, [$projectId, $title, $content, $userId]);
    }

    /**
     * Proje detaylarını ve kurallarını çeker
     */
    public function getProjectDetails(int $projectId): ?object
    {
        $rows = $this->query("SELECT id, project_code, title, description, agent_rules, workspace_path FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        if (empty($rows)) {
            return null;
        }
        $project = (object)$rows[0];

        // Eğer projenin agent_rules alanı boşsa, workspace_path içindeki AGENTS.md dosyasını kontrol et
        if (empty(trim($project->agent_rules ?? '')) && !empty($project->workspace_path)) {
            $wsPath = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $project->workspace_path), DIRECTORY_SEPARATOR);
            $agentsMdPath = $wsPath . DIRECTORY_SEPARATOR . 'AGENTS.md';
            if (file_exists($agentsMdPath) && is_readable($agentsMdPath)) {
                $fileContent = @file_get_contents($agentsMdPath);
                if (!empty($fileContent)) {
                    $project->agent_rules = $fileContent;
                }
            }
        }

        return $project;
    }

    /**
     * AI ayarlarını çeker
     */
    public function getAiSettings(): ?object
    {
        $rows = $this->query("SELECT active_ai_provider, default_ai_model, gemini_api_key, openai_api_key, deepseek_api_key FROM settings LIMIT 1");
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * En yüksek task ID'sini bularak sıradaki kodu üretir
     */
    public function getNextTaskCode(): string
    {
        $rows = $this->query("SELECT MAX(id) as max_id FROM tasks");
        $lastId = !empty($rows) ? (int)$rows[0]->max_id : 0;
        return 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Yeni ana modül / Epic görevi ekler
     */
    public function insertEpicTask(array $data): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO tasks (
                project_id, task_code, title, description, tags, is_gated, priority,
                status, deadline, created_by, assigned_to, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $data['project_id'],
            $data['task_code'],
            $data['title'],
            $data['description'],
            $data['tags'],
            $data['is_gated'],
            $data['priority'],
            $data['deadline'],
            $data['created_by'],
            $data['assigned_to']
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Yeni alt görev / Subtask ekler
     */
    public function insertSubTask(array $data): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO tasks (
                project_id, parent_task_id, task_code, title, description, priority,
                status, deadline, created_by, assigned_to, tags, time_estimate, time_spent,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'assigned', ?, ?, ?, ?, ?, '0m', NOW(), NOW())
        ");
        $stmt->execute([
            $data['project_id'],
            $data['parent_task_id'],
            $data['task_code'],
            $data['title'],
            $data['description'],
            $data['priority'],
            $data['deadline'],
            $data['created_by'],
            $data['assigned_to'],
            $data['tags'],
            $data['time_estimate']
        ]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Göreve çalışan atar
     */
    public function assignUserToTask(int $taskId, int $userId): void
    {
        $stmt = $this->getPdo()->prepare("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)");
        $stmt->execute([$taskId, $userId]);
    }

    /**
     * Görev eki ekler
     */
    public function attachFileToTask(int $taskId, int $userId, array $fileInfo): void
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $taskId,
            $userId,
            $fileInfo['file_name'],
            $fileInfo['file_path'],
            $fileInfo['file_type'] ?? 'image/jpeg',
            (int)($fileInfo['file_size'] ?? 0)
        ]);
    }

    /**
     * Veritabanı transaction başlatır
     */
    public function beginTransaction(): bool
    {
        if (!$this->getPdo()->inTransaction()) {
            return $this->getPdo()->beginTransaction();
        }
        return true;
    }

    /**
     * Veritabanı transaction commit eder
     */
    public function commit(): bool
    {
        if ($this->getPdo()->inTransaction()) {
            return $this->getPdo()->commit();
        }
        return true;
    }

    /**
     * Veritabanı transaction geri alır
     */
    public function rollBack(): bool
    {
        if ($this->getPdo()->inTransaction()) {
            return $this->getPdo()->rollBack();
        }
        return true;
    }
}
