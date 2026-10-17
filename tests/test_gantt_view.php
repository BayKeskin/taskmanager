<?php
// Test script for 01-104 Gantt & Calendar View
require_once __DIR__ . '/../system/System.php';
require_once __DIR__ . '/../Model/Model.php';
require_once __DIR__ . '/../Model/TaskModel.php';
require_once __DIR__ . '/../Controller/Controller.php';
require_once __DIR__ . '/../Controller/CalendarController.php';

$sys = new System();
$controller = new CalendarController($sys);

echo "=== TEST 01-104: GANTT & CALENDAR CONTROLLER ===" . PHP_EOL;

// 1. Test month scale
$monthData = $controller->index(2026, 10, 1, null, 'month');
echo "Month Tasks count: " . count($monthData['calendarTasks']) . PHP_EOL;
echo "All Gantt Tasks count: " . count($monthData['allGanttTasks']) . PHP_EOL;
echo "Days in Month: " . $monthData['daysInMonth'] . PHP_EOL;

// 2. Test week scale
$weekData = $controller->index(2026, 10, 1, null, 'week');
echo "Week viewMode: " . $weekData['viewMode'] . PHP_EOL;

if (isset($monthData['allGanttTasks']) && count($monthData['allGanttTasks']) > 0) {
    echo "SUCCESS: Gantt data pipeline verified!" . PHP_EOL;
} else {
    echo "WARNING: No gantt tasks found." . PHP_EOL;
}
