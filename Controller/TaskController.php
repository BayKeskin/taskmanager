<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/TaskModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/UserModel.php';

class TaskController extends Controller
{
    private TaskModel $taskModel;
    private ProjectModel $projectModel;
    private UserModel $userModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->taskModel = new TaskModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Görevler liste görünümü verilerini hazırlar
     */
    public function listIndex(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $projectInfo = $activeProjectId ? $this->projectModel->getProjectById($activeProjectId) : null;
        $allUsers = $this->userModel->getUsersForFilter();
        $allTasks = $this->taskModel->getTasksForList($activeProjectId);
        $assigneesMap = $this->taskModel->getAssigneesMap();
        $subtaskMap = $this->taskModel->getSubtaskMap();

        return [
            'currentUser'     => $currentUser,
            'activeProjectId' => $activeProjectId,
            'projectInfo'     => $projectInfo,
            'allUsers'        => $allUsers,
            'allTasks'        => $allTasks,
            'assigneesMap'    => $assigneesMap,
            'subtaskMap'      => $subtaskMap
        ];
    }
}
