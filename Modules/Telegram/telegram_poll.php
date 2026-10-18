<?php
/**
 * TaskManager Localhost Telegram Long-Polling Worker
 * 
 * This script runs in Localhost environment without requiring a public Webhook / domain,
 * actively listening for incoming Telegram Bot API messages and commands (getUpdates).
 * 
 * Usage:
 * php bin/telegram_poll.php
 * php bin/telegram_poll.php --once
 */

require_once dirname(__DIR__, 2) . '/Core/System.php';

echo "========================================================\n";
echo "🤖 TaskManager Telegram Localhost Polling Worker\n";
echo "========================================================\n";

$test = Telegram::testConnection();
echo "Status: " . ($test['message'] ?? '') . "\n";
if (!$test['success']) {
    echo "Please configure a valid Telegram bot token in settings.\n";
    exit(1);
}

echo "Listening for updates... Press Ctrl+C to exit.\n\n";

$offset = 0;
$runCount = 0;

while (true) {
    try {
        $res = Telegram::pollUpdates($offset);
        if ($res['success']) {
            if ($res['updates_processed'] > 0) {
                echo "[" . date('H:i:s') . "] ✅ " . $res['updates_processed'] . " Telegram message(s)/command(s) processed.\n";
            }
            $offset = $res['new_offset'];
        }
    } catch (Exception $e) {
        echo "[" . date('H:i:s') . "] ⚠️ Error: " . $e->getMessage() . "\n";
    }

    $runCount++;
    // Wait 1 second between polling iterations
    sleep(1);

    // If --once argument is provided, exit after a single iteration
    if (isset($argv[1]) && $argv[1] === '--once') {
        echo "Single-run check completed.\n";
        break;
    }
}
