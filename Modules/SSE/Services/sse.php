<?php
// Modules/SSE/Services/sse.php
require_once dirname(__DIR__, 3) . '/Core/System.php';

$controller = new \Modules\SSE\Controllers\SseController();
$controller->stream();
