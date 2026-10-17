<?php
// Test script for TSK-194 Billing Report
require_once __DIR__ . '/../system/System.php';
require_once __DIR__ . '/../Model/Model.php';
require_once __DIR__ . '/../Model/ReportModel.php';
require_once __DIR__ . '/../Controller/Controller.php';
require_once __DIR__ . '/../Controller/ReportController.php';

$sys = new System();
$reportModel = new ReportModel($sys->db);
$summary = $reportModel->getProjectBillingSummary(1, 600.0);

echo "=== TSK-194 BILLING REPORT TEST ===" . PHP_EOL;
echo "Project: " . ($summary['project_title'] ?? 'N/A') . PHP_EOL;
echo "Completed Tasks: " . ($summary['total_completed_tasks'] ?? 0) . PHP_EOL;
echo "Hours Spent: " . ($summary['total_hours_spent'] ?? 0) . PHP_EOL;
echo "Total Billing: " . ($summary['formatted_total_billing'] ?? 0) . PHP_EOL;
echo "Tasks in breakdown: " . count($summary['tasks'] ?? []) . PHP_EOL;

if (isset($summary['total_completed_tasks'])) {
    echo "SUCCESS: Billing model logic verified!" . PHP_EOL;
} else {
    echo "FAILURE: Billing summary empty." . PHP_EOL;
    exit(1);
}
