<?php
require_once __DIR__ . '/Core/System.php';
$res = \Modules\Board\Services\TaskLifecycleService::submitTaskDirectly(1223, 'Canli QA Sablonu Dogrulama Testi - Token, Description, Attachments ve Login kontrolu.');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
