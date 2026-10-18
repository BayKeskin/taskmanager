<?php
require_once __DIR__ . '/../Core/System.php';
require_once __DIR__ . '/../Model/ReportModel.php';

$system = new System();
$rm = new ReportModel($system->db);
$res = $rm->getProjectBillingSummary(1);
echo "Project: " . $res["project_title"] . "\n";
echo "Hourly Rate: " . $res["hourly_rate"] . " TL\n";
echo "Total Completed Tasks: " . $res["total_completed_tasks"] . "\n";
echo "Total Hours Spent: " . $res["total_hours_spent"] . " sa\n";
echo "Total Cost: " . $res["total_cost"] . " TL\n";
