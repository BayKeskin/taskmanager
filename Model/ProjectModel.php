<?php

require_once __DIR__ . '/Model.php';

class ProjectModel extends Model
{
    /**
     * ID'ye göre tekil proje çeker
     */
    public function getProjectById(int $id): ?object
    {
        $rows = $this->db->multi_record("projects", ["id"], [$id]);
        return $rows[0] ?? null;
    }

    /**
     * Duruma göre tüm projeleri çeker
     */
    public function getProjects(?string $status = null): array
    {
        if ($status !== null) {
            return $this->db->multi_record("projects", ["status"], [$status], "=", "title", "ASC") ?: [];
        }
        return $this->db->multi_record("projects", [], [], "", "id", "ASC") ?: [];
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
        return $this->db->multi_record_query($sql) ?: [];
    }

    /**
     * Koda göre tekil proje çeker
     */
    public function getProjectByCode(string $code): ?object
    {
        $rows = $this->db->multi_record("projects", ["project_code"], [$code]);
        return $rows[0] ?? null;
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

        $ok = $this->db->exec_query("INSERT INTO projects ({$colsSql}) VALUES ({$placeholdersSql})", $values);
        return $ok ? (int)$this->db->last_insert_id() : 0;
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

        return (bool)$this->db->exec_query("UPDATE projects SET {$setSql} WHERE id = ?", $values);
    }

    /**
     * Projenin kurallarını yerel AGENTS.md ve GEMINI.md dosyalarına senkronize eder
     */
    public function syncProjectRulesToFile(int $id): array
    {
        return Helper::syncProjectRulesToWorkspace($id);
    }
}

