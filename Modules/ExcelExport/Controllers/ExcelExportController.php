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
    public function export(int $projectId): void
    {
        $this->requireAuth();

        if ($projectId <= 0) {
            echo "Geçersiz proje.";
            exit;
        }

        // Proje var mı kontrol et
        require_once dirname(__DIR__, 3) . '/Model/ProjectModel.php';
        $projectModel = new \ProjectModel($this->db);
        $project = $projectModel->getProjectById($projectId);
        if (!$project) {
            echo "Proje bulunamadı.";
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
            __('ExcelExport::export_task_id', 'Görev No'),
            __('ExcelExport::export_title', 'Görev Başlığı'),
            __('ExcelExport::export_status', 'Mevcut Durum'),
            __('ExcelExport::export_priority', 'Öncelik'),
            __('ExcelExport::export_assignee', 'Atanan'),
            __('ExcelExport::export_creator', 'Oluşturan'),
            __('ExcelExport::export_start_date', 'Başlangıç Tarihi'),
            __('ExcelExport::export_due_date', 'Termin Tarihi'),
            __('ExcelExport::export_progress', 'İlerleme'),
            __('ExcelExport::export_created_at', 'Oluşturulma Tarihi')
        ];
        fputcsv($output, $headers, ';');

        $statusTitles = [
            'assigned'               => __('tasks.col_assigned', 'YAPILACAK İŞLER'),
            'accepted'               => __('tasks.list_group_accepted', 'KABUL EDİLENLER'),
            'in_progress'            => __('tasks.col_in_progress', 'ÇALIŞILIYOR'),
            'modification_requested' => __('tasks.col_modification_requested', 'REVİZYON İSTENDİ'),
            'submitted'              => __('tasks.col_submitted', 'İNCELEMEDE'),
            'completed'              => __('tasks.col_completed', 'TAMAMLANDI')
        ];

        $priorityTitles = [
            'urgent' => __('tasks.priority_urgent_badge', 'Acil'),
            'high'   => __('tasks.priority_high_badge', 'Yüksek'),
            'normal' => __('tasks.priority_normal_badge', 'Normal'),
            'low'    => __('tasks.priority_low_badge', 'Düşük')
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
                !empty($tObj->assignee_name) ? trim($tObj->assignee_name) : __('ExcelExport::export_unassigned', 'Atanmamış'),
                !empty($tObj->creator_name) ? trim($tObj->creator_name) : '-',
                !empty($tObj->created_at) ? date('Y-m-d', strtotime($tObj->created_at)) : __('ExcelExport::export_no_date', 'Belirtilmedi'),
                !empty($tObj->deadline) ? date('Y-m-d', strtotime($tObj->deadline)) : __('ExcelExport::export_no_date', 'Belirtilmedi'),
                $progressStr,
                date('Y-m-d H:i', strtotime($tObj->created_at))
            ], ';');
        }

        fclose($output);
        exit;
    }
}
