<?php
namespace Modules\Dispatcher\Services;

use Core\BaseModel;

class DispatcherService extends BaseModel
{
    private string $dispatcherDir;

    public function __construct()
    {
        parent::__construct();
        $this->dispatcherDir = dirname(dirname(__DIR__)) . '/dispatcher';
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
}
