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
            $this->respond(false, __('Docs::docs_invalid_id'));
            return;
        }

        $doc = $this->docModel->getDocById($docId);
        if (!$doc) {
            $this->respond(false, __('Docs::docs_not_found'));
            return;
        }

        $attachments = $this->docModel->getDocAttachments($docId);

        $this->respond(true, '', ['doc' => $doc, 'attachments' => $attachments]);
    }

    public function createDoc(): void
    {
        $defaultDocTitle = __('Docs::untitled_document');
        $title = trim($_POST['title'] ?? $defaultDocTitle);
        if (empty($title)) {
            $title = $defaultDocTitle;
        }
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $icon = trim($_POST['icon'] ?? '📄');

        $defaultDocContent = __('Docs::start_typing_document');
        $initialContent = "<h2>{$title}</h2><p>{$defaultDocContent}</p>";

        $newId = $this->docModel->createDoc($projectId, $title, $icon, $initialContent, (int)$this->user->id);

        if ($newId > 0) {
            $this->respond(true, __('Docs::docs_created'), ['doc_id' => $newId]);
            return;
        }
        $this->respond(false, __('Docs::docs_create_error'));
    }

    public function saveDoc(): void
    {
        $docId = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? ($_POST['name'] ?? ($_POST['subject'] ?? '')));
        $content = $_POST['content'] ?? ($_POST['body'] ?? ($_POST['description'] ?? ($_POST['text'] ?? ($_POST['markdown'] ?? ($_POST['document'] ?? ($_POST['html'] ?? ''))))));
        $icon = trim($_POST['icon'] ?? '📄');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

        if ($docId <= 0 || empty($title)) {
            $this->respond(false, __('Docs::docs_invalid_params'));
            return;
        }

        $updated = $this->docModel->updateDoc($docId, $title, $content, $icon, $projectId);

        if ($updated) {
            $this->respond(true, __('Docs::doc_saved'));
            return;
        }
        $this->respond(false, __('Docs::docs_save_error'));
    }

    public function deleteDoc(): void
    {
        $docId = (int)($_POST['id'] ?? 0);
        if ($docId <= 0) {
            $this->respond(false, __('Docs::docs_invalid_id'));
            return;
        }
        $this->docModel->deleteDoc($docId);
        $this->respond(true, __('Docs::doc_deleted'));
    }

    public function uploadAttachment(): void
    {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId <= 0) {
            $this->respond(false, __('Docs::docs_invalid_id'));
            return;
        }

        if (empty($_FILES['file']) && empty($_FILES['attachment'])) {
            $this->respond(false, __('Docs::docs_no_file_selected'));
            return;
        }

        $file = $_FILES['file'] ?? $_FILES['attachment'];
        $uploadResult = \Modules\Storage\Services\StorageService::upload($file, 'docs');

        if (!$uploadResult['success']) {
            $errMsg = $uploadResult['error'] ?? __('Docs::docs_upload_error');
            $this->respond(false, $errMsg);
            return;
        }

        $docAttId = $this->docModel->addAttachment(
            $docId,
            (int)$this->user->id,
            $uploadResult['file_name'],
            $uploadResult['file_path'],
            $uploadResult['file_type'],
            (int)$uploadResult['file_size']
        );

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

        $this->respond(true, __('Docs::docs_att_uploaded'), [
            'data'       => $docAttPayload,
            'attachment' => $docAttPayload,
            ...$docAttPayload
        ]);
    }

    public function getAttachments(): void
    {
        $docId = (int)($_GET['doc_id'] ?? ($_POST['doc_id'] ?? 0));
        if ($docId <= 0) {
            $this->respond(false, __('Docs::docs_invalid_id'));
            return;
        }

        $docAttachments = $this->docModel->getDocAttachments($docId);

        $this->respond(true, '', [
            'attachments' => $docAttachments,
            'data'        => $docAttachments
        ]);
    }

    public function deleteAttachment(): void
    {
        $docAttId = (int)($_POST['attachment_id'] ?? 0);
        if ($docAttId <= 0) {
            $this->respond(false, __('Docs::docs_invalid_att_id'));
            return;
        }

        $docAtt = $this->docModel->getAttachmentById($docAttId);
        if (!$docAtt) {
            $this->respond(false, __('Docs::docs_att_not_found'));
            return;
        }

        \Modules\Storage\Services\StorageService::delete($docAtt->file_path);
        $this->docModel->deleteAttachment($docAttId);

        $this->respond(true, __('Docs::docs_att_deleted'), [
            'attachment_id' => $docAttId,
            'data'          => ['attachment_id' => $docAttId]
        ]);
    }

    public function uploadDocAttachment(): void
    {
        $this->uploadAttachment();
    }

    public function getDocAttachments(): void
    {
        $this->getAttachments();
    }

    public function deleteDocAttachment(): void
    {
        $this->deleteAttachment();
    }
}
