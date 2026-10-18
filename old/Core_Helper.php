<?php

use Modules\Board\Services\TaskLifecycleService;
use Modules\GithubDevops\Services\GitService;
use Modules\RestApi\Services\WebhookService;
use Modules\Dispatcher\Services\DispatcherService;
use Modules\AiArchitect\Services\GeminiClient;
use Modules\AiArchitect\Services\TaskDecomposerService;
use Modules\Reports\Services\StandupService;
use Modules\SSE\Services\SseBroadcaster;
use Modules\Watchdog\Services\WatchdogService;

/**
 * Helper - Core Pure Utilities and Unified Facade Bridge.
 * 
 * Domain-specific heavy implementations have been disintegrated and moved directly into:
 * - Modules\Board\Services\TaskLifecycleService (Task workflow, submission, dispatch queue)
 * - Modules\GithubDevops\Services\GitService (Git commit, origin push, surgical rollback, schema diff)
 * - Modules\RestApi\Services\WebhookService (Project webhooks, chat notifications)
 * - Modules\Dispatcher\Services\DispatcherService (Antigravity language server discovery & dispatch)
 * - Modules\AiArchitect\Services\GeminiClient & TaskDecomposerService (Prompt engineering & brief decomposition)
 * - Modules\Reports\Services\StandupService (Standup aggregation & digest generator)
 * - Modules\SSE\Services\SseBroadcaster (Realtime SSE broadcasts)
 * - Modules\Watchdog\Services\WatchdogService (Autonomous recovery & queue monitoring)
 */
class Helper
{
    /**
     * Çoklu dil destekli SEO / URL dostu slug oluşturucu (TR, DE, EN vb.)
     */
    public static function slug(string $text): string
    {
        $charMap = [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
            'ä' => 'ae', 'Ä' => 'ae', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ñ' => 'n',
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

    public static function format_date_mounthname(string $mysql_datetime): string
    {
        $timestamp = strtotime($mysql_datetime);
        return $timestamp !== false ? date('F j, Y', $timestamp) : "Invalid Date";
    }

    public static function format_date_mounthnameInTurkish(string $mysql_datetime): string
    {
        return self::format_date_localized($mysql_datetime);
    }

    /**
     * Çok dilli tarih formatı (TR, EN, DE)
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

    public static function add_date_day(string $who_date, int|string $which_day): string
    {
        return date('Y-m-d', strtotime("+{$which_day} day", strtotime($who_date)));
    }

    public static function delete_date_day(string $who_date, int|string $which_day): string
    {
        return date('Y-m-d', strtotime("-{$which_day} day", strtotime($who_date)));
    }

    public static function percentage_calculation(float|int $number, float|int $percentage): float|int
    {
        return ($number * $percentage) / 100;
    }

    public static function getRandomUserAgent(): string
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0"
        ];
        return $userAgents[array_rand($userAgents)];
    }

    public static function HttpStatus(int $code): string
    {
        $status = [
            200 => 'OK', 201 => 'Created', 204 => 'No Content',
            400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
            500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        ];
        return $status[$code] ?? 'Unknown Status';
    }

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

    public static function urlSegment(int $index = 1): string
    {
        $url = ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $explode = explode('/', $url);
        return isset($explode[$index]) ? str_replace('-', '', $explode[$index]) : '';
    }

    public static function fullUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return $protocol . $host . $uri;
    }

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

    public static function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    public static function activePage(): string
    {
        $file = explode('/', $_SERVER['SCRIPT_NAME'] ?? '');
        return end($file);
    }

    public static function setHeader(int $code): void
    {
        header("HTTP/1.1 {$code} " . self::HttpStatus($code));
        header("Content-Type: application/json; charset=utf-8");
    }

    private static array $configCache = [];

    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = $parts[0];

        if (count($parts) === 1 && $file === 'env') {
            $file = 'app';
            $parts = ['app', 'env'];
        }

        if (!isset(self::$configCache[$file])) {
            $candidates = [
                __DIR__ . '/Config/' . $file . '.php',
                __DIR__ . '/../config/' . $file . '.php'
            ];
            $loaded = false;
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    self::$configCache[$file] = require $candidate;
                    $loaded = true;
                    break;
                }
            }
            if (!$loaded) {
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

    public static function env(): string
    {
        return strtolower((string)self::config('app.env', 'development'));
    }

    public static function isDev(): bool
    {
        $env = self::env();
        return in_array($env, ['dev', 'development', 'local'], true);
    }

    public static function isProduction(): bool
    {
        return !self::isDev();
    }

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

    public static function fixUtf8Mojibake(?string $text): string
    {
        return TaskLifecycleService::fixUtf8Mojibake($text);
    }

    // =========================================================================
    // MODULAR DISPATCH & SYSTEM CONFIGURATION
    // =========================================================================

    public static function getDynamicApiConfig(): array
    {
        $baseUrl = 'http://localhost/taskmanager';
        $apiToken = '';

        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
            }

            $sUrl = $db->single_query("SELECT siteurl FROM settings WHERE id = 1");
            if (!empty($sUrl)) {
                $baseUrl = rtrim(trim((string)$sUrl), '/');
            } elseif (!empty($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/taskmanager';
            }

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

    public static function getAgentSubmitCommandBlock(string $taskCode): string
    {
        return DispatcherService::getAgentSubmitCommandBlock($taskCode);
    }

    public static function formatAgentMessage(string $event, object $project, array $data): array
    {
        return DispatcherService::formatAgentMessage($event, $project, $data);
    }

    // =========================================================================
    // MODULAR DELEGATION BRIDGES (ZERO HARD DEPENDENCIES)
    // =========================================================================

    public static function isAiTask(mixed $taskOrTags): bool
    {
        return TaskLifecycleService::isAiTask($taskOrTags);
    }

    public static function submitTaskDirectly(int|string $taskIdOrCode, string $notes = 'Görev tamamlandı ve test edildi.'): array
    {
        return TaskLifecycleService::submitTaskDirectly($taskIdOrCode, $notes);
    }

    public static function dispatchNextAiTaskIfAvailable(int $projectId, ?int $justFinishedTaskId = null): ?array
    {
        return TaskLifecycleService::dispatchNextAiTaskIfAvailable($projectId, $justFinishedTaskId);
    }

    public static function triggerTaskStartedWebhook(int $taskId, ?string $oldStatus = null, array $extra = []): array
    {
        return TaskLifecycleService::triggerTaskStartedWebhook($taskId, $oldStatus, $extra);
    }

    public static function triggerTaskRevisionWebhook(int $taskId, string $reason, array $extra = []): array
    {
        return TaskLifecycleService::triggerTaskRevisionWebhook($taskId, $reason, $extra);
    }

    public static function triggerProjectWebhook(int $projectId, string $event, array $data = []): array
    {
        return WebhookService::triggerProjectWebhook($projectId, $event, $data);
    }

    public static function dispatchChatWebhooks(string $event, string $projectTitle, array $data = []): void
    {
        WebhookService::dispatchChatWebhooks($event, $projectTitle, $data);
    }

    public static function postJsonWebhook(string $url, array $payload): array
    {
        return WebhookService::postJsonWebhook($url, $payload);
    }

    public static function ensureDispatcherRunning(): void
    {
        $disp = new DispatcherService();
        $disp->ensureRunning();
    }

    public static function dispatchDirectToAntigravity(object $project, string $event, array $data = []): array
    {
        $disp = new DispatcherService();
        return $disp->dispatchDirect($project, $event, $data);
    }

    public static function discoverLatestConversation(?string $workspacePath = null, ?string $projectCode = null): ?string
    {
        $disp = new DispatcherService();
        return $disp->discoverLatestConversation($workspacePath, $projectCode);
    }

    public static function getAntigravityLsEnv(?string $workspacePath = null): array
    {
        $disp = new DispatcherService();
        return $disp->getAntigravityLsEnv($workspacePath);
    }

    public static function getAntigravityLsAddress(): ?string
    {
        $disp = new DispatcherService();
        return $disp->getAntigravityLsAddress();
    }

    public static function syncWorkspacesJson(): bool
    {
        $disp = new DispatcherService();
        return $disp->syncWorkspaces();
    }

    public static function removeProjectFromWorkspacesJson(string $projectCode): void
    {
        $disp = new DispatcherService();
        $disp->removeProjectWorkspace($projectCode);
    }

    public static function purgeProject(int $projectId): bool
    {
        return ProjectService::purgeProject($projectId);
    }

    public static function ensureGithubAndRollbackSchema(): void
    {
        GitService::ensureGithubAndRollbackSchema();
    }

    public static function getGithubCredentials(): array
    {
        return GitService::getGithubCredentials();
    }

    public static function detectActiveBranch(string $workspacePath): string
    {
        return GitService::detectActiveBranch($workspacePath);
    }

    public static function discoverAndSyncExistingProjectRemotes(): array
    {
        return GitService::discoverAndSyncExistingProjectRemotes();
    }

    public static function initAndSyncProjectGithubRepo(int $projectId): array
    {
        return GitService::initAndSyncProjectGithubRepo($projectId);
    }

    public static function autoCommitAndPushTask(object $project, object $task): array
    {
        return GitService::autoCommitAndPushTask($project, $task);
    }

    public static function resolveProjectDatabaseName(object $project): ?string
    {
        return GitService::resolveProjectDatabaseName($project);
    }

    public static function getProjectSchemaMap(string $dbName): array
    {
        return GitService::getProjectSchemaMap($dbName);
    }

    public static function capturePreTaskSnapshot(int $taskId): void
    {
        GitService::capturePreTaskSnapshot($taskId);
    }

    public static function computePostTaskSchemaDiff(int $taskId, object $project, string $taskCode): array
    {
        return GitService::computePostTaskSchemaDiff($taskId, $project, $taskCode);
    }

    public static function rollbackTaskChanges(int $taskId, string $mode = 'safe'): array
    {
        return GitService::rollbackTaskChanges($taskId, $mode);
    }

    public static function refineTaskPromptToSpecification(
        int $projectId,
        string $userInstruction,
        array $referenceImages = [],
        array $referenceFiles = [],
        bool $skipLiveApi = false
    ): array {
        $client = new GeminiClient();
        return $client->refineTaskSpecification($projectId, $userInstruction, $referenceImages, $referenceFiles, $skipLiveApi);
    }

    public static function refineRulesWithAi(string $rawRules, string $projectCode = '', string $projectTitle = ''): array
    {
        $client = new GeminiClient();
        return $client->refineRulesWithAi($rawRules, $projectCode, $projectTitle);
    }

    public static function compileRulesHeuristic(string $raw, string $code = '', string $title = ''): string
    {
        $client = new GeminiClient();
        return $client->compileRulesHeuristic($raw, $code, $title);
    }

    public static function extractContentAndImagesFromDocument(array $file): array
    {
        return TaskDecomposerService::extractContentAndImagesFromDocument($file);
    }

    public static function decomposeBriefWithAi(string $brief, int $projectId = 1, array $docInfo = []): array
    {
        return TaskDecomposerService::decomposeBriefWithAi($brief, $projectId, $docInfo);
    }

    public static function decomposeBriefDynamicParser(string $brief, string $projTitle = 'Proje', string $projRules = ''): array
    {
        return TaskDecomposerService::decomposeBriefDynamicParser($brief, $projTitle, $projRules);
    }

    public static function decomposeBriefHeuristic(string $brief, string $projTitle = 'Proje'): array
    {
        return TaskDecomposerService::decomposeBriefDynamicParser($brief, $projTitle);
    }

    public static function broadcastLiveEvent(string $eventType, ?int $projectId, ?int $taskId, ?int $userId, array $payload): bool
    {
        return SseBroadcaster::broadcast($eventType, $projectId, $taskId, $userId, $payload);
    }

    public static function runWatchdogInspection(): array
    {
        return WatchdogService::runInspection();
    }

    public static function getDailyStandupData(?int $projectId = null): array
    {
        return StandupService::getDailyStandupData($projectId);
    }

    public static function generateAiStandupSummary(?int $projectId = null, ?string $lang = null): array
    {
        return StandupService::generateAiStandupSummary($projectId, $lang);
    }

    public static function sendDailyStandupEmail(string $recipientEmail, array $standupData): bool
    {
        return StandupService::sendDailyStandupEmail($recipientEmail, $standupData);
    }

    public static function syncProjectRulesToWorkspace(object|int $project): array
    {
        return ProjectService::syncProjectRulesToWorkspace($project);
    }
}

// Global Helper Functions
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
