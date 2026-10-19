<?php

use Modules\Telegram\Models\TelegramModel;

/**
 * TaskManager Telegram Bot Entegrasyon & Otonom Yönetim Motoru
 * Dışarıdayken cep telefonundan görev durumlarını değiştirme, liste sorgulama ve anlık bildirim alma motorudur.
 */
class Telegram
{
    private static ?array $config = null;
    private static ?TelegramModel $model = null;

    private static function getModel(): TelegramModel
    {
        if (self::$model === null) {
            self::$model = new TelegramModel();
        }
        return self::$model;
    }

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
                $s = self::getModel()->getTelegramSettings();
                if ($s) {
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
                }
            } catch (\Throwable $e) {
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
        if (empty($token)) {
            return ['ok' => false, 'description' => 'Bot token is empty'];
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'description' => 'cURL Error: ' . $err];
        }

        $data = json_decode($res, true);
        return is_array($data) ? $data : ['ok' => false, 'description' => 'Invalid JSON response from Telegram API'];
    }

    /**
     * Belirtilen Chat ID'ye mesaj iletir
     */
    public static function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        if ($replyMarkup) {
            $params['reply_markup'] = $replyMarkup;
        }
        return self::apiRequest('sendMessage', $params);
    }

    /**
     * Standart sistem bildirim kanalı mesajı
     */
    public static function sendNotification(string $text, ?array $replyMarkup = null): array
    {
        $config = self::getConfig();
        $chatId = $config['default_notification_chat_id'] ?? null;
        if (empty($chatId)) {
            return ['ok' => false, 'description' => 'Default notification chat_id is not set.'];
        }
        return self::sendMessage($chatId, $text, $replyMarkup);
    }

    /**
     * İnline Buton tıklandığında Telegram loading animasyonunu kapatır
     */
    public static function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }
        return self::apiRequest('answerCallbackQuery', $params);
    }

    /**
     * Var olan mesaj metnini dinamik olarak günceller
     */
    public static function editMessageText(string|int $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup) {
            $params['reply_markup'] = $replyMarkup;
        }
        return self::apiRequest('editMessageText', $params);
    }

    /**
     * Webhook veya Polling üzerinden gelen Telegram Update paketini işler
     */
    public static function handleUpdate(array $update): array
    {
        $config = self::getConfig();
        $allowedChatIds = $config['allowed_chat_ids'] ?? [];

        // 1. CALLBACK QUERY (Buton Tıklaması)
        if (isset($update['callback_query'])) {
            $cq = $update['callback_query'];
            $cqId = $cq['id'];
            $cqData = $cq['data'] ?? '';
            $msg = $cq['message'] ?? [];
            $chatId = $msg['chat']['id'] ?? 0;
            $msgId = $msg['message_id'] ?? 0;

            if (!empty($allowedChatIds) && !in_array((string)$chatId, array_map('strval', $allowedChatIds), true)) {
                self::answerCallbackQuery($cqId, 'Unauthorized Chat ID', true);
                return ['status' => 'rejected', 'reason' => 'Unauthorized chat ID'];
            }

            if (str_starts_with($cqData, 'status:')) {
                $parts = explode(':', $cqData);
                if (count($parts) >= 3) {
                    $taskCode = $parts[1];
                    $newStatus = $parts[2];
                    $res = self::updateTaskStatusFromTelegram($taskCode, $newStatus);

                    if ($res['success']) {
                        $cbSuccessMsg = str_replace(['{code}', '{status}'], [$taskCode, $newStatus], __('Telegram::status_updated_cb'));
                        self::answerCallbackQuery($cqId, $cbSuccessMsg);

                        $header = str_replace('{code}', $taskCode, __('Telegram::task_updated_header'));
                        $statusText = str_replace('{status}', self::getStatusLabel($newStatus), __('Telegram::new_status_label'));
                        $timeText = str_replace('{time}', date('H:i:s'), __('Telegram::process_time_label'));
                        $updatedText = "{$header}\n{$statusText}\n{$timeText}";

                        self::editMessageText($chatId, $msgId, $updatedText, null);
                        return ['status' => 'handled', 'action' => 'status_updated'];
                    } else {
                        $errLabel = __('Telegram::error_prefix');
                        $fallbackErr = __('Telegram::operation_failed');
                        self::answerCallbackQuery($cqId, $errLabel . ($res['error'] ?? $fallbackErr), true);
                        return ['status' => 'error', 'message' => $res['error']];
                    }
                }
            }
            self::answerCallbackQuery($cqId, __('Telegram::action_received'));
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
                $help = __('Telegram::help_welcome') . "\n\n" .
                        __('Telegram::help_desc') . "\n\n" .
                        __('Telegram::help_cmd_tasks') . "\n" .
                        __('Telegram::help_cmd_start') . "\n" .
                        __('Telegram::help_cmd_done') . "\n" .
                        __('Telegram::help_cmd_status') . "\n" .
                        __('Telegram::help_cmd_new') . "\n" .
                        __('Telegram::help_cmd_search');
                self::sendMessage($chatId, $help);
                return ['status' => 'handled', 'command' => $command];

            case '/gorevler':
            case '/tasks':
            case '/list':
                $tasks = self::getModel()->getActiveTasks(10);

                if (empty($tasks)) {
                    $emptyMsg = __('Telegram::no_active_tasks');
                    self::sendMessage($chatId, $emptyMsg);
                    return ['status' => 'handled', 'count' => 0];
                }

                $reply = __('Telegram::active_tasks_header') . "\n\n";

                foreach ($tasks as $t) {
                    $item = (object)$t;
                    $stBadge = self::getStatusLabel($item->status);
                    $dueText = $item->deadline ?: __('Telegram::unspecified');
                    $statusPrefix = __('Telegram::field_status');
                    $duePrefix = __('Telegram::field_due');
                    $reply .= "• <b>[{$item->task_code}]</b> {$item->title}\n" .
                              "   {$statusPrefix}: {$stBadge} | {$duePrefix}: {$dueText}\n";
                }
                $reply .= "\n" . __('Telegram::tasks_hint');
                self::sendMessage($chatId, $reply);
                return ['status' => 'handled', 'count' => count($tasks)];

            case '/calisiyor':
            case '/starttask':
            case '/inprogress':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                if (empty($taskCode)) {
                    $missingMsg = __('Telegram::missing_task_code');
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, 'in_progress');
                if ($res['success']) {
                    $okMsg = str_replace('{code}', $taskCode, __('Telegram::task_started_success'));
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = __('Telegram::error_prefix');
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/tamamla':
            case '/done':
            case '/submit':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                if (empty($taskCode)) {
                    $missingMsg = __('Telegram::missing_task_code_done');
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, 'submitted');
                if ($res['success']) {
                    $okMsg = str_replace('{code}', $taskCode, __('Telegram::task_submitted_success'));
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = __('Telegram::error_prefix');
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/durum':
            case '/status':
                $taskCode = strtoupper($cmdParts[1] ?? '');
                $statusInput = strtolower($cmdParts[2] ?? '');
                if (empty($taskCode) || empty($statusInput)) {
                    $usageMsg = __('Telegram::status_usage');
                    self::sendMessage($chatId, $usageMsg);
                    return ['status' => 'invalid_params'];
                }
                $res = self::updateTaskStatusFromTelegram($taskCode, $statusInput);
                if ($res['success']) {
                    $okMsg = str_replace(['{code}', '{status}'], [$taskCode, self::getStatusLabel($statusInput)], __('Telegram::status_updated_msg'));
                    self::sendMessage($chatId, $okMsg);
                } else {
                    $errPrefix = __('Telegram::error_prefix');
                    self::sendMessage($chatId, $errPrefix . $res['error']);
                }
                return ['status' => 'handled', 'result' => $res];

            case '/yenigorev':
            case '/newtask':
                array_shift($cmdParts);
                $titleText = trim(implode(' ', $cmdParts));
                if (empty($titleText)) {
                    $missingMsg = __('Telegram::missing_task_title');
                    self::sendMessage($chatId, $missingMsg);
                    return ['status' => 'invalid_params'];
                }

                $projectId = 1;
                $lastId = self::getModel()->getLastTaskId();
                $nextCode = 'TSK-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
                $taskDesc = __('Telegram::created_via_bot');

                $newId = self::getModel()->createTask([
                    'project_id'  => $projectId,
                    'task_code'   => $nextCode,
                    'title'       => $titleText,
                    'description' => $taskDesc,
                    'priority'    => 'normal',
                    'status'      => 'assigned',
                    'created_by'  => 1,
                    'assigned_to' => 1,
                    'deadline'    => date('Y-m-d', strtotime('+3 days')),
                    'created_at'  => date('Y-m-d H:i:s')
                ]);

                if ($newId > 0) {
                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_created', $projectId, $newId, 1, [
                        'task_id'   => $newId,
                        'task_code' => $nextCode,
                        'title'     => $titleText,
                        'status'    => 'assigned'
                    ]);

                    $startBtn = __('Telegram::btn_start');
                    $detailBtn = __('Telegram::btn_details');

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => $startBtn, 'callback_data' => "status:{$nextCode}:in_progress"],
                                ['text' => $detailBtn, 'url' => "http://localhost/taskmanager/board?project_id=1"]
                            ]
                        ]
                    ];

                    $createdHeader = __('Telegram::new_task_created_header');
                    $codeLine = __('Telegram::field_code') . ": <code>{$nextCode}</code>";
                    $titleLine = __('Telegram::field_title') . ": {$titleText}";
                    $statusLine = __('Telegram::field_status') . ": " . self::getStatusLabel('assigned');
                    $assigneeLine = str_replace('{name}', 'Talha Keskin', __('Telegram::assignee_label'));

                    $createdMsg = "{$createdHeader}\n\n📋 {$codeLine}\n📝 {$titleLine}\n📌 {$statusLine}\n{$assigneeLine}";

                    self::sendMessage($chatId, $createdMsg, $keyboard);
                } else {
                    $dbErr = __('Telegram::db_create_error');
                    self::sendMessage($chatId, $dbErr);
                }
                return ['status' => 'handled', 'created_task' => $nextCode ?? null];

            default:
                $unknownMsg = __('Telegram::unknown_command');
                self::sendMessage($chatId, $unknownMsg);
                return ['status' => 'unknown_command'];
        }
    }

    /**
     * Telegram üzerinden gelen komutla görev durumunu günceller ve canlı SSE yayınlar
     */
    public static function updateTaskStatusFromTelegram(string $taskCode, string $newStatus): array
    {
        $task = self::getModel()->findByTaskCode($taskCode);
        if (!$task) {
            $err = str_replace('{code}', $taskCode, __('Telegram::task_not_found'));
            return ['success' => false, 'error' => $err];
        }
        $task = (object)$task;

        $validStatuses = ['assigned', 'in_progress', 'modification_requested', 'submitted', 'completed'];
        if (!in_array($newStatus, $validStatuses, true)) {
            $err = str_replace('{status}', $newStatus, __('Telegram::invalid_status'));
            return ['success' => false, 'error' => $err];
        }

        $oldStatus = $task->status;
        self::getModel()->updateTaskStatus((int)$task->id, $newStatus);

        // Aktivite günlüğü
        $actComment = str_replace(['{old}', '{new}'], [$oldStatus, $newStatus], __('Telegram::bot_activity_log'));

        self::getModel()->logActivity(
            (int)$task->id,
            1,
            'status_change',
            $oldStatus,
            $newStatus,
            $actComment
        );

        // Canlı SSE yayını (Masaüstündeki ekranda anında kart kaysın)
        $botUserName = __('Telegram::bot_user_name');
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
    public static function getStatusLabel(string $status): string
    {
        $map = [
            'assigned'               => __('Telegram::status_assigned'),
            'in_progress'            => __('Telegram::status_in_progress'),
            'modification_requested' => __('Telegram::status_modification'),
            'submitted'              => __('Telegram::status_submitted'),
            'completed'              => __('Telegram::status_completed')
        ];
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
