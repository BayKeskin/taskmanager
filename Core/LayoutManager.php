<?php
namespace Core;

use Modules\Projects\Models\ProjectModel;
use Modules\Team\Models\UserModel;
use Modules\Settings\Models\SettingsModel;

/**
 * LayoutManager - Çekirdek Sayfa Düzeni ve Ortak Arayüz Servisi
 * Header, Sidebar ve Modallar için gerekli ortak verileri ilgili HMVC modüllerinden toplar.
 */
class LayoutManager
{
    private static ?LayoutManager $instance = null;
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;

    public function __construct()
    {
        $this->projectModel = new ProjectModel();
        $this->userModel = new UserModel();
        $this->settingsModel = new SettingsModel();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sol menü (Sidebar) için gerekli verileri ve sayaçları döner
     */
    public function getSidebarData(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $projectsList = $this->projectModel->getProjects();
        $db = $GLOBALS['db'] ?? null;

        $unreadDmCount = 0;
        $totalTasksCount = 0;
        $totalDocsCount = 0;
        $totalUsersCount = 0;
        $projectTaskCounts = [];

        if ($db) {
            try {
                $unreadDmCount = (int)$db->single_query("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0", [$currentUser->id]);
                $totalTasksCount = (int)($db->count_record("tasks") ?: 0);
                $totalDocsCount = (int)($db->count_record("docs") ?: 0);
                $totalUsersCount = (int)($db->count_record("users") ?: 0);
                $projectTaskCounts = $db->pair_query("SELECT project_id, COUNT(*) as cnt FROM tasks GROUP BY project_id") ?: [];
            } catch (\Throwable $e) {}
        }

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
        return [
            'allUsers'    => $this->userModel->getAllUsers(),
            'allProjects' => $this->projectModel->getProjects(),
            'sysSettings' => $this->settingsModel->getSettings()
        ];
    }
}
