<?php
namespace Modules\Docs\Models;

use Core\BaseModel;

class DocModel extends BaseModel
{
    protected string $table = 'docs';

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

        return $this->query($sql, $params);
    }

    /**
     * ID'ye göre tekil döküman çeker
     */
    public function getDocById(int $id): ?object
    {
        $rows = $this->query("
            SELECT d.*, p.title as project_title, p.color as project_color, u.name as author_name
            FROM docs d
            LEFT JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ? LIMIT 1
        ", [$id]);

        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Yeni döküman ekler
     */
    public function createDoc(?int $projectId, string $title, string $icon, string $content, int $userId): int
    {
        $stmt = $this->getPdo()->prepare("INSERT INTO docs (project_id, title, icon, content, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$projectId, $title, $icon, $content, $userId]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Dökümanı günceller
     */
    public function updateDoc(int $id, string $title, string $content, string $icon, ?int $projectId): bool
    {
        $stmt = $this->getPdo()->prepare("UPDATE docs SET title = ?, content = ?, icon = ?, project_id = ? WHERE id = ?");
        return $stmt->execute([$title, $content, $icon, $projectId, $id]);
    }

    /**
     * Dökümanı siler
     */
    public function deleteDoc(int $id): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM docs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Dökümana ait ekleri çeker
     */
    public function getDocAttachments(int $docId): array
    {
        $sql = "
            SELECT da.*, u.name as user_name
            FROM doc_attachments da
            LEFT JOIN users u ON da.user_id = u.id
            WHERE da.doc_id = ?
            ORDER BY da.id DESC
        ";
        return $this->query($sql, [$docId]);
    }

    /**
     * Tekil ek kaydı çeker
     */
    public function getAttachmentById(int $attId): ?object
    {
        $rows = $this->query("SELECT * FROM doc_attachments WHERE id = ? LIMIT 1", [$attId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Dökümana yeni ek kaydı ekler
     */
    public function addAttachment(int $docId, int $userId, string $fileName, string $filePath, string $fileType, int $fileSize): int
    {
        $stmt = $this->getPdo()->prepare("
            INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$docId, $userId, $fileName, $filePath, $fileType, $fileSize]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Döküman ek kaydını siler
     */
    public function deleteAttachment(int $attId): bool
    {
        $stmt = $this->getPdo()->prepare("DELETE FROM doc_attachments WHERE id = ?");
        return $stmt->execute([$attId]);
    }
}
