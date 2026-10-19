<?php
namespace Modules\GithubDevops\Controllers;

use Core\BaseController;
use Modules\GithubDevops\Models\GitDevopsModel;
use Modules\GithubDevops\Services\GitService;

class AjaxController extends BaseController
{
    private GitDevopsModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new GitDevopsModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getSettings(): void
    {
        $this->model->ensureGithubAndRollbackSchema();
        $creds = $this->model->getGithubCredentials();
        $this->respond(true, '', [
            'username' => $creds['username'],
            'token'    => $creds['token']
        ]);
    }

    public function saveSettings(): void
    {
        $this->model->ensureGithubAndRollbackSchema();
        $ghUser  = trim($_POST['github_username'] ?? '');
        $ghToken = trim($_POST['github_token'] ?? '');

        if (empty($ghUser) || empty($ghToken)) {
            $this->respond(false, __('GithubDevops::err_username_token_required'));
        }

        $this->model->updateGithubSettings($ghUser, $ghToken);
        $this->respond(true, __('GithubDevops::msg_github_connected'));
    }

    public function syncProject(): void
    {
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, __('GithubDevops::err_invalid_project_id'));
        }

        $res = GitService::initAndSyncProjectGithubRepo($projectId);
        if (!empty($res['success'])) {
            $this->respond(true, $res['message'] ?? '', $res);
        } else {
            $this->respond(false, $res['error'] ?? __('GithubDevops::err_github_sync_failed'), $res);
        }
    }

    public function rollbackTask(): void
    {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $mode   = trim($_POST['mode'] ?? 'safe');
        if ($taskId <= 0) {
            $this->respond(false, __('GithubDevops::err_invalid_task_id'));
        }

        $res = GitService::rollbackTaskChanges($taskId, $mode);
        if (!empty($res['success'])) {
            $this->respond(true, $res['message'] ?? '', $res);
        } else {
            $this->respond(false, $res['error'] ?? __('GithubDevops::err_rollback_failed'), $res);
        }
    }
}
