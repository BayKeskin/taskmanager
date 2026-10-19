<?php
namespace Modules\Reports\Models;

use Core\BaseModel;
use TaskModel;
use UserModel;
use ProjectModel;

class ReportModel extends BaseModel
{
    private ?TaskModel $taskModel = null;
    private ?UserModel $userModel = null;
    private ?ProjectModel $projectModel = null;

    public function __construct()
    {
        parent::__construct();
        if (class_exists('TaskModel')) {
            $this->taskModel = new TaskModel($this->db);
        }
        if (class_exists('UserModel')) {
            $this->userModel = new UserModel($this->db);
        }
        if (class_exists('ProjectModel')) {
            $this->projectModel = new ProjectModel($this->db);
        }
    }

    /**
     * Raporlar ekranı için tüm metrikleri toplar
     */
    public function getAnalyticsSummary(): array
    {
        $today = date('Y-m-d');
        $totalTasks = $this->taskModel ? $this->taskModel->countTasksByStatus(null) : 0;
        $completedTasks = $this->taskModel ? $this->taskModel->countTasksByStatus('completed') : 0;
        $inProgressTasks = $this->taskModel ? $this->taskModel->countTasksByStatus('in_progress') : 0;
        $submittedTasks = $this->taskModel ? $this->taskModel->countTasksByStatus('submitted') : 0;
        $modTasks = $this->taskModel ? $this->taskModel->countTasksByStatus('modification_requested') : 0;

        $completionRate = $totalTasks > 0 ? (int)round(($completedTasks / $totalTasks) * 100) : 0;
        $overdueCount = $this->taskModel ? $this->taskModel->countOverdueTasks($today) : 0;
        $teamStats = $this->userModel ? $this->userModel->getTeamPerformanceStats($today) : [];
        $projectStats = $this->projectModel ? $this->projectModel->getProjectStats() : [];

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

    /**
     * Efor, Maliyet ve Hakediş/Teslimat Özeti üretir
     */
    public function getProjectBillingSummary(?int $projectId = null, ?float $customHourlyRate = null): array
    {
        $sql = "
            SELECT t.id, t.project_id, t.task_code, t.title, t.description, t.priority, t.status, 
                   t.time_estimate, t.time_spent, t.created_at, t.completed_at,
                   p.title as project_title, p.project_code, p.color as project_color, p.hourly_rate as project_hourly_rate,
                   u.name as assignee_name,
                   sub.notes as delivery_notes, sub.created_at as delivered_at
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN task_submissions sub ON (sub.task_id = t.id AND sub.status = 'approved')
            WHERE t.status = 'completed' AND t.parent_task_id IS NULL
        ";
        $params = [];

        if ($projectId > 0) {
            $sql .= " AND t.project_id = ? ";
            $params[] = $projectId;
        }

        $sql .= " ORDER BY t.completed_at DESC, t.id DESC ";
        $tasks = $this->query($sql, $params);

        $totalMinutesSpent = 0;
        $totalMinutesEstimated = 0;
        $detailedTasks = [];

        foreach ($tasks as $task) {
            $task = (object)$task;
            $spentMin = 0;
            if (!empty($task->time_spent)) {
                if (preg_match('/(\d+)\s*h/i', $task->time_spent, $m)) $spentMin += ((int)$m[1]) * 60;
                if (preg_match('/(\d+)\s*m/i', $task->time_spent, $m)) $spentMin += (int)$m[1];
            }
            if ($spentMin === 0) $spentMin = 60;

            $estMin = 0;
            if (!empty($task->time_estimate)) {
                if (preg_match('/(\d+)\s*h/i', $task->time_estimate, $m)) $estMin += ((int)$m[1]) * 60;
                if (preg_match('/(\d+)\s*m/i', $task->time_estimate, $m)) $estMin += (int)$m[1];
            }
            if ($estMin === 0) $estMin = $spentMin;

            $totalMinutesSpent += $spentMin;
            $totalMinutesEstimated += $estMin;

            $effectiveRate = ($customHourlyRate !== null && $customHourlyRate > 0)
                ? $customHourlyRate
                : (!empty($task->project_hourly_rate) && (float)$task->project_hourly_rate > 0 ? (float)$task->project_hourly_rate : 500.0);

            $hoursSpent = round($spentMin / 60, 2);
            $taskCost = round($hoursSpent * $effectiveRate, 2);

            $detailedTasks[] = [
                'id'              => $task->id,
                'task_code'       => $task->task_code,
                'title'           => $task->title,
                'project_title'   => $task->project_title,
                'project_code'    => $task->project_code,
                'assignee_name'   => $task->assignee_name,
                'time_estimate'   => $task->time_estimate,
                'time_spent'      => $task->time_spent,
                'spent_minutes'   => $spentMin,
                'spent_hours'     => $hoursSpent,
                'hourly_rate'     => $effectiveRate,
                'task_cost'       => $taskCost,
                'completed_at'    => $task->completed_at ?: $task->created_at,
                'delivery_notes'  => $task->delivery_notes ?: ''
            ];
        }

        $totalHoursSpent = round($totalMinutesSpent / 60, 2);
        $totalHoursEstimated = round($totalMinutesEstimated / 60, 2);
        $totalCost = round(array_sum(array_column($detailedTasks, 'task_cost')), 2);

        $projectTitle = __('Reports::all_projects');
        $projectCode = 'ALL';
        $hourlyRate = 500.0;

        if ($projectId > 0 && !empty($detailedTasks)) {
            $projectTitle = $detailedTasks[0]['project_title'] ?? 'Proje #' . $projectId;
            $projectCode = $detailedTasks[0]['project_code'] ?? 'PRJ';
            $hourlyRate = $detailedTasks[0]['hourly_rate'] ?? 500.0;
        }

        return [
            'project_id'            => $projectId,
            'project_title'         => $projectTitle,
            'project_code'          => $projectCode,
            'hourly_rate'           => $hourlyRate,
            'total_completed_tasks' => count($detailedTasks),
            'total_hours_estimated' => $totalHoursEstimated,
            'total_hours_spent'     => $totalHoursSpent,
            'total_cost'            => $totalCost,
            'generated_at'          => date('d.m.Y H:i'),
            'tasks'                 => $detailedTasks
        ];
    }
}
