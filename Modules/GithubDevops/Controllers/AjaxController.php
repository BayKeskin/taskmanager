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

    /**
     * Projeye ait zaman tüneli & commit geçmişini çeker
     */
    public function getProjectTimeline(): void
    {
        $projectId = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
        if ($projectId <= 0) {
            $this->respond(false, __('GithubDevops::err_invalid_project_id'));
            return;
        }

        $res = GitService::getProjectTimeline($projectId);
        $this->json($res);
    }

    /**
     * Projeyi seçilen commit sürümüne geri alır
     */
    public function revertToVersion(): void
    {
        $projectId  = (int)($_POST['project_id'] ?? 0);
        $commitHash = trim($_POST['commit_hash'] ?? '');

        if ($projectId <= 0) {
            $this->respond(false, __('GithubDevops::err_invalid_project_id'));
            return;
        }

        if (empty($commitHash)) {
            $this->respond(false, __('GithubDevops::err_no_commit_hash'));
            return;
        }

        $userId = (int)($_SESSION['user']->id ?? 1);
        $res = GitService::rollbackToCommit($projectId, $commitHash, $userId);

        if (!empty($res['success'])) {
            $this->respond(true, $res['message'] ?? '', $res);
        } else {
            $this->respond(false, $res['error'] ?? __('GithubDevops::err_rollback_failed'), $res);
        }
    }

    public function saveGithubSettings(): void
    {
        $this->saveSettings();
    }

    public function syncProjectGithub(): void
    {
        $this->syncProject();
    }

    public function revertProjectVersion(): void
    {
        $this->revertToVersion();
    }
}
