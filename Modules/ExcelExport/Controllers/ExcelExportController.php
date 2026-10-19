<?php
namespace Modules\ExcelExport\Controllers;

use Core\BaseController;
use Modules\ExcelExport\Models\ExcelExportModel;

/**
 * ExcelExportController - Görevleri CSV / Excel formatında dışa aktarır
 */
class ExcelExportController extends BaseController
{
    private ExcelExportModel $exportModel;

    public function __construct()
    {
        parent::__construct();
        $this->exportModel = new ExcelExportModel();
    }

    /**
     * CSV/Excel indirme işlemini yürütür
     */
    public function export(int $projectId, ?object $currentUser = null): void
    {
        if ($currentUser) {
            $this->user = $currentUser;
        } else {
            $this->requireAuth();
        }

        if ($projectId <= 0) {
            echo __('ExcelExport::invalid_project');
            exit;
        }

        // Proje var mı kontrol et
        $projectModel = new \Modules\Projects\Models\ProjectModel();
        $project = $projectModel->getProjectById($projectId);
        if (!$project) {
            echo __('ExcelExport::project_not_found');
            exit;
        }

        $tasks = $this->exportModel->getTasksForExport($projectId);

        $safeProjectCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project->project_code ?? 'PROJ');
        $fileName = 'kanban_tasks_' . $safeProjectCode . '_' . date('Y-m-d_His') . '.csv';

        // HTTP Başlıkları
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM ekle (Excel'de karakter bozulmasını engeller)
        fputs($output, "\xEF\xBB\xBF");

        // Başlık Satırı
        $headers = [
            __('ExcelExport::export_task_id'),
            __('ExcelExport::export_title'),
            __('ExcelExport::export_status'),
            __('ExcelExport::export_priority'),
            __('ExcelExport::export_assignee'),
            __('ExcelExport::export_creator'),
            __('ExcelExport::export_start_date'),
            __('ExcelExport::export_due_date'),
            __('ExcelExport::export_progress'),
            __('ExcelExport::export_created_at')
        ];
        fputcsv($output, $headers, ';');

        $statusTitles = [
            'assigned'               => __('ExcelExport::status_assigned'),
            'accepted'               => __('ExcelExport::status_accepted'),
            'in_progress'            => __('ExcelExport::status_in_progress'),
            'modification_requested' => __('ExcelExport::status_modification_requested'),
            'submitted'              => __('ExcelExport::status_submitted'),
            'completed'              => __('ExcelExport::status_completed')
        ];

        $priorityTitles = [
            'urgent' => __('ExcelExport::priority_urgent'),
            'high'   => __('ExcelExport::priority_high'),
            'normal' => __('ExcelExport::priority_normal'),
            'low'    => __('ExcelExport::priority_low')
        ];

        foreach ($tasks as $task) {
            $tObj = (object)$task;
            $progressStr = '0%';
            if (!empty($tObj->subtask_count)) {
                $pct = round(((int)$tObj->subtask_completed_count / (int)$tObj->subtask_count) * 100);
                $progressStr = $pct . '%';
            } elseif ($tObj->status === 'completed') {
                $progressStr = '100%';
            } elseif ($tObj->status === 'submitted') {
                $progressStr = '90%';
            } elseif ($tObj->status === 'in_progress') {
                $progressStr = '50%';
            }

            fputcsv($output, [
                $tObj->task_code ?: ('#' . $tObj->id),
                $tObj->title,
                $statusTitles[$tObj->status] ?? $tObj->status,
                $priorityTitles[$tObj->priority] ?? $tObj->priority,
                !empty($tObj->assignee_name) ? trim($tObj->assignee_name) : __('ExcelExport::export_unassigned'),
                !empty($tObj->creator_name) ? trim($tObj->creator_name) : '-',
                !empty($tObj->created_at) ? date('Y-m-d', strtotime($tObj->created_at)) : __('ExcelExport::export_no_date'),
                !empty($tObj->deadline) ? date('Y-m-d', strtotime($tObj->deadline)) : __('ExcelExport::export_no_date'),
                $progressStr,
                date('Y-m-d H:i', strtotime($tObj->created_at))
            ], ';');
        }

        fclose($output);
        exit;
    }
}
