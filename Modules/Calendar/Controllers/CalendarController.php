<?php
namespace Modules\Calendar\Controllers;

use Core\BaseController;
use Core\View;

/**
 * CalendarController - Takvim ve Gantt görünümü için ay, gün ve görev verilerini koordine eder.
 */
class CalendarController extends BaseController
{
    /**
     * Takvim ve Gantt görünümü verilerini hazırlar
     */
    public function index(?int $year = null, ?int $month = null, ?int $activeProjectId = null, string $ganttScale = 'month'): array
    {
        $this->requireAuth();

        $currentUser = $this->user;
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $year = $year ?: (int)date('Y');
        $month = $month ?: (int)date('n');

        $defaultMonths = [
            'tr' => [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'],
            'de' => [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'],
            'en' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
        ];
        $currentLang = class_exists('\Language') ? \Language::current() : 'tr';
        $monthNames = $defaultMonths[$currentLang] ?? $defaultMonths['en'];

        $firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDayTimestamp);
        $firstDayOfWeek = (int)date('N', $firstDayTimestamp); // 1 = Pazartesi, 7 = Pazar

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $taskModel = new \Modules\Board\Models\TaskModel();

        // Standart Takvim görevleri (seçili ay için)
        $calendarTasks = $taskModel->getTasksForCalendar($startDate, $endDate, $activeProjectId);

        // Gantt Şeması için tüm aktif/takvimli görevler
        $allGanttTasks = $taskModel->getTasksForCalendar(null, null, $activeProjectId);

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
            'viewMode'           => $ganttScale
        ];
    }
}
