<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/SettingsModel.php';
require_once __DIR__ . '/../Model/TaskModel.php';

class LayoutController extends Controller
{
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;
    private TaskModel $taskModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->projectModel = new ProjectModel($this->db);
        $this->userModel = new UserModel($this->db);
        $this->settingsModel = new SettingsModel($this->db);
        $this->taskModel = new TaskModel($this->db);
    }

    /**
     * Sol menü (Sidebar) için gerekli tüm verileri ve sayaçları döner
     */
    public function getSidebarData(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $projectsList = $this->projectModel->getProjects();

        // Okunmamış DM sayısı
        $unreadDmCount = (int)$this->db->single_query("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0", [$currentUser->id]);

        $totalTasksCount = (int)($this->db->count_record("tasks") ?: 0);
        $totalDocsCount = (int)($this->db->count_record("docs") ?: 0);
        $totalUsersCount = (int)($this->db->count_record("users") ?: 0);

        // Proje bazlı görev sayıları
        $projectTaskCounts = $this->db->pair_query("SELECT project_id, COUNT(*) as cnt FROM tasks GROUP BY project_id") ?: [];

        $clientProj = null;
        if (($currentUser->role ?? '') === 'client' && !empty($currentUser->client_project_id)) {
            $clientProj = $this->projectModel->getProjectById((int)$currentUser->client_project_id);
        }

        return [
            'currentUser'        => $currentUser,
            'projectsList'       => $projectsList,
            'unreadDmCount'      => $unreadDmCount,
            'totalTasksCount'    => $totalTasksCount,
            'totalDocsCount'     => $totalDocsCount,
            'totalUsersCount'    => $totalUsersCount,
            'projectTaskCounts'  => $projectTaskCounts,
            'clientProj'         => $clientProj
        ];
    }

    /**
     * Ortak modallar (modalNewTask, modalNewProject vb.) için verileri döner
     */
    public function getModalsData(): array
    {
        $allUsers = $this->userModel->getAllUsers();
        $allProjects = $this->projectModel->getProjects();
        $sysSettings = $this->settingsModel->getSettings();

        return [
            'allUsers'    => $allUsers,
            'allProjects' => $allProjects,
            'sysSettings' => $sysSettings
        ];
    }
}
