<?php
$pdo = new PDO("mysql:host=localhost;dbname=taskmanager_db;charset=utf8mb4", "root", "");
$pdo->exec("ALTER TABLE `projects` ADD COLUMN `hourly_rate` DECIMAL(10,2) NOT NULL DEFAULT 500.00 AFTER `color`");
echo "Column added successfully\n";
