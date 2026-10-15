<?php

/**
 * TaskManager Telegram Bot Entegrasyon Yapılandırması
 */
return [
    // Telegram Bot Token (BotFather'dan temin edilir)
    'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '7123456789:AAExampleBotTokenTaskManagerXYZ',

    // Bot Adı & Kullanıcı Adı
    'bot_username' => 'TaskManagerBot',

    // Yetkili Telegram Chat ID Listesi (Yalnızca bu ID'ler bot üzerinden görev yönetebilir)
    // Güvenlik: Yetkisiz kişilerin durum değiştirmesi engellenir.
    'allowed_chat_ids' => [
        // 123456789, // Talha Keskin Telegram Chat ID
    ],

    // Bildirimlerin Otomatik Gönderileceği Varsayılan Kanal veya Yönetici Chat ID
    'default_notification_chat_id' => getenv('TELEGRAM_CHAT_ID') ?: null,

    // Bildirim Tetikleyicileri (Hangi durumlarda Telegram'a mesaj atılsın?)
    'notifications' => [
        'task_created'    => true,  // Yeni görev oluşturulduğunda
        'task_submitted'  => true,  // Görev teslim edildiğinde
        'urgent_task'     => true,  // Acil (urgent) öncelikli görev açıldığında
        'direct_message'  => true,  // Çalışana özel DM geldiğinde
    ],

    // Webhook URL (Canlı sunucuda HTTPS zorunludur)
    'webhook_url' => 'https://taskmanager.domain.com/api/telegram',
];
