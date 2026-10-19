<?php
namespace Modules\Board\Controllers;

use Core\BaseController;
use Modules\Board\Models\TaskModel;
use Modules\Projects\Models\ProjectModel;
use Modules\Team\Models\UserModel;
use Modules\Settings\Models\SettingsModel;

class BoardController extends BaseController
{
    private TaskModel $taskModel;
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;

    public function __construct()
    {
        parent::__construct();
        $this->taskModel = new TaskModel();
        $this->projectModel = new ProjectModel();
        $this->userModel = new UserModel();
        $this->settingsModel = new SettingsModel();
    }

    /**
     * Kanban panosu verilerini hazırlar
     */
    public function index(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $projectInfo = $activeProjectId ? $this->projectModel->getProjectById($activeProjectId) : null;
        $sysSettings = $this->settingsModel->getSettings();

        $employeeCanCreateTasks = (int)($sysSettings->employee_can_create_tasks ?? 1);
        $employeeCanAiTasks = (int)($sysSettings->employee_can_ai_tasks ?? 0);
        $canCreateTask = ($currentUser->role === 'admin') || (!$isClient && $employeeCanCreateTasks);
        $canUseAiTasks = ($currentUser->role === 'admin') || (!$isClient && $employeeCanAiTasks);

        $allUsers = $this->userModel->getUsersForFilter();
        $allTasks = $this->taskModel->getTasksForBoard($activeProjectId);
        $assigneesMap = $this->taskModel->getAssigneesMap();
        $attachmentCounts = $this->taskModel->getAttachmentCounts();

        // JS Client-Side i18n & Context köprüsü (Inline script bloğu yerine AssetManager üzerinden)
        \Core\AssetManager::localizeScript('board-js', 'BOARD_CONTEXT', [
            'projectId' => $activeProjectId ? (string)$activeProjectId : null,
            'userEmail' => $currentUser->email ?? ''
        ]);
        \Core\AssetManager::localizeScript('board-js', 'BOARD_I18N', [
            'approve_confirm' => __('Board::client_approve_confirm'),
            'approve_success' => __('Board::client_approve_success'),
            'request_prompt'  => __('Board::client_request_reason_prompt'),
            'request_success' => __('Board::client_request_success'),
            'analyzing'       => __('Board::ai_standup_analyzing'),
            'period'          => __('Board::ai_standup_period_label'),
            'last_24h'        => __('Board::ai_standup_last_24h'),
            'comp_stat'       => __('Board::ai_standup_completed_stat'),
            'in_prog_stat'    => __('Board::ai_standup_in_progress_stat'),
            'delayed_stat'    => __('Board::ai_standup_delayed_stat'),
            'email_prompt'    => __('Board::ai_standup_email_prompt'),
            'email_success'   => __('Board::ai_standup_email_success'),
            'err_fetch'       => __('Board::ai_standup_err_fetch'),
            'err_conn'        => __('Board::ai_standup_err_conn'),
            'server_error'    => __('Board::server_error'),
            'error_prefix'    => __('Board::error_prefix')
        ]);

        return [
            'currentUser'            => $currentUser,
            'isClient'               => $isClient,
            'activeProjectId'        => $activeProjectId,
            'projectInfo'            => $projectInfo,
            'sysSettings'            => $sysSettings,
            'canCreateTask'          => $canCreateTask,
            'canUseAiTasks'          => $canUseAiTasks,
            'allUsers'               => $allUsers,
            'allTasks'               => $allTasks,
            'assigneesMap'           => $assigneesMap,
            'attachmentCounts'       => $attachmentCounts,
        ];
    }
}
