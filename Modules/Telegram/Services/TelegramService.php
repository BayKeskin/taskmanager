<?php

/**
 * TaskManager Telegram Bot Entegrasyon & Otonom Yönetim Motoru
 * Dışarıdayken cep telefonundan görev durumlarını değiştirme, liste sorgulama ve anlık bildirim alma motorudur.
 */
class Telegram
{
    private static ?array $config = null;

    /**
     * Yapılandırmayı yükler
     */
    public static function getConfig(): array
    {
        if (self::$config === null) {
            $file = __DIR__ . '/../Config/telegram.php';
            if (file_exists($file)) {
                self::$config = require $file;
            } else {
                self::$config = [
                    'bot_token' => '',
                    'allowed_chat_ids' => [],
                    'default_notification_chat_id' => null
                ];
            }

            // DB settings tablosundan oku (Sistem Ayarlarındaki bot_token varsa önceliklendir)
            try {
                global $db;
                if (!isset($db) || !($db instanceof Database)) {
                    $db = new Database();
                }
                $s = $db->connect->query("SELECT telegram_bot_token, telegram_chat_id, telegram_notifications_enabled FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                if (!empty($s['telegram_bot_token'])) {
                    self::$config['bot_token'] = trim($s['telegram_bot_token']);
                }
                if (!empty($s['telegram_chat_id'])) {
                    self::$config['default_notification_chat_id'] = trim($s['telegram_chat_id']);
                    if (!isset(self::$config['allowed_chat_ids'])) {
                        self::$config['allowed_chat_ids'] = [];
                    }
                    if (!in_array(trim($s['telegram_chat_id']), self::$config['allowed_chat_ids'])) {
                        self::$config['allowed_chat_ids'][] = trim($s['telegram_chat_id']);
                    }
                }
            } catch (Exception $e) {
                // Hata durumunda config/telegram.php değerini koru
            }
        }
        return self::$config;
    }

    /**
     * Telegram Bot API'ye HTTP POST isteği gönderir
     */
    public static function apiRequest(string $method, array $params = []): array
    {
        $config = self::getConfig();
        $token = $config['bot_token'] ?? '';
        if (empty($token) || str_contains($token, 'AAExampleBotToken')) {
            // Test veya yapılandırılmamış ortamlar için yerel log
            return [
                'ok' => true,
                'simulated' => true,
                'method' => $method,
                'params' => $params
            ];
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err];
        }

        $json = json_decode($response, true);
        return is_array($json) ? $json : ['ok' => false, 'raw' => $response];
    }

    /**
     * Mesaj gönderir
     */
    public static function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): array
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($keyboard) {
            $params['reply_markup'] = $keyboard;
        }
        return self::apiRequest('sendMessage', $params);
    }

    /**
     * Inline Callback Sorgusunu yanıtlar
     */
    public static function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        return self::apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert
        ]);
    }

    /**
     * Mesaj metnini günceller (Inline buton tıklandığında durumu yenilemek için)
     */
    public static function editMessageText(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): array
    {
        $params = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML'
        ];
        if ($keyboard) {
            $params['reply_markup'] = $keyboard;
        }
        return self::apiRequest('editMessageText', $params);
    }

    /**
     * Gelen Telegram Webhook güncellemesini (Update) işler
     */
    public static function handleUpdate(array $update): array
    {
        global $db;
        if (!$db || empty($db->connect)) {
            require_once dirname(__DIR__, 2) . '/Core/DataLayer.php';
            $db = new \Database();
        }

        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        // 1. INLINE BUTON TIKLAMASI (Callback Query)
        if (isset($update['callback_query'])) {
            $cq = $update['callback_query'];
            $cqId = $cq['id'];
            $chatId = $cq['message']['chat']['id'] ?? 0;
            $msgId = $cq['message']['message_id'] ?? 0;
            $data = $cq['data'] ?? '';

            // Örnek: status:TSK-104:in_progress
            if (str_starts_with($data, 'status:')) {
                $parts = explode(':', $data);
                if (count($parts) === 3) {
                    $taskCode = $parts[1];
                    $newStatus = $parts[2];
                    $res = self::updateTaskStatusFromTelegram($taskCode, $newStatus);

                    if ($res['success']) {
                        $cbSuccessMsg = $isTr 
                            ? "✅ {$taskCode} durumu '{$newStatus}' olarak güncellendi!"
                            : "✅ {$taskCode} status updated to '{$newStatus}'!";
                        self::answerCallbackQuery($cqId, $cbSuccessMsg);

                        $updatedText = $isTr
                            ? "⚡ <b>Görev Güncellendi:</b> [{$taskCode}]\n📌 <b>Yeni Durum:</b> " . self::getStatusLabel($newStatus, 'tr') . "\n🕒 <b>İşlem Zamanı:</b> " . date('H:i:s')
                            : "⚡ <b>Task Updated:</b> [{$taskCode}]\n📌 <b>New Status:</b> " . self::getStatusLabel($newStatus, 'en') . "\n🕒 <b>Time:</b> " . date('H:i:s');

                        self::editMessageText($chatId, $msgId, $updatedText, null);
                        return ['status' => 'handled', 'action' => 'status_updated'];
                    } else {
                        $errLabel = $isTr ? "❌ Hata: " : "❌ Error: ";
                        $fallbackErr = $isTr ? 'İşlem başarısız' : 'Operation failed';
                        self::answerCallbackQuery($cqId, $errLabel . ($res['error'] ?? $fallbackErr), true);
                        return ['status' => 'error', 'message' => $res['error']];
                    }
                }
            }
            self::answerCallbackQuery($cqId, $isTr ? "İşlem alındı." : "Action received.");
            return ['status' => 'callback_handled'];
        }

        // 2. NORMAL MESAJ
        if (!isset($update['message'])) {
            return ['status' => 'ignored', 'reason' => 'No message found'];
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'] ?? 0;
        $text = trim($message['text'] ?? '');

        if (empty($text)) {
            return ['status' => 'ignored', 'reason' => 'Empty text'];
        }

        // Komut ayrıştırıcı
        $cmdParts = explode(' ', $text);
        $command = strtolower($cmdParts[0]);

        switch ($command) {
            case '/start':
            case '/yardim':
            case '/help':
                if ($isTr) {
                    $help = "👋 <b>TaskManager Telegram Botuna Hoş Geldiniz!</b>\n\n" .
                            "Masaüstünden uzaktayken cep telefonunuzdan tüm görevleri yönetebilirsiniz:\n\n" .
                            "🔹 <b>/gorevler</b> veya <b>/tasks</b> — Bekleyen ve aktif görevleri listeler.\n" .
                            "🔹 <b>/calisiyor TSK-104</b> veya <b>/starttask</b> — Görevi 'Çalışılıyor' durumuna çeker.\n" .
                            "🔹 <b>/tamamla TSK-104</b> veya <b>/done</b> — Görevi tamamlandı / incelemeye teslim eder.\n" .
                            "🔹 <b>/durum TSK-104 [durum]</b> veya <b>/status</b> — Durumu manuel ayarlar.\n" .
                            "🔹 <b>/yenigorev [Başlık]</b> veya <b>/newtask</b> — Anında yeni görev oluşturur.\n" .
                            "🔹 <b>/ara [kelime]</b> veya <b>/search</b> — Görev arar.";
                } else {
                    $help = "👋 <b>Welcome to TaskManager Telegram Bot!</b>\n\n" .
                            "Manage all your tasks directly from your mobile device:\n\n" .
                            "🔹 <b>/tasks</b> (or <b>/gorevler</b>) — Lists pending and active tasks.\n" .
                            "🔹 <b>/starttask TSK-104</b> (or <b>/calisiyor</b>) — Marks task as 'In Progress'.\n" .
                            "🔹 <b>/done TSK-104</b> (or <b>/tamamla</b>) — Submits task for review.\n" .
                            "🔹 <b>/status TSK-104 [status]</b> — Manually sets task status.\n" .
                            "🔹 <b>/newtask [Title]</b> — Instantly creates a new task.\n" .
                            "🔹 <b>/search [query]</b> — Search tasks.";
                }
                self::sendMessage($chatId, $help);
                return ['status' => 'handled', 'command' => $command];

            case '/gorevler':
            case '/tasks':
            case '/list':
                $tasks = $db->multi_record_query("
                    SELECT id, task_code, title, status, priority, deadline 
                    FROM tasks 
                    WHERE status IN ('assigned', 'in_progress', 'modification_requested')
                    ORDER BY id DESC LIMIT 10
                ") ?: [];

                if (empty($tasks)) {
                    $emptyMsg = $isTr 
                        ? "🎉 Harika! Şu anda bekleyen veya çalışılan aktif görev bulunmuyor."
                        : "🎉 Great! There are no active or pending tasks at the moment.";
                    self::sendMessage($chatId, $emptyMsg);
                    return ['status' => 'handled', 'count' => 0];
                }

                $reply = $isTr 
                    ? "📋 <b>Aktif & Bekleyen Görevler Listesi:</b>\n\n"
                    : "📋 <b>Active & Pending Tasks List:</b>\n\n";

                foreach ($tasks as $t) {
                    $stBadge = self::getStatusLabel($t->status, $isTr ? 'tr' : 'en');
                    $dueText = $t->deadline ?: ($isTr ? 'Belirtilmedi' : 'Unspecified');
                    $statusPrefix = $isTr ? 'Durum' : 'Status';
                    $duePrefix = $isTr ? 'Bitiş' : 'Due';
                    $reply .= "• <b>[{$t->task_code}]</b> {$t->title}\n" .
                              "   {$statusPrefix}: {$stBadge} | {$duePrefix}: {$dueText}\n";
                }
                $hint = $isTr 
                    ? "\n👉 Bir görevi çalışılıyor yapmak için:\n<code>/calisiyor TSK-XXX</code>"
                    : "\n👉 To mark a task as in progress:\n<code>/starttask TSK-XXX</code>";
                $reply .= $hint;
                self::sendMessage($chatId, $reply);
                return ['status' => 'handled', 'count' => count($tasks)];

            case '/calisiyor':
            case '/starttask':
            case '/inprogress':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                if (empty($taskCode)) {
                    $missingMsg = $isTr
                        ? "⚠️ Lütfen görev kodunu belirtin.\nÖrnek: <code>/calisiyor TSK-104</code>"
                        : "⚠️ Please specify the task code.\nExample: <code>/starttask TSK-104</code>";
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, 'in_progress');
                if ($res['success']) {
                    $okMsg = $isTr
                        ? "🚀 <b>[{$taskCode}]</b> görevi başarıyla <b>ÇALIŞILIYOR</b> durumuna alındı!\nPanoda anlık SSE güncellemesi yayınlandı."
                        : "🚀 Task <b>[{$taskCode}]</b> successfully marked as <b>IN PROGRESS</b>!\nRealtime SSE update broadcasted to the board.";
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = $isTr ? "❌ Hata: " : "❌ Error: ";
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/tamamla':
            case '/done':
            case '/submit':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                if (empty($taskCode)) {
                    $missingMsg = $isTr
                        ? "⚠️ Lütfen görev kodunu belirtin.\nÖrnek: <code>/tamamla TSK-104</code>"
                        : "⚠️ Please specify the task code.\nExample: <code>/done TSK-104</code>";
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, 'submitted');
                if ($res['success']) {
                    $okMsg = $isTr
                        ? "✅ <b>[{$taskCode}]</b> görevi <b>İNCELEMEDE / TESLİM EDİLDİ</b> durumuna alındı!"
                        : "✅ Task <b>[{$taskCode}]</b> marked as <b>SUBMITTED / UNDER REVIEW</b>!";
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = $isTr ? "❌ Hata: " : "❌ Error: ";
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/durum':
            case '/status':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                $statusInput = strtolower($cmdParts[2] ?? '');
                if (empty($taskCode) || empty($statusInput)) {
                    $usageMsg = $isTr
                        ? "⚠️ Kullanım: <code>/durum [GÖREV_KODU] [DURUM]</code>\nÖrnek: <code>/durum TSK-104 in_progress</code>\nGeçerli durumlar: assigned, in_progress, submitted, completed"
                        : "⚠️ Usage: <code>/status [TASK_CODE] [STATUS]</code>\nExample: <code>/status TSK-104 in_progress</code>\nValid statuses: assigned, in_progress, submitted, completed";
                    self::sendMessage($chatId, $usageMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, $statusInput);
                if ($res['success']) {
                    $okMsg = $isTr
                        ? "👌 <b>[{$taskCode}]</b> görevinin yeni durumu: <b>" . self::getStatusLabel($statusInput, 'tr') . "</b>"
                        : "👌 New status for <b>[{$taskCode}]</b>: <b>" . self::getStatusLabel($statusInput, 'en') . "</b>";
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = $isTr ? "❌ Hata: " : "❌ Error: ";
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/yenigorev':
            case '/newtask':
                array_shift($cmdParts);
                $titleText = trim(implode(' ', $cmdParts));
                if (empty($titleText)) {
                    $missingMsg = $isTr
                        ? "⚠️ Lütfen görev başlığı yazın.\nÖrnek: <code>/yenigorev Müşteri toplantısı revizyonları</code>"
                        : "⚠️ Please provide a task title.\nExample: <code>/newtask Client meeting revisions</code>";
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }

                $projectId = 1;
                $lastId = (int)$db->single_query("SELECT MAX(id) FROM tasks") ?: 0;
                $nextCode = 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
                $taskDesc = $isTr ? "Telegram Botu üzerinden açıldı." : "Created via Telegram Bot.";

                $newId = $db->insert_return_count('tasks', [
                    'project_id', 'task_code', 'title', 'description', 'priority', 'status', 'created_by', 'assigned_to', 'deadline', 'created_at'
                ], [
                    $projectId, $nextCode, $titleText, $taskDesc, 'normal', 'assigned', 1, 1, date('Y-m-d', strtotime('+3 days')), date('Y-m-d H:i:s')
                ]);

                if ($newId > 0) {
                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', $projectId, $newId, 1, [
                        'task_id'   => $newId,
                        'task_code' => $nextCode,
                        'title'     => $titleText,
                        'status'    => 'assigned'
                    ]);

                    $startBtn = $isTr ? '▶️ Çalışılıyor Yap' : '▶️ Mark In Progress';
                    $detailBtn = $isTr ? '🔍 Detay' : '🔍 View Details';

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $startBtn, 'callback_data' => "status:{$nextCode}:in_progress"],
                                ['text' => $detailBtn, 'url' => "http://localhost/taskmanager/board?project_id=1"]
                            ]
                        ]
                    ];
                    $createdMsg = $isTr
                        ? "✨ <b>Yeni Görev Oluşturuldu!</b>\n\n📋 <b>Kod:</b> <code>{$nextCode}</code>\n📝 <b>Başlık:</b> {$titleText}\n📌 <b>Durum:</b> Yapılacak İşler\n👤 <b>Atanan:</b> Talha Keskin"
                        : "✨ <b>New Task Created!</b>\n\n📋 <b>Code:</b> <code>{$nextCode}</code>\n📝 <b>Title:</b> {$titleText}\n📌 <b>Status:</b> Assigned / To-Do\n👤 <b>Assignee:</b> Talha Keskin";

                    self::sendMessage($chatId, $createdMsg, $keyboard);
                } else {
                    $dbErr = $isTr 
                        ? "❌ Görev oluşturulurken veritabanı hatası meydana geldi." 
                        : "❌ A database error occurred while creating the task.";
                    self::sendMessage($chatId, $dbErr);
                }
                return ['status' => 'handled', 'created_task' => $nextCode ?? null];

            default:
                $unknownMsg = $isTr 
                    ? "❓ Bilinmeyen komut. Komut listesi için /start yazabilirsiniz."
                    : "❓ Unknown command. Type /start for the list of available commands.";
                self::sendMessage($chatId, $unknownMsg);
                return ['status' => 'unknown_command'];
        }
    }

    /**
     * Telegram üzerinden gelen komutla görev durumunu günceller ve canlı SSE yayınlar
     */
    public static function updateTaskStatusFromTelegram(string $taskCode, string $newStatus): array
    {
        global $db;
        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        $task = $db->row_query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$taskCode]);
        if (!$task) {
            $err = $isTr ? "Görev bulunamadı: {$taskCode}" : "Task not found: {$taskCode}";
            return ['success' => false, 'error' => $err];
        }

        $validStatuses = ['assigned', 'in_progress', 'modification_requested', 'submitted', 'completed'];
        if (!in_array($newStatus, $validStatuses, true)) {
            $err = $isTr ? "Geçersiz durum: {$newStatus}" : "Invalid status: {$newStatus}";
            return ['success' => false, 'error' => $err];
        }

        $oldStatus = $task->status;
        $db->exec_query("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $task->id]);

        // Aktivite günlüğü
        $actComment = $isTr 
            ? "📱 Telegram Botu üzerinden durum '{$oldStatus}' -> '{$newStatus}' olarak güncellendi."
            : "📱 Status updated via Telegram Bot: '{$oldStatus}' -> '{$newStatus}'.";

        $db->insert('task_activities', [
            'task_id', 'user_id', 'action_type', 'old_value', 'new_value', 'comment', 'created_at'
        ], [
            $task->id, 1, 'status_change', $oldStatus, $newStatus,
            $actComment, date('Y-m-d H:i:s')
        ]);

        // Canlı SSE yayını (Masaüstündeki ekranda anında kart kaysın)
        $botUserName = $isTr ? 'Telegram Bot (Mobil)' : 'Telegram Bot (Mobile)';
        \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_updated', (int)$task->project_id, (int)$task->id, 1, [
            'task_id'    => (int)$task->id,
            'task_code'  => $task->task_code,
            'title'      => $task->title,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_name'  => $botUserName
        ]);

        return ['success' => true, 'task_id' => $task->id, 'new_status' => $newStatus];
    }

    /**
     * Durum kodunu kullanıcı dostu etikete çevirir
     */
    public static function getStatusLabel(string $status, ?string $lang = null): string
    {
        $isTr = ($lang === 'tr') || (!$lang && class_exists('Language') && Language::getCurrentLanguage() === 'tr');
        if ($isTr) {
            $map = [
                'assigned'               => '🔵 Yapılacak İşler',
                'in_progress'            => '🟣 Çalışılıyor',
                'modification_requested' => '🟠 Revizyon İstendi',
                'submitted'              => '🟣 İncelemede / Teslim',
                'completed'              => '🟢 Tamamlandı'
            ];
        } else {
            $map = [
                'assigned'               => '🔵 Assigned / To-Do',
                'in_progress'            => '🟣 In Progress',
                'modification_requested' => '🟠 Revision Requested',
                'submitted'              => '🟣 Under Review / Submitted',
                'completed'              => '🟢 Completed'
            ];
        }
        return $map[$status] ?? $status;
    }

    /**
     * Bot bağlantısını test eder (getMe çağrısı)
     */
    public static function testConnection(): array
    {
        $config = self::getConfig();
        $token = $config['bot_token'] ?? '';
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Telegram Bot Token is not configured. Please save your token in System Settings.'
            ];
        }

        if (str_contains($token, 'AAExampleBotToken')) {
            return [
                'success' => true,
                'is_simulated' => true,
                'message' => 'Simulated Test Mode: Bot token has not been updated with a real API key.'
            ];
        }

        $res = self::apiRequest('getMe');
        if (!empty($res['ok']) && !empty($res['result'])) {
            $botInfo = $res['result'];
            return [
                'success' => true,
                'is_simulated' => false,
                'bot_name' => $botInfo['first_name'] ?? 'Bot',
                'username' => '@' . ($botInfo['username'] ?? ''),
                'bot_id'   => $botInfo['id'] ?? 0,
                'message'  => "✅ Connection Successful! Bot: {$botInfo['first_name']} (@{$botInfo['username']})"
            ];
        }

        $errDesc = $res['description'] ?? 'Telegram API did not respond or token is invalid.';
        return [
            'success' => false,
            'message' => "❌ Telegram Connection Error: {$errDesc}"
        ];
    }

    /**
     * Localhost veya geliştirme ortamında Webhook olmadan komutları dinlemek için getUpdates (Long-Polling)
     */
    public static function pollUpdates(int $offset = 0): array
    {
        $res = self::apiRequest('getUpdates', [
            'offset' => $offset,
            'limit' => 20,
            'timeout' => 5
        ]);

        if (empty($res['ok']) || empty($res['result']) || !is_array($res['result'])) {
            return [
                'success' => false,
                'updates_processed' => 0,
                'new_offset' => $offset,
                'message' => $res['description'] ?? 'Failed to receive updates'
            ];
        }

        $processed = 0;
        $maxUpdateId = $offset;

        foreach ($res['result'] as $update) {
            $uId = (int)($update['update_id'] ?? 0);
            if ($uId >= $maxUpdateId) {
                $maxUpdateId = $uId + 1;
            }
            self::handleUpdate($update);
            $processed++;
        }

        return [
            'success' => true,
            'updates_processed' => $processed,
            'new_offset' => $maxUpdateId
        ];
    }
}
