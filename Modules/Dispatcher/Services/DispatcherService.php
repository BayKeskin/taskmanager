<?php
namespace Modules\Dispatcher\Services;

use Core\BaseModel;

class DispatcherService extends BaseModel
{
    private string $dispatcherDir;

    public function __construct()
    {
        parent::__construct();
        $this->dispatcherDir = dirname(__DIR__) . '/Bin';
    }

    /**
     * Dispatcher Port 4000 aktif mi kontrol eder
     */
    public function isRunning(): bool
    {
        $fp = @fsockopen('127.0.0.1', 4000, $errno, $errstr, 0.15);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    /**
     * Dispatcher servisi port 4000'de dinlemiyorsa sessizce arka planda başlatır
     */
    public function ensureRunning(): void
    {
        if ($this->isRunning()) {
            return;
        }

        $vbs = $this->dispatcherDir . '/start_silent.vbs';
        if (file_exists($vbs) && class_exists('COM')) {
            try {
                $shell = new \COM("WScript.Shell");
                $shell->Run('wscript.exe "' . $vbs . '"', 0, false);
                usleep(250000);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Antigravity Language Server portunu ve CSRF token'ını tespit eder
     */
    public function getAntigravityLsEnv(?string $workspacePath = null): array
    {
        $python = 'C:/Users/DELL/AppData/Local/Programs/Python/Python311/python.exe';
        $script = $this->dispatcherDir . '/dispatcher.py';
        if (file_exists($python) && file_exists($script)) {
            $wsArg = $workspacePath ? ' "' . addslashes($workspacePath) . '"' : '';
            $probeJson = @shell_exec("\"{$python}\" \"{$script}\" --probe{$wsArg}");
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

        $lsEnvFile = $this->dispatcherDir . '/ls_env.json';
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

        $lsFile = $this->dispatcherDir . '/ls_address.txt';
        $addr = file_exists($lsFile) ? trim((string)file_get_contents($lsFile)) : (getenv('ANTIGRAVITY_LS_ADDRESS') ?: null);
        return ['address' => $addr, 'csrf_token' => ''];
    }

    /**
     * Antigravity Language Server portunu tespit eder
     */
    public function getAntigravityLsAddress(): ?string
    {
        $lsFile = $this->dispatcherDir . '/ls_address.txt';
        if (file_exists($lsFile)) {
            $addr = trim((string)file_get_contents($lsFile));
            if (!empty($addr)) {
                return $addr;
            }
        }
        return getenv('ANTIGRAVITY_LS_ADDRESS') ?: null;
    }

    /**
     * Tüm projelerin workspace_path değerlerini dispatcher/workspaces.json dosyasına senkronize eder
     */
    public function syncWorkspaces(): bool
    {
        try {
            $projects = $this->query("SELECT project_code, workspace_path FROM projects WHERE workspace_path IS NOT NULL AND workspace_path != ''");
            $jsonFile = $this->dispatcherDir . '/workspaces.json';
            $map = [];
            foreach ($projects as $proj) {
                $p = (object)$proj;
                $code = strtoupper(trim($p->project_code));
                $path = trim($p->workspace_path);
                if ($code && $path) {
                    $map[$code] = [$path];
                }
            }
            return file_put_contents($jsonFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Proje silindiğinde workspaces.json'dan kaldırır
     */
    public function removeProjectWorkspace(string $projectCode): bool
    {
        $wsFile = $this->dispatcherDir . '/workspaces.json';
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
                return file_put_contents($wsFile, json_encode($wsMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
            }
        }
        return true;
    }

    /**
     * Antigravity SQLite oturumlarını tarayarak projeye ait en güncel açık pencereyi dinamik bulur.
     */
    public function discoverLatestConversation(?string $workspacePath = null, ?string $projectCode = null): ?string
    {
        $convDir = 'C:/Users/DELL/.gemini/antigravity-ide/conversations';
        if (!is_dir($convDir)) {
            return null;
        }

        $targets = [];
        if (!empty($workspacePath)) {
            $targets[] = str_replace('\\', '/', strtolower(rtrim($workspacePath, '/')));
        }

        $wsFile = $this->dispatcherDir . '/workspaces.json';
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

        if (empty($targets)) {
            return null;
        }

        // 1. ÖNCELİK: state.vscdb
        $vscdb = 'C:/Users/DELL/AppData/Roaming/Antigravity IDE/User/globalStorage/state.vscdb';
        if (file_exists($vscdb)) {
            try {
                $sdb = new \SQLite3($vscdb, SQLITE3_OPEN_READONLY);
                $val = $sdb->querySingle("SELECT value FROM ItemTable WHERE key = 'erennyuksell.ag-multi-account-switchboard'");
                $sdb->close();
                if ($val) {
                    $swData = json_decode((string)$val, true);
                    $activeCid = $swData['ag.lastContextWindow']['conversationId'] ?? null;
                    if ($activeCid) {
                        $activeDb = $convDir . '/' . $activeCid . '.db';
                        if (file_exists($activeDb)) {
                            $adb = new \SQLite3($activeDb, SQLITE3_OPEN_READONLY);
                            $blob = $adb->querySingle("SELECT data FROM trajectory_metadata_blob WHERE id='main'");
                            $adb->close();
                            if ($blob) {
                                $decoded = strtolower(str_replace('\\', '/', rawurldecode((string)$blob)));
                                foreach ($targets as $target) {
                                    $pattern = '~(?:^|[^\w\-\.])' . preg_quote($target, '~') . '(?:[/?#\"\'\s,\x00-\x1f\\\\]|$)~i';
                                    if (preg_match($pattern, $decoded)) {
                                        return $activeCid;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. İKİNCİ AŞAMA: Son 4 saat
        $files = glob($convDir . '/*.db') ?: [];
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $now = time();
        foreach ($files as $file) {
            if (($now - filemtime($file)) > 14400) {
                continue;
            }

            $cid = basename($file, '.db');
            try {
                $sdb = new \SQLite3($file, SQLITE3_OPEN_READONLY);
                $res = $sdb->querySingle("SELECT data FROM trajectory_metadata_blob WHERE id='main'");
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
     * Port 4000'e gerek duymadan doğrudan Antigravity Language Server API'yi tetikler.
     */
    public function dispatchDirect(object $project, string $event, array $data = []): array
    {
        $agentExe = 'c:\Users\DELL\AppData\Local\Programs\Antigravity IDE\resources\app\extensions\antigravity\bin\language_server_windows_x64.exe';
        if (!file_exists($agentExe)) {
            return ['sent' => false, 'error' => 'Antigravity language_server binary not found.'];
        }

        $workspacePath = $project->workspace_path ?? '';
        $projectCode   = $project->project_code ?? '';
        $discoveredCid = $this->discoverLatestConversation($workspacePath, $projectCode);
        $targetCid     = $discoveredCid ?: ($project->agent_conversation_id ?? '');

        if (!empty($discoveredCid) && $discoveredCid !== ($project->agent_conversation_id ?? '')) {
            $this->exec("UPDATE projects SET agent_conversation_id = ? WHERE id = ?", [$discoveredCid, $project->id]);
        }

        [$titleHeader, $messageText] = \Helper::formatAgentMessage($event, $project, $data);

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

        if ($exitCode === 0 && empty($targetCid) && !empty($stdout)) {
            $resJson = json_decode((string)$stdout, true);
            $newCid = $resJson['response']['newConversation']['conversationId'] ?? null;
            if ($newCid) {
                $targetCid = $newCid;
                $this->exec("UPDATE projects SET agent_conversation_id = ? WHERE id = ?", [$newCid, $project->id]);
            }
        }

        if ($exitCode !== 0) {
            $cmdFallback = [$agentExe, 'agentapi', 'new-conversation', '--title=' . $titleHeader, $messageText];
            $pFallback = proc_open($cmdFallback, $descriptors, $pipes, $cwd, $envVars);
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
                        $this->exec("UPDATE projects SET agent_conversation_id = ? WHERE id = ?", [$newCid, $project->id]);
                    }
                }
            }
        }

        $sent = ($exitCode === 0);
        $this->ensureRunning();

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
}
