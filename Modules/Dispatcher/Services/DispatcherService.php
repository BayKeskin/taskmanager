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

        $agentRules = !empty($project->agent_rules) ? trim($project->agent_rules) : '';
        if (empty($agentRules) && !empty($project->id)) {
            $db = $GLOBALS['db'] ?? null;
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

            $attachmentsMd = '';
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $attachmentsMd = "\n\n### 📎 Task Attachments & Reference Designs:\n"
                    . "> **MANDATORY ASSET INSPECTION:** Carefully inspect all attached visual designs and reference documents before writing any code.\n";
                foreach ($data['attachments'] as $att) {
                    $attObj = is_object($att) ? $att : (object)$att;
                    $fName = $attObj->file_name ?? basename($attObj->file_path ?? 'attachment');
                    $fUri  = $attObj->file_uri ?? ('file:///' . ltrim(str_replace('\\', '/', $attObj->abs_path ?? ''), '/'));
                    $fType = $attObj->file_type ?? '';
                    $isImg = str_starts_with($fType, 'image/') || preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $fName);
                    $icon  = $isImg ? '🖼️' : '📄';
                    $hint  = $isImg 
                        ? "*(Reference Image: inspect using `view_file` or visual tools to match design pixel-perfect)*" 
                        : "*(Attached Document: view using `view_file`)*";
                    $attachmentsMd .= "- {$icon} [{$fName}]({$fUri}) {$hint}\n";
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
                . "{$attachmentsMd}"
                . "{$projectRulesBlock}\n\n"
                . "---\n"
                . "🛡️ **MANDATORY QA DOCTRINE: \"THE USER IS NOT A QA TESTER\"**\n"
                . "> **🚨 RED LINE RULE:**\n"
                . "> Before an engineer or AI agent tells the user \"It's done, please check\", \"Try it now\", or marks any task as completed:\n"
                . "> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**\n\n"
                . "📋 **DEFINITION OF DONE & DELIVERY PROTOCOL:**\n"
                . "1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.\n"
                . "2. **Where to Test (Nereden Test Edilir):** Direct testable URLs.\n"
                . "3. **How to Test (Nasıl Test Edilir):** Step-by-step actions and expected outcomes.\n"
                . "4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Automated test summary.\n\n"
                . "🎯 **Directive:** Focus EXCLUSIVELY on this specific task: [{$taskCode}].\n\n"
                . "🗄️ **DATABASE MIGRATION & ROLLBACK RULE:**\n"
                . "If your task introduces database modifications, generate a reverse rollback SQL script inside: `.migrations/{$taskCode}_down.sql`.\n\n"
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION:**\n"
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
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION:**\n"
                . self::getAgentSubmitCommandBlock($taskCode);
        } else {
            $titleHeader = "ℹ️ Task Update: {$event}";
            $messageText = "ℹ️ **TaskManager Event: {$event}**\n\n"
                . "- **Project:** [{$projectCode}] {$projectTitle}\n"
                . "- **Data:** " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        return [$titleHeader, $messageText];
    }

    /**
     * Ajanın görev teslim komut bloğunu üretir
     */
    public static function getAgentSubmitCommandBlock(string $taskCode): string
    {
        $cfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
        $endpoint = "{$cfg['base_url']}/api/tasks/{$taskCode}/workflow/submit";
        $token    = $cfg['api_token'];

        return "```powershell\n"
             . "\$headers = @{ 'Authorization' = 'Bearer {$token}'; 'Content-Type' = 'application/json; charset=utf-8' }\n"
             . "\$body = @{ notes = '1. What Was Done (Neler Yapıldı): ... 2. Where to Test (Nereden Test Edilir): ... 3. How to Test (Nasıl Test Edilir): ... 4. Test Evidence (Test Kanıtı): ...' } | ConvertTo-Json -Compress\n"
             . "Invoke-RestMethod -Uri '{$endpoint}' -Method Post -Headers \$headers -Body ([System.Text.Encoding]::UTF8.GetBytes(\$body))\n"
             . "```";
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

        [$titleHeader, $messageText] = self::formatAgentMessage($event, $project, $data);

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
