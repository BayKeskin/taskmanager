<?php

require_once __DIR__ . '/Model.php';

class DocModel extends Model
{
    /**
     * Dökümanları proje ve yazar detaylarıyla birlikte listeler
     */
    public function getDocsList(?int $projectId = null): array
    {
        $sql = "
            SELECT d.*, p.title as project_title, p.color as project_color, u.name as author_name
            FROM docs d
            LEFT JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON d.created_by = u.id
        ";
        $params = [];
        if ($projectId) {
            $sql .= " WHERE d.project_id = ? ";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY d.id ASC ";

        return $this->db->multi_record_query($sql, $params) ?: [];
    }

    /**
     * ID'ye göre tekil döküman çeker
     */
    public function getDocById(int $id): ?object
    {
        $rows = $this->db->multi_record_query("
            SELECT d.*, p.title as project_title, p.color as project_color, u.name as author_name
            FROM docs d
            LEFT JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ? LIMIT 1
        ", [$id]);

        return $rows[0] ?? null;
    }
}
