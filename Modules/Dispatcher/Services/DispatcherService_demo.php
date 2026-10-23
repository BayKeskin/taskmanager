<?php
namespace Modules\Dispatcher\Services;

use Core\BaseService;
use Modules\Dispatcher\Models\DispatcherModel;

/**
 * DispatcherService_demo
 * 
 * Port 4006 hedefli, sabit kullanıcı yolları ve eklenti bağımlılıklarından arındırılmış,
 * getenv('USERPROFILE') ve getenv('LOCALAPPDATA') ile dinamik çalışan demo servis.
 */
class DispatcherService_demo
{
    private string $dispatcherDir;
    private DispatcherModel $model;

    public function __construct()
    {
        $this->dispatcherDir = dirname(__DIR__) . '/Bin';
        $this->model = new DispatcherModel();
    }

    /**
     * Dinamik kullanıcı ev dizinini döner
     */
    public static function getUserHome(): string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME');
        if (!$home) {
            $home = 'C:/Users/' . (getenv('USERNAME') ?: 'DELL');
        }
        return str_replace('\\', '/', rtrim($home, '\\/'));
    }

    /**
     * Dinamik LOCALAPPDATA dizinini döner
     */
    public static function getLocalAppData(): string
    {
        $local = getenv('LOCALAPPDATA');
        if (!$local) {
            $local = self::getUserHome() . '/AppData/Local';
        }
        return str_replace('\\', '/', rtrim($local, '\\/'));
    }

    /**
     * Dinamik APPDATA (Roaming) dizinini döner
     */
    public static function getAppData(): string
    {
        $roaming = getenv('APPDATA');
        if (!$roaming) {
            $roaming = self::getUserHome() . '/AppData/Roaming';
        }
        return str_replace('\\', '/', rtrim($roaming, '\\/'));
    }

    /**
     * Dispatcher Demo Port 4006 aktif mi kontrol eder
     */
    public function isRunning(): bool
    {
        $fp = @fsockopen('127.0.0.1', 4006, $errno, $errstr, 0.15);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * Dispatcher demo servisi port 4006'da dinlemiyorsa arka planda başlatır
     */
    public function ensureRunning(): void
    {
        if ($this->isRunning()) {
            return;
        }

        $script = $this->dispatcherDir . '/dispatcher_demo.py';
        if (file_exists($script) && class_exists('COM')) {
            try {
                $shell = new \COM("WScript.Shell");
                $cmd = 'python "' . str_replace('/', '\\', $script) . '"';
                $shell->Run($cmd, 0, false);
                usleep(250000);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Antigravity Language Server portunu ve CSRF token'ını dinamik yollarla tespit eder
     */
    public function getAntigravityLsEnv(?string $workspacePath = null): array
    {
        $localApp = self::getLocalAppData();
        $python = $localApp . '/Programs/Python/Python311/python.exe';
        if (!file_exists($python)) {
            $python = 'python';
        }

        $script = $this->dispatcherDir . '/dispatcher_demo.py';
        if (file_exists($script)) {
            $wsArg = $workspacePath ? ' "' . addslashes($workspacePath) . '"' : '';
            $probeJson = @shell_exec("\"{$python}\" \"{$script}\" --probe{$wsArg}");
            if ($probeJson) {
                $probeData = json_decode(trim($probeJson), true);
                if (!empty($probeData['address'])) {
                    return [
                        'address'    => (string)$probeData['address'],
                        'csrf_token' => (string)($probeData['csrf_token'] ?? '')
                    ];
                }
            }
        }

        $probeFile = self::getAppData() . '/Antigravity IDE/User/globalStorage/antigravity-ls-probe.json';
        if (file_exists($probeFile)) {
            $probeData = json_decode((string)file_get_contents($probeFile), true);
            if (!empty($probeData['address'])) {
                return [
                    'address'    => (string)$probeData['address'],
                    'csrf_token' => (string)($probeData['csrf_token'] ?? '')
                ];
            }
        }

        $lsAddress = $this->getAntigravityLsAddress();
        return [
            'address'    => $lsAddress,
            'csrf_token' => null
        ];
    }

    /**
     * Antigravity Language Server portunu tespit eder (Eklenti sorguları kaldırılmış)
     */
    public function getAntigravityLsAddress(): ?string
    {
        $probeFile = self::getAppData() . '/Antigravity IDE/User/globalStorage/antigravity-ls-probe.json';
        if (file_exists($probeFile)) {
            $probeData = json_decode((string)file_get_contents($probeFile), true);
            if (!empty($probeData['address'])) {
                return (string)$probeData['address'];
            }
        }

        return getenv('ANTIGRAVITY_LS_ADDRESS') ?: null;
    }

    /**
     * Verilen çalışma alanı ve proje kodu için en güncel Antigravity conversation_id değerini tespit eder.
     * (Salt yerli veritabanı ve Antigravity trajectory SQLite taraması kullanılır)
     */
    public function discoverLatestConversation(?string $workspacePath, ?string $projectCode = null): ?string
    {
        $convDir = self::getUserHome() . '/.gemini/antigravity-ide/conversations';
        if (!is_dir($convDir)) {
            return null;
        }

        $targets = [];
        if ($workspacePath) {
            $normalizedWs = strtolower(str_replace('\\', '/', trim($workspacePath)));
            $targets[] = $normalizedWs;
            $targets[] = rtrim($normalizedWs, '/');
            $wsBase = basename($normalizedWs);
            if ($wsBase) {
                $targets[] = $wsBase;
            }
        }

        if (empty($targets) && $projectCode) {
            $targets[] = 'c:/wamp64/www/' . strtolower($projectCode);
        }

        $targets = array_values(array_unique(array_filter($targets)));
        usort($targets, fn($a, $b) => strlen($b) - strlen($a));

        if (empty($targets)) {
            return null;
        }

        $files = glob($convDir . '/*.db') ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $files = array_slice($files, 0, 30);

        foreach ($files as $file) {
            $cid = basename($file, '.db');
            try {
                $sdb = new \SQLite3($file, SQLITE3_OPEN_READONLY);
                $res = $this->model->querySqliteSingleValue($sdb, 'trajectory_metadata_blob', 'data', 'id', 'main');
                $sdb->close();
                if ($res) {
                    $decoded = rawurldecode((string)$res);
                    $decoded = strtolower(str_replace('\\', '/', $decoded));
                    foreach ($targets as $target) {
                        $pattern = '~(?:^|[^\w\-\.])' . preg_quote($target, '~') . '(?:[/?#\"\'\s,\x00-\x1f\\\\]|$)~i';
                        if (preg_match($pattern, $decoded)) {
                            return $cid;
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        return null;
    }

    /**
     * Demo Port 4006'daki Python webhook servisine doğrudan HTTP isteği fırlatır
     */
    public function triggerDemoWebhook(int $projectId, string $event, array $data = []): array
    {
        $model = new \Modules\RestApi\Models\RestApiModel();
        $project = $model->getProjectWebhookConfig($projectId);

        if (!$project) {
            return ['sent' => false, 'error' => 'Project not found'];
        }

        $targetUrl = 'http://127.0.0.1:4006/webhook';
        $apiCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();

        $qaModel = new \Modules\AgentRules\Models\AgentRulesModel();
        $qaGlobal = $qaModel->getQaGlobalInstructions();

        $payload = [
            'event'                  => $event,
            'project_id'             => (int)$project->id,
            'project_code'           => $project->project_code,
            'project_title'          => $project->title,
            'workspace_path'         => $project->workspace_path ?: null,
            'agent_rules'            => $project->agent_rules ?: null,
            'agent_conversation_id'  => $project->agent_conversation_id ?: null,
            'dev_conversation_id'    => $project->dev_conversation_id ?: ($project->agent_conversation_id ?: null),
            'qa_conversation_id'     => $project->qa_conversation_id ?: null,
            'qa_test_instructions'   => $project->qa_test_instructions ?: null,
            'qa_global_instructions' => $qaGlobal ?: null,
            'api_base_url'           => $apiCfg['base_url'],
            'api_token'              => $apiCfg['api_token'],
            'timestamp'              => date('c'),
            'data'                   => $data
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: TaskManager-Agent-Webhook-Demo/1.0',
            'X-TaskManager-Event: ' . $event,
            'X-TaskManager-Project: ' . $project->project_code,
        ];

        if (!empty($project->agent_conversation_id)) {
            $headers[] = 'X-TaskManager-Conversation-ID: ' . $project->agent_conversation_id;
        }

        $this->ensureRunning();

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $sent = ($httpCode >= 200 && $httpCode < 300);

        return [
            'sent'      => $sent,
            'port'      => 4006,
            'http_code' => $httpCode,
            'response'  => $response,
            'error'     => $error ?: null
        ];
    }

    /**
     * Port 4006'ya gerek duymadan doğrudan Antigravity Language Server API'yi dinamik yollarla tetikler.
     */
    public function dispatchDirect(object $project, string $event, array $data = []): array
    {
        $agentExe = self::getLocalAppData() . '/Programs/Antigravity IDE/resources/app/extensions/antigravity/bin/language_server_windows_x64.exe';
        if (!file_exists($agentExe)) {
            return ['sent' => false, 'error' => 'Antigravity language_server binary not found at: ' . $agentExe];
        }

        $workspacePath = $project->workspace_path ?? '';
        $projectCode   = $project->project_code ?? '';

        if ($event === 'task.submitted') {
            $targetCid = trim($project->qa_conversation_id ?? '');
            if (empty($targetCid)) {
                return ['sent' => false, 'single_agent' => true];
            }
        } else {
            $devCid = trim($project->dev_conversation_id ?? ($project->agent_conversation_id ?? ''));
            $discoveredCid = $this->discoverLatestConversation($workspacePath, $projectCode);
            $targetCid     = $devCid ?: trim($discoveredCid ?? '');
        }

        [$titleHeader, $messageText] = DispatcherService::formatAgentMessage($event, $project, $data);

        if (!empty($targetCid)) {
            $cmd = [$agentExe, 'agentapi', 'send-message', '--title=' . $titleHeader, $targetCid, $messageText];
        } else {
            $cmd = [$agentExe, 'agentapi', 'new-conversation', '--title=' . $titleHeader, $messageText];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $lsEnv = $this->getAntigravityLsEnv($workspacePath);
        $lsAddress = $lsEnv['address'] ?? null;
        $csrfToken = $lsEnv['csrf_token'] ?? null;
        $envVars = array_merge($_SERVER, $_ENV);
        if ($lsAddress) {
            $envVars['ANTIGRAVITY_LS_ADDRESS'] = $lsAddress;
        }
        if ($csrfToken) {
            $envVars['ANTIGRAVITY_CSRF_TOKEN'] = $csrfToken;
        }

        $cwd = (!empty($workspacePath) && is_dir($workspacePath)) ? $workspacePath : dirname(__DIR__, 3);
        $process = proc_open($cmd, $descriptors, $pipes, $cwd, $envVars);
        if (!is_resource($process)) {
            return ['sent' => false, 'error' => 'proc_open failed to launch language_server'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $sent = ($exitCode === 0);

        return [
            'sent'            => $sent,
            'port_target'     => 4006,
            'direct_dispatch' => true,
            'recipient_id'    => $targetCid,
            'exit_code'       => $exitCode,
            'stdout'          => trim((string)$stdout),
            'stderr'          => trim((string)$stderr)
        ];
    }
}
