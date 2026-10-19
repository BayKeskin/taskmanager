<?php
/**
 * Telegram Modülü Başlatıcı (init.php)
 * EventManager kancalarını kaydeder.
 */
use Core\EventManager;

require_once __DIR__ . '/Services/TelegramService.php';

// Görev oluşturulduğunda Telegram bildirimi kancası
EventManager::listen('task.created', function(array $data) {
    if (class_exists('Telegram')) {
        $msg = "📌 <b>" . __('Telegram::notify_created') . "</b>\n";
        $msg .= __('Telegram::field_code') . ": <code>" . ($data['task_code'] ?? '') . "</code>\n";
        $msg .= __('Telegram::field_title') . ": " . ($data['title'] ?? '') . "\n";
        $msg .= __('Telegram::field_priority') . ": " . ($data['priority'] ?? 'normal') . "\n";
        @\Telegram::sendNotification($msg);
    }
});

// Görev durumu değiştiğinde Telegram bildirimi kancası
EventManager::listen('task.status_changed', function(array $data) {
    if (class_exists('Telegram')) {
        $msg = "⚡ <b>" . __('Telegram::notify_status') . "</b>\n";
        $msg .= __('Telegram::field_code') . ": <code>" . ($data['task_code'] ?? '') . "</code>\n";
        $msg .= __('Telegram::field_status') . ": " . ($data['new_status'] ?? '') . "\n";
        @\Telegram::sendNotification($msg);
    }
});
