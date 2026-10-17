<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/TaskModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';
require_once __DIR__ . '/../Model/UserModel.php';
require_once __DIR__ . '/../Model/SettingsModel.php';

class BoardController extends Controller
{
    private TaskModel $taskModel;
    private ProjectModel $projectModel;
    private UserModel $userModel;
    private SettingsModel $settingsModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->taskModel = new TaskModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
        $this->userModel = new UserModel($this->db);
        $this->settingsModel = new SettingsModel($this->db);
    }

    /**
     * Kanban panosu verilerini hazırlar
     */
    public function index(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $projectInfo = $activeProjectId ? $this->projectModel->getProjectById($activeProjectId) : null;
        $sysSettings = $this->settingsModel->getSettings();

        $employeeCanCreateTasks = (int)($sysSettings->employee_can_create_tasks ?? 1);
        $employeeCanAiTasks = (int)($sysSettings->employee_can_ai_tasks ?? 0);
        $canCreateTask = ($currentUser->role === 'admin') || (!$isClient && $employeeCanCreateTasks);
        $canUseAiTasks = ($currentUser->role === 'admin') || (!$isClient && $employeeCanAiTasks);

        $allUsers = $this->userModel->getUsersForFilter();
        $allTasks = $this->taskModel->getTasksForBoard($activeProjectId);
        $assigneesMap = $this->taskModel->getAssigneesMap();
        $attachmentCounts = $this->taskModel->getAttachmentCounts();

        return [
            'currentUser'            => $currentUser,
            'isClient'               => $isClient,
            'activeProjectId'        => $activeProjectId,
            'projectInfo'            => $projectInfo,
            'sysSettings'            => $sysSettings,
            'canCreateTask'          => $canCreateTask,
            'canUseAiTasks'          => $canUseAiTasks,
            'allUsers'               => $allUsers,
            'allTasks'               => $allTasks,
            'assigneesMap'           => $assigneesMap,
            'attachmentCounts'       => $attachmentCounts,
        ];
    }

    /**
     * Kanban görevlerini UTF-8 BOM destekli Excel (.csv / .xlsx uyumlu) olarak dışa aktarır (TSK-1156)
     */
    public function exportExcel(?int $projectId = null, ?object $currentUser = null): void
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? null);
        if (!$currentUser) {
            header('Location: /taskmanager/login');
            exit;
        }

        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $projectId = (int)($currentUser->client_project_id ?? 0);
        }

        if (!$projectId || $projectId <= 0) {
            echo "Geçersiz veya seçilmemiş proje.";
            exit;
        }

        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            echo "Proje bulunamadı.";
            exit;
        }

        $tasks = $this->taskModel->getTasksForExport($projectId);

        $safeProjectCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project->project_code ?? 'PROJ');
        $fileName = 'kanban_tasks_' . $safeProjectCode . '_' . date('Y-m-d_His') . '.csv';

        // HTTP Headers for Excel download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM ekleme (Excel'de Türkçe, İngilizce, Almanca karakter bozulmasını kesin olarak engeller)
        fputs($output, "\xEF\xBB\xBF");

        // Başlık Satırı
        $headers = [
            __('app.export_task_id', 'Görev No'),
            __('app.export_title', 'Görev Başlığı'),
            __('app.export_status', 'Mevcut Durum'),
            __('app.export_priority', 'Öncelik'),
            __('app.export_assignee', 'Atanan'),
            __('app.export_creator', 'Oluşturan'),
            __('app.export_start_date', 'Başlangıç Tarihi'),
            __('app.export_due_date', 'Termin Tarihi'),
            __('tasks.gantt_task_progress', 'İlerleme'),
            __('app.export_created_at', 'Oluşturulma Tarihi')
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

        // Veri Satırları
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
                !empty($tObj->assignee_name) ? trim($tObj->assignee_name) : __('app.export_unassigned', 'Atanmamış'),
                !empty($tObj->creator_name) ? trim($tObj->creator_name) : '-',
                !empty($tObj->created_at) ? date('Y-m-d', strtotime($tObj->created_at)) : __('app.export_no_date', 'Belirtilmedi'),
                !empty($tObj->deadline) ? date('Y-m-d', strtotime($tObj->deadline)) : __('app.export_no_date', 'Belirtilmedi'),
                $progressStr,
                date('Y-m-d H:i', strtotime($tObj->created_at))
            ], ';');
        }

        fclose($output);
        exit;
    }
}

