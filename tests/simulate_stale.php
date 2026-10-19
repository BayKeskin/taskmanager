<?php
require_once dirname(__DIR__) . '/Core/System.php';

$model = new \Modules\Watchdog\Models\WatchdogModel();
$task = $model->query("SELECT id, task_code, title, status, updated_at FROM tasks WHERE id = 1")[0] ?? null;
if (!$task) {
    $task = $model->query("SELECT id, task_code, title, status, updated_at FROM tasks ORDER BY id DESC LIMIT 1")[0] ?? null;
}


echo "Current Task:\n";
print_r($task);

if ($task) {
    $id = (int)$task->id;
    // 30 dakika geriye çek ve in_progress yap
    $sql = "UPDATE tasks SET status = 'in_progress', updated_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?";
    $model->execute($sql, [$id]);
    echo "\nTask {$id} set to in_progress with updated_at -30 minutes.\n";

    $after = $model->query("SELECT id, task_code, title, status, updated_at FROM tasks WHERE id = ?", [$id])[0] ?? null;
    print_r($after);
}


