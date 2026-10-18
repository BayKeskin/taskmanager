<?php
namespace Modules\GithubDevops\Controllers;

use Core\BaseController;

class AjaxController extends BaseController
{
    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function saveSettings(): void
    {
        \Modules\GithubDevops\Services\GitService::ensureGithubAndRollbackSchema();
        $ghUser  = trim($_POST['github_username'] ?? '');
        $ghToken = trim($_POST['github_token'] ?? '');

        if (empty($ghUser) || empty($ghToken)) {
            $this->respond(false, 'GitHub Kullanıcı Adı ve Personal Access Token (PAT) zorunludur.');
        }

        $this->db->exec_query("UPDATE settings SET github_username = ?, github_token = ? WHERE id = 1", [$ghUser, $ghToken]);
        $this->respond(true, 'GitHub hesabınız başarıyla bağlandı! Artık projeleriniz güvenle GitHub ile senkronize edilecek.');
    }

    public function syncProject(): void
    {
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        $res = \Modules\GithubDevops\Services\GitService::initAndSyncProjectGithubRepo($projectId);
        if (!empty($res['success'])) {
            $this->respond(true, $res['message'], $res);
        } else {
            $this->respond(false, $res['error'] ?? 'GitHub senkronizasyonu başarısız oldu.', $res);
        }
    }

    public function rollbackTask(): void
    {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $mode   = trim($_POST['mode'] ?? 'safe');
        if ($taskId <= 0) {
            $this->respond(false, 'Geçersiz görev ID.');
        }

        $res = \Modules\GithubDevops\Services\GitService::rollbackTaskChanges($taskId, $mode);
        if (!empty($res['success'])) {
            $this->respond(true, $res['message'], $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Geri alma işlemi yapılamadı.', $res);
        }
    }
}
