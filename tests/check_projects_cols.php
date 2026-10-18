<?php
$pdo = new PDO("mysql:host=localhost;dbname=taskmanager_db;charset=utf8mb4", "root", "");
$stmt = $pdo->query("DESCRIBE projects");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " => " . $row['Type'] . "\n";
}
