<?php
namespace Modules\Team\Controllers;

use Core\BaseController;
use Modules\Team\Models\UserModel;
use ProjectModel;
use TaskModel;

class TeamController extends BaseController
{
    private UserModel $userModel;
    private ?ProjectModel $projectModel = null;
    private ?TaskModel $taskModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
        if (class_exists('ProjectModel')) {
            $this->projectModel = new ProjectModel();
        }
        if (class_exists('TaskModel')) {
            $this->taskModel = new TaskModel();
        }
    }

    /**
     * Ekip yönetimi verilerini hazırlar
     */
    public function index(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isAuthorized = ($currentUser->role === 'admin');

        $usersList = $isAuthorized ? $this->userModel->getAllUsers() : [];
        $allProjects = ($isAuthorized && $this->projectModel) ? $this->projectModel->getProjects('active') : [];

        $projectsMap = [];
        foreach ($allProjects as $p) {
            $pObj = (object)$p;
            $projectsMap[$pObj->id] = $pObj;
        }

        $userTaskCounts = [];
        foreach ($usersList as $u) {
            $uObj = (object)$u;
            $userTaskCounts[$uObj->id] = $this->taskModel ? $this->taskModel->countUserTasks($uObj->id) : 0;
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
