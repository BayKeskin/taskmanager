<?php

require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/TaskModel.php';
require_once __DIR__ . '/UserModel.php';
require_once __DIR__ . '/ProjectModel.php';

class ReportModel extends Model
{
    private TaskModel $taskModel;
    private UserModel $userModel;
    private ProjectModel $projectModel;

    public function __construct(?Database $db = null)
    {
        parent::__construct($db);
        $this->taskModel = new TaskModel($this->db);
        $this->userModel = new UserModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
    }

    /**
     * Raporlar ekranı için tüm metrikleri toplar
     */
    public function getAnalyticsSummary(): array
    {
        $today = date('Y-m-d');
        $totalTasks = $this->taskModel->countTasksByStatus(null);
        $completedTasks = $this->taskModel->countTasksByStatus('completed');
        $inProgressTasks = $this->taskModel->countTasksByStatus('in_progress');
        $submittedTasks = $this->taskModel->countTasksByStatus('submitted');
        $modTasks = $this->taskModel->countTasksByStatus('modification_requested');

        $completionRate = $totalTasks > 0 ? (int)round(($completedTasks / $totalTasks) * 100) : 0;
        $overdueCount = $this->taskModel->countOverdueTasks($today);
        $teamStats = $this->userModel->getTeamPerformanceStats($today);
        $projectStats = $this->projectModel->getProjectStats();

        return [
            'totalTasks'       => $totalTasks,
            'completedTasks'   => $completedTasks,
            'inProgressTasks'  => $inProgressTasks,
            'submittedTasks'   => $submittedTasks,
            'modTasks'         => $modTasks,
            'completionRate'   => $completionRate,
            'overdueCount'     => $overdueCount,
            'teamStats'        => $teamStats,
            'projectStats'     => $projectStats
        ];
    }
}
