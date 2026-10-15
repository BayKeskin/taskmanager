<?php

class Helper
{
    /**
     * Çoklu dil destekli SEO / URL dostu slug oluşturucu (TR, DE, EN vb.)
     */
    public static function slug(string $text): string
    {
        $charMap = [
            // Türkçe
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
            // Almanca
            'ä' => 'ae', 'Ä' => 'ae', 'ß' => 'ss',
            // Fransızca / İspanyolca / İtalyanca
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ñ' => 'n',
            // Semboller
            '+' => 'plus', '#' => 'sharp', '&' => 'and'
        ];
        $text = strtr($text, $charMap);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-_\.]+/i', '-', $text);
        $text = trim($text, '-');
        return preg_replace('/-+/', '-', $text);
    }

    /**
     * Tarih formatlayıcı (Y-m-d -> d-m-Y)
     */
    public static function date(string $date, string $separator = '-'): string
    {
        $parts = explode('-', $date);
        if (count($parts) === 3) {
            return $parts[2] . $separator . $parts[1] . $separator . $parts[0];
        }
        return $date;
    }

    /**
     * Tarih ve Saat formatlayıcı
     */
    public static function dateTime(string $dateTime): string
    {
        $parts = explode(' ', $dateTime);
        if (count($parts) === 2) {
            $d = explode('-', $parts[0]);
            $t = explode(':', $parts[1]);
            if (count($d) === 3 && count($t) >= 2) {
                return $d[2] . '/' . $d[1] . '/' . $d[0] . ' ' . $t[0] . ':' . $t[1];
            }
        }
        return $dateTime;
    }

    /**
     * İngilizce ay isimli tarih formatı: "January 15, 2026"
     */
    public static function format_date_mounthname(string $mysql_datetime): string
    {
        $timestamp = strtotime($mysql_datetime);
        return $timestamp !== false ? date('F j, Y', $timestamp) : "Invalid Date";
    }

    /**
     * Türkçe ay isimli tarih formatı: "15 Ocak 2026"
     */
    public static function format_date_mounthnameInTurkish(string $mysql_datetime): string
    {
        return self::format_date_localized($mysql_datetime);
    }

    /**
     * Çok dilli tarih formatı (Multi-lingual localized date formatter: TR, EN, DE)
     * Örn: "15 Ekim 2026", "October 15, 2026", "15. Oktober 2026"
     */
    public static function format_date_localized(string $mysql_datetime, ?string $locale = null): string
    {
        $timestamp = strtotime($mysql_datetime);
        if ($timestamp === false) {
            $cur = (class_exists('Language') ? Language::current() : 'en');
            if ($cur === 'tr') return "Geçersiz Tarih";
            if ($cur === 'de') return "Ungültiges Datum";
            return "Invalid Date";
        }

        if ($locale === null && class_exists('Language')) {
            $locale = Language::current();
        }
        $locale = $locale ?? 'en';

        $day = date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $year = date('Y', $timestamp);

        $monthNames = [
            'tr' => [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'],
            'de' => [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'],
            'en' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
        ];

        $mName = $monthNames[$locale][$month] ?? $monthNames['en'][$month] ?? date('F', $timestamp);

        if ($locale === 'tr') {
            return "{$day} {$mName} {$year}";
        } elseif ($locale === 'de') {
            return "{$day}. {$mName} {$year}";
        } else {
            return "{$mName} {$day}, {$year}";
        }
    }

    /**
     * Tarihe gün ekleme
     */
    public static function add_date_day(string $who_date, int|string $which_day): string
    {
        return date('Y-m-d', strtotime("+{$which_day} day", strtotime($who_date)));
    }

    /**
     * Tarihten gün çıkarma
     */
    public static function delete_date_day(string $who_date, int|string $which_day): string
    {
        return date('Y-m-d', strtotime("-{$which_day} day", strtotime($who_date)));
    }

    /**
     * Yüzde hesaplama
     */
    public static function percentage_calculation(float|int $number, float|int $percentage): float|int
    {
        return ($number * $percentage) / 100;
    }

    /**
     * Rastgele User Agent üretici
     */
    public static function getRandomUserAgent(): string
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
            "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        ];
        return $userAgents[array_rand($userAgents)];
    }

    /**
     * HTTP Durum Kodları Başlığı
     */
    public static function HttpStatus(int $code): string
    {
        $status = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];
        return $status[$code] ?? 'Unknown Status';
    }

    /**
     * Bootstrap Alert HTML üretici
     */
    public static function alert(string $message, string $type = 'success'): void
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo "<div class=\"alert alert-{$type}\" role=\"alert\">{$escaped}</div>";
    }

    public static function alertSuccess(string $message): void
    {
        self::alert($message, 'success');
    }

    public static function alertError(string $message): void
    {
        self::alert($message, 'danger');
    }

    /**
     * Mevcut URI segmentini döner
     */
    public static function urlSegment(int $index = 1): string
    {
        $url = ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $explode = explode('/', $url);
        return isset($explode[$index]) ? str_replace('-', '', $explode[$index]) : '';
    }

    /**
     * Tam URL adresini döner
     */
    public static function fullUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . $host . $uri;
    }

    /**
     * Kullanıcı IP adresini döner
     */
    public static function ip(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Güvenli $_POST okuyucu
     */
    public static function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Güvenli $_GET okuyucu
     */
    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Aktif dosya / script adını döner
     */
    public static function activePage(): string
    {
        $file = explode('/', $_SERVER['SCRIPT_NAME'] ?? '');
        return end($file);
    }

    /**
     * JSON API Başlığı Ayarlar
     */
    public static function setHeader(int $code): void
    {
        header("HTTP/1.1 {$code} " . self::HttpStatus($code));
        header("Content-Type: application/json; charset=utf-8");
    }

    private static array $configCache = [];

    /**
     * config/ dizinindeki dosyalardan nokta notasyonu ile ayar okur.
     * Örnek: Helper::config('app.env') veya config('app.env')
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        if (count($parts) === 1 && $file === 'env') {
            $file = 'app';
            $parts = ['app', 'env'];
        }

        if (!isset(self::$configCache[$file])) {
            $path = __DIR__ . '/../config/' . $file . '.php';
            if (file_exists($path)) {
                self::$configCache[$file] = require $path;
            } else {
                self::$configCache[$file] = [];
            }
        }

        $value = self::$configCache[$file];
        for ($i = 1; $i < count($parts); $i++) {
            if (!is_array($value) || !array_key_exists($parts[$i], $value)) {
                return $default;
            }
            $value = $value[$parts[$i]];
        }
        return $value;
    }

    /**
     * Aktif çalışma ortamını döner ('development' veya 'production')
     */
    public static function env(): string
    {
        return strtolower((string)self::config('app.env', 'development'));
    }

    /**
     * Geliştirme (development) modunda mıyız?
     */
    public static function isDev(): bool
    {
        $env = self::env();
        return in_array($env, ['dev', 'development', 'local'], true);
    }

    /**
     * Canlı (production) modunda mıyız?
     */
    public static function isProduction(): bool
    {
        return !self::isDev();
    }

    /**
     * Projeye özel Yapay Zeka (AI Agent) Webhook Olay Tetikleyicisi
     */
    public static function triggerProjectWebhook(int $projectId, string $event, array $data = []): array
    {
        global $db;
        if (!$db || empty($db->connect)) {
            require_once __DIR__ . '/../Model/DataLayer.php';
            $db = new Database();
        }

        $project = $db->row_query("
            SELECT id, project_code, title, webhook_url, workspace_path, agent_rules, agent_conversation_id, webhook_secret 
            FROM projects 
            WHERE id = ?
        ", [$projectId]);

        $targetUrl = !empty($project->webhook_url) ? trim($project->webhook_url) : 'http://localhost:4000/webhook';

        $apiCfg = self::getDynamicApiConfig();

        $payload = [
            'event'                 => $event,
            'project_id'            => (int)$project->id,
            'project_code'          => $project->project_code,
            'project_title'         => $project->title,
            'workspace_path'        => $project->workspace_path ?: null,
            'agent_rules'           => $project->agent_rules ?: null,
            'agent_conversation_id' => $project->agent_conversation_id ?: null,
            'api_base_url'          => $apiCfg['base_url'],
            'api_token'             => $apiCfg['api_token'],
            'timestamp'             => date('c'),
            'data'                  => $data
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: TaskManager-Agent-Webhook/1.0',
            'X-TaskManager-Event: ' . $event,
            'X-TaskManager-Project: ' . $project->project_code,
        ];

        if (!empty($project->agent_conversation_id)) {
            $headers[] = 'X-TaskManager-Conversation-ID: ' . $project->agent_conversation_id;
        }

        if (!empty($project->webhook_secret)) {
            $signature = hash_hmac('sha256', $jsonPayload, $project->webhook_secret);
            $headers[] = 'X-TaskManager-Signature: sha256=' . $signature;
        }

        // Port 4000 hedefliyse ve kapalıysa arka planda otomatik ayağa kaldır (Self-Healing)
        if (str_contains($targetUrl, '4000') || str_contains($targetUrl, 'localhost') || empty($targetUrl)) {
            self::ensureDispatcherRunning();
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $sent = ($httpCode >= 200 && $httpCode < 300);
        $effectiveConvId = $project->agent_conversation_id;

        // KÖKTEN ÇÖZÜM (Native Fallback):
        // Eğer hedef localhost:4000 ise ve port kapalı/timeout ise VEYA yanıt başarısızsa;
        // Asla vazgeçme! Doğrudan PHP üzerinden Antigravity Agent API'yi dinamik olarak keşfedip fırlat.
        if (!$sent && (str_contains($targetUrl, 'localhost:4000') || str_contains($targetUrl, '127.0.0.1:4000') || empty($targetUrl))) {
            $directResult = self::dispatchDirectToAntigravity($project, $event, $data);
            if (!empty($directResult['sent'])) {
                $sent = true;
                $httpCode = 200;
                $response = json_encode($directResult, JSON_UNESCAPED_UNICODE);
                $error = null;
                if (!empty($directResult['recipient_id'])) {
                    $effectiveConvId = $directResult['recipient_id'];
                }
            }
        }

        // Webhook günlüğü tut (Public/uploads/webhook_logs.json)
        $logDir = __DIR__ . '/../Public/uploads';
        if (is_dir($logDir) && is_writable($logDir)) {
            $logEntry = [
                'time'                  => date('Y-m-d H:i:s'),
                'event'                 => $event,
                'project_code'          => $project->project_code,
                'agent_conversation_id' => $effectiveConvId,
                'url'                   => $project->webhook_url,
                'http_code'             => $httpCode,
                'error'                 => $error ?: null
            ];
            $logFile = $logDir . '/webhook_logs.json';
            $existing = file_exists($logFile) ? (json_decode(file_get_contents($logFile), true) ?: []) : [];
            array_unshift($existing, $logEntry);
            if (count($existing) > 50) $existing = array_slice($existing, 0, 50);
            @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Genel Slack & Discord Webhook Kanallarına Otomatik İletim
        self::dispatchChatWebhooks($event, $project->title ?? 'TaskManager', $data);

        return [
            'sent'                  => $sent,
            'http_code'             => $httpCode,
            'response'              => $response,
            'error'                 => $error ?: null,
            'agent_conversation_id' => $project->agent_conversation_id
        ];
    }

    /**
     * Slack ve Discord genel bildirim webhooklarını tetikler
     */
    public static function dispatchChatWebhooks(string $event, string $projectTitle, array $data = []): void
    {
        try {
            global $db;
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $settings = $db->row_query("SELECT slack_webhook_url, discord_webhook_url FROM settings WHERE id = 1");
            if (!$settings) return;

            $taskTitle = $data['title'] ?? ($data['task_code'] ?? 'Task Operation');
            $msgText = "🔔 [{$projectTitle}] {$event}: {$taskTitle}";

            // Slack Webhook
            if (!empty($settings->slack_webhook_url)) {
                self::postJsonWebhook(trim($settings->slack_webhook_url), ['text' => $msgText]);
            }

            // Discord Webhook
            if (!empty($settings->discord_webhook_url)) {
                self::postJsonWebhook(trim($settings->discord_webhook_url), ['content' => $msgText]);
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Basit ve güvenli JSON HTTP POST isteği
     */
    public static function postJsonWebhook(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [
            'success' => ($code >= 200 && $code < 300),
            'code'    => $code,
            'body'    => $res,
            'error'   => $err ?: null
        ];
    }

    /**
     * Antigravity SQLite oturumlarını tarayarak projeye ait en güncel açık pencereyi dinamik bulur.
     */
    public static function discoverLatestConversation(?string $workspacePath = null, ?string $projectCode = null): ?string
    {
        $convDir = 'C:/Users/DELL/.gemini/antigravity-ide/conversations';
        if (!is_dir($convDir)) {
            return null;
        }

        $targets = [];
        if (!empty($workspacePath)) {
            $targets[] = str_replace('\\', '/', strtolower(rtrim($workspacePath, '/')));
        }

        // workspaces.json eşleşme kontrolü
        $wsFile = dirname(__DIR__) . '/dispatcher/workspaces.json';
        if (file_exists($wsFile) && $projectCode) {
            $wsMap = json_decode(file_get_contents($wsFile), true) ?: [];
            if (!empty($wsMap[$projectCode])) {
                foreach ((array)$wsMap[$projectCode] as $p) {
                    $targets[] = str_replace('\\', '/', strtolower(rtrim($p, '/')));
                }
            }
        }

        if (empty($targets) && $projectCode) {
            $targets[] = 'c:/wamp64/www/' . strtolower($projectCode);
        }

        $targets = array_values(array_unique(array_filter($targets)));
        usort($targets, fn($a, $b) => strlen($b) - strlen($a));

        $files = glob($convDir . '/*.db') ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        foreach ($files as $file) {
            $cid = basename($file, '.db');
            try {
                $sdb = new \SQLite3($file, SQLITE3_OPEN_READONLY);
                $res = $sdb->querySingle("SELECT data FROM trajectory_metadata_blob WHERE id='main'");
                $sdb->close();
                if ($res) {
                    $decoded = rawurldecode((string)$res);
                    $decoded = strtolower(str_replace('\\', '/', $decoded));
                    foreach ($targets as $target) {
                        // Strict boundary: hedef klasör sonrasında - veya harf/rakam gelmemeli (örn. ufkayolculuk -> ufkayolculuk-kitap-okuma engeli)
                        $pattern = '~(?:^|[^\w\-\.])' . preg_quote($target, '~') . '(?:[/?#\"\'\s,\x00-\x1f\\\\]|$)~i';
                        if (preg_match($pattern, $decoded)) {
                            return $cid;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Kilitli / okunamayan db olursa atla
            }
        }

        return null;
    }

    /**
     * Sistemin güncel Site URL'sini ve aktif Admin API Token'ını veritabanından dinamik çeker.
     * Eğer sistem yeni kurulmuşsa veya token henüz oluşturulmamışsa otomatik üretip kaydeder!
     */
    public static function getDynamicApiConfig(): array
    {
        global $db;
        $baseUrl = 'http://localhost/taskmanager';
        $apiToken = '';

        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            // 1. Sistemin güncel URL'sini settings tablosundan al
            $sUrl = $db->single_query("SELECT siteurl FROM settings WHERE id = 1");
            if (!empty($sUrl)) {
                $baseUrl = rtrim(trim((string)$sUrl), '/');
            } elseif (!empty($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/taskmanager';
            }

            // 2. Aktif yöneticinin güncel api_token değerini al
            $adminUser = $db->row_query("
                SELECT id, api_token 
                FROM users 
                WHERE role = 'admin' AND status = 'active' 
                ORDER BY id ASC LIMIT 1
            ");

            if ($adminUser) {
                if (!empty($adminUser->api_token)) {
                    $apiToken = trim((string)$adminUser->api_token);
                } else {
                    // Sistem yeni kurulduysa ve adminin henüz token'ı yoksa otomatik üret!
                    $apiToken = 'tm_live_' . bin2hex(random_bytes(16));
                    $db->exec_query("UPDATE users SET api_token = ? WHERE id = ?", [$apiToken, $adminUser->id]);
                }
            }
        } catch (\Throwable $e) {}

        return [
            'base_url'  => $baseUrl,
            'api_token' => $apiToken
        ];
    }

    /**
     * Ajanın hangi dizinde veya kimin sunucusunda çalıştığından bağımsız olarak
     * dinamik URL ve dinamik Bearer Token ile görev teslim komutunu üretir.
     */
    public static function getAgentSubmitCommandBlock(string $taskCode): string
    {
        $cfg = self::getDynamicApiConfig();
        $endpoint = "{$cfg['base_url']}/api/tasks/{$taskCode}/workflow/submit";
        $token    = $cfg['api_token'];

        return "```powershell\n"
             . "\$headers = @{ 'Authorization' = 'Bearer {$token}'; 'Content-Type' = 'application/json; charset=utf-8' }\n"
             . "\$body = @{ notes = '1. What Was Done (Neler Yapıldı): ... 2. Where to Test (Nereden Test Edilir): ... 3. How to Test (Nasıl Test Edilir): ... 4. Test Evidence (Test Kanıtı): ...' } | ConvertTo-Json -Compress\n"
             . "Invoke-RestMethod -Uri '{$endpoint}' -Method Post -Headers \$headers -Body ([System.Text.Encoding]::UTF8.GetBytes(\$body))\n"
             . "```";
    }

    /**
     * Olay tipine göre Antigravity penceresine gidecek bildirim başlığı ve markdown içeriğini oluşturur.
     */
    public static function formatAgentMessage(string $event, object $project, array $data): array
    {
        $projectCode  = $project->project_code ?? '';
        $projectTitle = $project->title ?? '';
        $taskCode     = $data['task_code'] ?? '';
        $taskTitle    = $data['title'] ?? '';
        $priority     = $data['priority'] ?? 'normal';
        $isResumed    = !empty($data['resumed']);

        // Projeye Özel Yapay Zeka Kuralları (Project-Specific Agent Rules)
        $agentRules = !empty($project->agent_rules) ? trim($project->agent_rules) : '';
        if (empty($agentRules) && !empty($project->id)) {
            global $db;
            if ($db && !empty($db->connect)) {
                $agentRules = (string)($db->get_single("projects", "agent_rules", "id", $project->id) ?? '');
            }
        }

        $projectRulesBlock = '';
        if (!empty($agentRules)) {
            $projectRulesBlock = "\n\n---\n"
                . "📌 **PROJECT-SPECIFIC AGENT RULES (PROJEYE ÖZEL AJAN KURALLARI):**\n"
                . "> [!IMPORTANT]\n"
                . "> The following project-specific technical rules, constraints, and instructions are MANDATORY for [{$projectCode}] {$projectTitle}:\n\n"
                . $agentRules . "\n";
        }

        if (in_array($event, ['task.started', 'task.in_progress'], true)) {
            $titleHeader = $isResumed 
                ? "🔄 [RESUMING TASK] {$taskCode}: {$taskTitle}" 
                : "🚀 [IN PROGRESS] {$taskCode}: {$taskTitle}";
            $desc = !empty($data['description']) ? trim((string)$data['description']) : '*(No description provided)*';

            $checklistMd = '';
            if (!empty($data['checklists']) && is_array($data['checklists'])) {
                $checklistMd = "\n\n### ✅ Checklist:\n";
                foreach ($data['checklists'] as $item) {
                    $itemObj = is_object($item) ? $item : (object)$item;
                    $box = !empty($itemObj->is_completed) ? '[x]' : '[ ]';
                    $itemText = $itemObj->title ?? $itemObj->item_text ?? '';
                    $checklistMd .= "- {$box} {$itemText}\n";
                }
            }

            $assigneesStr = !empty($data['assignees']) ? implode(', ', (array)$data['assignees']) : 'Unassigned';
            $timeEstimate = $data['time_estimate'] ?? '-';
            $deadline     = $data['deadline'] ?? '-';
            $triggeredBy  = $data['triggered_by'] ?? 'User';

            $bannerText = $isResumed
                ? "🔄 **Interrupted Task Resumed! Returning to the pending task where you left off!**\n\n📌 **Status:** Previous priority/revision task was completed. This task is now un-suspended and running autonomously.\n\n"
                : "🚀 **TaskManager Task Marked as 'In Progress'!**\n\n";

            $messageText = "{$bannerText}"
                . "📌 **Project:** [{$projectCode}] {$projectTitle}\n"
                . "📋 **Task Code:** {$taskCode}\n"
                . "🏷️ **Title:** {$taskTitle}\n"
                . "⚡ **Priority:** {$priority} | ⏳ **Estimated Time:** {$timeEstimate} | 📅 **Due Date:** {$deadline}\n"
                . "👥 **Assignees:** {$assigneesStr} | 👤 **Triggered By:** {$triggeredBy}\n\n"
                . "### 📝 Task Description & Requirements:\n{$desc}"
                . "{$checklistMd}"
                . "{$projectRulesBlock}\n\n"
                . "---\n"
                . "🛡️ **MANDATORY QA DOCTRINE: \"THE USER IS NOT A QA TESTER\"**\n"
                . "> **🚨 RED LINE RULE:**\n"
                . "> Before an engineer or AI agent tells the user \"It's done, please check\", \"Try it now\", or marks any task as completed:\n"
                . "> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**\n"
                . "> Under NO circumstances shall the user ever encounter:\n"
                . "> 1. A blank or white screen (`Blank Screen`),\n"
                . "> 2. An unresolved PHP/SQL error, syntax error, warning, or crash (`Fatal error`, `Warning`, `Notice`),\n"
                . "> 3. An unresponsive, unclickable, or dead button,\n"
                . "> 4. Console JavaScript errors or network 404/500 failures,\n"
                . "> 5. Misaligned typography, poor borders, clipped elements, or responsive layout defects,\n"
                . "> 6. Ambiguous deliverables where what was done or how to test is unclear.\n"
                . "> **The User's Role:** The user is NOT a QA/test employee. Finding missing parts, broken buttons, or bugs is NEVER the user's responsibility. The user only reviews high-level product vision and user experience.\n"
                . "> **The Agent's Role:** Verifying and proving that the system functions logically, visually, and operationally with 100% perfection is 100% the Agent's responsibility.\n\n"
                . "📋 **DEFINITION OF DONE & DELIVERY PROTOCOL:**\n"
                . "Every completed task must provide an exhaustive summary formatted as follows (written in the language of the task or creator's default language):\n"
                . "1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.\n"
                . "2. **Where to Test (Nereden Test Edilir):** Direct testable URLs (e.g., `http://localhost/taskmanager/board?project_id=1`).\n"
                . "3. **How to Test (Nasıl Test Edilir):** Step-by-step credentials, actions, buttons to click, and expected outcomes.\n"
                . "4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Summary of automated headless browser and CLI test results.\n\n"
                . "🎯 **Directive:** This task is actively assigned and in progress. Focus EXCLUSIVELY on this specific task: [{$taskCode}]. Do NOT jump to older or previously completed tasks. If the user in the active chat gave a direct instruction or question, prioritize addressing the user.\n\n"
                . "🗄️ **DATABASE MIGRATION & ROLLBACK RULE:**\n"
                . "If your task introduces database modifications (CREATE TABLE, ALTER TABLE, INSERT, UPDATE, DROP), you MUST generate a reverse rollback SQL script inside the project directory: `.migrations/{$taskCode}_down.sql` so changes can be reverted if rejected. If no DB changes are made, do not create this file.\n\n"
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\n"
                . "When development and autonomous verification are 100% complete, deliver the task immediately from your current directory by running this HTTP POST command in your shell:\n"
                . self::getAgentSubmitCommandBlock($taskCode);
        } elseif ($event === 'task.revision_requested' || $event === 'task.modification_requested') {
            $titleHeader = "⚠️ [REVISION REQUEST] {$taskCode}: {$taskTitle}";
            $revReason = $data['revision_reason'] ?? '*(No reason specified)*';
            $desc = !empty($data['description']) ? trim((string)$data['description']) : '';
            $messageText = "⚠️ **TaskManager Revision Request!**\n\n"
                . "📌 **Project:** [{$projectCode}] {$projectTitle}\n"
                . "📋 **Task Code:** {$taskCode}\n"
                . "🏷️ **Title:** {$taskTitle}\n\n"
                . "🔴 **Requested Revision & Feedback:**\n{$revReason}\n\n"
                . "### 📝 Original Task Description:\n{$desc}"
                . "{$projectRulesBlock}\n\n"
                . "---\n"
                . "🛡️ **MANDATORY QA DOCTRINE: \"THE USER IS NOT A QA TESTER\"**\n"
                . "> **🚨 RED LINE RULE:**\n"
                . "> Before an engineer or AI agent tells the user \"It's done, please check\", \"Try it now\", or marks any task as completed:\n"
                . "> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**\n\n"
                . "🎯 **Directive:** Review the requested revision items and apply all necessary corrections and autonomous tests.\n\n"
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\n"
                . "When revision fixes and autonomous verification are complete, deliver the task immediately from your current directory by running this HTTP POST command in your shell:\n"
                . self::getAgentSubmitCommandBlock($taskCode);
        } elseif ($event === 'task.created') {
            $titleHeader = "🔔 New Task: [{$taskCode}] {$taskTitle}";
            $messageText = "🔔 **TaskManager New Task Notification**\n\n"
                . "- **Project:** [{$projectCode}] {$projectTitle}\n"
                . "- **Task:** [{$taskCode}] {$taskTitle}\n"
                . "- **Priority:** {$priority}";
        } elseif ($event === 'project.webhook_test') {
            $titleHeader = "✅ Webhook Test Ping: [{$projectCode}]";
            $messageText = "✅ **TaskManager Webhook Test Ping Successful!**\n\n"
                . "- **Project:** [{$projectCode}] {$projectTitle}\n"
                . "Connection and dynamic agent bridge is active and operational.";
        } else {
            $titleHeader = "ℹ️ Task Update: {$event}";
            $messageText = "ℹ️ **TaskManager Event Notification: {$event}**\n\n"
                . "- **Project:** [{$projectCode}] {$projectTitle}\n"
                . "- **Data:** " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        return [$titleHeader, $messageText];
    }

    /**
     * Dispatcher servisi port 4000'de dinlemiyorsa sessizce arka planda ayağa kaldırır.
     */
    public static function ensureDispatcherRunning(): void
    {
        $fp = @fsockopen('127.0.0.1', 4000, $errno, $errstr, 0.15);
        if ($fp) {
            fclose($fp);
            return;
        }
        // Siyah pencere açmaması için sadece VBS üzerinden sessiz başlat
        $vbs = dirname(__DIR__) . '/dispatcher/start_silent.vbs';
        if (file_exists($vbs) && class_exists('COM')) {
            try {
                $shell = new \COM("WScript.Shell");
                $shell->Run('wscript.exe "' . $vbs . '"', 0, false);
                usleep(250000);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Port 4000'e gerek duymadan doğrudan Antigravity Language Server API'yi tetikler.
     */
    public static function dispatchDirectToAntigravity(object $project, string $event, array $data = []): array
    {
        $agentExe = 'c:\Users\DELL\AppData\Local\Programs\Antigravity IDE\resources\app\extensions\antigravity\bin\language_server_windows_x64.exe';
        if (!file_exists($agentExe)) {
            return ['sent' => false, 'error' => 'Antigravity language_server binary not found.'];
        }

        // 1. Dinamik Keşif
        $workspacePath = $project->workspace_path ?? '';
        $projectCode   = $project->project_code ?? '';
        $discoveredCid = self::discoverLatestConversation($workspacePath, $projectCode);
        $targetCid     = $discoveredCid ?: ($project->agent_conversation_id ?? '');

        if (empty($targetCid)) {
            return ['sent' => false, 'error' => 'No active conversation window or Conversation ID found.'];
        }

        // Yeni pencere keşfedildiyse veritabanını eşitle
        if (!empty($discoveredCid) && $discoveredCid !== ($project->agent_conversation_id ?? '')) {
            global $db;
            if ($db && !empty($db->connect)) {
                $db->exec_query("UPDATE projects SET agent_conversation_id = ? WHERE id = ?", [$discoveredCid, $project->id]);
            }
        }

        // 2. Mesaj metnini hazırla
        [$titleHeader, $messageText] = self::formatAgentMessage($event, $project, $data);

        // 3. proc_open ile komut çalıştır (Dinamik LS Adresi ile)
        $cmd = [$agentExe, 'agentapi', 'send-message', '--title=' . $titleHeader, $targetCid, $messageText];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $lsEnv = self::getAntigravityLsEnv();
        $lsAddress = $lsEnv['address'] ?? null;
        $csrfToken = $lsEnv['csrf_token'] ?? null;
        $envVars = array_merge($_SERVER, $_ENV);
        if ($lsAddress) {
            $envVars['ANTIGRAVITY_LS_ADDRESS'] = $lsAddress;
        }
        if ($csrfToken) {
            $envVars['ANTIGRAVITY_CSRF_TOKEN'] = $csrfToken;
        }
        $process = proc_open($cmd, $descriptors, $pipes, null, $envVars);
        if (!is_resource($process)) {
            return ['sent' => false, 'error' => 'proc_open failed to launch language_server'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        // Eğer hedef sohbet silinmişse (trajectory not found), doğrudan yeni bir sohbet başlat
        $combinedErr = strtolower((string)$stdout . ' ' . (string)$stderr);
        if ($exitCode !== 0 && str_contains($combinedErr, 'trajectory not found')) {
            $cmdFallback = [$agentExe, 'agentapi', 'new-conversation', '--title=' . $titleHeader, $messageText];
            $pFallback = proc_open($cmdFallback, $descriptors, $pipes, null, $envVars);
            if (is_resource($pFallback)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($pFallback);
                if ($exitCode === 0) {
                    $resJson = json_decode((string)$stdout, true);
                    $newCid = $resJson['response']['newConversation']['conversationId'] ?? null;
                    if ($newCid) {
                        $targetCid = $newCid;
                        global $db;
                        if ($db && !empty($db->connect)) {
                            $db->exec_query("UPDATE projects SET agent_conversation_id = ? WHERE id = ?", [$newCid, $project->id]);
                        }
                    }
                }
            }
        }

        $sent = ($exitCode === 0);

        // 4. Arka plan servisini de canlandır
        self::ensureDispatcherRunning();

        return [
            'sent'            => $sent,
            'direct_dispatch' => true,
            'recipient_id'    => $targetCid,
            'discovered_cid'  => $discoveredCid,
            'exit_code'       => $exitCode,
            'stdout'          => trim((string)$stdout),
            'stderr'          => trim((string)$stderr)
        ];
    }

    /**
     * Antigravity Language Server portunu ve CSRF token'ını dinamik tespit eder
     */
    public static function getAntigravityLsEnv(): array
    {
        $lsEnvFile = dirname(__DIR__) . '/dispatcher/ls_env.json';
        if (file_exists($lsEnvFile)) {
            $data = json_decode((string)file_get_contents($lsEnvFile), true) ?: [];
            $addr = $data['address'] ?? '';
            $token = $data['csrf_token'] ?? '';
            if (!empty($addr) && preg_match('/^(?:127\.0\.0\.1|localhost):(\d+)$/', $addr, $m)) {
                $fp = @fsockopen('127.0.0.1', (int)$m[1], $errno, $errstr, 0.05);
                if ($fp) {
                    fclose($fp);
                    return ['address' => $addr, 'csrf_token' => $token];
                }
            }
        }

        // Python probe ile dinamik yenile
        $python = 'C:/Users/DELL/AppData/Local/Programs/Python/Python311/python.exe';
        $script = dirname(__DIR__) . '/dispatcher/dispatcher.py';
        if (file_exists($python) && file_exists($script)) {
            $probeJson = @shell_exec("\"{$python}\" \"{$script}\" --probe");
            if ($probeJson) {
                $probeData = json_decode(trim($probeJson), true);
                if (!empty($probeData['address'])) {
                    return [
                        'address'    => $probeData['address'],
                        'csrf_token' => $probeData['csrf_token'] ?? ''
                    ];
                }
            }
        }

        $addr = self::getAntigravityLsAddress();
        return ['address' => $addr, 'csrf_token' => ''];
    }

    /**
     * Antigravity Language Server portunu tespit eder (Geriye dönük uyumluluk)
     */
    public static function getAntigravityLsAddress(): ?string
    {
        $lsFile = dirname(__DIR__) . '/dispatcher/ls_address.txt';
        if (file_exists($lsFile)) {
            $addr = trim((string)file_get_contents($lsFile));
            if (!empty($addr)) {
                return $addr;
            }
        }
        return getenv('ANTIGRAVITY_LS_ADDRESS') ?: null;
    }

    /**
     * Proje silindiğinde workspaces.json dosyasından da otomatik kaldırır (Büyük/küçük harf duyarsız).
     */
    public static function removeProjectFromWorkspacesJson(string $projectCode): void
    {
        $wsFile = dirname(__DIR__) . '/dispatcher/workspaces.json';
        if (file_exists($wsFile) && !empty($projectCode)) {
            $wsMap = json_decode(file_get_contents($wsFile), true) ?: [];
            $changed = false;
            foreach ($wsMap as $code => $paths) {
                if (strcasecmp((string)$code, (string)$projectCode) === 0) {
                    unset($wsMap[$code]);
                    $changed = true;
                }
            }
            if ($changed) {
                @file_put_contents($wsFile, json_encode($wsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    /**
     * Bir proje silindiğinde ona ait TÜM verileri (görevler, alt görevler, checklistler, yorumlar, aktiviteler,
     * ekler, bağımlılıklar, dökümanlar, canlı eventler, kullanıcı ilişkileri) ve workspaces.json yapılandırmasını kalıcı olarak temizler.
     */
    public static function purgeProject(int $projectId): bool
    {
        global $db;
        if (!$db || empty($db->connect)) {
            require_once __DIR__ . '/../Model/DataLayer.php';
            $db = new Database();
        }

        $proj = $db->row_query("SELECT id, project_code FROM projects WHERE id = ?", [$projectId]);
        if (!$proj) {
            return false;
        }

        // Projeye ait tüm görev ID'lerini al
        $taskRows = $db->multi_record_query("SELECT id FROM tasks WHERE project_id = ?", [$projectId]) ?: [];
        $taskIds = array_map(fn($t) => (int)$t->id, $taskRows);

        if (!empty($taskIds)) {
            $inClause = implode(',', $taskIds);

            $db->exec_query("DELETE FROM task_checklists WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_comments WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_activities WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_attachments WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_submissions WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_modifications WHERE task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_dependencies WHERE task_id IN ({$inClause}) OR depends_on_task_id IN ({$inClause})");
            $db->exec_query("DELETE FROM task_assignees WHERE task_id IN ({$inClause})");
        }

        // Görevleri ve dökümanları sil
        $db->exec_query("DELETE FROM tasks WHERE project_id = ?", [$projectId]);
        $db->exec_query("DELETE FROM docs WHERE project_id = ?", [$projectId]);
        $db->exec_query("DELETE FROM live_events WHERE project_id = ?", [$projectId]);

        // Bu projeye bağlı client kullanıcıların proje bağını temizle
        $db->exec_query("UPDATE users SET client_project_id = NULL WHERE client_project_id = ?", [$projectId]);

        // Projeyi sil
        $deleted = $db->exec_query("DELETE FROM projects WHERE id = ?", [$projectId]);

        // workspaces.json dosyasından kaldır
        if (!empty($proj->project_code)) {
            self::removeProjectFromWorkspacesJson($proj->project_code);
        }

        return (bool)$deleted;
    }

    /**
     * Bir görevin Yapay Zeka / Otonom Ajan görevi olup olmadığını denetler.
     * Sadece etiketlerinde 'ai', '#ai', 'otonom', 'agent', 'yapay zeka' veya 'antigravity'
     * bulunan görevler otonom ajan tarafından ele alınır.
     */
    public static function isAiTask(mixed $taskOrTags): bool
    {
        if (empty($taskOrTags)) {
            return false;
        }
        $tagString = '';
        if (is_string($taskOrTags)) {
            $tagString = $taskOrTags;
        } elseif (is_object($taskOrTags)) {
            $tagString = $taskOrTags->tags ?? '';
        } elseif (is_array($taskOrTags)) {
            $tagString = $taskOrTags['tags'] ?? '';
        }
        if (empty($tagString)) {
            return false;
        }
        $rawTags = array_map('trim', explode(',', mb_strtolower($tagString)));
        $aiKeywords = ['ai', 'otonom', 'agent', 'yapay zeka', 'antigravity'];
        foreach ($rawTags as $tg) {
            $clean = ltrim($tg, '#');
            if (in_array($clean, $aiKeywords, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Görev "Çalışılıyor (in_progress)" durumuna geçtiğinde tam açıklama ve checklist ile Ajanı tetikler
     */
    public static function triggerTaskStartedWebhook(int $taskId, ?string $oldStatus = null, array $extra = []): array
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            $task = $db->row_query("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) {
                return ['sent' => false, 'reason' => 'Task not found'];
            }

            // 0. Görev başlamadan önce 5 KB'lık DB tablo/sütun haritasını al (Rollback sigortası)
            self::capturePreTaskSnapshot((int)$task->id);

            // 🔥 KRİTİK KURAL: Görevde 'ai' / otonom etiketi YOKSA bu bir insan görevidir; ajana webhook fırlatma!
            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            // Checklist maddelerini çek (kolon adı: title)
            $checklists = $db->multi_record_query("
                SELECT id, title, is_completed 
                FROM task_checklists 
                WHERE task_id = ? 
                ORDER BY id ASC
            ", [$taskId]) ?: [];

            // Atanan çalışanları çek
            $assigneeRows = $db->multi_record_query("
                SELECT u.name 
                FROM task_assignees ta 
                JOIN users u ON ta.user_id = u.id 
                WHERE ta.task_id = ?
            ", [$taskId]) ?: [];
            $assignees = array_map(fn($a) => $a->name, $assigneeRows);

            $isResumed = !empty($extra['resumed']);
            $taskTitle = $isResumed ? "🔄 [RESUMING TASK] " . $task->title : $task->title;

            $desc = $task->description ?: '(No description provided)';

            // If subtask, inject parent task title and description as architectural context
            if (!empty($task->parent_task_id)) {
                $parentTask = $db->row_query("SELECT task_code, title, description FROM tasks WHERE id = ?", [(int)$task->parent_task_id]);
                if ($parentTask) {
                    $desc = "📌 **PARENT TASK ARCHITECTURAL CONTEXT:** [{$parentTask->task_code}] {$parentTask->title}\n"
                          . "📝 **Parent Task Overview:** " . ($parentTask->description ?: '-') . "\n\n"
                          . "🎯 **ACTIVE SUBTASK SCOPE & REQUIREMENTS:**\n" . $desc;
                }
            }

            if ($isResumed) {
                $desc = "🔄 **Previous priority/revision task was completed! Resumed this pending task autonomously.**\n\n" . $desc;
            }
            $desc .= "\n\n---\n⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\nWhen development and autonomous verification are 100% complete, to prevent the system from deadlocking and allow the queue to immediately proceed to the next pending task, you MUST deliver the task directly via HTTP API by running this command in your shell:\n" . self::getAgentSubmitCommandBlock($task->task_code);

            $payloadData = array_merge([
                'task_id'       => (int)$task->id,
                'task_code'     => $task->task_code,
                'title'         => $taskTitle,
                'description'   => $desc,
                'priority'      => $task->priority,
                'deadline'      => $task->deadline,
                'time_estimate' => $task->time_estimate,
                'tags'          => $task->tags,
                'assignees'     => $assignees,
                'checklists'    => $checklists,
                'old_status'    => $oldStatus,
                'new_status'    => 'in_progress',
                'resumed'       => $isResumed,
            ], $extra);

            return self::triggerProjectWebhook((int)$task->project_id, 'task.started', $payloadData);
        } catch (\Throwable $e) {
            error_log("triggerTaskStartedWebhook error: " . $e->getMessage());
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Görev için revizyon talep edildiğinde Ajanı tetikler
     */
    public static function triggerTaskRevisionWebhook(int $taskId, string $reason, array $extra = []): array
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $task = $db->row_query("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) return ['sent' => false];

            // 🔥 KRİTİK KURAL: Görevde 'ai' / otonom etiketi YOKSA bu bir insan görevidir; ajana webhook fırlatma!
            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            $checklists = $db->multi_record_query("SELECT id, title, is_completed FROM task_checklists WHERE task_id = ? ORDER BY id ASC", [$taskId]) ?: [];

            $revDesc = ($task->description ?: '') . "\n\n---\n⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\nWhen revision fixes and autonomous verification are complete, to prevent the system from deadlocking and allow the queue to immediately proceed to the next pending task, you MUST deliver the task directly via HTTP API by running this command in your shell:\n" . self::getAgentSubmitCommandBlock($task->task_code);

            $payloadData = array_merge([
                'task_id'         => (int)$task->id,
                'task_code'       => $task->task_code,
                'title'           => "⚠️ [REVISION REQUEST] " . $task->title,
                'description'     => $revDesc,
                'revision_reason' => $reason,
                'priority'        => $task->priority,
                'checklists'      => $checklists,
                'old_status'      => $task->status,
                'new_status'      => 'modification_requested'
            ], $extra);

            return self::triggerProjectWebhook((int)$task->project_id, 'task.revision_requested', $payloadData);
        } catch (\Throwable $e) {
            error_log("triggerTaskRevisionWebhook error: " . $e->getMessage());
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Antigravity Ajanı tarafından görevi tek hamlede İNCELEMEDE ('submitted') sütununa taşır.
     * DİKKAT: Ajanın mevcut yanıtını (turn) bozmamak için sıradaki görevi aynı milisaniyede DEĞİL,
     * arka plan asenkron tetikleyici / Watchdog üzerinden 4 saniye gecikmeyle başlatır!
     */
    public static function submitTaskDirectly(int|string $taskIdOrCode, string $notes = 'Görev tamamlandı ve test edildi.'): array
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            $task = is_numeric($taskIdOrCode)
                ? $db->row_query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [(int)$taskIdOrCode])
                : $db->row_query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$taskIdOrCode]);

            if (!$task) {
                return ['success' => false, 'error' => 'Task not found'];
            }

            $taskId = (int)$task->id;
            $projectId = (int)$task->project_id;
            $oldStatus = $task->status;

            // 1. Görevi İNCELEMEDE ('submitted') durumuna al
            $db->exec_query("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?", [$taskId]);

            // 1.1 [AI ARCHITECT AUTO-ROLLUP]: Alt görev bittiğinde ve tüm kardeşleri bittiyse Ana Görevi de 'submitted' yap
            if (!empty($task->parent_task_id)) {
                $parentId = (int)$task->parent_task_id;
                $remainingSubs = (int)$db->single_query("
                    SELECT COUNT(*) FROM tasks 
                    WHERE parent_task_id = ? AND status NOT IN ('submitted', 'completed')
                ", [$parentId]);
                
                if ($remainingSubs === 0) {
                    $parentRow = $db->row_query("SELECT id, task_code, title, status FROM tasks WHERE id = ?", [$parentId]);
                    if ($parentRow && $parentRow->status !== 'submitted' && $parentRow->status !== 'completed') {
                        $db->exec_query("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?", [$parentId]);
                        self::broadcastLiveEvent('task_status_changed', $projectId, $parentId, 1, [
                            'task_id'    => $parentId,
                            'task_code'  => $parentRow->task_code,
                            'title'      => $parentRow->title,
                            'old_status' => $parentRow->status,
                            'new_status' => 'submitted',
                            'user_name'  => 'AI Architect Rollup'
                        ]);
                    }
                }
            }

            // 1.2 [GITHUB DEVOPS & AUTO-PUSH]: Görev tesliminde otomatik commit & push ve commit url üretimi
            $commitHash = null;
            $attachmentUrl = '';
            try {
                $projectObj = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$projectId]);
                if ($projectObj) {
                    $gitRes = self::autoCommitAndPushTask($projectObj, $task);
                    if (!empty($gitRes['success'])) {
                        $commitHash = $gitRes['commit_hash'] ?? null;
                        $attachmentUrl = $gitRes['commit_url'] ?? '';
                    }
                }
            } catch (\Throwable $e) {
                error_log("autoCommitAndPushTask error: " . $e->getMessage());
            }

            // 1.3 [SCHEMA DIFF & ROLLBACK SQL]: Otomatik şema farkı ve ajanın .migrations/{task_code}_down.sql kaydı
            $rollbackSql = null;
            $schemaDiffSummary = null;
            try {
                if ($projectObj) {
                    $diffRes = self::computePostTaskSchemaDiff($taskId, $projectObj, $task->task_code);
                    $rollbackSql = !empty($diffRes['rollback_sql']) ? $diffRes['rollback_sql'] : null;
                    $schemaDiffSummary = !empty($diffRes['summary']) ? $diffRes['summary'] : null;
                }
            } catch (\Throwable $e) {
                error_log("computePostTaskSchemaDiff error: " . $e->getMessage());
            }

            // 2. Teslimat kaydı (Commit hash, GitHub diff linki ve Rollback SQL ile)
            $db->exec_query("
                INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, commit_hash, rollback_sql, schema_diff_summary, status, created_at)
                VALUES (?, 1, ?, ?, ?, ?, ?, 'pending', NOW())
            ", [$taskId, $notes, $attachmentUrl, $commitHash, $rollbackSql, $schemaDiffSummary]);

            // 3. Aktivite kaydı
            $db->exec_query("
                INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                VALUES (?, 1, 'submitted', ?, 'submitted', ?, NOW())
            ", [$taskId, $oldStatus, "🤖 AI Agent görevi tamamladı ve İncelemede sütununa taşıdı: {$notes}"]);

            // 4. Canlı SSE yayını (Kanban panosunda kart anında İNCELEMEDE sütununa kayar)
            self::broadcastLiveEvent('task_submitted', $projectId, $taskId, 1, [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'title'      => $task->title,
                'old_status' => $oldStatus,
                'new_status' => 'submitted',
                'notes'      => $notes,
                'user_name'  => 'AI Agent'
            ]);

            // 5. Dispatcher'daki aktif görev kilidini temizlemek ve 4sn sonra sıradaki görevi tetiklemek için sinyal gönder
            self::triggerProjectWebhook($projectId, 'task.submitted', [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'title'      => $task->title,
                'project_id' => $projectId
            ]);

            return [
                'success'   => true,
                'task_id'   => $taskId,
                'task_code' => $task->task_code,
                'status'    => 'submitted',
                'message'   => "Görev [{$task->task_code}] İNCELEMEDE sütununa taşındı. Şimdi başka hiçbir işlem yapmadan işlemin bittiğini özetle ve dur. Sıradaki görev 4 saniye içinde otomatik tetiklenecektir."
            ];
        } catch (\Throwable $e) {
            error_log("submitTaskDirectly error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Otonom Yapay Zeka Kuyruk Yöneticisi:
     * KURAL:
     * 1. Eğer projede halihazırda 'in_progress' (ÇALIŞILIYOR) durumunda bir AI görevi varsa onu uyandırır.
     * 2. Yoksa SADECE 'assigned' (YAPILACAK İŞLER) sütunundaki sıradaki 'ai' etiketli görevi 'in_progress' yapıp başlatır.
     * ('modification_requested' yani REVİZYON İSTENDİ sütunu bekleme alanıdır, kullanıcı ÇALIŞILIYOR'a sürüklemeden otomatik alınmaz!)
     */
    public static function dispatchNextAiTaskIfAvailable(int $projectId, ?int $justFinishedTaskId = null): ?array
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            // AŞAMA 1: ÇALIŞILIYOR ('in_progress') sütununda bekleyen / yarım kalan AI görevi var mı?
            $inProgressSql = "
                SELECT * FROM tasks 
                WHERE project_id = ? 
                  AND status = 'in_progress'
                  AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = tasks.id)
                  AND (
                      LOWER(tags) LIKE '%ai%' 
                      OR LOWER(tags) LIKE '%otonom%' 
                      OR LOWER(tags) LIKE '%agent%'
                      OR LOWER(tags) LIKE '%yapay zeka%'
                      OR LOWER(tags) LIKE '%antigravity%'
                  )
            ";
            $inProgressParams = [$projectId];
            if ($justFinishedTaskId) {
                $inProgressSql .= " AND id != ?";
                $inProgressParams[] = $justFinishedTaskId;
            }
            $inProgressSql .= "
                ORDER BY 
                    CASE priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'normal' THEN 3 
                        WHEN 'low' THEN 4 
                        ELSE 5 
                    END ASC, 
                    id ASC 
                LIMIT 1
            ";
            $activeTask = $db->row_query($inProgressSql, $inProgressParams);

            if ($activeTask && self::isAiTask($activeTask)) {
                $res = self::triggerTaskStartedWebhook((int)$activeTask->id, 'in_progress', [
                    'resumed'         => true,
                    'auto_dispatched' => true,
                    'source'          => 'auto_ai_resume'
                ]);
                return [
                    'dispatched' => true,
                    'resumed'    => true,
                    'task_id'    => $activeTask->id,
                    'task_code'  => $activeTask->task_code,
                    'title'      => $activeTask->title,
                    'webhook'    => $res
                ];
            }

            // AŞAMA 2: YAPILACAK İŞLER ('assigned') sütunundaki sıradaki 'ai' etiketli görevi bul (Aday Havuzu)
            $sqlParams = [$projectId];
            $sql = "
                SELECT * FROM tasks 
                WHERE project_id = ? 
                  AND status = 'assigned'
                  AND (
                      LOWER(tags) LIKE '%ai%' 
                      OR LOWER(tags) LIKE '%otonom%' 
                      OR LOWER(tags) LIKE '%agent%'
                      OR LOWER(tags) LIKE '%yapay zeka%'
                      OR LOWER(tags) LIKE '%antigravity%'
                  )
            ";
            if ($justFinishedTaskId) {
                $sql .= " AND id != ?";
                $sqlParams[] = $justFinishedTaskId;
            }
            $sql .= "
                ORDER BY 
                    CASE priority 
                        WHEN 'urgent' THEN 1 
                        WHEN 'high' THEN 2 
                        WHEN 'normal' THEN 3 
                        WHEN 'low' THEN 4 
                        ELSE 5 
                    END ASC, 
                    id ASC 
                LIMIT 30
            ";

            $candidateTasks = $db->multi_record_query($sql, $sqlParams) ?: [];
            $nextTask = null;

            foreach ($candidateTasks as $cand) {
                if (!self::isAiTask($cand)) {
                    continue;
                }

                // KURAL 1: Eğer bu görevin altında alt görevler varsa bu bir Modül/Ana Görevdir (Epic), doğrudan ajana gönderilmez!
                $subCount = (int)$db->single_query("SELECT COUNT(*) FROM tasks WHERE parent_task_id = ?", [(int)$cand->id]);
                if ($subCount > 0) {
                    continue;
                }

                // KURAL 2: Eğer bu bir alt görevse ve bağlı olduğu Ana Görev "Onay Kapılı" (is_gated / chain_gated) ise:
                if (!empty($cand->parent_task_id)) {
                    $parentTask = $db->row_query("SELECT * FROM tasks WHERE id = ?", [(int)$cand->parent_task_id]);
                    if ($parentTask) {
                        $isGated = !empty($parentTask->is_gated) 
                            || str_contains(strtolower($parentTask->tags ?? ''), 'chain_gated') 
                            || str_contains($parentTask->description ?? '', '[ONAY_KAPILI]');
                        
                        if ($isGated) {
                            // Kendisinden önceki (ID'si daha küçük) kardeş alt görevlerden henüz 'completed' olmayan var mı?
                            $unapprovedPrev = (int)$db->single_query("
                                SELECT COUNT(*) FROM tasks 
                                WHERE parent_task_id = ? AND id < ? AND status != 'completed'
                            ", [(int)$parentTask->id, (int)$cand->id]);

                            if ($unapprovedPrev > 0) {
                                // Önceki adım henüz yönetici/müşteri tarafından onaylanmamış! Bu görevi atla, kuyruktaki diğer işlere bak!
                                continue;
                            }
                        }
                    }
                }

                $nextTask = $cand;
                break;
            }

            if (!$nextTask) {
                return null;
            }

            // Görevi 'in_progress' (ÇALIŞILIYOR) durumuna al
            $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$nextTask->id]);

            // KURAL 3 (KANBAN GÖRÜNÜRLÜĞÜ): Eğer başlayan görev bir alt görevse, bağlı olduğu Ana Görevi de Kanban panosunda 'in_progress' sütununa taşı!
            if (!empty($nextTask->parent_task_id)) {
                $pId = (int)$nextTask->parent_task_id;
                $parentRow = $db->row_query("SELECT id, task_code, title, status FROM tasks WHERE id = ?", [$pId]);
                if ($parentRow && $parentRow->status === 'assigned') {
                    $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$pId]);
                    self::broadcastLiveEvent('task_status_changed', (int)$projectId, $pId, 1, [
                        'task_id'    => $pId,
                        'task_code'  => $parentRow->task_code,
                        'title'      => $parentRow->title,
                        'old_status' => 'assigned',
                        'new_status' => 'in_progress',
                        'user_name'  => 'AI Architect'
                    ]);
                }
            }

            $db->exec_query("
                INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                VALUES (?, 1, 'status_change', 'assigned', 'in_progress', ?, NOW())
            ", [
                $nextTask->id,
                "🤖 Otonom Kuyruk: Sıradaki 'ai' etiketli görev otomatik olarak 'Çalışılıyor' sütununa alındı."
            ]);

            self::broadcastLiveEvent('task_status_changed', (int)$projectId, (int)$nextTask->id, 1, [
                'task_id'    => (int)$nextTask->id,
                'task_code'  => $nextTask->task_code,
                'title'      => $nextTask->title,
                'old_status' => 'assigned',
                'new_status' => 'in_progress'
            ]);

            $res = self::triggerTaskStartedWebhook((int)$nextTask->id, 'assigned', [
                'auto_dispatched' => true,
                'source'          => 'auto_ai_queue'
            ]);

            return [
                'dispatched' => true,
                'task_id'    => $nextTask->id,
                'task_code'  => $nextTask->task_code,
                'title'      => $nextTask->title,
                'webhook'    => $res
            ];
        } catch (\Throwable $e) {
            error_log("dispatchNextAiTaskIfAvailable error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tüm projelerin workspace_path değerlerini dispatcher/workspaces.json dosyasına senkronize eder.
     */
    public static function syncWorkspacesJson(): bool
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $projects = $db->multi_record_query("SELECT project_code, workspace_path FROM projects WHERE workspace_path IS NOT NULL AND workspace_path != ''") ?: [];
            $jsonMap = [];
            foreach ($projects as $p) {
                $jsonMap[$p->project_code] = [$p->workspace_path];
            }
            $jsonFile = dirname(__DIR__) . '/dispatcher/workspaces.json';
            file_put_contents($jsonFile, json_encode($jsonMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return true;
        } catch (\Throwable $e) {
            error_log("syncWorkspacesJson error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Projeye özel kuralları (agent_rules) projenin yerel klasöründeki AGENTS.md ve GEMINI.md dosyalarına senkronize eder.
     */
    public static function syncProjectRulesToWorkspace(object|int $project): array
    {
        global $db;
        if (is_numeric($project)) {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $project = $db->row_query("SELECT * FROM projects WHERE id = ?", [(int)$project]);
        }

        if (!$project || empty($project->workspace_path)) {
            return ['success' => false, 'error' => 'Geçerli bir çalışma alanı yolu (workspace_path) tanımlı değil.'];
        }

        $wsPath = rtrim(str_replace('\\', '/', $project->workspace_path), '/');
        if (!is_dir($wsPath)) {
            return ['success' => false, 'error' => "Çalışma alanı klasörü diskte bulunamadı: {$wsPath}"];
        }

        $rules = trim($project->agent_rules ?? '');
        $projectCode = $project->project_code ?? '';
        $projectTitle = $project->title ?? '';

        $sectionHeader = "<!-- PROJECT_RULES_START -->\n## 7. PROJECT-SPECIFIC AGENT RULES (PROJEYE ÖZEL KURALLAR)\n\n> ### 📌 MANDATORY PROJECT-SPECIFIC CONSTRAINTS:\n> The following project-specific technical rules, styling constraints, and workflow directives must be strictly observed for project [{$projectCode}] {$projectTitle}:\n\n";
        $sectionFooter = "\n<!-- PROJECT_RULES_END -->";
        $customSection = $sectionHeader . ($rules ?: "*(Henüz projeye özel kural tanımlanmadı / No project-specific rules defined yet)*") . $sectionFooter;

        $targetFiles = ['AGENTS.md'];
        if (file_exists($wsPath . '/GEMINI.md')) {
            $targetFiles[] = 'GEMINI.md';
        }
        $updatedFiles = [];

        foreach ($targetFiles as $fname) {
            $fpath = $wsPath . '/' . $fname;
            if (file_exists($fpath)) {
                $content = file_get_contents($fpath);
                if (str_contains($content, '<!-- PROJECT_RULES_START -->')) {
                    $pattern = '/<!-- PROJECT_RULES_START -->.*?<!-- PROJECT_RULES_END -->/s';
                    $newContent = preg_replace($pattern, $customSection, $content);
                } else {
                    $newContent = rtrim($content) . "\n\n" . $customSection . "\n";
                }
                file_put_contents($fpath, $newContent);
                $updatedFiles[] = $fname;
            } else {
                $baseContent = self::getUniversalAgentTemplate($projectCode, $projectTitle);
                $newContent = str_replace('<!-- PROJECT_RULES_START -->', $customSection, $baseContent);
                file_put_contents($fpath, $newContent);
                $updatedFiles[] = $fname;
            }
        }

        return [
            'success'       => true,
            'updated_files' => $updatedFiles,
            'workspace'     => $wsPath
        ];
    }

    /**
     * Harici projeler (CodeIgniter, Laravel, Flutter, Vue vb.) için
     * TaskManager'a özel 3-dil ve iç mimari kurallarından arındırılmış,
     * hem doğrudan kullanıcı sohbetini hem de TaskManager otonom kuyruğunu destekleyen
     * evrensel ve tertemiz AGENTS.md şablonunu üretir.
     */
    public static function getUniversalAgentTemplate(string $projectCode = '', string $projectTitle = ''): string
    {
        return <<< 'MARKDOWN'
# 🛡️ PROJECT AGENT INSTRUCTIONS & EXECUTION DOCTRINE (AGENTS.md)

## 0. CORE DOCTRINE: "THE USER IS NOT A QA TESTER"

> ### 🚨 RED LINE RULE:
> Before an engineer or AI agent tells the user "It's done, please check", "Try it now", or marks any task as completed:
> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**

Under NO circumstances shall the user ever encounter:
1. A blank or white screen (`Blank Screen`),
2. An unresolved PHP/SQL/JS error, syntax error, warning, or crash,
3. An unresponsive, unclickable, or dead button,
4. Console JavaScript errors or network 404/500 failures,
5. Misaligned typography, poor borders, clipped elements, or responsive layout defects,
6. Ambiguous deliverables where what was done or how to test is unclear.

---

## 1. USER'S ROLE vs. AGENT'S ROLE
- **The User's Role:** The user is NOT a QA/test employee. Finding missing parts, broken buttons, or bugs is NEVER the user's responsibility. The user only reviews high-level product vision and user experience.
- **The Agent's Role:** Verifying and proving that the system functions logically, visually, and operationally with 100% perfection is **100% the Agent's responsibility**.

---

## 2. DUAL-MODE EXECUTION: DIRECT USER CHAT vs. TASKMANAGER AUTONOMOUS QUEUE

> ### 🛑 OPERATIONAL DIRECTIVE:
> 
> ### MODE A: DIRECT USER CHAT (User Prompts Manually in IDE Window)
> * When the user speaks to you directly in this chat:
> * **USER INSTRUCTION TAKES 100% SUPREMACY.** Address the user's request immediately.
> * **DO NOT SEARCH FOR REST API CALLS OR TASK CODES.** Do not attempt to submit to TaskManager API unless a task code was explicitly given. Simply complete the work, test autonomously, and reply directly to the user.
> 
> ### MODE B: TASKMANAGER AUTONOMOUS DISPATCH (Prompt Contains Task Code)
> * When triggered by TaskManager background queue with an assigned `task_code`:
> * Focus EXCLUSIVELY on that single task. Never jump to older tasks.
> * When complete and autonomously verified, execute the official REST API submit command provided in the dispatch prompt, then **END YOUR TURN**. Do not search TaskManager internal files or database.

---

## 3. PROJECT-NATIVE LANGUAGE & ARCHITECTURE RESPECT
1. **Respect Native Framework:** Adhere strictly to THIS project's existing framework and directory conventions. Never import or assume architectural patterns from external systems.
2. **Project-Native Language:** Follow the language naturally used in THIS project and task. Do NOT force multi-language dictionaries unless explicitly requested by this project's specific rules.
3. **Preserve Codebase Integrity:** Preserve existing code conventions, folder structures, and comments.

---

## 4. DEFINITION OF DONE (DELIVERY PROTOCOL)
Every completed task must provide an exhaustive summary formatted as follows:
1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.
2. **Where to Test (Nereden Test Edilir):** Direct testable local or live URLs.
3. **How to Test (Nasıl Test Edilir):** Step-by-step credentials, actions, buttons to click, and expected outcomes.
4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Summary of automated headless browser, curl, or CLI test results.

---

## 5. MANDATORY GIT REPOSITORY SYNCHRONIZATION (GITHUB PUSH DOCTRINE)

> ### 🚨 STRICT GIT PUSH DIRECTIVE:
> **After every modification, completed feature, bugfix, or task execution, the agent is STRICTLY OBLIGATED to immediately stage, commit with a descriptive message, and push all changed code to the remote repository (`origin <active-branch>`).**

---

## 6. MANDATORY DATABASE MIGRATION & ROLLBACK DOCTRINE (.migrations/{task_code}_down.sql)

> ### 🗄️ DATABASE ROLLBACK INTEGRITY DIRECTIVE:
> **Whenever an agent creates or modifies database structures (CREATE TABLE, ALTER TABLE) or performs data mutations (INSERT, UPDATE, DELETE) during a task:**
> **The agent is STRICTLY OBLIGATED to generate an inverse rollback SQL script inside the project directory: `.migrations/{task_code}_down.sql`.**

<!-- PROJECT_RULES_START -->
MARKDOWN;
    }

    /**
     * Kullanıcının yazdığı kısa/kaba kural notlarını (Türkçe/İngilizce)
     * yapay zeka (Gemini/OpenAI) veya dahili uzman kural derleyicisi ile
     * profesyonel, disiplinli ve yapay zeka ajanlarının %100 anlayıp uyacağı
     * İngilizce standart kural maddelerine dönüştürür.
     */
    public static function refineRulesWithAi(string $rawRules, string $projectCode = '', string $projectTitle = ''): array
    {
        global $db;
        $rawRules = trim($rawRules);
        if (empty($rawRules)) {
            return [
                'success' => false,
                'error'   => 'Lütfen derlenmesini istediğiniz kural veya kısıtlamaları kısaca yazın (Örn: "css kullanmak yasak, gutenberg projesi").'
            ];
        }

        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $settings = $db->row_query("SELECT active_ai_provider, default_ai_model, gemini_api_key, openai_api_key, deepseek_api_key FROM settings LIMIT 1");
        } catch (\Throwable $e) {
            $settings = null;
        }

        $systemPrompt = "You are a Principal AI Software Architect and prompt engineer specializing in autonomous coding agents (Antigravity, Cursor, Gemini).
Your role is to translate and expand brief, informal developer rule notes (often written in Turkish, e.g., 'css kullanmak yasak, bu bir gutenberg projesi', or 'flutter riverpod kullan test et') into strict, authoritative, highly disciplined English project-specific rules for AI coding agents.

CRITICAL MANDATES:
1. The rules MUST be written in authoritative, disciplined English.
2. Ensure explicit prohibitions: If the user indicates something is forbidden (e.g. if the user says 'css yasak, gutenberg', explicitly forbid raw CSS, <style> tags, inline style attributes, and enforce standard WordPress Block APIs and theme.json tokens).
3. Ensure strict architectural boundaries: Define the technology stack, framework conventions, and state management pattern.
4. Ensure autonomous QA verification directives: Remind the agent that 'The user is not a QA tester', and specify exact actions the agent must take to verify the code in the live environment before completion.
5. Output format: Clean markdown bullet points under '### [{$projectCode}] {$projectTitle} Project Rules' with bold directive headers:
   - **Platform & Core Framework:** ...
   - **Strict Architectural & Coding Prohibitions:** ...
   - **Styling & Design Guidelines:** ...
   - **Autonomous QA & Verification Directives:** ...
6. Output ONLY the raw markdown list items. Do NOT include markdown code fences (```markdown) and NO conversational chatter (no 'Here are the rules:', no 'Sure!').";

        $userPrompt = "Project Code: [{$projectCode}]\nProject Title: {$projectTitle}\nDeveloper Notes: {$rawRules}\n\nGenerate the complete, disciplined agent rules in English now:";

        $apiKey = $settings->gemini_api_key ?? '';
        $isDummyKey = empty($apiKey) || str_contains($apiKey, 'test_') || strlen($apiKey) < 20;

        // 1. Google Gemini API Denemesi (Geçerli anahtar varsa)
        if (!$isDummyKey && ($settings->active_ai_provider ?? 'gemini') === 'gemini') {
            $model = !empty($settings->default_ai_model) ? $settings->default_ai_model : 'gemini-1.5-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
            $payload = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userPrompt]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1024
                ]
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!empty($text)) {
                    $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                    return [
                        'success'       => true,
                        'refined_rules' => $cleaned,
                        'engine'        => 'gemini'
                    ];
                }
            }
        }

        // 2. OpenAI API Denemesi (Geçerli anahtar varsa)
        $openAiKey = $settings->openai_api_key ?? '';
        if (!empty($openAiKey) && !str_contains($openAiKey, 'test_')) {
            $model = $settings->default_ai_model ?? 'gpt-4o-mini';
            $url = "https://api.openai.com/v1/chat/completions";
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'temperature' => 0.2
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openAiKey
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? null;
                if (!empty($text)) {
                    $cleaned = trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/i', '', trim($text)));
                    return [
                        'success'       => true,
                        'refined_rules' => $cleaned,
                        'engine'        => 'openai'
                    ];
                }
            }
        }

        // 3. Dahili Yüksek Seviye Uzman Kural Derleyicisi (Deterministic Heuristic Engine)
        // Dış API olmasa veya ağ hatası olsa dahi %100 kusursuz ve profesyonel sonuç üretir.
        $heuristicRules = self::compileRulesHeuristic($rawRules, $projectCode, $projectTitle);
        return [
            'success'       => true,
            'refined_rules' => $heuristicRules,
            'engine'        => 'heuristic'
        ];
    }

    /**
     * Dahili Uzman Kural Derleyicisi:
     * Gutenberg, WordPress, Flutter, CI4, Laravel, CSS kısıtlamaları vb.
     * anahtar terimleri analiz edip katı İngilizce kurallar üretir.
     */
    public static function compileRulesHeuristic(string $raw, string $code = '', string $title = ''): string
    {
        $code = $code ?: 'PROJECT';
        $title = $title ?: 'Custom Project';
        $lower = mb_strtolower($raw, 'UTF-8');
        $rules = [];

        // 1. Gutenberg / WordPress Tespiti
        if (str_contains($lower, 'gutenberg') || str_contains($lower, 'wordpress') || str_contains($lower, 'block') || str_contains($lower, 'blok')) {
            $rules[] = "- **Platform & Core Framework:** WordPress Gutenberg Block Architecture. All UI elements MUST be developed natively as Gutenberg Blocks using `@wordpress/block-editor`, `@wordpress/components`, and `@wordpress/element`.";
            if (str_contains($lower, 'css') && (str_contains($lower, 'yasak') || str_contains($lower, 'kullanma') || str_contains($lower, 'yok') || str_contains($lower, 'prohibit') || str_contains($lower, 'forbidden') || str_contains($lower, 'no css'))) {
                $rules[] = "- **Strict CSS Prohibition:** Writing raw, global, or inline CSS (`<style>`, external `.css` files, or inline `style=\"...\"` attributes) is STRICTLY FORBIDDEN. All styling and layout variations MUST be controlled solely through WordPress Block Supports (e.g. `theme.json`, standard block attributes for color, typography, spacing) or pre-registered block style variations.";
            } else {
                $rules[] = "- **Block Styling Standards:** Adhere strictly to block-level styling and `theme.json` theme tokens; avoid polluting global CSS scopes.";
            }
            $rules[] = "- **Editor Integrity & Component Hygiene:** Custom controls must use standard Gutenberg InspectorControls and BlockControls. Never inject unvetted third-party JavaScript libraries into the editor canvas.";
            $rules[] = "- **Live Block Verification:** Before submitting any task, the agent MUST verify within the WordPress block editor that blocks render cleanly, block attributes persist correctly upon post save, and zero JavaScript console errors occur.";
        }
        // 2. Flutter / Mobil Tespiti
        elseif (str_contains($lower, 'flutter') || str_contains($lower, 'dart') || str_contains($lower, 'mobil') || str_contains($lower, 'mobile')) {
            $rules[] = "- **Technology Stack:** Flutter (Dart) cross-platform mobile development for iOS and Android.";
            if (str_contains($lower, 'riverpod')) {
                $rules[] = "- **State Management:** Riverpod MUST be used as the exclusive state management solution. Avoid mixing conflicting state paradigms.";
            } elseif (str_contains($lower, 'bloc') || str_contains($lower, 'cubit')) {
                $rules[] = "- **State Management:** BLoC / Cubit pattern MUST be used for state separation and business logic.";
            } else {
                $rules[] = "- **State Management:** Follow strict unidirectional data flow and clean state encapsulation.";
            }
            $rules[] = "- **UI & Responsiveness:** Material 3 design guidelines; strictly avoid render overflow errors by wrapping dynamic content in responsive layouts (`LayoutBuilder`, `SingleChildScrollView`, `Expanded`).";
            $rules[] = "- **Mobile QA Verification:** The user is not a QA tester. Form interactions, navigation transitions, and button states must be autonomously verified in the emulator/test environment.";
        }
        // 3. PHP / CodeIgniter / Laravel / MVC Tespiti
        elseif (str_contains($lower, 'codeigniter') || str_contains($lower, 'ci4') || str_contains($lower, 'laravel') || str_contains($lower, 'mvc') || str_contains($lower, 'php')) {
            $rules[] = "- **Backend Architecture:** Strict Model-View-Controller (MVC) architecture. Controllers coordinate request flow, while Models/Entities encapsulate database queries and business logic.";
            if (str_contains($lower, 'view') || str_contains($lower, 'sorgu') || str_contains($lower, 'sql') || str_contains($lower, 'db')) {
                $rules[] = "- **Zero Direct SQL in Views:** Executing raw SQL queries or database calls inside View templates is strictly forbidden. All data access must pass through dedicated Model classes.";
            }
        }
        // 4. Genel Web / Modern Frontend (React, Vue, vb.)
        else {
            $rules[] = "- **Architecture & Framework Guidelines:** Follow strict separation of concerns, modular structure, and clean coding standards throughout the codebase.";
        }

        // Genel CSS kısıtlaması (Gutenberg harici projelerde de CSS yasak dendiyse)
        if (!str_contains($lower, 'gutenberg') && str_contains($lower, 'css') && (str_contains($lower, 'yasak') || str_contains($lower, 'kullanma') || str_contains($lower, 'no css') || str_contains($lower, 'inline'))) {
            $rules[] = "- **Strict CSS Prohibition:** Writing raw custom CSS or inline styling is strictly forbidden. Utilize only the approved design tokens, utilities, or component framework properties.";
        }

        // Git Kuralları
        if (str_contains($lower, 'git') || str_contains($lower, 'pull') || str_contains($lower, 'push') || str_contains($lower, 'commit')) {
            $rules[] = "- **Git Synchronization Protocol:** Run `git pull` before beginning work on any task; upon autonomous verification, commit with descriptive messages and push via `git push`.";
        }

        // Çok Dilli Yapı
        if (str_contains($lower, 'dil') || str_contains($lower, 'i18n') || str_contains($lower, 'çeviri') || str_contains($lower, 'lang') || str_contains($lower, 'translate')) {
            $rules[] = "- **Mandatory Tri-Lingual Standard (EN, TR, DE):** Zero hardcoded strings. All user-facing labels and messages must resolve dynamically across English, Turkish, and German language dictionaries.";
        }

        // Kullanıcının yazdığı özel spesifik cümleleri de koru ve formatla
        $lines = preg_split('/[\r\n,;]+/', $raw);
        $customSpecifics = [];
        foreach ($lines as $ln) {
            $cleanLn = trim($ln);
            if (mb_strlen($cleanLn) > 5 && !preg_match('/^(css|gutenberg|flutter|codeigniter|git)/i', $cleanLn)) {
                $customSpecifics[] = "- **Special Project Constraint:** " . htmlspecialchars($cleanLn, ENT_QUOTES, 'UTF-8');
            }
        }
        if (!empty($customSpecifics)) {
            $rules = array_merge($rules, array_slice($customSpecifics, 0, 3));
        }

        // Zorunlu QA Doktrini
        $rules[] = "- **Mandatory QA Doctrine ('The User is not a QA Tester'):** Every UI component, form step, state change, and interactive button MUST be autonomously verified in the live environment before task submission. Submitting broken code, blank screens, or unverified changes is strictly forbidden.";

        $header = "### [{$code}] {$title} Project Rules\n";
        return $header . implode("\n", $rules);
    }

    /**
     * Canlı Event Yayınlar (Server-Sent Events / SSE ve Realtime Sync için)
     */
    public static function broadcastLiveEvent(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, array $payload): bool
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->exec_query("
                INSERT INTO live_events (event_type, project_id, task_id, user_id, payload, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$eventType, $projectId, $taskId, $userId, $jsonPayload]);
            return true;
        } catch (\Throwable $e) {
            error_log("broadcastLiveEvent error: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Otonom Arka Plan Kontrol Mekanizması (Watchdog Inspection):
     * - Eğer projede 'in_progress' (ÇALIŞILIYOR) durumunda AI görevi varsa:
     *   Hesap değiştiğinde, sohbet silindiğinde veya görev ortada kaldığında devam edebilmesi için
     *   dispatcher'a yoklama gönderir (Dispatcher zaten aynı pencerede çalışıyorsa anti-spam ile sessizce atlar,
     *   ama yeni hesap/sohbet açıldıysa anında yeni pencereye görevi aktarır!).
     * - Eğer projede 'in_progress' durumunda AI görevi YOKSA:
     *   YAPILACAK İŞLER ('assigned') sütununda bekleyen sıradaki AI görevini otomatik başlatır!
     */
    public static function runWatchdogInspection(): array
    {
        global $db;
        $results = [];
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            $projects = $db->multi_record_query("SELECT id, project_code, title FROM projects WHERE status = 'active'") ?: [];
            foreach ($projects as $prj) {
                $pId = (int)$prj->id;
                
                // Projede 'in_progress' durumunda olan AI görevlerini bul
                $allInProgress = $db->multi_record_query("
                    SELECT * FROM tasks 
                    WHERE project_id = ? AND status = 'in_progress'
                    ORDER BY 
                        CASE priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'normal' THEN 3 
                            WHEN 'low' THEN 4 
                            ELSE 5 
                        END ASC,
                        updated_at DESC
                ", [$pId]) ?: [];

                $runningAiTasks = array_values(array_filter($allInProgress, fn($t) => self::isAiTask($t)));

                if (count($runningAiTasks) >= 1) {
                    $primaryTask = $runningAiTasks[0];
                    // Eğer birden fazla AI görevi yanlışlıkla ÇALIŞILIYOR'da kaldıysa, diğerlerini YAPILACAK İŞLER'e geri çek (Tek Görev Kuralı)
                    if (count($runningAiTasks) > 1) {
                        for ($i = 1; $i < count($runningAiTasks); $i++) {
                            $extra = $runningAiTasks[$i];
                            $db->exec_query("UPDATE tasks SET status = 'assigned' WHERE id = ?", [$extra->id]);
                            self::broadcastLiveEvent('task_status_changed', $pId, (int)$extra->id, 1, [
                                'task_id'    => (int)$extra->id,
                                'task_code'  => $extra->task_code,
                                'old_status' => 'in_progress',
                                'new_status' => 'assigned'
                            ]);
                        }
                    }

                    // Aktif AI görevini Dispatcher'a bildir (Dispatcher aynı pencerede çalışıyorsa dokunmaz,
                    // ancak hesap/sohbet değiştiyse veya ajan durduysa kaldığı yerden devam ettirir!)
                    self::triggerTaskStartedWebhook((int)$primaryTask->id, 'in_progress', [
                        'resumed'          => true,
                        'watchdog_check'   => true,
                        'triggered_by'     => 'Watchdog'
                    ]);

                    $results[$prj->project_code] = [
                        'status' => 'ensured_running',
                        'active' => $primaryTask->task_code
                    ];
                } else {
                    // ÇALIŞILIYOR sütununda hiç AI görevi yok! YAPILACAK İŞLER'de bekleyen AI görevi varsa başlat:
                    $dispatched = self::dispatchNextAiTaskIfAvailable($pId);
                    if ($dispatched) {
                        $results[$prj->project_code] = [
                            'status'    => 'auto_dispatched',
                            'task_code' => $dispatched['task_code'] ?? null
                        ];
                    } else {
                        $results[$prj->project_code] = ['status' => 'idle'];
                    }
                }
            }
            return ['success' => true, 'projects' => $results];
        } catch (\Throwable $e) {
            error_log("runWatchdogInspection error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Son 24 saatteki görev hareketlerini toplayan Standup Aggregation motoru
     */
    public static function getDailyStandupData(?int $projectId = null): array
    {
        global $db;
        if (!$db || empty($db->connect)) {
            require_once __DIR__ . '/../Model/DataLayer.php';
            $db = new Database();
        }

        $projFilterAct = $projectId ? " AND t.project_id = " . (int)$projectId : "";
        $projFilterTask = $projectId ? " AND project_id = " . (int)$projectId : "";

        // 1. Dün / Son 24 Saatte Tamamlanan veya Teslim Edilen Görevler
        $completedTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.status, u.name as assignee_name, a.created_at as activity_time
            FROM task_activities a
            JOIN tasks t ON a.task_id = t.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE a.new_value IN ('completed', 'submitted')
              AND a.created_at >= NOW() - INTERVAL 24 HOUR
              {$projFilterAct}
            GROUP BY t.id
            ORDER BY a.id DESC
        ") ?: [];

        // 2. Bugün Devam Eden / Çalışılan Görevler
        $inProgressTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.deadline, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status = 'in_progress'
              {$projFilterTask}
            ORDER BY t.priority = 'urgent' DESC, t.updated_at DESC
        ") ?: [];

        // 3. Geciken / Riskli Görevler (Teslim tarihi geçmiş veya acil)
        $delayedTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.status, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status NOT IN ('completed', 'cancelled')
              AND (
                  (t.deadline IS NOT NULL AND t.deadline < CURDATE())
                  OR t.priority = 'urgent'
              )
              {$projFilterTask}
            ORDER BY t.deadline ASC LIMIT 15
        ") ?: [];

        // 4. Son 24 Saatte Yeni Eklenen Görevler
        $newTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.created_at >= NOW() - INTERVAL 24 HOUR
              {$projFilterTask}
            ORDER BY t.id DESC LIMIT 15
        ") ?: [];

        return [
            'period'           => date('d.m.Y H:i', strtotime('-24 hours')) . ' - ' . date('d.m.Y H:i'),
            'completed'        => $completedTasks,
            'completed_count'  => count($completedTasks),
            'in_progress'      => $inProgressTasks,
            'in_progress_count'=> count($inProgressTasks),
            'delayed'          => $delayedTasks,
            'delayed_count'    => count($delayedTasks),
            'new_tasks'        => $newTasks,
            'new_count'        => count($newTasks)
        ];
    }

    /**
     * Günlük Standup özetini LLM prompt şablonuyla profesyonel bültene dönüştürür.
     */
    public static function generateAiStandupSummary(?int $projectId = null, ?string $lang = null): array
    {
        $data = self::getDailyStandupData($projectId);
        $isTr = ($lang === 'tr') || (!$lang && class_exists('Language') && Language::getCurrentLanguage() === 'tr');

        // Markdown formatlı zengin bülten içeriği
        $md = $isTr 
            ? "# 📋 Günlük AI Standup & Görev Özeti (" . date('d.m.Y') . ")\n\n"
            : "# 📋 Daily AI Standup & Task Summary (" . date('d.m.Y') . ")\n\n";

        // 1. Completed / Submitted
        $md .= $isTr 
            ? "### 🎯 1. Son 24 Saatte Tamamlanan & Teslim Edilenler ({$data['completed_count']})\n"
            : "### 🎯 1. Completed & Submitted in the Last 24 Hours ({$data['completed_count']})\n";

        if (empty($data['completed'])) {
            $md .= $isTr 
                ? "_Son 24 saatte tamamlanan veya teslim edilen görev kaydedilmedi._\n\n"
                : "_No tasks were completed or submitted in the last 24 hours._\n\n";
        } else {
            foreach ($data['completed'] as $c) {
                if ($isTr) {
                    $statusBadge = ($c->status === 'submitted') ? '[TESLİM EDİLDİ]' : '[TAMAMLANDI]';
                    $assignee = $c->assignee_name ?: 'Atanmamış';
                    $md .= "- **{$c->task_code}**: {$c->title} — {$statusBadge} _(Sorumlu: {$assignee})_\n";
                } else {
                    $statusBadge = ($c->status === 'submitted') ? '[SUBMITTED]' : '[COMPLETED]';
                    $assignee = $c->assignee_name ?: 'Unassigned';
                    $md .= "- **{$c->task_code}**: {$c->title} — {$statusBadge} _(Assignee: {$assignee})_\n";
                }
            }
            $md .= "\n";
        }

        // 2. In Progress
        $md .= $isTr 
            ? "### ⚡ 2. Aktif Çalışılan Görevler ({$data['in_progress_count']})\n"
            : "### ⚡ 2. Active Tasks in Progress ({$data['in_progress_count']})\n";

        if (empty($data['in_progress'])) {
            $md .= $isTr 
                ? "_Şu anda 'Çalışılıyor' durumunda bekleyen aktif görev yok._\n\n"
                : "_There are currently no active tasks in progress._\n\n";
        } else {
            foreach ($data['in_progress'] as $ip) {
                if ($isTr) {
                    $assignee = $ip->assignee_name ?: 'Atanmamış';
                    $md .= "- **{$ip->task_code}**: {$ip->title} _(Öncelik: {$ip->priority}, Sorumlu: {$assignee})_\n";
                } else {
                    $assignee = $ip->assignee_name ?: 'Unassigned';
                    $md .= "- **{$ip->task_code}**: {$ip->title} _(Priority: {$ip->priority}, Assignee: {$assignee})_\n";
                }
            }
            $md .= "\n";
        }

        // 3. Delayed / Risky
        $md .= $isTr 
            ? "### ⚠️ 3. Dikkat Gerektiren Riskli / Geciken Görevler ({$data['delayed_count']})\n"
            : "### ⚠️ 3. Risky / Overdue Tasks Requiring Attention ({$data['delayed_count']})\n";

        if (empty($data['delayed'])) {
            $md .= $isTr 
                ? "✅ _Tebrikler! Takvimde gecikmeye düşen veya kritik riskli görev bulunmuyor._\n\n"
                : "✅ _Great! No overdue or critically risky tasks on the schedule._\n\n";
        } else {
            foreach ($data['delayed'] as $d) {
                if ($isTr) {
                    $deadlineTxt = !empty($d->deadline) ? $d->deadline : 'Tarihsiz Acil';
                    $assignee = $d->assignee_name ?: 'Atanmamış';
                    $md .= "- 🔴 **{$d->task_code}**: {$d->title} (Bitiş: {$deadlineTxt}, Sorumlu: {$assignee})\n";
                } else {
                    $deadlineTxt = !empty($d->deadline) ? $d->deadline : 'Urgent / No Due Date';
                    $assignee = $d->assignee_name ?: 'Unassigned';
                    $md .= "- 🔴 **{$d->task_code}**: {$d->title} (Due: {$deadlineTxt}, Assignee: {$assignee})\n";
                }
            }
            $md .= "\n";
        }

        // 4. Recommendations
        $md .= $isTr 
            ? "### 💡 4. Scrum Master & AI Tavsiyeleri\n"
            : "### 💡 4. Scrum Master & AI Recommendations\n";

        if ($data['delayed_count'] > 0) {
            $md .= $isTr 
                ? "- ⚠️ Geciken görevler için takım içi kaynak transferi önerilir.\n"
                : "- ⚠️ Internal team resource reallocation is recommended for delayed tasks.\n";
        }
        if ($data['in_progress_count'] > 5) {
            $md .= $isTr 
                ? "- 📌 WIP (Work in Progress) limiti yüksek görünüyor; tamamlamaya odaklanılması önerilir.\n"
                : "- 📌 WIP (Work in Progress) limit appears high; focus on task completion is recommended.\n";
        } else {
            $md .= $isTr 
                ? "- 🚀 Ekip akışı sağlıklı, sprint hedeflerine uyum yüksek seyrediyor.\n"
                : "- 🚀 Team workflow is healthy and sprint target alignment is strong.\n";
        }

        return [
            'success'   => true,
            'date'      => date('d.m.Y'),
            'summary'   => $md,
            'raw_data'  => $data
        ];
    }

    /**
     * Opsiyonel Günlük E-Posta Bülteni Gönderimi
     */
    public static function sendDailyStandupEmail(string $recipientEmail, array $standupData): bool
    {
        $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');
        $subject = $isTr 
            ? ("📋 TaskManager Günlük AI Standup Bülteni - " . date('d.m.Y'))
            : ("📋 TaskManager Daily AI Standup Digest - " . date('d.m.Y'));

        $footerText = $isTr 
            ? "Bu bülten TaskManager Otonom AI Asistanı tarafından otomatik oluşturulmuştur."
            : "This digest was automatically generated by TaskManager Autonomous AI Assistant.";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: TaskManager AI <noreply@taskmanager.local>\r\n";

        $htmlBody = "<h2>" . htmlspecialchars($subject) . "</h2>";
        $htmlBody .= "<div style='font-family:sans-serif; line-height:1.6; color:#333; background:#f9f9f9; padding:20px; border-radius:8px;'>";
        $htmlBody .= nl2br(htmlspecialchars($standupData['summary'] ?? ''));
        $htmlBody .= "</div><p style='font-size:12px; color:#888;'>" . htmlspecialchars($footerText) . "</p>";

        return @mail($recipientEmail, $subject, $htmlBody, $headers);
    }

    /**
     * Yüklenen Dökümandan (.docx, .doc, .pdf, .txt, .md, görsel) Metin ve Gömülü Resimleri Çıkarır.
     * Word (.docx) içindeki 'word/media/*' resimlerini otomatik olarak Public/uploads/tasks/ altına kaydeder.
     */
    public static function extractContentAndImagesFromDocument(array $file): array
    {
        $result = [
            'text'           => '',
            'extracted_imgs' => [],
            'original_doc'   => null
        ];

        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return $result;
        }

        $origName = $file['name'] ?? 'document';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $uploadDir = dirname(__DIR__) . '/Public/uploads/tasks';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        // 1. Orijinal dosyayı görevlere ek dosya (attachment) olarak bağlamak üzere kaydet
        $safeOrigName = 'brief_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $origName);
        $origDestPath = $uploadDir . '/' . $safeOrigName;
        $copied = @copy($file['tmp_name'], $origDestPath) || @move_uploaded_file($file['tmp_name'], $origDestPath);
        if ($copied) {
            $result['original_doc'] = [
                'file_name' => $origName,
                'file_path' => '/taskmanager/Public/uploads/tasks/' . $safeOrigName,
                'file_type' => $file['type'] ?? 'application/octet-stream',
                'file_size' => (int)($file['size'] ?? (file_exists($origDestPath) ? filesize($origDestPath) : 0))
            ];
        }

        $sourceFileToRead = file_exists($origDestPath) ? $origDestPath : $file['tmp_name'];

        // 2. Doğrudan Görsel Yüklendiyse (PNG, JPG, WEBP, GIF)
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            if ($result['original_doc']) {
                $result['extracted_imgs'][] = $result['original_doc'];
            }
            return $result;
        }

        // 3. Düz Metin Dosyaları (.txt, .md, .json, .csv, .html)
        if (in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm'], true)) {
            $raw = (string)@file_get_contents($sourceFileToRead);
            $result['text'] = strip_tags($raw);
            return $result;
        }

        // 4. WORD (.DOCX, .DOCM, .DOTX) DOSYASI: Metinleri ve Gömülü Resimleri Çıkar
        if (($ext === 'docx' || $ext === 'docm' || $ext === 'dotx') && class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = ($zip->open($sourceFileToRead, \ZipArchive::RDONLY) === true)
                   || ($zip->open($sourceFileToRead) === true);

            if ($opened) {
                // A) Metinleri Çıkar (word/document.xml, header, footer vb.)
                $textParts = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (preg_match('/^word\/(document\d*|header\d*|footer\d*|footnotes)\.xml$/i', $entryName)) {
                        $xmlData = $zip->getFromIndex($i);
                        if ($xmlData) {
                            $xmlData = preg_replace('/<w:p(?: [^>]*)?>/i', "\n", $xmlData);
                            $xmlData = str_replace(['</w:p>', '</w:tr>', '<w:br/>', '<w:cr/>'], ["\n", "\n", "\n", "\n"], $xmlData);
                            $xmlData = str_replace(['</w:tc>'], ["\t"], $xmlData);
                            $clean = html_entity_decode(strip_tags($xmlData), ENT_QUOTES | ENT_XML1, 'UTF-8');
                            $clean = trim(preg_replace("/[ \t]+/", " ", $clean));
                            if (!empty($clean)) {
                                $textParts[] = $clean;
                            }
                        }
                    }
                }
                if (!empty($textParts)) {
                    $result['text'] = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n\n", $textParts)));
                }

                // B) Word İçindeki Resimleri Çıkar (word/media/*)
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_starts_with(strtolower($entryName), 'word/media/')) {
                        $imgExt = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                        if (in_array($imgExt, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true)) {
                            $imgBuffer = $zip->getFromIndex($i);
                            if ($imgBuffer && strlen($imgBuffer) > 512) {
                                $savedImgName = 'docx_img_' . time() . '_' . ($i + 1) . '.' . $imgExt;
                                $savedFullPath = $uploadDir . '/' . $savedImgName;
                                if (@file_put_contents($savedFullPath, $imgBuffer)) {
                                    $result['extracted_imgs'][] = [
                                        'file_name' => basename($entryName),
                                        'file_path' => '/taskmanager/Public/uploads/tasks/' . $savedImgName,
                                        'file_type' => 'image/' . ($imgExt === 'jpg' ? 'jpeg' : $imgExt),
                                        'file_size' => strlen($imgBuffer)
                                    ];
                                }
                            }
                        }
                    }
                }
                $zip->close();
            }
            return $result;
        }

        // 5. Eski WORD (.DOC) Dosyası (Metin Çıkarımı)
        if ($ext === 'doc') {
            $raw = (string)@file_get_contents($sourceFileToRead);
            $extractedDocText = '';
            if (preg_match_all('/[\x20-\x7E\t\r\n]{4,}/', $raw, $matches)) {
                $extractedDocText = implode(" ", $matches[0]);
            }
            if (mb_strlen($extractedDocText) < 50 && preg_match_all('/(?:[\x20-\x7E]\x00){4,}/', $raw, $matches16)) {
                $extractedDocText .= " " . mb_convert_encoding(implode("", $matches16[0]), 'UTF-8', 'UTF-16LE');
            }
            $result['text'] = trim(strip_tags($extractedDocText));
            return $result;
        }

        // 6. PDF Dosyası (Basit Akış Metin Çıkarma)
        if ($ext === 'pdf') {
            $rawPdf = (string)@file_get_contents($sourceFileToRead);
            $extracted = '';
            if (preg_match_all('/\(([^\)]+)\)\s*Tj/s', $rawPdf, $matches)) {
                $extracted = implode(' ', $matches[1]);
            }
            if (mb_strlen(trim($extracted)) < 20 && preg_match_all('/\[([^\]]+)\]\s*TJ/s', $rawPdf, $matches2)) {
                $extracted .= ' ' . preg_replace('/[\(\)\-\d\.]+/', ' ', implode(' ', $matches2[1]));
            }
            $result['text'] = trim($extracted);
            return $result;
        }

        return $result;
    }

    /**
     * AI Proje Mimarı (Task Decomposer):
     * Kullanıcının girdiği proje brifini ve/veya yüklediği Word/PDF dökümanını analiz edip
     * her görev için detaylı "Nasıl Yapılacak, Teknik Adımlar ve Kabul Kriterleri (DoD)" içeren
     * modüler Ana Görev (Epic) ve Alt Görev ağacını oluşturur.
     */
    public static function decomposeBriefWithAi(string $brief, int $projectId = 1, array $docInfo = []): array
    {
        global $db;
        $docText = trim($docInfo['text'] ?? '');
        $hasDoc = !empty($docInfo['original_doc']);
        $combinedBrief = trim($brief . ($docText ? "\n\n--- UPLOADED DOCUMENT CONTENT ---\n" . $docText : ''));

        // Eğer ne metin var, ne döküman var, ne de görsel var
        if (empty($combinedBrief) && empty($docInfo['extracted_imgs']) && !$hasDoc) {
            $isTr = (class_exists('Language') && Language::getCurrentLanguage() === 'tr');
            return [
                'success' => false, 
                'error' => $isTr 
                    ? 'Lütfen ayrıştırılacak proje brifini yazın veya bir Word (.docx) / PDF dökümanı yükleyin.' 
                    : 'Please provide the project brief or upload a Word (.docx) / PDF document to decompose.'
            ];
        }

        // Eğer döküman yüklendi ama içinden sadece görsel çıktıysa
        if (empty($combinedBrief) && !empty($docInfo['extracted_imgs'])) {
            $combinedBrief = "Analyze the uploaded UI screenshots, architecture mockups, and wireframes to generate full frontend and backend implementation tasks.";
        }

        // Eğer döküman yüklendi ama içinden metin çıkmadıysa (örn: salt çizim/tablo)
        if (empty($combinedBrief) && $hasDoc) {
            $docName = $docInfo['original_doc']['file_name'] ?? 'Proje Şartnamesi';
            $combinedBrief = "Yüklenen dökümanı ([{$docName}]) analiz ederek projenin gereksinim duyduğu modülleri, arayüz geliştirmelerini, teknik altyapıyı ve canlı ortam kabul kriterlerini oluştur.";
        }

        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }
            $project = $db->row_query("SELECT project_code, title, description, agent_rules FROM projects WHERE id = ?", [$projectId]);
            $settings = $db->row_query("SELECT active_ai_provider, default_ai_model, gemini_api_key, openai_api_key, deepseek_api_key FROM settings LIMIT 1");
        } catch (\Throwable $e) {
            $project = null;
            $settings = null;
        }

        $projTitle = $project->title ?? 'Proje';
        $projCode  = $project->project_code ?? 'PRJ';
        $projDesc  = $project->description ?? '';
        $projRules = $project->agent_rules ?? '';
        $imgCount  = count($docInfo['extracted_imgs'] ?? []);

        // AI DIRECTIVE: Instructions are strictly in English, but the generated output MUST match user language
        $systemPrompt = "You are a Senior Technical Project Architect and Software Engineering Lead.
Your task is to analyze the user's project brief, contract specifications, or uploaded document and decompose it into modular Parent Tasks (Functional Epics/Modules) and concrete, single-purpose Subtasks.

CRITICAL ARCHITECTURAL RULES:
1. NEVER output mere titles with empty or generic descriptions! For EVERY Parent Task ('description') and EVERY Subtask ('description'), you MUST provide a detailed, actionable breakdown containing:
   - Purpose & Functional Scope (Görev Amacı ve Kapsamı)
   - Step-by-Step Implementation Guide (🛠️ Nasıl Yapılacak / Adımlar)
   - Acceptance & Test Criteria / Definition of Done (✅ Kabul ve Test Kriterleri)
2. NEVER create a single generic 'Coding' module! Break features into distinct functional modules (e.g., 'Homepage & UI Presentation', 'Cart, Coupon & Checkout Module', 'Admin Management & CRUD', 'Technical SEO & Sitemaps', 'Legal & Compliance Pages').
3. Subtask category MUST be one of: 'coding', 'design', 'seo', 'marketing', 'legal', 'content'. NEVER assign the 'ai' tag!
4. 'execution_mode': If the module is automated software infrastructure, mark as 'auto' (Continuous Unattended Flow); if it requires design, SEO, legal, or client acceptance, mark as 'gated' (Step-by-Step Approval Required).
5. MULTILINGUAL OUTPUT MANDATE: You MUST write all task titles, descriptions, step-by-step guides, and acceptance criteria in the EXACT SAME LANGUAGE used in the user's brief or document (e.g., if the user input is Turkish, write all titles and descriptions in Turkish; if German, in German; if English, in English).
6. Return ONLY valid JSON matching this schema, with no markdown code fences or conversational text:
{
  \"epics\": [
    {
      \"title\": \"Functional Module Title\",
      \"description\": \"Architectural scope, purpose, and integration notes\",
      \"priority\": \"high\",
      \"execution_mode\": \"auto\",
      \"subtasks\": [
        {
          \"title\": \"Concrete subtask title\",
          \"description\": \"🛠️ NASIL YAPILACAK (Uygulama Adımları):\\n1. ...\\n2. ...\\n\\n✅ KABUL VE TEST KRİTERLERİ (DoD):\\n- ...\",
          \"category\": \"coding\",
          \"priority\": \"normal\",
          \"time_estimate\": \"2h\"
        }
      ]
    }
  ]
}";

        $userPrompt = "Project: [{$projCode}] {$projTitle}\n"
            . ($projDesc ? "Project Overview: {$projDesc}\n" : "")
            . ($projRules ? "Technical Rules & Stack: {$projRules}\n" : "")
            . ($imgCount > 0 ? "Note: {$imgCount} reference screenshots/diagrams were extracted from the document and will be attached.\n" : "")
            . "\nUser Brief & Document Content:\n" . mb_substr($combinedBrief, 0, 18000) . "\n\n"
            . "Please analyze the above requirements and generate the complete modular JSON task tree with comprehensive step-by-step 'how-to' guides in each subtask description. Match the user's language:";

        // 1. Gemini API Denemesi (Model Kaskadı: Biri meşgulse diğerine otomatik geçer)
        $apiKey = trim($settings->gemini_api_key ?? '');
        if (!empty($apiKey) && !str_contains($apiKey, 'test_') && strlen($apiKey) >= 20) {
            $candidateModels = ['gemini-3.7-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-flash'];
            $configuredModel = $settings->default_ai_model ?? '';
            if (!empty($configuredModel) && !in_array($configuredModel, $candidateModels, true)) {
                array_unshift($candidateModels, $configuredModel);
            }

            $lastGeminiError = '';
            foreach ($candidateModels as $tryModel) {
                if (str_contains($tryModel, '1.5') || str_contains($tryModel, '2.5')) {
                    continue;
                }
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$tryModel}:generateContent?key=" . urlencode($apiKey);
                $payload = [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents'          => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                    'generationConfig'  => [
                        'temperature'      => 0.2,
                        'maxOutputTokens'  => 8192,
                        'responseMimeType' => 'application/json'
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    CURLOPT_TIMEOUT        => 90,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
                    $parsed = json_decode($cleaned, true);
                    if (!empty($parsed['epics']) && is_array($parsed['epics'])) {
                        return self::sanitizeUtf8Deep([
                            'success'        => true,
                            'epics'          => $parsed['epics'],
                            'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
                            'original_doc'   => $docInfo['original_doc'] ?? null,
                            'engine'         => 'gemini (' . $tryModel . ')'
                        ]);
                    }
                } else {
                    $errObj = json_decode((string)$response, true);
                    $lastGeminiError = $errObj['error']['message'] ?? "HTTP $httpCode";
                    // 503 veya 429 gibi yoğunluk hatalarında sıradaki Gemini modeline geç
                    continue;
                }
            }

            // Gerçek bir API anahtarı girildiyse ve Google modellerinin tamamı hata verdiyse
            // kullanıcıya ASLA saçma regex çıktıları gösterme, gerçek hatayı bildir!
            if (!empty($lastGeminiError)) {
                return [
                    'success' => false,
                    'error'   => "Google Gemini API geçici hata yanıtı verdi: {$lastGeminiError}. Lütfen 5-10 saniye sonra tekrar deneyin."
                ];
            }
        }

        // 2. OpenAI / DeepSeek API Denemesi
        $openAiKey = trim($settings->openai_api_key ?? '');
        $deepSeekKey = trim($settings->deepseek_api_key ?? '');
        if ((!empty($openAiKey) && !str_contains($openAiKey, 'test_')) || (!empty($deepSeekKey) && !str_contains($deepSeekKey, 'test_'))) {
            $useDeepSeek = empty($openAiKey) || ($settings->active_ai_provider ?? '') === 'deepseek';
            $url = $useDeepSeek ? "https://api.deepseek.com/chat/completions" : "https://api.openai.com/v1/chat/completions";
            $key = $useDeepSeek ? $deepSeekKey : $openAiKey;
            $model = $useDeepSeek ? 'deepseek-chat' : 'gpt-4o-mini';

            $payload = [
                'model'           => $model,
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ],
                'temperature'     => 0.2,
                'response_format' => ['type' => 'json_object']
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? '';
                $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
                $parsed = json_decode($cleaned, true);
                if (!empty($parsed['epics']) && is_array($parsed['epics'])) {
                    return [
                        'success'        => true,
                        'epics'          => $parsed['epics'],
                        'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
                        'original_doc'   => $docInfo['original_doc'] ?? null,
                        'engine'         => $useDeepSeek ? 'deepseek' : 'openai'
                    ];
                }
            }
        }

        // 3. Dahili Akıllı Mimari Ayrıştırıcı (Offline / Dynamic Heuristic Fallback - API anahtarı olmasa bile %100 çalışır)
        $epics = self::decomposeBriefDynamicParser($combinedBrief, $projTitle, $projRules);
        return self::sanitizeUtf8Deep([
            'success'        => true,
            'epics'          => $epics,
            'extracted_imgs' => $docInfo['extracted_imgs'] ?? [],
            'original_doc'   => $docInfo['original_doc'] ?? null,
            'engine'         => 'dynamic_parser'
        ]);
    }

    /**
     * API Anahtarı olmadığında veya çevrimdışı modda brifi ve dökümanı analiz edip
     * modüler Ana Görev ve her alt görev için detaylı "Nasıl Yapılacak & Kabul Kriterleri (DoD)" üreten uzman motor.
     */
    public static function decomposeBriefDynamicParser(string $brief, string $projTitle = 'Proje', string $projRules = ''): array
    {
        $epics = [];
        $cleanBrief = str_replace("--- UPLOADED DOCUMENT CONTENT ---", "\n", $brief);
        $cleanBrief = str_replace("--- YÜKLENEN DÖKÜMAN İÇERİĞİ ---", "\n", $cleanBrief);
        $rawLines = preg_split('/[\r\n]+/', trim($cleanBrief));
        $currentEpic = null;

        $detectCategory = function(string $text): string {
            $l = mb_strtolower($text, 'UTF-8');
            if (preg_match('/(tasarım|ui|ux|renk|ikon|css|görsel|tema|buton|responsive|mobil görünüm|figma|font|ekran|front|slider|vitrin)/u', $l)) return 'design';
            if (preg_match('/(seo|sitemap|meta|google|anahtar kelime|canonical|index|robots|arama motoru)/u', $l)) return 'seo';
            if (preg_match('/(reklam|kampanya|pazarlama|sosyal medya|instagram|bülten|mail|bildirim|analitik)/u', $l)) return 'marketing';
            if (preg_match('/(sözleşme|kvkk|yasal|hukuk|gizlilik|iade|şartname|çerez|aydınlatma|legal)/u', $l)) return 'legal';
            if (preg_match('/(yazı|makale|içerik|metin|blog|açıklama|çeviri|dil|content)/u', $l)) return 'content';
            return 'coding';
        };

        // Kategoriye ve göreve göre zengin "Nasıl Yapılacak & DoD" şablonu oluşturucu
        $buildDetailedHowTo = function(string $taskTitle, string $rawContext, string $cat) use ($projTitle, $projRules): string {
            $steps = "📌 **GÖREV AMACI VE KAPSAMI:**\n{$rawContext}\n\n🛠️ **NASIL YAPILACAK (UYGULAMA ADIMLARI):**\n";
            if ($cat === 'design') {
                $steps .= "1. İlgili sayfa ve bileşenlerin UI/UX gereksinimlerini belirle.\n"
                        . "2. Masaüstü ve mobil ekranlarda responsive uyumu, renk kontrastlarını ve buton yerleşimlerini CSS ile kodla.\n"
                        . "3. Farklı çözünürlüklerde (mobil, tablet, masaüstü) taşma ve hizalama olmadığını doğrula.\n";
            } elseif ($cat === 'seo') {
                $steps .= "1. İlgili sayfaların URL yapısını (slug), Meta Title, Meta Description ve OpenGraph etiketlerini yapılandır.\n"
                        . "2. Arama motoru standartlarına uygun Sitemap.xml veya JSON-LD yapılandırılmış veri şemasını kodla.\n"
                        . "3. Canlı sayfa kaynağını ve arama motoru önizlemelerini doğrula.\n";
            } elseif ($cat === 'legal' || $cat === 'content') {
                $steps .= "1. İlgili kurumsal veya yasal metin şablonunu mevzuata uygun şekilde hazırla.\n"
                        . "2. Şablon sayfalarını veya form onay kutucuklarını (checkbox) kullanıcı arayüzüne entegre et.\n"
                        . "3. Kullanıcı onay kayıtlarının veritabanında tarih ve IP bilgisiyle saklandığını doğrula.\n";
            } else {
                $steps .= "1. **Veri & Model Katmanı:** Gerekli veritabanı tablolarını, sütunları veya Model sorgu metodlarını hazırla.\n"
                        . "2. **Backend & İş Mantığı:** Controller ve AJAX servis uçlarını veri doğrulama ve yetkilendirme kurallarıyla kodla.\n"
                        . "3. **Arayüz Entegrasyonu:** Kullanıcı arayüzündeki buton ve formları bağla, dinamik geri bildirimleri (Toast/Modal) test et.\n";
            }

            $steps .= "\n✅ **KABUL VE TEST KRİTERLERİ (DoD):**\n"
                    . "- İşlem sırasında hiçbir PHP hatası veya JavaScript konsol hatası oluşmamalıdır.\n"
                    . "- Canlı ortamda (`{$projTitle}`) uçtan uca test edilerek çalıştığı kanıtlanmalıdır.";

            if (!empty($projRules)) {
                $steps .= "\n- Proje mimari kurallarına (AGENTS.md) %100 uyulmalıdır.";
            }
            return $steps;
        };

        // Standart Anahtar Kelime Taraması (Sepet, Frontend, SEO, Yasal)
        $lower = mb_strtolower($cleanBrief, 'UTF-8');
        if (str_contains($lower, 'sepet') || str_contains($lower, 'ödeme') || str_contains($lower, 'kupon') || str_contains($lower, 'iyzico') || str_contains($lower, 'stripe') || str_contains($lower, 'ticaret') || str_contains($lower, 'cart') || str_contains($lower, 'checkout')) {
            $epics[] = [
                'title'          => 'Sepet, Kupon ve Ödeme Modülü',
                'description'    => 'Kullanıcıların sepete ürün eklemesi, adet güncellemesi, kupon uygulaması ve ödeme akışı geliştirmeleri.',
                'priority'       => 'high',
                'execution_mode' => 'auto',
                'subtasks'       => [
                    [
                        'title'         => 'Sepet, Sipariş ve İndirim Kuponu Veritabanı Tabloları',
                        'description'   => $buildDetailedHowTo('Sepet Veritabanı Tabloları', 'Cart, orders ve coupon tablolarının şema tasarımı ve indekslemeleri.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'high',
                        'time_estimate' => '2h'
                    ],
                    [
                        'title'         => 'Sepete Ekle, Adet Güncelle ve Sil AJAX Servisleri',
                        'description'   => $buildDetailedHowTo('Sepet AJAX Servisleri', 'Sayfa yenilenmeden dinamik sepet ara toplam, KDV ve genel toplam hesaplama servisi.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'high',
                        'time_estimate' => '3h'
                    ],
                    [
                        'title'         => 'İndirim Kuponu Doğrulama ve İndirim Uygulama Motoru',
                        'description'   => $buildDetailedHowTo('Kupon Doğrulama', 'Yüzdelik ve sabit tutarlı kuponların geçerlilik tarihi ve sepet tutarı kontrolleri.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'normal',
                        'time_estimate' => '2h'
                    ],
                    [
                        'title'         => 'Ödeme Geçidi (Payment Gateway) API ve Callback Entegrasyonu',
                        'description'   => $buildDetailedHowTo('Ödeme Entegrasyonu', '3D Secure ödeme akışı başlatma, banka dönüşü doğrulama ve sipariş tamamlama.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'urgent',
                        'time_estimate' => '4h'
                    ]
                ]
            ];
        }

        if (str_contains($lower, 'ana sayfa') || str_contains($lower, 'tasarım') || str_contains($lower, 'vitrin') || str_contains($lower, 'slider') || str_contains($lower, 'mobil') || str_contains($lower, 'ui') || str_contains($lower, 'frontend')) {
            $epics[] = [
                'title'          => 'Önyüz (Frontend) & Ana Sayfa Tasarımı',
                'description'    => 'Kullanıcı karşılama ekranları, responsive tasarım uyumu ve etkileşimli vitrin bileşenleri.',
                'priority'       => 'high',
                'execution_mode' => 'gated',
                'subtasks'       => [
                    [
                        'title'         => 'Hero Alanı, Navigasyon ve Üst Bar (Header) Arayüzü',
                        'description'   => $buildDetailedHowTo('Header & Hero Alanı', 'Masaüstü ve mobil uyumlu modern üst menü ve karşılama alanı tasarımı.', 'design'),
                        'category'      => 'design',
                        'priority'      => 'high',
                        'time_estimate' => '3h'
                    ],
                    [
                        'title'         => 'Dinamik Vitrin ve İçerik Kartları Grid Yapısı',
                        'description'   => $buildDetailedHowTo('Vitrin Grid Yapısı', 'Filtrelenebilir ve hızlı yüklenen içerik listeleme kartları bileşeni.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'normal',
                        'time_estimate' => '3h'
                    ],
                    [
                        'title'         => 'Mobil Responsive Uyum ve Ekran Testleri',
                        'description'   => $buildDetailedHowTo('Mobil Responsive Testi', 'Farklı ekran çözünürlüklerinde taşma ve dokunmatik etkileşim kontrolü.', 'design'),
                        'category'      => 'design',
                        'priority'      => 'normal',
                        'time_estimate' => '2h'
                    ]
                ]
            ];
        }

        if (str_contains($lower, 'seo') || str_contains($lower, 'google') || str_contains($lower, 'sitemap') || str_contains($lower, 'robots') || str_contains($lower, 'meta')) {
            $epics[] = [
                'title'          => 'Teknik SEO ve Arama Motoru Altyapısı',
                'description'    => 'Arama motoru optimizasyonu, meta etiketler ve site haritası yapılandırması.',
                'priority'       => 'normal',
                'execution_mode' => 'gated',
                'subtasks'       => [
                    [
                        'title'         => 'Dinamik Sitemap.xml ve Robots.txt Servisleri',
                        'description'   => $buildDetailedHowTo('Sitemap & Robots', 'Sistemdeki sayfaların otomatik olarak XML site haritasına yansıtılması.', 'seo'),
                        'category'      => 'seo',
                        'priority'      => 'normal',
                        'time_estimate' => '2h'
                    ],
                    [
                        'title'         => 'Sayfa Bazlı Dinamik Meta Title, Description ve OpenGraph Etiketleri',
                        'description'   => $buildDetailedHowTo('Meta & OpenGraph', 'Sosyal medya paylaşım önizlemeleri ve SEO uyumlu URL (slug) yapısı.', 'seo'),
                        'category'      => 'seo',
                        'priority'      => 'normal',
                        'time_estimate' => '2h'
                    ]
                ]
            ];
        }

        if (str_contains($lower, 'kvkk') || str_contains($lower, 'sözleşme') || str_contains($lower, 'yasal') || str_contains($lower, 'gizlilik') || str_contains($lower, 'iade') || str_contains($lower, 'çerez') || str_contains($lower, 'legal')) {
            $epics[] = [
                'title'          => 'Yasal Metinler, KVKK ve Kurumsal Sayfalar',
                'description'    => 'Yasal uyumluluk sayfaları, onay kutucukları ve çerez politikası bildirimleri.',
                'priority'       => 'normal',
                'execution_mode' => 'gated',
                'subtasks'       => [
                    [
                        'title'         => 'KVKK Aydınlatma Metni ve Gizlilik Politikası Sayfaları',
                        'description'   => $buildDetailedHowTo('KVKK Sayfaları', 'Kurumsal metinlerin sisteme girilmesi ve footer bağlantılarının tamamlanması.', 'legal'),
                        'category'      => 'legal',
                        'priority'      => 'normal',
                        'time_estimate' => '2h'
                    ],
                    [
                        'title'         => 'Kayıt ve Formlara Zorunlu Sözleşme Onay Checkboxlarının Eklenmesi',
                        'description'   => $buildDetailedHowTo('Sözleşme Onayları', 'Kullanıcı onaylarının IP ve tarih loglarıyla veritabanında saklanması.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'high',
                        'time_estimate' => '2h'
                    ]
                ]
            ];
        }

        // Eğer kullanıcı maddeler/paragraflar halinde ek gereksinimler yazdıysa onları da ayrıştır
        foreach ($rawLines as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 4) continue;

            $isHeader = preg_match('/^(#+|\d+[\.\)]|\*+|\b(?:modül|bölüm|aşama|faz|grup|ana görev|ekran)\b)[\s\:\-]*(.+)$/iu', $line)
                        || (str_ends_with($line, ':') && mb_strlen($line) < 90);

            if ($isHeader && mb_strlen($line) < 95 && !str_starts_with($line, '-') && !str_starts_with($line, '•')) {
                if ($currentEpic && !empty($currentEpic['subtasks'])) {
                    $epics[] = $currentEpic;
                }
                $cleanHeader = rtrim(preg_replace('/^(#+|\d+[\.\)]|\*+)\s*/u', '', $line), ': ');
                $cleanHeader = mb_convert_encoding($cleanHeader, 'UTF-8', 'UTF-8');
                $cat = $detectCategory($cleanHeader);
                $currentEpic = [
                    'title'          => $cleanHeader,
                    'description'    => "{$cleanHeader} modülü kapsamındaki tüm teknik, arayüz ve doğrulama geliştirmeleri.",
                    'priority'       => 'high',
                    'execution_mode' => in_array($cat, ['design', 'seo', 'legal', 'marketing'], true) ? 'gated' : 'auto',
                    'subtasks'       => []
                ];
                continue;
            }

            if ($currentEpic && !empty($currentEpic['subtasks']) && preg_match('/^(?:açıklama|detay|not|nasıl|adım|kriter)[\s\:]/iu', $line)) {
                $lastIdx = count($currentEpic['subtasks']) - 1;
                $currentEpic['subtasks'][$lastIdx]['description'] .= "\n📌 **Ek Detay:** " . $line;
                continue;
            }

            $cleanSub = trim(preg_replace('/^[\-\x{2022}\x{2013}\x{2014}\*\>\+\d\.\)\s]+/u', '', $line));
            $cleanSub = mb_convert_encoding($cleanSub, 'UTF-8', 'UTF-8');
            if (mb_strlen($cleanSub) >= 4) {
                if (!$currentEpic) {
                    $currentEpic = [
                        'title'          => $projTitle . ' - Özel Geliştirme Maddeleri',
                        'description'    => 'Kullanıcı dökümanında ve brifinde belirtilen özel isterler.',
                        'priority'       => 'high',
                        'execution_mode' => 'auto',
                        'subtasks'       => []
                    ];
                }
                $cat = $detectCategory($cleanSub);
                $shortTitle = mb_strlen($cleanSub) > 95 ? (mb_substr($cleanSub, 0, 92) . '...') : $cleanSub;
                $currentEpic['subtasks'][] = [
                    'title'         => $shortTitle,
                    'description'   => $buildDetailedHowTo($shortTitle, $cleanSub, $cat),
                    'category'      => $cat,
                    'priority'      => 'normal',
                    'time_estimate' => '2h'
                ];
            }
        }

        if ($currentEpic && !empty($currentEpic['subtasks'])) {
            $epics[] = $currentEpic;
        }

        // Eğer hiç modül üretilemediyse temel 1 modül oluştur
        if (empty($epics)) {
            $epics[] = [
                'title'          => $projTitle . ' - Genel Geliştirme Modülü',
                'description'    => 'Proje brifinde belirtilen temel geliştirmeler.',
                'priority'       => 'high',
                'execution_mode' => 'auto',
                'subtasks'       => [
                    [
                        'title'         => 'Veri ve Altyapı Hazırlığı',
                        'description'   => $buildDetailedHowTo('Veri ve Altyapı', 'İsterler doğrultusunda veritabanı şeması ve model metodlarının oluşturulması.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'high',
                        'time_estimate' => '2h'
                    ],
                    [
                        'title'         => 'Kullanıcı Arayüzü ve İş Akışı Entegrasyonu',
                        'description'   => $buildDetailedHowTo('Arayüz Entegrasyonu', 'Önyüz bileşenlerinin ve form akışlarının kodlanması.', 'coding'),
                        'category'      => 'coding',
                        'priority'      => 'normal',
                        'time_estimate' => '3h'
                    ]
                ]
            ];
        }

        return $epics;
    }

    /**
     * Geriye uyumluluk için alias
     */
    public static function decomposeBriefHeuristic(string $brief, string $projTitle = 'Proje'): array
    {
        return self::decomposeBriefDynamicParser($brief, $projTitle);
    }

    /**
     * Tüm veri ağacını (string ve diziler) güvenli ve geçerli UTF-8 formatına dönüştürür.
     */
    public static function sanitizeUtf8Deep(mixed $data): mixed
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $k => $v) {
                $cleanKey = is_string($k) ? mb_convert_encoding($k, 'UTF-8', 'UTF-8') : $k;
                $cleaned[$cleanKey] = self::sanitizeUtf8Deep($v);
            }
            return $cleaned;
        }
        if (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = self::sanitizeUtf8Deep($v);
            }
            return $data;
        }
        return $data;
    }

    // =========================================================================
    // 🐙 GITHUB DEVOPS & CERRAHİ ROLLBACK VERİTABANI MOTORU
    // =========================================================================

    /**
     * Gerekli veritabanı sütunlarını (settings, projects, task_submissions) otomatik kontrol eder ve yoksa oluşturur.
     */
    public static function ensureGithubAndRollbackSchema(): void
    {
        global $db;
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            // 1. settings tablosuna github_username ve github_token ekle
            $colsSettings = $db->multi_record_query("SHOW COLUMNS FROM settings") ?: [];
            $sNames = array_map(fn($c) => $c->Field, $colsSettings);
            if (!in_array('github_username', $sNames)) {
                $db->exec_query("ALTER TABLE settings ADD COLUMN github_username VARCHAR(120) NULL DEFAULT NULL");
            }
            if (!in_array('github_token', $sNames)) {
                $db->exec_query("ALTER TABLE settings ADD COLUMN github_token VARCHAR(255) NULL DEFAULT NULL");
            }

            // 2. projects tablosuna github_repo ve db_name ekle
            $colsProjects = $db->multi_record_query("SHOW COLUMNS FROM projects") ?: [];
            $pNames = array_map(fn($c) => $c->Field, $colsProjects);
            if (!in_array('github_repo', $pNames)) {
                $db->exec_query("ALTER TABLE projects ADD COLUMN github_repo VARCHAR(180) NULL DEFAULT NULL");
            }
            if (!in_array('db_name', $pNames)) {
                $db->exec_query("ALTER TABLE projects ADD COLUMN db_name VARCHAR(120) NULL DEFAULT NULL");
            }

            // 3. task_submissions tablosuna commit_hash, rollback_sql, schema_diff_summary, is_reverted ekle
            $colsSub = $db->multi_record_query("SHOW COLUMNS FROM task_submissions") ?: [];
            $subNames = array_map(fn($c) => $c->Field, $colsSub);
            if (!in_array('commit_hash', $subNames)) {
                $db->exec_query("ALTER TABLE task_submissions ADD COLUMN commit_hash VARCHAR(64) NULL DEFAULT NULL");
            }
            if (!in_array('rollback_sql', $subNames)) {
                $db->exec_query("ALTER TABLE task_submissions ADD COLUMN rollback_sql LONGTEXT NULL DEFAULT NULL");
            }
            if (!in_array('schema_diff_summary', $subNames)) {
                $db->exec_query("ALTER TABLE task_submissions ADD COLUMN schema_diff_summary TEXT NULL DEFAULT NULL");
            }
            if (!in_array('is_reverted', $subNames)) {
                $db->exec_query("ALTER TABLE task_submissions ADD COLUMN is_reverted TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            error_log("ensureGithubAndRollbackSchema error: " . $e->getMessage());
        }
    }

    /**
     * TaskManager'da kayıtlı projelerin çalışma dizinlerindeki mevcut Git remote origin adreslerini
     * sisteme zarar vermeden otomatik keşfeder ve projects.github_repo alanına işler.
     */
    public static function discoverAndSyncExistingProjectRemotes(): array
    {
        global $db;
        self::ensureGithubAndRollbackSchema();

        $projects = $db->multi_record_query("SELECT id, project_code, title, workspace_path, github_repo FROM projects") ?: [];
        $results = [];

        foreach ($projects as $p) {
            $ws = rtrim(str_replace('\\', '/', trim((string)($p->workspace_path ?? ''))), '/');
            if (empty($ws) || !is_dir($ws) || !is_dir($ws . '/.git')) {
                continue;
            }

            // Mevcut git remote url'sini oku (Asla remote silmez veya değiştirmez!)
            $escapedWs = escapeshellarg($ws);
            $remoteUrl = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));

            if (!empty($remoteUrl)) {
                // github.com/owner/repo veya git@github.com:owner/repo desenini yakala
                if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $remoteUrl, $m)) {
                    $repoSlug = trim($m[1]) . '/' . trim($m[2]);

                    if (empty($p->github_repo) || $p->github_repo !== $repoSlug) {
                        $db->exec_query("UPDATE projects SET github_repo = ?, updated_at = NOW() WHERE id = ?", [$repoSlug, $p->id]);
                        $results[$p->project_code] = [
                            'title'  => $p->title,
                            'repo'   => $repoSlug,
                            'status' => 'updated'
                        ];
                    } else {
                        $results[$p->project_code] = [
                            'title'  => $p->title,
                            'repo'   => $repoSlug,
                            'status' => 'already_synced'
                        ];
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Sistem Ayarlarındaki GitHub Kimlik Bilgilerini döndürür.
     */
    public static function getGithubCredentials(): array
    {
        global $db;
        self::ensureGithubAndRollbackSchema();
        $row = $db->row_query("SELECT github_username, github_token FROM settings WHERE id = 1 LIMIT 1");
        return [
            'username' => trim((string)($row->github_username ?? '')),
            'token'    => trim((string)($row->github_token ?? ''))
        ];
    }

    /**
     * Projenin çalışma dizinindeki aktif Git branch'ini dinamik olarak tespit eder.
     * Sabit 'main' varsaymaz; master, dev vb. tüm branch adlarına tam uyum sağlar.
     */
    public static function detectActiveBranch(string $workspacePath): string
    {
        $ws = rtrim(str_replace('\\', '/', trim($workspacePath)), '/');
        if (empty($ws) || !is_dir($ws . '/.git')) {
            return 'main';
        }
        $escapedWs = escapeshellarg($ws);
        $branch = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse --abbrev-ref HEAD 2>nul"));
        if (empty($branch) || $branch === 'HEAD') {
            return 'main';
        }
        return $branch;
    }

    /**
     * Projeyi GitHub'a bağlar:
     * - Eğer projede zaten .git ve remote origin varsa MEVCUT REPOYU KORUR, asla ezmez veya silmez!
     * - Sadece dizinde hiç .git veya remote yoksa GitHub API ile yeni Private Repo açar ve .gitignore oluşturur.
     */
    public static function initAndSyncProjectGithubRepo(int $projectId): array
    {
        global $db;
        self::ensureGithubAndRollbackSchema();

        $project = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        if (!$project) return ['success' => false, 'error' => 'Proje bulunamadı.'];

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => 'Projenin yerel çalışma dizini (workspace_path) tanımlı değil veya klasör bulunamadı.'];
        }

        $escapedWs = escapeshellarg($ws);

        // 1. Önce mevcut bir remote origin var mı kontrol et (MEVCUTLARI ASLA BOZMA!)
        if (is_dir($ws . '/.git')) {
            $existingRemote = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));
            if (!empty($existingRemote)) {
                if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $existingRemote, $m)) {
                    $repoSlug = trim($m[1]) . '/' . trim($m[2]);
                    $db->exec_query("UPDATE projects SET github_repo = ?, updated_at = NOW() WHERE id = ?", [$repoSlug, $project->id]);
                    return [
                        'success'     => true,
                        'github_repo' => $repoSlug,
                        'repo_url'    => "https://github.com/{$repoSlug}",
                        'message'     => "Mevcut GitHub deposu korundu ve projeye bağlandı ({$repoSlug})!"
                    ];
                }
            }
        }

        $creds = self::getGithubCredentials();
        if (empty($creds['username']) || empty($creds['token'])) {
            return ['success' => false, 'error' => 'Lütfen önce Sistem Ayarları ekranından GitHub Kullanıcı Adı ve Personal Access Token (PAT) bilgilerinizi kaydedin.'];
        }

        // 2. Repo adını belirle (Örn: ufka-yolculuk)
        $repoSlug = trim((string)($project->github_repo ?? ''));
        if (empty($repoSlug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', str_replace(['ı','ğ','ü','ş','ö','ç','İ','Ğ','Ü','Ş','Ö','Ç'], ['i','g','u','s','o','c','i','g','u','s','o','c'], $project->title)));
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            if (empty($slug)) $slug = strtolower($project->project_code);
            $repoSlug = $creds['username'] . '/' . $slug;
        } elseif (strpos($repoSlug, '/') === false) {
            $repoSlug = $creds['username'] . '/' . $repoSlug;
        }

        [$owner, $repoName] = explode('/', $repoSlug, 2);

        // 3. GitHub API üzerinden Private Repository oluştur (Zaten varsa 422 döner, sorun değil)
        $ch = curl_init('https://api.github.com/user/repos');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $creds['token'],
                'User-Agent: TaskManager-DevOps-Engine',
                'Accept: application/vnd.github+json',
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'name'        => $repoName,
                'description' => $project->title . ' - Managed by TaskManager AI DevOps',
                'private'     => true,
                'auto_init'   => false
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 422 && $httpCode !== 200) {
            $errJson = json_decode((string)$resp, true);
            $errMsg  = $errJson['message'] ?? 'HTTP ' . $httpCode;
            return ['success' => false, 'error' => "GitHub API Hatası: {$errMsg}"];
        }

        // 4. .gitignore yoksa oluştur
        $gitignorePath = $ws . '/.gitignore';
        if (!file_exists($gitignorePath)) {
            $ignoreContent = ".env\n/vendor/\n/node_modules/\n/Public/uploads/\n*.sql\n*.zip\n*.log\n.DS_Store\nThumbs.db\n";
            @file_put_contents($gitignorePath, $ignoreContent);
        }

        // 5. Git init, clean remote add ve dinamik branch ile ilk push
        $cleanRemoteUrl = "https://github.com/{$owner}/{$repoName}.git";
        $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");

        if (!is_dir($ws . '/.git')) {
            @shell_exec("cd /d {$escapedWs} && git init && git branch -M main 2>&1");
        }

        $runGit = function(string $cmd) use ($escapedWs): string {
            return (string)@shell_exec("cd /d {$escapedWs} && {$cmd} 2>&1");
        };

        $runGit("git config user.name " . escapeshellarg($creds['username']));
        $runGit("git config user.email " . escapeshellarg($creds['username'] . "@users.noreply.github.com"));
        $runGit("git remote remove origin");
        $runGit("git remote add origin " . escapeshellarg($cleanRemoteUrl));
        $runGit("git add -A");
        $runGit('git commit -m "chore: initial repository setup via TaskManager"');

        $branch = self::detectActiveBranch($ws);
        $output = $runGit("git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push -u origin {$branch}");

        // Eğer push edilen branch 'main' değilse (örn: 'master'), GitHub default branch'i otomatik güncelle ki repo boş görünmesin!
        if (!empty($branch) && $branch !== 'main') {
            $chBranch = curl_init("https://api.github.com/repos/{$owner}/{$repoName}");
            curl_setopt_array($chBranch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'PATCH',
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $creds['token'],
                    'User-Agent: TaskManager-DevOps',
                    'Accept: application/vnd.github+json',
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS     => json_encode(['default_branch' => $branch]),
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            curl_exec($chBranch);
            curl_close($chBranch);
        }

        $db->exec_query("UPDATE projects SET github_repo = ?, updated_at = NOW() WHERE id = ?", ["{$owner}/{$repoName}", $project->id]);

        return [
            'success'     => true,
            'github_repo' => "{$owner}/{$repoName}",
            'repo_url'    => "https://github.com/{$owner}/{$repoName}",
            'message'     => "Proje başarıyla GitHub'a ({$owner}/{$repoName}) bağlandı ve senkronize edildi!",
            'git_output'  => $output
        ];
    }

    /**
     * Görev teslim edildiğinde (submitTaskDirectly) otomatik Git Commit & Güvenli GitHub Push yapar.
     * Token asla diskteki remote URL içine yazılmaz; http.extraheader ile anlık iletilir.
     * Aktif branch dinamik tespit edilir (main/master).
     */
    public static function autoCommitAndPushTask(object $project, object $task): array
    {
        self::ensureGithubAndRollbackSchema();

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => 'Workspace dizini yok.'];
        }

        $escapedWs = escapeshellarg($ws);

        if (!is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => 'Git deposu (.git) bulunamadı.'];
        }

        $commitMsg = "feat({$task->task_code}): " . str_replace(['"', '`', '$'], '', $task->title);

        // Değişiklikleri ekle ve commit at
        $commitCmd = "cd /d {$escapedWs} && git add -A && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
        @shell_exec($commitCmd);

        // Son commit hash'ini al
        $hash = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse HEAD 2>nul"));
        if (empty($hash) || strlen($hash) < 7) {
            return ['success' => false, 'error' => 'Commit oluşturulamadı veya dosya değişikliği yok.'];
        }

        // Aktif branch'i dinamik bul (main/master)
        $branch = self::detectActiveBranch($ws);

        // Projede github_repo tanımlı mı? Yoksa mevcut remote'u oku
        $repoSlug = trim((string)($project->github_repo ?? ''));
        if (empty($repoSlug)) {
            $existingRemote = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));
            if (!empty($existingRemote) && preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $existingRemote, $m)) {
                $repoSlug = trim($m[1]) . '/' . trim($m[2]);
            }
        }

        $commitUrl = '';
        $creds = self::getGithubCredentials();

        if (!empty($repoSlug)) {
            $commitUrl = "https://github.com/{$repoSlug}/commit/{$hash}";

            // Güvenli push: Token varsa http.extraheader ile gönder
            if (!empty($creds['token']) && !empty($creds['username'])) {
                $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
                $pushCmd = "cd /d {$escapedWs} && git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1";
                @shell_exec($pushCmd);
            } else {
                // Token yoksa mevcut sistem git kimliğiyle push dene
                @shell_exec("cd /d {$escapedWs} && git push origin {$branch} 2>&1");
            }
        }

        return [
            'success'     => true,
            'commit_hash' => $hash,
            'commit_url'  => $commitUrl,
            'branch'      => $branch
        ];
    }

    /**
     * Hedef projenin kullandığı MySQL veritabanı adını otomatik keşfeder (veya projects.db_name'den okur).
     */
    public static function resolveProjectDatabaseName(object $project): ?string
    {
        self::ensureGithubAndRollbackSchema();
        if (!empty($project->db_name)) {
            return trim((string)$project->db_name);
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return 'taskmanager';
        }

        // 1. .env dosyasından DB_DATABASE veya DB_NAME oku
        if (file_exists($ws . '/.env')) {
            $envContent = (string)@file_get_contents($ws . '/.env');
            if (preg_match('/^\s*(?:DB_DATABASE|DB_NAME|database\.default\.database)\s*=\s*[\'"]?([a-zA-Z0-9_]+)[\'"]?/m', $envContent, $m)) {
                return $m[1];
            }
        }

        // 2. Model/DataLayer.php, config/database.php veya config.php tara
        $candidates = [
            $ws . '/config/database.php',
            $ws . '/Config/database.php',
            $ws . '/Model/DataLayer.php',
            $ws . '/System/Database.php',
            $ws . '/Config/Database.php',
            $ws . '/config.php',
            $ws . '/wp-config.php'
        ];
        foreach ($candidates as $file) {
            if (file_exists($file)) {
                $code = (string)@file_get_contents($file);
                if (preg_match('/dbname\s*=\s*([a-zA-Z0-9_]+)/i', $code, $m)) return $m[1];
                if (preg_match('/[\'"](?:database|db_name|dbname|DB_NAME)[\'"]\s*(?:=>|:|=)\s*[\'"]([a-zA-Z0-9_]+)[\'"]/i', $code, $m)) return $m[1];
                if (preg_match('/\$[\'"]?(?:db_name|dbname|database)[\'"]?\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/i', $code, $m)) return $m[1];
            }
        }

        if ((int)$project->id === 1 || $project->project_code === '01') {
            return 'taskmanager';
        }

        return null;
    }

    /**
     * Hedef projenin veritabanı tablo ve sütun iskeletini (Sadece ~5 KB!) JSON dizisi olarak çıkarır.
     */
    public static function getProjectSchemaMap(string $dbName): array
    {
        global $db;
        $map = [];
        if (empty($dbName)) return $map;

        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            $rows = $db->multi_record_query("
                SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = ? 
                ORDER BY TABLE_NAME, ORDINAL_POSITION
            ", [$dbName]) ?: [];

            foreach ($rows as $r) {
                $t = $r->TABLE_NAME;
                if (!isset($map[$t])) $map[$t] = [];
                $map[$t][$r->COLUMN_NAME] = $r->COLUMN_TYPE;
            }
        } catch (\Throwable $e) {
            error_log("getProjectSchemaMap error: " . $e->getMessage());
        }

        return $map;
    }

    /**
     * Görev ÇALIŞILIYOR (in_progress) durumuna geçtiğinde 5 KB'lık şema özetini geçici belleğe kaydeder.
     */
    public static function capturePreTaskSnapshot(int $taskId): void
    {
        global $db;
        try {
            if (!$db || empty($db->connect)) {
                require_once __DIR__ . '/../Model/DataLayer.php';
                $db = new Database();
            }

            $task = $db->row_query("SELECT id, task_code, project_id FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
            if (!$task) return;
            $project = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$task->project_id]);
            if (!$project) return;

            $dbName = self::resolveProjectDatabaseName($project);
            if (!$dbName) return;

            $schemaMap = self::getProjectSchemaMap($dbName);
            if (empty($schemaMap)) return;

            $snapDir = __DIR__ . '/../Public/uploads/schema_snapshots';
            if (!is_dir($snapDir)) @mkdir($snapDir, 0777, true);

            @file_put_contents($snapDir . '/snap_task_' . (int)$task->id . '.json', json_encode([
                'db_name'    => $dbName,
                'task_code'  => $task->task_code,
                'created_at' => date('c'),
                'schema'     => $schemaMap
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            error_log("capturePreTaskSnapshot error: " . $e->getMessage());
        }
    }

    /**
     * Görev tamamlandığında önceki şema ile yeni şemayı karşılaştırır, Rollback SQL üretir ve 5 KB'lık geçici dosyayı siler!
     */
    public static function computePostTaskSchemaDiff(int $taskId, object $project, string $taskCode): array
    {
        $sqlStatements = [];
        $summaryParts  = [];

        // 1. Ajanın kendi yazdığı .migrations/{task_code}_down.sql dosyası varsa oku
        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (!empty($ws) && is_dir($ws)) {
            $agentDownFile = $ws . '/.migrations/' . $taskCode . '_down.sql';
            if (file_exists($agentDownFile)) {
                $customSql = trim((string)@file_get_contents($agentDownFile));
                if ($customSql !== '') {
                    $sqlStatements[] = "-- Agent Custom Rollback SQL:\n" . $customSql;
                    $summaryParts[]  = "Ajan özel geri alma SQL'i (.migrations/{$taskCode}_down.sql)";
                }
            }
        }

        // 2. Otomatik 5 KB Şema Farkı (Diff)
        $snapFile = __DIR__ . '/../Public/uploads/schema_snapshots/snap_task_' . $taskId . '.json';
        if (file_exists($snapFile)) {
            $snapData = json_decode((string)@file_get_contents($snapFile), true);
            $dbName   = $snapData['db_name'] ?? self::resolveProjectDatabaseName($project);
            $oldMap   = $snapData['schema'] ?? [];

            if ($dbName && !empty($oldMap)) {
                $newMap = self::getProjectSchemaMap($dbName);
                foreach ($newMap as $tableName => $cols) {
                    if (!isset($oldMap[$tableName])) {
                        // Görev sırasında YENİ TABLO açılmış!
                        $sqlStatements[] = "DROP TABLE IF EXISTS `{$dbName}`.`{$tableName}`;";
                        $summaryParts[]  = "`{$tableName}` tablosu oluşturuldu";
                    } else {
                        // Mevcut tabloya YENİ SÜTUN eklenmiş mi?
                        foreach ($cols as $colName => $colType) {
                            if (!isset($oldMap[$tableName][$colName])) {
                                $sqlStatements[] = "ALTER TABLE `{$dbName}`.`{$tableName}` DROP COLUMN `{$colName}`;";
                                $summaryParts[]  = "`{$tableName}.{$colName}` sütunu eklendi";
                            }
                        }
                    }
                }
            }
            // Diskte 0 Byte yer kaplaması için geçici şema dosyasını hemen temizle!
            @unlink($snapFile);
        }

        return [
            'rollback_sql' => implode("\n", $sqlStatements),
            'summary'      => !empty($summaryParts) ? implode(', ', $summaryParts) : 'Veritabanı şema değişikliği yok (Sadece Kod)'
        ];
    }

    /**
     * CERRAHİ GERİ ALMA (ROLLBACK):
     * 1. Önce git revert --no-commit ile sonraki görevlerle çakışma (conflict) var mı test eder.
     * 2. Çakışma varsa sistemi bozmaz, abort eder, 'has_conflict' döner ve kullanıcıya AI Cerrah seçeneği sunar.
     * 3. Çakışma yoksa sadece o görevin DB tablosunu/sütununu geri alır ve commit'ini revert edip GitHub'a pushlar!
     * 4. AI Cerrah modunda ($mode === 'ai_surgeon'): Görevi revizyona alıp AI ajanına diğer görevleri koruyarak sadece bu görevi temizleme talimatı verir.
     */
    public static function rollbackTaskChanges(int $taskId, string $mode = 'safe'): array
    {
        global $db;
        self::ensureGithubAndRollbackSchema();

        if (!$db || empty($db->connect)) {
            require_once __DIR__ . '/../Model/DataLayer.php';
            $db = new Database();
        }

        $task = $db->row_query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
        if (!$task) return ['success' => false, 'error' => 'Görev bulunamadı.'];

        $project = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$task->project_id]);
        if (!$project) return ['success' => false, 'error' => 'Proje bulunamadı.'];

        $sub = $db->row_query("SELECT * FROM task_submissions WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]);
        if (!$sub || empty($sub->commit_hash)) {
            return ['success' => false, 'error' => 'Bu göreve ait kayıtlı bir Git Commit Hash bulunamadı.'];
        }
        if ((int)($sub->is_reverted ?? 0) === 1) {
            return ['success' => false, 'error' => 'Bu görevin değişiklikleri zaten daha önce geri alınmış.'];
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => 'Proje dizininde Git deposu (.git) bulunamadı.'];
        }

        $hash      = trim($sub->commit_hash);
        $escapedWs = escapeshellarg($ws);

        // =========================================================================
        // AŞAMA 2: EĞER KULLANICI "AI CERRAH İLE AYIKLA" MODUNU SEÇTİYSE
        // =========================================================================
        if ($mode === 'ai_surgeon') {
            $reason = "⚠️ CERRAHİ GERİ ALMA (ROLLBACK) TALEBİ:\n"
                . "`{$task->task_code}` ({$task->title}) görevinde yapılan değişiklikler geri alınmak isteniyor ancak bu commit (`{$hash}`) sonrasında başka bağımlı görevler tamamlandığı için doğrudan `git revert` çakışma (conflict) üretiyor.\n\n"
                . "GÖREVİN:\n"
                . "1. `git show {$hash}` komutuyla bu görevde hangi dosya ve satırların değiştiğini dikkatle incele.\n"
                . "2. Bu görevden SONRA yapılan diğer görevlerin kodlarına ve özelliklerine KESİNLİKLE ZARAR VERMEDEN, sadece `{$task->task_code}` görevinde eklenen/değiştirilen kodları ve veritabanı yapılarını (" . ($sub->schema_diff_summary ?: 'varsa tabloları') . ") cımbızla sistemden temizle.\n"
                . "3. Sistemi canlı ortamda test et, çalıştığını kanıtla ve tekrar teslim et!";

            $db->exec_query("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$task->id]);
            $db->exec_query("INSERT INTO task_modifications (task_id, requested_by, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())", [
                $task->id, $_SESSION['user']->id ?? 1, $reason
            ]);
            self::triggerTaskRevisionWebhook((int)$task->id, $reason);

            return [
                'success' => true,
                'mode'    => 'ai_surgeon',
                'message' => "🤖 AI Cerrah görevlendirildi! Sonraki görevlere dokunmadan sadece {$task->task_code} görevinin kodlarını ve DB değişikliklerini ayıklayacak."
            ];
        }

        // =========================================================================
        // AŞAMA 1: GÜVENLİ KURU TEST (DRY-RUN / ÇAKIŞMA TESTİ)
        // =========================================================================
        $testRevert = (string)@shell_exec("cd /d {$escapedWs} && git revert --no-commit " . escapeshellarg($hash) . " 2>&1");
        $statusCheck = (string)@shell_exec("cd /d {$escapedWs} && git status --porcelain 2>&1");

        // Unmerged / Conflict (UU, AA, DD, DU, UD) veya error/conflict mesajı var mı kontrol et
        if (preg_match('/^(UU|AA|DD|DU|UD)\s/m', $statusCheck) || stripos($testRevert, 'conflict') !== false || stripos($testRevert, 'error:') !== false) {
            // Hemen işlemden vazgeç ve çalışma dizinini ilk haline getirerek tertemiz bırak!
            @shell_exec("cd /d {$escapedWs} && git revert --abort 2>nul || git reset --hard HEAD 2>nul");

            return [
                'success'      => false,
                'has_conflict' => true,
                'commit_hash'  => substr($hash, 0, 7),
                'error'        => "Bağımlılık / Çakışma Tespit Edildi! {$task->task_code} görevinden sonra yapılan bazı görevler bu görevin yazdığı kodların üstüne inşa edilmiş.",
                'details'      => $testRevert
            ];
        }

        // =========================================================================
        // AŞAMA 3: ÇAKIŞMA YOK! SADECE BU GÖREVİN DEĞİŞİKLİKLERİNİ GERİ AL
        // =========================================================================
        $revertMsg = "Revert \"{$task->task_code}: {$task->title}\" (Commit {$hash})";
        @shell_exec("cd /d {$escapedWs} && git commit -m " . escapeshellarg($revertMsg) . " 2>&1");

        // Dinamik branch ve güvenli token ile push et
        $branch = self::detectActiveBranch($ws);
        $creds = self::getGithubCredentials();
        if (!empty($creds['username']) && !empty($creds['token'])) {
            $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
            @shell_exec("cd /d {$escapedWs} && git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1");
        } else {
            @shell_exec("cd /d {$escapedWs} && git push origin {$branch} 2>&1");
        }

        // VERİTABANI DEĞİŞİKLİKLERİNİ GERİ AL (Varsa sadece bu görevin açtığı tablo/sütun/satırları sil)
        $dbRolledBack = false;
        if (!empty($sub->rollback_sql)) {
            $statements = array_filter(array_map('trim', explode(';', $sub->rollback_sql)));
            foreach ($statements as $sqlStmt) {
                $cleanSql = trim(preg_replace('/^--.*$/m', '', $sqlStmt));
                if ($cleanSql !== '') {
                    try {
                        $db->exec_query($cleanSql);
                        $dbRolledBack = true;
                    } catch (\Throwable $e) {
                        error_log("Rollback SQL error: " . $e->getMessage());
                    }
                }
            }
        }

        // Teslimat kaydını 'is_reverted = 1' olarak işaretle ve aktivite ekle
        $db->exec_query("UPDATE task_submissions SET is_reverted = 1 WHERE id = ?", [$sub->id]);
        $db->exec_query("
            INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
            VALUES (?, ?, 'task_reverted', ?, NOW())
        ", [
            $task->id, $_SESSION['user']->id ?? 1,
            "⏪ Görev değişiklikleri GitHub'dan" . ($dbRolledBack ? " ve Veritabanından ({$sub->schema_diff_summary})" : "") . " cerrahi olarak geri alındı (Commit: " . substr($hash, 0, 7) . ")."
        ]);

        return [
            'success'        => true,
            'db_rolled_back' => $dbRolledBack,
            'message'        => "✅ {$task->task_code} görevinin kodları GitHub'dan" . ($dbRolledBack ? " ve açtığı veritabanı yapıları MySQL'den" : "") . " diğer görevlere dokunulmadan cerrahi olarak geri alındı!"
        ];
    }
}


// Global Yardımcı Fonksiyonlar
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Helper::config($key, $default);
    }
}

if (!function_exists('env')) {
    function env(): string
    {
        return Helper::env();
    }
}

if (!function_exists('is_dev')) {
    function is_dev(): bool
    {
        return Helper::isDev();
    }
}

if (!function_exists('is_production')) {
    function is_production(): bool
    {
        return Helper::isProduction();
    }
}

