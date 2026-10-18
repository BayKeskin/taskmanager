<?php
namespace Modules\Billing\Models;

use Core\BaseModel;

/**
 * BillingModel - Müşteri Hakediş & Efor Raporu Veri Katmanı
 */
class BillingModel extends BaseModel
{
    protected string $table = 'tasks';

    /**
     * Projeye ait tamamlanan görevlerin hakediş ve efor özetini hesaplar
     */
    public function getProjectBillingSummary(?int $projectId = null, ?float $customHourlyRate = null): array
    {
        $sql = "
            SELECT t.id, t.task_code, t.title, t.time_estimate, t.time_spent, 
                   t.created_at, t.completed_at, t.status,
                   p.id as project_id, p.title as project_title, p.project_code, p.hourly_rate as project_hourly_rate,
                   u.name as assignee_name,
                   sub.notes as delivery_notes
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN task_submissions sub ON (sub.task_id = t.id AND sub.status = 'approved')
            WHERE t.parent_task_id IS NULL 
              AND (t.status = 'completed' OR t.status = 'submitted')
        ";
        $params = [];
        if ($projectId !== null && $projectId > 0) {
            $sql .= " AND t.project_id = ? ";
            $params[] = $projectId;
        }
        $sql .= " ORDER BY t.completed_at DESC, t.id DESC ";

        $tasks = $this->query($sql, $params);

        $totalMinutesSpent = 0;
        $totalMinutesEstimated = 0;
        $detailedTasks = [];

        foreach ($tasks as $task) {
            $tObj = (object)$task;
            $spentMin = 0;
            if (!empty($tObj->time_spent)) {
                if (preg_match('/(\d+)\s*h/i', $tObj->time_spent, $m)) $spentMin += ((int)$m[1]) * 60;
                if (preg_match('/(\d+)\s*m/i', $tObj->time_spent, $m)) $spentMin += (int)$m[1];
            }
            if ($spentMin === 0) {
                $spentMin = 60; // Varsayılan 1 saat (60 dk)
            }

            $estMin = 0;
            if (!empty($tObj->time_estimate)) {
                if (preg_match('/(\d+)\s*h/i', $tObj->time_estimate, $m)) $estMin += ((int)$m[1]) * 60;
                if (preg_match('/(\d+)\s*m/i', $tObj->time_estimate, $m)) $estMin += (int)$m[1];
            }
            if ($estMin === 0) $estMin = $spentMin;

            $totalMinutesSpent += $spentMin;
            $totalMinutesEstimated += $estMin;

            $effectiveRate = ($customHourlyRate !== null && $customHourlyRate > 0)
                ? $customHourlyRate
                : (!empty($tObj->project_hourly_rate) && (float)$tObj->project_hourly_rate > 0 ? (float)$tObj->project_hourly_rate : 500.0);

            $hoursSpent = round($spentMin / 60, 2);
            $taskCost = round($hoursSpent * $effectiveRate, 2);

            $detailedTasks[] = [
                'id'              => $tObj->id,
                'task_code'       => $tObj->task_code,
                'title'           => $tObj->title,
                'project_title'   => $tObj->project_title,
                'project_code'    => $tObj->project_code,
                'assignee_name'   => $tObj->assignee_name,
                'time_estimate'   => $tObj->time_estimate,
                'time_spent'      => $tObj->time_spent,
                'spent_minutes'   => $spentMin,
                'spent_hours'     => $hoursSpent,
                'hourly_rate'     => $effectiveRate,
                'task_cost'       => $taskCost,
                'completed_at'    => $tObj->completed_at ?: $tObj->created_at,
                'delivery_notes'  => $tObj->delivery_notes ?: ''
            ];
        }

        $totalHoursSpent = round($totalMinutesSpent / 60, 2);
        $totalHoursEstimated = round($totalMinutesEstimated / 60, 2);
        $totalCost = round(array_sum(array_column($detailedTasks, 'task_cost')), 2);

        $projectTitle = 'Tüm Projeler';
        $projectCode = 'ALL';
        $resolvedHourlyRate = ($customHourlyRate !== null && $customHourlyRate > 0) ? $customHourlyRate : 500.0;

        if ($projectId > 0) {
            if (!empty($detailedTasks)) {
                $projectTitle = $detailedTasks[0]['project_title'] ?? 'Proje #' . $projectId;
                $projectCode = $detailedTasks[0]['project_code'] ?? 'PRJ';
                $resolvedHourlyRate = $detailedTasks[0]['hourly_rate'] ?? $resolvedHourlyRate;
            } else {
                $pRow = $this->queryOne("SELECT title, project_code, hourly_rate FROM projects WHERE id = ? LIMIT 1", [$projectId]);
                if ($pRow) {
                    $projectTitle = $pRow->title;
                    $projectCode = $pRow->project_code;
                    $resolvedHourlyRate = ($customHourlyRate !== null && $customHourlyRate > 0)
                        ? $customHourlyRate
                        : (!empty($pRow->hourly_rate) ? (float)$pRow->hourly_rate : 500.0);
                }
            }
        }

        return [
            'project_id'             => $projectId,
            'project_title'          => $projectTitle,
            'project_code'           => $projectCode,
            'hourly_rate'            => $resolvedHourlyRate,
            'total_completed_tasks'  => count($detailedTasks),
            'total_hours_spent'      => $totalHoursSpent,
            'total_hours_estimated'  => $totalHoursEstimated,
            'total_cost'             => $totalCost,
            'currency'               => '₺',
            'tasks'                  => $detailedTasks,
            'generated_at'           => date('Y-m-d H:i:s')
        ];
    }
}
