<?php
namespace Modules\Dispatcher\Services;

use Core\BaseService;
use Modules\Dispatcher\Models\DispatcherModel;

class DispatcherService
{
    private string $dispatcherDir;
    private DispatcherModel $model;

    public function __construct()
    {
        $this->dispatcherDir = dirname(__DIR__) . '/Bin';
        $this->model = new DispatcherModel();
    }

    /**
     * Dispatcher Port 4005 aktif mi kontrol eder
     */
    public function isRunning(): bool
    {
        $fp = @fsockopen('127.0.0.1', 4005, $errno, $errstr, 0.15);
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
        $localAppData = getenv('LOCALAPPDATA') ?: (getenv('USERPROFILE') . '/AppData/Local');
        $python = $localAppData . '/Programs/Python/Python311/python.exe';
        if (!file_exists($python)) {
            $python = 'python';
        }
        $script = $this->dispatcherDir . '/dispatcher.py';
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

        $appData = getenv('APPDATA') ?: (getenv('USERPROFILE') . '/AppData/Roaming');
        $probeFile = $appData . '/Antigravity IDE/User/globalStorage/antigravity-ls-probe.json';
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
     * Antigravity Language Server portunu tespit eder
     */
    public function getAntigravityLsAddress(): ?string
    {
        $appData = getenv('APPDATA') ?: (getenv('USERPROFILE') . '/AppData/Roaming');
        $probeFile = $appData . '/Antigravity IDE/User/globalStorage/antigravity-ls-probe.json';
        if (file_exists($probeFile)) {
            $probeData = json_decode((string)file_get_contents($probeFile), true);
            if (!empty($probeData['address'])) {
                return (string)$probeData['address'];
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
            $projects = $this->model->getProjectsWithWorkspace();
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
        try {
            $jsonFile = $this->dispatcherDir . '/workspaces.json';
            if (!file_exists($jsonFile)) {
                return true;
            }
            $map = json_decode((string)file_get_contents($jsonFile), true) ?: [];
            $code = strtoupper(trim($projectCode));
            if (isset($map[$code])) {
                unset($map[$code]);
                return file_put_contents($jsonFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verilen çalışma alanı ve proje kodu için en güncel Antigravity conversation_id değerini tespit eder.
     */
    public function discoverLatestConversation(?string $workspacePath, ?string $projectCode = null): ?string
    {
        $userHome = getenv('USERPROFILE') ?: (getenv('HOME') ?: '');
        $convDir = $userHome . '/.gemini/antigravity-ide/conversations';
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
            $m = new DispatcherModel();
            $agentRules = $m->getProjectAgentRules((int)$project->id);
        }

        $projectRulesBlock = '';
        if (!empty($agentRules)) {
            $projectRulesBlock = "\n\n---\n"
                . "📌 **PROJECT-SPECIFIC AGENT RULES:**\n"
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

            $bannerText = "🚨 **[MANDATORY TASK INTERRUPT — STOP ALL PREVIOUS TOPICS!]**\n"
                . "🚨 **[EXECUTE IMMEDIATELY - DO NOT RE-ANSWER PREVIOUS CHAT MESSAGES OR INTRODUCTIONS]**\n"
                . "> **DIRECTIVE:** Drop any previous conversation in this window immediately. Your ONLY active objective now is to execute and deliver task **{$taskCode}** below:\n\n";

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
                . "1. **What Was Done:** Added/modified files, methods, DB schema changes.\n"
                . "2. **Where to Test:** Direct testable URLs.\n"
                . "3. **How to Test:** Step-by-step actions and expected outcomes.\n"
                . "4. **Autonomous Test Evidence:** Automated test summary.\n\n"
                . "🎯 **Directive:** Focus EXCLUSIVELY on this specific task: [{$taskCode}].\n\n"
                . "🗄️ **DATABASE MIGRATION & ROLLBACK RULE:**\n"
                . "If your task introduces database modifications, generate a reverse rollback SQL script inside: `.migrations/{$taskCode}_down.sql`.\n\n"
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION:**\n"
                . self::getAgentSubmitCommandBlock($taskCode);
        } elseif ($event === 'task.revision_requested' || $event === 'task.modification_requested') {
            $titleHeader = "⚠️ [REVISION REQUEST] {$taskCode}: {$taskTitle}";
            $revReason = $data['revision_reason'] ?? '*(No reason specified)*';
            $desc = !empty($data['description']) ? trim((string)$data['description']) : '';

            $messageText = "🚨 **[MANDATORY TASK INTERRUPT — STOP ALL PREVIOUS TOPICS!]**\n"
                . "🚨 **[EXECUTE IMMEDIATELY - DO NOT RE-ANSWER PREVIOUS CHAT MESSAGES OR INTRODUCTIONS]**\n"
                . "> **DIRECTIVE:** Drop any previous conversation in this window immediately. Your ONLY active objective now is to execute and deliver task **{$taskCode}** below:\n\n"
                . "⚠️ **TaskManager Revision Request!**\n\n"
                . "📌 **Project:** [{$projectCode}] {$projectTitle}\n"
                . "📋 **Task Code:** {$taskCode}\n"
                . "🏷️ **Title:** {$taskTitle}\n\n"
                . "🔴 **Requested Revision & Feedback:**\n{$revReason}\n\n"
                . "### 📝 Original Task Description:\n{$desc}"
                . "{$projectRulesBlock}\n\n"
                . "⚡ **AUTONOMOUS COMPLETION INSTRUCTION:**\n"
                . self::getAgentSubmitCommandBlock($taskCode);
        } elseif ($event === 'task.submitted') {
            $titleHeader = "🔍 [QA AUDIT REQUIRED] {$taskCode}: {$taskTitle}";
            $submissionNotes = !empty($data['submission_notes']) ? trim((string)$data['submission_notes']) : '*(No submission notes provided)*';
            $apiCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
            $apiBaseUrl = rtrim($apiCfg['base_url'], '/');
            $apiToken = !empty($apiCfg['api_token']) ? $apiCfg['api_token'] : 'tm_admin_talha_keskin_token_2026';

            $taskDesc = !empty($data['description']) ? trim((string)$data['description']) : '*(No description provided)*';
            $taskDescClean = preg_replace('/---\s*⚡\s*\*\*AUTONOMOUS COMPLETION INSTRUCTION:.*$/s', '', $taskDesc);
            $taskDescClean = trim($taskDescClean) ?: '*(No description provided)*';

            $attachments = $data['attachments'] ?? [];
            $attachmentsMd = '';
            if (!empty($attachments) && is_array($attachments)) {
                $attachmentsMd = "\n- **Task Attachments & Designs:**\n";
                foreach ($attachments as $att) {
                    $fname = $att['file_name'] ?? basename($att['file_path'] ?? 'attachment');
                    $absP = str_replace('\\', '/', $att['abs_path'] ?? ($att['file_path'] ?? ''));
                    $furi = $att['file_uri'] ?? ('file:///' . ltrim($absP, '/'));
                    $ftype = strtolower($att['file_type'] ?? '');
                    $isImg = str_starts_with($ftype, 'image/') || (bool)preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $fname);
                    $icon = $isImg ? '🖼️' : '📄';
                    $attachmentsMd .= "  - {$icon} [{$fname}]({$furi})\n";
                }
            }

            $qaModel = new \Modules\AgentRules\Models\AgentRulesModel();
            $qaGlobal = $qaModel->getQaGlobalInstructions();
            $qaProjectInstructions = trim((string)($project->qa_test_instructions ?? ''));

            $customQaSection = "";
            if (!empty($qaProjectInstructions)) {
                $customQaSection .= "\n\n### 🛠️ PROJECT-SPECIFIC QA TEST INSTRUCTIONS:\n{$qaProjectInstructions}\n";
            }
            if (!empty($qaGlobal)) {
                $customQaSection .= "\n\n### 🌐 GLOBAL QA TEST PROTOCOL:\n{$qaGlobal}\n";
            }

            $messageText = "🚨 **[MANDATORY QA VERIFICATION DIRECTIVE — NO CODE MODIFICATION ALLOWED!]**\n"
                . "> **ROLE:** You are the Lead QA & Autonomous Testing Inspector. You CANNOT write feature code. Your ONLY objective is to rigorously test, verify, and audit task **{$taskCode}** delivered by the Developer Agent.\n\n"
                . "🚨 **STRICT ANTI-RUBBERSTAMPING PROTOCOL:**\n"
                . "> NEVER trust the Developer's submission notes. What the developer wrote, claims to have done, or how the code was implemented is COMPLETELY IRRELEVANT.\n"
                . "> Your sole responsibility is the USER'S ORIGINAL REQUIREMENT. You must verify that the user's intended primary goal works 100% on the screen, regardless of how the Coder claimed to implement it.\n"
                . "> You MUST independently and autonomously execute the exact end-to-end user scenario in the live browser (e.g., clicking buttons, submitting forms, logging in, verifying result view).\n"
                . "> If the user's primary intended outcome does NOT work or cannot be proven in the live UI, you are STRICTLY FORBIDDEN to approve. REJECT the task immediately with OPTION B!\n\n"
                . "### 📋 DELIVERED TASK CONTEXT:\n"
                . "- **Project:** [{$projectCode}] {$projectTitle} (`{$project->workspace_path}`)\n"
                . "- **Task Code:** `{$taskCode}` | **Title:** {$taskTitle}\n"
                . "- **Original Task Requirements:**\n{$taskDescClean}\n"
                . "{$attachmentsMd}"
                . "- **Developer Submission Notes:**\n{$submissionNotes}\n\n"
                . "### 🔍 MANDATORY STEP-BY-STEP TESTING PROTOCOL:\n"
                . "1. **LIVE BROWSER FUNCTIONAL TEST:**\n"
                . "   - Open target URL in the live browser.\n"
                . "   - Verify forms submit properly, buttons work, UI layout is intact, and no dead elements exist.\n"
                . "2. **NETWORK & CONSOLE AUDIT (ZERO TOLERANCE):**\n"
                . "   - Check Developer Console: Must contain **0 JS errors** and **0 404 assets**.\n"
                . "   - Check Network Response: Inspect payload body. Even if HTTP 200, ensure NO PHP Fatals, Xdebug errors, PDOExceptions, Warnings, or HTTP 500s.\n"
                . "3. **PROJECT-SPECIFIC TEST EXECUTION:**\n"
                . "   - If this project defines specific CLI test/audit commands or rules below, execute only what is defined in the project instructions.\n"
                . "{$customQaSection}\n"
                . "---\n\n"
                . "### ⚖️ FINAL VERDICT COMMANDS (CHOOSE EXACTLY ONE):\n\n"
                . "**OPTION A — IF ALL TESTS PASS WITHOUT ANY FLAW (APPROVE & CLOSE):**\n"
                . "Execute in PowerShell:\n"
                . "```powershell\n"
                . "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; \$OutputEncoding = [System.Text.Encoding]::UTF8;\n"
                . "\$headers = @{{ 'Authorization' = 'Bearer {$apiToken}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
                . "\$body = @{{ action = 'review'; decision = 'approved'; feedback = 'All live browser UI, Console, Network, and Audit tests passed with zero defects.' }} | ConvertTo-Json -Compress\n"
                . "Invoke-RestMethod -Uri '{$apiBaseUrl}/api/tasks/{$taskCode}/workflow' -Method Post -Headers \$headers -Body ([System.Text.Encoding]::UTF8.GetBytes(\$body))\n"
                . "```\n\n"
                . "**OPTION B — IF ANY BUG, CSS GLITCH, JS ERROR OR AUDIT FAIL IS FOUND (REJECT & REQUEST REVISION):**\n"
                . "Execute in PowerShell (Clearly document the defect with reproduction steps):\n"
                . "```powershell\n"
                . "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8; \$OutputEncoding = [System.Text.Encoding]::UTF8;\n"
                . "\$headers = @{{ 'Authorization' = 'Bearer {$apiToken}'; 'Content-Type' = 'application/json; charset=utf-8' }}\n"
                . "\$body = @{{ action = 'review'; decision = 'rejected'; feedback = '[QA DEFECT REPORT]\n1. Visual/CSS Bug: ...\n2. Console/Network Bug: ...\n3. Audit Failure: ...\nPlease fix and re-submit.' }} | ConvertTo-Json -Compress\n"
                . "Invoke-RestMethod -Uri '{$apiBaseUrl}/api/tasks/{$taskCode}/workflow' -Method Post -Headers \$headers -Body ([System.Text.Encoding]::UTF8.GetBytes(\$body))\n"
                . "```";
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
             . "\$body = @{ notes = '1. What Was Done: ... 2. Where to Test: ... 3. How to Test: ... 4. Test Evidence: ...' } | ConvertTo-Json -Compress\n"
             . "Invoke-RestMethod -Uri '{$endpoint}' -Method Post -Headers \$headers -Body ([System.Text.Encoding]::UTF8.GetBytes(\$body))\n"
             . "```";
    }

    /**
     * Port 4000'e gerek duymadan doğrudan Antigravity Language Server API'yi tetikler.
     */
    public function dispatchDirect(object $project, string $event, array $data = []): array
    {
        $discoveredCid = null;
        $localAppData = getenv('LOCALAPPDATA') ?: (getenv('USERPROFILE') . '/AppData/Local');
        $agentExe = $localAppData . '/Programs/Antigravity IDE/resources/app/extensions/antigravity/bin/language_server_windows_x64.exe';
        if (!file_exists($agentExe)) {
            return ['sent' => false, 'error' => 'Antigravity language_server binary not found at ' . $agentExe];
        }

        $workspacePath = $project->workspace_path ?? '';
        $projectCode   = $project->project_code ?? '';

        if ($event === 'task.submitted') {
            $targetCid = trim($project->qa_conversation_id ?? '');
            if (empty($targetCid)) {
                // QA ajanı tanımlı değilse tek ajan modu
                return ['sent' => false, 'single_agent' => true];
            }
        } else {
            $devCid = trim($project->dev_conversation_id ?? ($project->agent_conversation_id ?? ''));
            $discoveredCid = $this->discoverLatestConversation($workspacePath, $projectCode);
            $targetCid     = $devCid ?: trim($discoveredCid ?? '');

            if (!empty($discoveredCid) && empty($devCid)) {
                $this->model->updateAgentConversationId((int)$project->id, $discoveredCid);
            }
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
                $this->model->updateAgentConversationId((int)$project->id, $newCid);
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
                        $this->model->updateAgentConversationId((int)$project->id, $newCid);
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
