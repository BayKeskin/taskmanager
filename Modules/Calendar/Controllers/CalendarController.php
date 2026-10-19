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

        $monthsLookup = __('Calendar::months');
        $monthNames = is_array($monthsLookup) ? $monthsLookup : [];

        $firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDayTimestamp);
        $firstDayOfWeek = (int)date('N', $firstDayTimestamp); // 1 = Pazartesi, 7 = Pazar

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $calendarModel = new \Modules\Calendar\Models\CalendarModel();

        // Standart Takvim görevleri (seçili ay için)
        $calendarTasks = $calendarModel->getCalendarTasks($startDate, $endDate, $activeProjectId);

        // Gantt Şeması için tüm aktif/takvimli görevler
        $allGanttTasks = $calendarModel->getCalendarTasks(null, null, $activeProjectId);

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
