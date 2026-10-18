<?php
$pdo = new PDO("mysql:host=localhost;dbname=taskmanager_db;charset=utf8mb4", "root", "");
$pdo->exec("UPDATE projects SET hourly_rate = 750.00 WHERE id = 1");

require_once __DIR__ . '/../System/System.php';
require_once __DIR__ . '/../Model/ReportModel.php';

$system = new System();
$rm = new ReportModel($system->db);
$res = $rm->getProjectBillingSummary(1);
echo "Updated Project 1 Hourly Rate: " . $res["hourly_rate"] . " TL\n";
echo "Recalculated Total Cost (39h * 750): " . $res["total_cost"] . " TL\n";
