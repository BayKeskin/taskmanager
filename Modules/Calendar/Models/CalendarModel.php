<?php
namespace Modules\Calendar\Models;

use Core\BaseModel;

class CalendarModel extends BaseModel
{
    /**
     * Takvim ve Gantt görünümü için görevleri çeker
     */
    public function getCalendarTasks(?string $startDate = null, ?string $endDate = null, ?int $projectId = null): array
    {
        $taskModel = new \Modules\Board\Models\TaskModel();
        return $taskModel->getTasksForCalendar($startDate, $endDate, $projectId);
    }
}
