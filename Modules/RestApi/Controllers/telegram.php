<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../Core/System.php';
require_once __DIR__ . '/../../Core/Telegram.php';

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode([
        'status' => 'online',
        'service' => 'TaskManager Telegram Bot Webhook Gateway',
        'time' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$update = json_decode($rawInput, true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$result = Telegram::handleUpdate($update);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
