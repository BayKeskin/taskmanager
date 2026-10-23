<?php
namespace Modules\Projects\Controllers;

use Core\BaseController;
use Modules\Projects\Models\ProjectModel;

class AjaxController extends BaseController
{
    private ProjectModel $projectModel;

    public function __construct()
    {
        parent::__construct();
        $this->projectModel = new ProjectModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function createProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Projects::unauthorized_admin_only'));
        }

        $title = trim($_POST['title'] ?? '');
        $projectCode = strtoupper(trim($_POST['project_code'] ?? ''));
        $color = $_POST['color'] ?? '#7B68EE';
        $hourlyRate = isset($_POST['hourly_rate']) && is_numeric($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : 500.00;
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if (empty($title) || empty($projectCode)) {
            $this->respond(false, __('Projects::title_code_required'));
        }

        if ($this->projectModel->getProjectByCode($projectCode)) {
            $this->respond(false, __('Projects::project_code_exists'));
        }

        $newId = $this->projectModel->createProject([
            'project_code'          => $projectCode,
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'hourly_rate'           => $hourlyRate,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId,
            'created_by'            => $this->user->id
        ]);

        if ($newId > 0) {
            if (class_exists('Modules\Dispatcher\Services\DispatcherService')) {
                (new \Modules\Dispatcher\Services\DispatcherService())->syncWorkspaces();
            }
            if ($workspacePath && $agentRules) {
                \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($newId);
            }
            $this->respond(true, __('Projects::project_created_success'), ['project_id' => $newId]);
        } else {
            $this->respond(false, __('Projects::project_create_failed'));
        }
    }

    public function getProject(): void
    {
        $projectId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, __('Projects::invalid_project_id'));
        }

        $proj = $this->projectModel->getProjectById($projectId);
        if (!$proj) {
            $this->respond(false, __('Projects::project_not_found'));
        }

        if (empty($proj->agent_rules) && !empty($proj->workspace_path)) {
            $ws = rtrim($proj->workspace_path, '/\\');
            $agentsMd = $ws . DIRECTORY_SEPARATOR . 'AGENTS.md';
            if (file_exists($agentsMd)) {
                $content = @file_get_contents($agentsMd);
                if ($content && preg_match('/<!-- PROJECT_RULES_START -->(.*?)<!-- PROJECT_RULES_END -->/s', $content, $m)) {
                    $extracted = trim($m[1]);
                    if ($extracted) {
                        $proj->agent_rules = $extracted;
                        $this->projectModel->updateProject($projectId, ['agent_rules' => $extracted]);
                    }
                }
            }
        }

        $this->respond(true, __('Projects::project_info_retrieved'), (array)$proj);
    }

    public function updateProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Projects::unauthorized_edit'));
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $color = $_POST['color'] ?? '#7B68EE';
        $hourlyRate = isset($_POST['hourly_rate']) && is_numeric($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : 500.00;
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if ($projectId <= 0 || empty($title)) {
            $this->respond(false, __('Projects::invalid_project_info'));
        }

        $updated = $this->projectModel->updateProject($projectId, [
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'hourly_rate'           => $hourlyRate,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId
        ]);

        if ($updated) {
            if (class_exists('Modules\Dispatcher\Services\DispatcherService')) {
                (new \Modules\Dispatcher\Services\DispatcherService())->syncWorkspaces();
            }
            if ($workspacePath && $agentRules) {
                \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($projectId);
            }
            $this->respond(true, __('Projects::project_update_success'));
        } else {
            $this->respond(false, __('Projects::project_update_failed'));
        }
    }

    public function syncProjectRules(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Projects::unauthorized_sync'));
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, __('Projects::invalid_project_id'));
        }

        if (isset($_POST['agent_rules'])) {
            $agentRules = trim($_POST['agent_rules']) ?: null;
            $this->projectModel->updateProject($projectId, ['agent_rules' => $agentRules]);
        }

        $syncRes = \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($projectId);
        if (!empty($syncRes['success'])) {
            $this->respond(true, __('Projects::rules_synced_success'), $syncRes);
        } else {
            $this->respond(false, $syncRes['error'] ?? __('Projects::rules_sync_failed'));
        }
    }

    public function refineProjectRules(): void
    {
        if ($this->user->role !== 'admin' && $this->user->role !== 'employee') {
            $this->respond(false, __('Projects::unauthorized_access'));
        }

        $rawRules = trim($_POST['raw_rules'] ?? '');
        $projectCode = trim($_POST['project_code'] ?? '');
        $projectTitle = trim($_POST['project_title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($projectId > 0 && (empty($projectCode) || empty($projectTitle))) {
            $proj = $this->projectModel->getProjectById($projectId);
            if ($proj) {
                $projectCode = $projectCode ?: ($proj->project_code ?? '');
                $projectTitle = $projectTitle ?: ($proj->title ?? '');
            }
        }

        $res = (new \Modules\AiArchitect\Services\GeminiClient())->refineRulesWithAi($rawRules, $projectCode, $projectTitle);
        if (!empty($res['success'])) {
            $this->respond(true, __('Projects::rules_refined_success'), $res);
        } else {
            $this->respond(false, $res['error'] ?? __('Projects::rules_refine_error'));
        }
    }

    public function testProjectWebhook(): void
    {
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, __('Projects::invalid_project_id'));
        }

        $res = \Modules\RestApi\Services\WebhookService::triggerProjectWebhook($projectId, 'project.webhook_test', [
            'message'    => __('Projects::webhook_test_pin_msg'),
            'test_time'  => date('Y-m-d H:i:s'),
            'user'       => $this->user->name
        ]);

        if ($res['sent']) {
            $this->respond(true, __('Projects::webhook_sent_success'), $res);
        } else {
            $this->respond(false, __('Projects::webhook_delivery_failed'), $res);
        }
    }

    public function toggleProjectStatus(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Projects::unauthorized_access'));
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'completed';

        if ($projectId <= 0) {
            $this->respond(false, __('Projects::invalid_project_id'));
        }

        $this->projectModel->updateProjectStatus($projectId, $newStatus);
        
        $msg = ($newStatus === 'completed' || $newStatus === 'archived') 
            ? __('Projects::project_moved_archive')
            : __('Projects::project_restored_active');
        $this->respond(true, $msg);
    }

    public function deleteProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Projects::unauthorized_access'));
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, __('Projects::invalid_project_id'));
        }

        $success = \Modules\Projects\Services\ProjectService::purgeProject($projectId);
        if ($success) {
            $this->respond(true, __('Projects::project_purged_success'));
        }
        $this->respond(false, __('Projects::project_purge_failed'));
    }
}
