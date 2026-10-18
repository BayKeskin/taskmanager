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
}
