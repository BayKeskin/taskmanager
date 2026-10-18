<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/TaskModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/SettingsModel.php';

class BoardController extends Controller
{
    private TaskModel $taskModel;
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->taskModel = new TaskModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
        $this->userModel = new UserModel($this->db);
        $this->settingsModel = new SettingsModel($this->db);
    }

    /**
     * Kanban panosu verilerini hazırlar
     */
    public function index(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        if (class_exists('Modules\\Board\\Controllers\\BoardController')) {
            $ctrl = new \Modules\Board\Controllers\BoardController();
            return $ctrl->index($activeProjectId, $currentUser);
        }

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

    /**
     * Kanban görevlerini UTF-8 BOM destekli Excel (.csv / .xlsx uyumlu) olarak dışa aktarır (TSK-1156)
     */
    public function exportExcel(?int $projectId = null, ?object $currentUser = null): void
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? null);
        if (!$currentUser) {
            header('Location: /taskmanager/login');
            exit;
        }

        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $projectId = (int)($currentUser->client_project_id ?? 0);
        }

        if (!$projectId || $projectId <= 0) {
            echo "Geçersiz veya seçilmemiş proje.";
            exit;
        }

        // HMVC Modülüne devret
        $exportController = new \Modules\ExcelExport\Controllers\ExcelExportController();
        $exportController->export($projectId);
    }
}

