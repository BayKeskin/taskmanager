<?php
require_once __DIR__ . '/Core/System.php';
$res = \Modules\Board\Services\TaskLifecycleService::submitTaskDirectly(1223, 'Argon 3-nokta onarim dogrulama teslimati.');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
