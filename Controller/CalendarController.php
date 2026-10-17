<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/TaskModel.php';
require_once __DIR__ . '/../System/Language.php';

class CalendarController extends Controller
{
    private TaskModel $taskModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->taskModel = new TaskModel($this->db);
    }

    /**
     * Takvim ve Gantt görünümü için ay, gün ve görev verilerini hazırlar
     */
    public function index(?int $year = null, ?int $month = null, ?int $activeProjectId = null, ?object $currentUser = null, string $viewMode = 'month'): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $year = $year ?: (int)date('Y');
        $month = $month ?: (int)date('n');

        $currentLang = class_exists('Language') ? Language::current() : 'tr';
        $defaultMonths = [
            'tr' => [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'],
            'de' => [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'],
            'en' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
        ];
        $rawMonths = function_exists('__') ? __('tasks.cal_months') : null;
        $monthNames = is_array($rawMonths) ? $rawMonths : ($defaultMonths[$currentLang] ?? $defaultMonths['en']);

        $firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDayTimestamp);
        $firstDayOfWeek = (int)date('N', $firstDayTimestamp); // 1 = Monday, 7 = Sunday

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Standart Takvim görevleri (seçili ay için)
        $calendarTasks = $this->taskModel->getTasksForCalendar($startDate, $endDate, $activeProjectId);

        // Gantt Şeması için tüm aktif/takvimli görevler
        $allGanttTasks = $this->taskModel->getTasksForCalendar(null, null, $activeProjectId);

        // Görevleri tarihe göre grupla (YYYY-MM-DD -> [tasks])
        $tasksByDate = [];
        foreach ($calendarTasks as $t) {
            if (!empty($t->deadline)) {
                if (!isset($tasksByDate[$t->deadline])) {
                    $tasksByDate[$t->deadline] = [];
                }
                $tasksByDate[$t->deadline][] = $t;
            }
        }

        return [
            'currentUser'        => $currentUser,
            'isClient'           => $isClient,
            'activeProjectId'    => $activeProjectId,
            'year'               => $year,
            'month'              => $month,
            'monthNames'         => $monthNames,
            'daysInMonth'        => $daysInMonth,
            'firstDayOfWeek'     => $firstDayOfWeek,
            'calendarTasks'      => $calendarTasks,
            'allGanttTasks'      => $allGanttTasks,
            'tasksByDate'        => $tasksByDate,
            'viewMode'           => $viewMode
        ];
    }
}
