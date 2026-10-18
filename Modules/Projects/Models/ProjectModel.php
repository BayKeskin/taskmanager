<?php
namespace Modules\Projects\Models;

use Core\BaseModel;
use Helper;

class ProjectModel extends BaseModel
{
    protected string $table = 'projects';

    /**
     * ID'ye göre tekil proje çeker
     */
    public function getProjectById(int $id): ?object
    {
        $rows = $this->query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Duruma göre tüm projeleri çeker
     */
    public function getProjects(?string $status = null): array
    {
        if ($status !== null) {
            return $this->query("SELECT * FROM projects WHERE status = ? ORDER BY title ASC", [$status]);
        }
        return $this->query("SELECT * FROM projects ORDER BY id ASC");
    }

    /**
     * Proje bazlı görev istatistiklerini hesaplar
     */
    public function getProjectStats(): array
    {
        $sql = "
            SELECT p.id, p.title, p.project_code, p.color,
                   COUNT(t.id) as total_tasks,
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM projects p
            LEFT JOIN tasks t ON t.project_id = p.id AND t.parent_task_id IS NULL
            GROUP BY p.id, p.title, p.project_code, p.color
            ORDER BY p.id ASC
        ";
        return $this->query($sql);
    }

    /**
     * Koda göre tekil proje çeker
     */
    public function getProjectByCode(string $code): ?object
    {
        $rows = $this->query("SELECT * FROM projects WHERE project_code = ? LIMIT 1", [$code]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Yeni proje oluşturur
     */
    public function createProject(array $data): int
    {
        $fields = ['project_code', 'title', 'description', 'color', 'hourly_rate', 'workspace_path', 'agent_rules', 'webhook_url', 'agent_conversation_id', 'created_by'];
        $cols = [];
        $placeholders = [];
        $values = [];

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = $f;
                $placeholders[] = '?';
                $values[] = $data[$f];
            }
        }

        $colsSql = implode(', ', $cols) . ', created_at, updated_at';
        $placeholdersSql = implode(', ', $placeholders) . ', NOW(), NOW()';

        $stmt = $this->getPdo()->prepare("INSERT INTO projects ({$colsSql}) VALUES ({$placeholdersSql})");
        $ok = $stmt->execute($values);
        return $ok ? (int)$this->getPdo()->lastInsertId() : 0;
    }

    /**
     * Projeyi günceller
     */
    public function updateProject(int $id, array $data): bool
    {
        $fields = ['title', 'description', 'color', 'hourly_rate', 'workspace_path', 'agent_rules', 'webhook_url', 'agent_conversation_id', 'status'];
        $setClauses = [];
        $values = [];

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $setClauses[] = "{$f} = ?";
                $values[] = $data[$f];
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = "updated_at = NOW()";
        $setSql = implode(', ', $setClauses);
        $values[] = $id;

        $stmt = $this->getPdo()->prepare("UPDATE projects SET {$setSql} WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * Projenin kurallarını yerel AGENTS.md ve GEMINI.md dosyalarına senkronize eder
     */
    public function syncProjectRulesToFile(int $id): array
    {
        if (class_exists('Helper') && method_exists('Helper', 'syncProjectRulesToWorkspace')) {
            return Helper::syncProjectRulesToWorkspace($id);
        }
        return ['success' => false, 'error' => 'Helper not found'];
    }
}
