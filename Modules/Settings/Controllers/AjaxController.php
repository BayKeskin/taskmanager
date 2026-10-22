<?php
namespace Modules\Settings\Controllers;

use Core\BaseController;
use Modules\Settings\Models\SettingsModel;

class AjaxController extends BaseController
{
    private SettingsModel $settingsModel;
    private \Modules\Settings\Models\AiAccountsModel $aiAccountsModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingsModel = new SettingsModel();
        $this->aiAccountsModel = new \Modules\Settings\Models\AiAccountsModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getAiAccounts(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_admin'));
            return;
        }
        $accounts = $this->aiAccountsModel->getAllAccounts();
        $this->respond(true, '', ['accounts' => $accounts]);
    }

    public function syncLocalVaultAccounts(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_admin'));
            return;
        }

        // Python account_manager üzerinden canlı kotaları çek ve veritabanına aktar
        $pythonPath = 'C:\\Users\\DELL\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }
        $scriptPath = dirname(__DIR__, 2) . '/Dispatcher/Bin/account_manager.py';

        $output = [];
        $returnVar = 0;
        $cmd = "\"$pythonPath\" -c \"import json, sys; sys.path.append(r'" . dirname(__DIR__, 2) . "/Dispatcher/Bin'); from account_manager import AccountManager; mgr = AccountManager(); print(json.dumps(mgr.inspect_accounts_health(target_model_family='gemini-3.8')))\"";
        
        exec($cmd, $output, $returnVar);
        $rawJson = implode('', $output);
        $accountsData = json_decode($rawJson, true);

        if (!is_array($accountsData)) {
            $this->respond(false, __('Settings::ai_account_sync_failed'));
            return;
        }

        $importedCount = 0;
        foreach ($accountsData as $acc) {
            $email = $acc['email'] ?? '';
            if (!$email) continue;

            $creds = $acc['creds'] ?? [];
            $this->aiAccountsModel->upsertAccount([
                'email'             => $email,
                'name'              => $creds['name'] ?? explode('@', $email)[0],
                'refresh_token'     => $creds['refresh_token'] ?? '',
                'access_token'      => $creds['access_token'] ?? '',
                'expiry_timestamp'  => $creds['expiry_timestamp'] ?? 0,
                'gemini_quota_pct'  => (int)($acc['target_model_pct'] ?? 100),
                'claude_quota_pct'  => (int)($acc['claude_quota_pct'] ?? 100),
                'status'            => $acc['status'] ?? 'HEALTHY',
                'reset_time'        => $acc['reset_time'] ?? null
            ]);
            $importedCount++;
        }

        $this->respond(true, str_replace(':count', (string)$importedCount, __('Settings::ai_account_sync_success')), [
            'accounts' => $this->aiAccountsModel->getAllAccounts()
        ]);
    }

    public function activateAiAccount(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_admin'));
            return;
        }
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            $this->respond(false, __('Settings::ai_account_email_required'));
            return;
        }

        $this->aiAccountsModel->setActiveAccount($email);

        // Python üzerinden Antigravity'ye de anında bildir
        $pythonPath = 'C:\\Users\\DELL\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }
        $acc = $this->aiAccountsModel->getAccountByEmail($email);
        if ($acc) {
            $payloadJson = escapeshellarg(json_encode([
                'email' => $email,
                'creds' => [
                    'access_token' => $acc['access_token'],
                    'refresh_token' => $acc['refresh_token'],
                    'expiry_timestamp' => $acc['expiry_timestamp'],
                    'name' => $acc['name']
                ]
            ]));
            $cmd = "\"$pythonPath\" -c \"import json, sys; sys.path.append(r'" . dirname(__DIR__, 2) . "/Dispatcher/Bin'); from account_manager import AccountManager; mgr = AccountManager(); mgr.switch_active_account(json.loads($payloadJson))\"";
            @exec($cmd);
        }

        $this->respond(true, __('Settings::ai_account_activated_success'), [
            'accounts' => $this->aiAccountsModel->getAllAccounts()
        ]);
    }

    public function deleteAiAccount(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_admin'));
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->respond(false, __('Settings::ai_account_invalid_id'));
            return;
        }

        $this->aiAccountsModel->deleteAccount($id);
        $this->respond(true, __('Settings::ai_account_deleted_success'), [
            'accounts' => $this->aiAccountsModel->getAllAccounts()
        ]);
    }

    public function saveSettings(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_admin'));
        }

        $defaultSiteUrl = function_exists('siteUrl') ? siteUrl() : 'http://localhost';
        $title = trim($_POST['title'] ?? 'TaskManager Pro');
        $siteurl = trim($_POST['siteurl'] ?? $defaultSiteUrl);
        $employeeCanCreate = !empty($_POST['employee_can_create_tasks']) ? 1 : 0;
        $employeeCanAi = !empty($_POST['employee_can_ai_tasks']) ? 1 : 0;
        $defaultLanguage = trim($_POST['default_language'] ?? 'tr');
        if (!in_array($defaultLanguage, ['tr', 'en', 'de'])) {
            $defaultLanguage = 'tr';
        }

        $activeAiProvider = trim($_POST['active_ai_provider'] ?? 'gemini');
        $defaultAiModel = trim($_POST['default_ai_model'] ?? 'gemini-1.5-pro');
        $openaiApiKey = trim($_POST['openai_api_key'] ?? '');
        $anthropicApiKey = trim($_POST['anthropic_api_key'] ?? '');
        $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
        $deepseekApiKey = trim($_POST['deepseek_api_key'] ?? '');
        $aiSystemPromptPrefix = trim($_POST['ai_system_prompt_prefix'] ?? '');

        $telegramBotToken = trim($_POST['telegram_bot_token'] ?? '');
        $telegramChatId = trim($_POST['telegram_chat_id'] ?? '');
        $telegramNotifications = !empty($_POST['telegram_notifications_enabled']) ? 1 : 0;
        $slackWebhook = trim($_POST['slack_webhook_url'] ?? '');
        $discordWebhook = trim($_POST['discord_webhook_url'] ?? '');

        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpSecure = trim($_POST['smtp_secure'] ?? 'tls');
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPass = trim($_POST['smtp_pass'] ?? '');
        $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
        $smtpFromName = trim($_POST['smtp_from_name'] ?? 'TaskManager');

        $bugherdApiKey = trim($_POST['bugherd_api_key'] ?? '');
        $bugherdProjectId = trim($_POST['bugherd_project_id'] ?? '');
        $githubToken = trim($_POST['github_token'] ?? '');
        $githubRepo = trim($_POST['github_repo'] ?? '');

        $sessionTimeout = (int)($_POST['session_timeout_minutes'] ?? 120);
        $maxFileUpload = (int)($_POST['max_file_upload_mb'] ?? 25);
        $allowedFileTypes = trim($_POST['allowed_file_types'] ?? 'jpg,png,pdf,zip,docx,xlsx,txt');

        $updated = $this->settingsModel->updateSettings([
            'title'                         => $title,
            'siteurl'                       => $siteurl,
            'employee_can_create_tasks'     => $employeeCanCreate,
            'employee_can_ai_tasks'         => $employeeCanAi,
            'default_language'              => $defaultLanguage,
            'active_ai_provider'            => $activeAiProvider,
            'default_ai_model'              => $defaultAiModel,
            'openai_api_key'                => $openaiApiKey,
            'anthropic_api_key'             => $anthropicApiKey,
            'gemini_api_key'                => $geminiApiKey,
            'deepseek_api_key'              => $deepseekApiKey,
            'ai_system_prompt_prefix'       => $aiSystemPromptPrefix,
            'telegram_bot_token'            => $telegramBotToken,
            'telegram_chat_id'              => $telegramChatId,
            'telegram_notifications_enabled'=> $telegramNotifications,
            'slack_webhook_url'             => $slackWebhook,
            'discord_webhook_url'           => $discordWebhook,
            'smtp_host'                     => $smtpHost,
            'smtp_port'                     => $smtpPort,
            'smtp_secure'                   => $smtpSecure,
            'smtp_user'                     => $smtpUser,
            'smtp_pass'                     => $smtpPass,
            'smtp_from_email'               => $smtpFromEmail,
            'smtp_from_name'                => $smtpFromName,
            'bugherd_api_key'               => $bugherdApiKey,
            'bugherd_project_id'            => $bugherdProjectId,
            'github_token'                  => $githubToken,
            'github_repo'                   => $githubRepo,
            'session_timeout_minutes'       => $sessionTimeout,
            'max_file_upload_mb'            => $maxFileUpload,
            'allowed_file_types'            => $allowedFileTypes
        ]);

        if ($updated) {
            \Language::setSystemDefaultLanguageCache($defaultLanguage);
            \Modules\SSE\Services\SseBroadcaster::broadcast('system_settings_updated', 0, 0, (int)$this->user->id, [
                'employee_can_create_tasks' => $employeeCanCreate,
                'employee_can_ai_tasks'     => $employeeCanAi,
                'default_language'          => $defaultLanguage,
                'active_ai_provider'        => $activeAiProvider,
                'updated_by'                => $this->user->name
            ]);

            $this->respond(true, __('Settings::ajax_save_success'), [
                'title'                     => $title,
                'employee_can_create_tasks' => $employeeCanCreate,
                'employee_can_ai_tasks'     => $employeeCanAi,
                'active_ai_provider'        => $activeAiProvider
            ]);
        }
        $this->respond(false, __('Settings::ajax_save_error'));
    }

    public function toggleModuleStatus(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::settings_modules_toggle_fail'));
        }

        $slug = trim($_POST['slug'] ?? '');
        $status = !empty($_POST['status']) && ($_POST['status'] === 'true' || $_POST['status'] === '1' || $_POST['status'] === 1);

        if (empty($slug)) {
            $this->respond(false, __('Settings::ajax_invalid_slug'));
        }

        $coreModuleSlugs = ['board', 'settings', 'storage', 'smoketest', 'team'];
        if (!$status && in_array($slug, $coreModuleSlugs, true)) {
            $this->respond(false, __('Settings::settings_modules_core_cannot_disable'));
        }

        $res = \Core\ModuleLoader::setModuleStatus($slug, $status);
        if ($res) {
            $this->respond(true, __('Settings::settings_modules_toggle_success'), [
                'slug'      => $slug,
                'is_active' => $status
            ]);
        }
        $this->respond(false, __('Settings::settings_modules_toggle_fail'));
    }

    public function testTelegram(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_test'));
        }
        $testResult = \Telegram::testConnection();
        $this->respond((bool)$testResult['success'], $testResult['message'], $testResult);
    }

    public function testSmtp(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_test'));
        }
        $testResult = \Modules\Mailer\Services\MailerService::testConnection();
        $this->respond((bool)$testResult['success'], $testResult['message'], $testResult);
    }

    public function testSlack(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_test'));
        }
        $url = $this->settingsModel->getSlackWebhookUrl();
        if (empty($url)) {
            $this->respond(false, __('Settings::ajax_slack_no_url'));
        }
        $testMsg = str_replace(':time', date('H:i:s'), __('Settings::ajax_slack_test_msg'));
        $res = \Modules\RestApi\Services\WebhookService::postJsonWebhook($url, [
            'text' => $testMsg
        ]);
        if ($res['success']) {
            $this->respond(true, __('Settings::ajax_slack_success'));
        } else {
            $errMsg = str_replace([':code', ':error'], [$res['code'], ($res['body'] ?: $res['error'])], __('Settings::ajax_slack_error'));
            $this->respond(false, $errMsg);
        }
    }

    public function testDiscord(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, __('Settings::ajax_unauthorized_test'));
        }
        $url = $this->settingsModel->getDiscordWebhookUrl();
        if (empty($url)) {
            $this->respond(false, __('Settings::ajax_discord_no_url'));
        }
        $testMsg = str_replace(':time', date('H:i:s'), __('Settings::ajax_discord_test_msg'));
        $res = \Modules\RestApi\Services\WebhookService::postJsonWebhook($url, [
            'content' => $testMsg
        ]);
        if ($res['success']) {
            $this->respond(true, __('Settings::ajax_discord_success'));
        } else {
            $errMsg = str_replace([':code', ':error'], [$res['code'], ($res['body'] ?: $res['error'])], __('Settings::ajax_discord_error'));
            $this->respond(false, $errMsg);
        }
    }
}
