<?php
namespace Modules\Docs\Controllers;

use Core\BaseController;
use Modules\Docs\Models\DocModel;

class AjaxController extends BaseController
{
    private DocModel $docModel;

    public function __construct()
    {
        parent::__construct();
        $this->docModel = new DocModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getDoc(): void
    {
        $docId = (int)($_GET['id'] ?? 0);
        if ($docId <= 0) {
            $this->respond(false, 'Geçersiz doküman ID.');
        }

        $doc = $this->db->row_query("
            SELECT d.*, p.title as project_title, p.color as project_color, u.name as author_name
            FROM docs d
            LEFT JOIN projects p ON d.project_id = p.id
            LEFT JOIN users u ON d.created_by = u.id
            WHERE d.id = ?
        ", [$docId]);

        if (!$doc) {
            $this->respond(false, 'Doküman bulunamadı.');
        }

        $attachments = $this->db->multi_record_query("
            SELECT da.*, u.name as user_name
            FROM doc_attachments da
            LEFT JOIN users u ON da.user_id = u.id
            WHERE da.doc_id = ?
            ORDER BY da.id DESC
        ", [$docId]) ?: [];

        $this->respond(true, '', ['doc' => $doc, 'attachments' => $attachments]);
    }

    public function createDoc(): void
    {
        $defaultDocTitle = __('Docs::untitled_document', [], 'Başlıksız Döküman');
        $title = trim($_POST['title'] ?? $defaultDocTitle);
        if (empty($title)) {
            $title = $defaultDocTitle;
        }
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $icon = trim($_POST['icon'] ?? '📄');

        $defaultDocContent = __('Docs::start_typing_document', [], 'Buraya döküman içeriğinizi yazmaya başlayın...');
        $initialContent = "<h2>{$title}</h2><p>{$defaultDocContent}</p>";

        $saved = $this->db->exec_query("INSERT INTO docs (project_id, title, icon, content, created_by) VALUES (?, ?, ?, ?, ?)", [
            $projectId, $title, $icon, $initialContent, $this->user->id
        ]);

        if ($saved) {
            $newId = (int)$this->db->last_insert_id();
            $this->respond(true, 'Doküman oluşturuldu!', ['doc_id' => $newId]);
        }
        $this->respond(false, 'Doküman oluşturulamadı.');
    }

    public function saveDoc(): void
    {
        $docId = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? ($_POST['name'] ?? ($_POST['subject'] ?? '')));
        $content = $_POST['content'] ?? ($_POST['body'] ?? ($_POST['description'] ?? ($_POST['text'] ?? ($_POST['markdown'] ?? ($_POST['document'] ?? ($_POST['html'] ?? ''))))));
        $icon = trim($_POST['icon'] ?? '📄');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if ($docId <= 0 || empty($title)) {
            $this->respond(false, 'Geçersiz parametreler.');
        }

        $updated = $this->db->exec_query("UPDATE docs SET title = ?, content = ?, icon = ?, project_id = ? WHERE id = ?", [
            $title, $content, $icon, $projectId, $docId
        ]);

        if ($updated) {
            $this->respond(true, 'Döküman başarıyla kaydedildi.');
        }
        $this->respond(false, 'Kayıt sırasında hata oluştu.');
    }

    public function deleteDoc(): void
    {
        $docId = (int)($_POST['id'] ?? 0);
        if ($docId <= 0) {
            $this->respond(false, 'Geçersiz doküman ID.');
        }
        $this->db->exec_query("DELETE FROM docs WHERE id = ?", [$docId]);
        $this->respond(true, 'Döküman silindi.');
    }

    public function uploadAttachment(): void
    {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId <= 0) {
            $this->respond(false, 'Geçersiz döküman ID.');
        }

        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            $this->respond(false, 'Yüklenecek dosya seçilmedi.');
        }

        $file = $_FILES['file'] ?? $_FILES['attachment'];
        $uploadResult = \Modules\Storage\Services\StorageService::upload($file, 'docs');

        if (!$uploadResult['success']) {
            $this->respond(false, $uploadResult['error'] ?? 'Dosya yüklenemedi.');
        }

        $this->db->exec_query("
            INSERT INTO doc_attachments (doc_id, user_id, file_name, file_path, file_type, file_size, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [
            $docId,
            $this->user->id,
            $uploadResult['file_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            $uploadResult['file_size']
        ]);
        $docAttId = (int)$this->db->last_insert_id();

        $docAttPayload = [
            'id'        => $docAttId,
            'doc_id'    => $docId,
            'file_name' => $uploadResult['file_name'],
            'file_path' => $uploadResult['file_path'],
            'file_size' => $uploadResult['file_size'],
            'file_type' => $uploadResult['file_type'],
            'is_image'  => $uploadResult['is_image'] ?? false,
            'created_at'=> date('Y-m-d H:i:s'),
            'user_name' => $this->user->name
        ];

        $this->respond(true, 'Döküman eki yüklendi!', [
            'data'       => $docAttPayload,
            'attachment' => $docAttPayload,
            ...$docAttPayload
        ]);
    }

    public function getAttachments(): void
    {
        $docId = (int)($_GET['doc_id'] ?? ($_POST['doc_id'] ?? 0));
        if ($docId <= 0) {
            $this->respond(false, 'Geçersiz döküman ID.');
        }

        $docAttachments = $this->db->multi_record_query("
            SELECT da.*, u.name as user_name
            FROM doc_attachments da
            LEFT JOIN users u ON da.user_id = u.id
            WHERE da.doc_id = ?
            ORDER BY da.id DESC
        ", [$docId]) ?: [];

        $this->respond(true, '', [
            'attachments' => $docAttachments,
            'data'        => $docAttachments
        ]);
    }

    public function deleteAttachment(): void
    {
        $docAttId = (int)($_POST['attachment_id'] ?? 0);
        if ($docAttId <= 0) {
            $this->respond(false, 'Geçersiz ek ID.');
        }

        $docAtt = $this->db->row_query("SELECT * FROM doc_attachments WHERE id = ? LIMIT 1", [$docAttId]);
        if (!$docAtt) {
            $this->respond(false, 'Ek dosya bulunamadı.');
        }

        \Modules\Storage\Services\StorageService::delete($docAtt->file_path);
        $this->db->exec_query("DELETE FROM doc_attachments WHERE id = ?", [$docAttId]);

        $this->respond(true, 'Döküman eki silindi!', [
            'attachment_id' => $docAttId,
            'data'          => ['attachment_id' => $docAttId]
        ]);
    }
}
