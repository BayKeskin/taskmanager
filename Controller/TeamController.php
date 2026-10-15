<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/TaskModel.php';

class TeamController extends Controller
{
    private UserModel $userModel;
    private ProjectModel $projectModel;
    private TaskModel $taskModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->userModel = new UserModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
        $this->taskModel = new TaskModel($this->db);
    }

    /**
     * Ekip yönetimi verilerini hazırlar
     */
    public function index(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isAuthorized = ($currentUser->role === 'admin');

        $usersList = $isAuthorized ? $this->userModel->getAllUsers() : [];
        $allProjects = $isAuthorized ? $this->projectModel->getProjects('active') : [];

        $projectsMap = [];
        foreach ($allProjects as $p) {
            $projectsMap[$p->id] = $p;
        }

        // Kullanıcıların görev sayılarını hesapla
        $userTaskCounts = [];
        foreach ($usersList as $u) {
            $userTaskCounts[$u->id] = $this->taskModel->countUserTasks($u->id);
        }

        return [
            'currentUser'    => $currentUser,
            'isAuthorized'   => $isAuthorized,
            'usersList'      => $usersList,
            'allProjects'    => $allProjects,
            'projectsMap'    => $projectsMap,
            'userTaskCounts' => $userTaskCounts
        ];
    }
}
