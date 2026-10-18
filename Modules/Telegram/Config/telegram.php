<?php
/**
 * TaskManager Telegram Bot Entegrasyon Yapılandırması
 */
return [
    'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'bot_username' => 'TaskManagerBot',
    'allowed_chat_ids' => [],
    'default_notification_chat_id' => getenv('TELEGRAM_CHAT_ID') ?: null,
    'notifications' => [
        'task_created'    => true,
        'task_submitted'  => true,
        'urgent_task'     => true,
        'direct_message'  => true,
    ],
    'webhook_url' => 'https://taskmanager.domain.com/api/telegram',
];
