<?php
namespace Modules\GithubDevops\Services;

use Modules\GithubDevops\Models\GitDevopsModel;

/**
 * GitService - Projelerin yerel ve uzak Git/GitHub operasyonlarını yönetir.
 * Atomic commit, origin push, diff analizi, schema snapshot ve cerrahi rollback işlemlerini yürütür.
 */
class GitService
{
    private static function getModel(): GitDevopsModel
    {
        static $model = null;
        if ($model === null) {
            $model = new GitDevopsModel();
        }
        return $model;
    }

    /**
     * Gerekli veritabanı sütunlarını (settings, projects, task_submissions) otomatik kontrol eder.
     */
    public static function ensureGithubAndRollbackSchema(): void
    {
        self::getModel()->ensureGithubAndRollbackSchema();
    }

    /**
     * Sistem Ayarlarındaki GitHub Kimlik Bilgilerini döndürür.
     */
    public static function getGithubCredentials(): array
    {
        return self::getModel()->getGithubCredentials();
    }

    /**
     * Aktif branch adını tespit eder (main, master vs.)
     */
    public static function detectActiveBranch(string $workspacePath): string
    {
        $ws = rtrim(str_replace('\\', '/', trim($workspacePath)), '/');
        if (empty($ws) || !is_dir($ws . '/.git')) {
            return 'main';
        }
        $escapedWs = escapeshellarg($ws);
        $branch = trim((string)@shell_exec("cd /d {$escapedWs} && git -c safe.directory=* rev-parse --abbrev-ref HEAD 2>nul"));
        if (empty($branch) || $branch === 'HEAD') {
            return 'main';
        }
        return $branch;
    }

    /**
     * TaskManager'da kayıtlı projelerin mevcut Git remote origin adreslerini sisteme bağlar.
     */
    public static function discoverAndSyncExistingProjectRemotes(): array
    {
        $model = self::getModel();
        $model->ensureGithubAndRollbackSchema();

        $projects = $model->getAllProjects();
        $results = [];

        foreach ($projects as $p) {
            $pObj = (object)$p;
            $ws = rtrim(str_replace('\\', '/', trim((string)($pObj->workspace_path ?? ''))), '/');
            if (empty($ws) || !is_dir($ws) || !is_dir($ws . '/.git')) {
                continue;
            }

            $escapedWs = escapeshellarg($ws);
            $remoteUrl = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));

            if (!empty($remoteUrl)) {
                if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $remoteUrl, $m)) {
                    $repoSlug = trim($m[1]) . '/' . trim($m[2]);

                    if (empty($pObj->github_repo) || $pObj->github_repo !== $repoSlug) {
                        $model->updateProjectGithubRepo((int)$pObj->id, $repoSlug);
                        $results[$pObj->project_code] = [
                            'title'  => $pObj->title,
                            'repo'   => $repoSlug,
                            'status' => 'updated'
                        ];
                    } else {
                        $results[$pObj->project_code] = [
                            'title'  => $pObj->title,
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
     * Projeyi GitHub'a bağlar (Var olanı korur, yoksa private repo açar)
     */
    public static function initAndSyncProjectGithubRepo(int $projectId): array
    {
        $model = self::getModel();
        $model->ensureGithubAndRollbackSchema();

        $project = $model->getProjectById($projectId);
        if (!$project) {
            return ['success' => false, 'error' => __('GithubDevops::err_project_not_found')];
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => __('GithubDevops::err_workspace_not_found')];
        }

        $escapedWs = escapeshellarg($ws);

        if (is_dir($ws . '/.git')) {
            $existingRemote = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));
            if (!empty($existingRemote)) {
                if (preg_match('#github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#i', $existingRemote, $m)) {
                    $repoSlug = trim($m[1]) . '/' . trim($m[2]);
                    $model->updateProjectGithubRepo((int)$project->id, $repoSlug);
                    return [
                        'success'     => true,
                        'github_repo' => $repoSlug,
                        'repo_url'    => "https://github.com/{$repoSlug}",
                        'message'     => str_replace('{repo}', $repoSlug, __('GithubDevops::msg_existing_repo_kept'))
                    ];
                }
            }
        }

        $creds = $model->getGithubCredentials();
        if (empty($creds['username']) || empty($creds['token'])) {
            return ['success' => false, 'error' => __('GithubDevops::err_save_github_creds_first')];
        }

        $repoSlug = trim((string)($project->github_repo ?? ''));
        if (empty($repoSlug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '-', str_replace(
                ['\u{0131}','\u{011f}','\u{00fc}','\u{015f}','\u{00f6}','\u{00e7}','\u{0130}','\u{011e}','\u{00dc}','\u{015e}','\u{00d6}','\u{00c7}'],
                ['i','g','u','s','o','c','i','g','u','s','o','c'],
                $project->title
            )));
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            if (empty($slug)) $slug = strtolower($project->project_code);
            $repoSlug = $creds['username'] . '/' . $slug;
        } elseif (strpos($repoSlug, '/') === false) {
            $repoSlug = $creds['username'] . '/' . $repoSlug;
        }

        [$owner, $repoName] = explode('/', $repoSlug, 2);

        $createRepo = function(string $nameCandidate) use ($creds, $project): array {
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
                    'name'        => $nameCandidate,
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
            return ['code' => $httpCode, 'response' => $resp];
        };

        $res = $createRepo($repoName);
        $httpCode = $res['code'];
        $resp = $res['response'];

        // Eğer repo adı GitHub'da zaten varsa (HTTP 422 - already exists), çakışmayı önlemek için projeye özel benzersiz yeni bir repo adı üret
        if ($httpCode === 422) {
            $projCodeSuffix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$project->project_code));
            $candidates = [
                $repoName . '-' . $projCodeSuffix,
                $repoName . '-' . $project->id,
                $repoName . '-' . substr(md5(uniqid((string)$project->id, true)), 0, 6)
            ];

            foreach ($candidates as $candidate) {
                $candidateRes = $createRepo($candidate);
                if ($candidateRes['code'] === 201) {
                    $repoName = $candidate;
                    $httpCode = 201;
                    $resp = $candidateRes['response'];
                    break;
                }
            }
        }

        if ($httpCode !== 201 && $httpCode !== 422 && $httpCode !== 200) {
            $errJson = json_decode((string)$resp, true);
            $errMsg  = $errJson['message'] ?? 'HTTP ' . $httpCode;
            return ['success' => false, 'error' => str_replace('{error}', $errMsg, __('GithubDevops::err_github_api'))];
        }

        $gitignorePath = $ws . '/.gitignore';
        if (!file_exists($gitignorePath)) {
            $ignoreContent = ".env\n/vendor/\n/node_modules/\n/Public/uploads/\n*.sql\n*.zip\n*.log\n.DS_Store\nThumbs.db\n";
            @file_put_contents($gitignorePath, $ignoreContent);
        }

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

        $model->updateProjectGithubRepo((int)$project->id, "{$owner}/{$repoName}");

        return [
            'success'     => true,
            'github_repo' => "{$owner}/{$repoName}",
            'repo_url'    => "https://github.com/{$owner}/{$repoName}",
            'message'     => str_replace('{repo}', "{$owner}/{$repoName}", __('GithubDevops::msg_project_synced')),
            'git_output'  => $output
        ];
    }

    /**
     * Görev tesliminde otomatik atomic commit ve push yapar.
     */
    public static function autoCommitAndPushTask(object $project, object $task): array
    {
        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => __('GithubDevops::err_workspace_dir_missing')];
        }

        $escapedWs = escapeshellarg($ws);

        if (!is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => __('GithubDevops::err_git_not_found')];
        }

        $commitMsg = "feat({$task->task_code}): " . str_replace(['"', '`', '$'], '', $task->title);

        $commitCmd = "cd /d {$escapedWs} && git add -A && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
        @shell_exec($commitCmd);

        $hash = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse HEAD 2>nul"));
        if (empty($hash) || strlen($hash) < 7) {
            return ['success' => false, 'error' => __('GithubDevops::err_commit_failed')];
        }

        $branch = self::detectActiveBranch($ws);

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

            if (!empty($creds['token']) && !empty($creds['username'])) {
                $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
                $pushCmd = "cd /d {$escapedWs} && git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1";
                @shell_exec($pushCmd);
            } else {
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
     * Hedef projenin veritabanı adını tespit eder.
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

        if (file_exists($ws . '/.env')) {
            $envContent = (string)@file_get_contents($ws . '/.env');
            if (preg_match('/^\s*(?:DB_DATABASE|DB_NAME|database\.default\.database)\s*=\s*[\'"]?([a-zA-Z0-9_]+)[\'"]?/m', $envContent, $m)) {
                return $m[1];
            }
        }

        $candidates = [
            $ws . '/config/database.php',
            $ws . '/Config/database.php',
            $ws . '/Core/Config/database.php',
            $ws . '/Model/DataLayer.php',
            $ws . '/Core/DataLayer.php',
            $ws . '/Core/Database.php',
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
     * Proje şema haritasını çıkarır.
     */
    public static function getProjectSchemaMap(string $dbName): array
    {
        $map = [];
        if (empty($dbName)) return $map;

        try {
            $rows = self::getModel()->getSchemaColumns($dbName);
            foreach ($rows as $r) {
                $rObj = (object)$r;
                $t = $rObj->TABLE_NAME;
                if (!isset($map[$t])) $map[$t] = [];
                $map[$t][$rObj->COLUMN_NAME] = $rObj->COLUMN_TYPE;
            }
        } catch (\Throwable $e) {
            error_log("getProjectSchemaMap error: " . $e->getMessage());
        }

        return $map;
    }

    /**
     * Görev öncesi şema anlık görüntüsünü çeker.
     */
    public static function capturePreTaskSnapshot(int $taskId): void
    {
        try {
            $model = self::getModel();
            $task = $model->getTaskById($taskId);
            if (!$task) return;
            $project = $model->getProjectById((int)$task->project_id);
            if (!$project) return;

            $dbName = self::resolveProjectDatabaseName($project);
            if (!$dbName) return;

            $schemaMap = self::getProjectSchemaMap($dbName);
            if (empty($schemaMap)) return;

            $snapDir = dirname(__DIR__, 3) . '/Public/uploads/schema_snapshots';
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
     * Görev tamamlandığında şema farkını hesaplar ve rollback SQL üretir.
     */
    public static function computePostTaskSchemaDiff(int $taskId, object $project, string $taskCode): array
    {
        $sqlStatements = [];
        $summaryParts  = [];

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (!empty($ws) && is_dir($ws)) {
            $agentDownFile = $ws . '/.migrations/' . $taskCode . '_down.sql';
            if (file_exists($agentDownFile)) {
                $customSql = trim((string)@file_get_contents($agentDownFile));
                if ($customSql !== '') {
                    $sqlStatements[] = "-- Agent Custom Rollback SQL:\n" . $customSql;
                    $summaryParts[]  = str_replace('{task_code}', $taskCode, __('GithubDevops::summary_agent_down_sql'));
                }
            }
        }

        $snapFile = dirname(__DIR__, 3) . '/Public/uploads/schema_snapshots/snap_task_' . $taskId . '.json';
        if (file_exists($snapFile)) {
            $snapData = json_decode((string)@file_get_contents($snapFile), true);
            $dbName   = $snapData['db_name'] ?? self::resolveProjectDatabaseName($project);
            $oldMap   = $snapData['schema'] ?? [];

            if ($dbName && !empty($oldMap)) {
                $newMap = self::getProjectSchemaMap($dbName);
                foreach ($newMap as $tableName => $cols) {
                    if (!isset($oldMap[$tableName])) {
                        $sqlStatements[] = "DROP TABLE IF EXISTS `{$dbName}`.`{$tableName}`;";
                        $summaryParts[]  = str_replace('{table}', $tableName, __('GithubDevops::summary_table_created'));
                    } else {
                        foreach ($cols as $colName => $colType) {
                            if (!isset($oldMap[$tableName][$colName])) {
                                $sqlStatements[] = "ALTER TABLE `{$dbName}`.`{$tableName}` DROP COLUMN `{$colName}`;";
                                $summaryParts[]  = str_replace(['{table}', '{column}'], [$tableName, $colName], __('GithubDevops::summary_column_added'));
                            }
                        }
                    }
                }
            }
            @unlink($snapFile);
        }

        return [
            'rollback_sql' => implode("\n", $sqlStatements),
            'summary'      => !empty($summaryParts) ? implode(', ', $summaryParts) : __('GithubDevops::summary_no_schema_diff')
        ];
    }

    /**
     * CERRAHİ GERİ ALMA (ROLLBACK):
     * Görevin Git değişikliklerini ve veritabanı şemasını diğer görevleri bozmadan güvenle geri alır.
     */
    public static function rollbackTaskChanges(int $taskId, string $mode = 'safe'): array
    {
        $model = self::getModel();
        $model->ensureGithubAndRollbackSchema();

        $task = $model->getTaskById($taskId);
        if (!$task) {
            return ['success' => false, 'error' => __('GithubDevops::err_task_not_found')];
        }

        $project = $model->getProjectById((int)$task->project_id);
        if (!$project) {
            return ['success' => false, 'error' => __('GithubDevops::err_project_not_found')];
        }

        $sub = $model->getLatestSubmission($taskId);
        if (!$sub || empty($sub->commit_hash)) {
            return ['success' => false, 'error' => __('GithubDevops::err_no_commit_hash')];
        }
        if ((int)($sub->is_reverted ?? 0) === 1) {
            return ['success' => false, 'error' => __('GithubDevops::err_already_reverted')];
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => __('GithubDevops::err_git_not_found')];
        }

        $hash      = trim($sub->commit_hash);
        $escapedWs = escapeshellarg($ws);

        if ($mode === 'ai_surgeon') {
            $reasonTemplate = __('GithubDevops::rollback_reason_title');
            $schemaPart = $sub->schema_diff_summary ?: __('GithubDevops::default_schema_tables');
            $reason = str_replace(
                ['{task_code}', '{title}', '{hash}', '{schema}'],
                [$task->task_code, $task->title, $hash, $schemaPart],
                $reasonTemplate
            );

            $userId = $_SESSION['user']->id ?? 1;
            $model->requestModification((int)$task->id, (int)$userId, $reason);

            if (class_exists('Modules\\Board\\Services\\TaskLifecycleService')) {
                \Modules\Board\Services\TaskLifecycleService::triggerTaskRevisionWebhook((int)$task->id, $reason);
            }

            return [
                'success' => true,
                'mode'    => 'ai_surgeon',
                'message' => str_replace('{task_code}', $task->task_code, __('GithubDevops::msg_ai_surgeon_assigned'))
            ];
        }

        $testRevert = (string)@shell_exec("cd /d {$escapedWs} && git revert --no-commit " . escapeshellarg($hash) . " 2>&1");
        $statusCheck = (string)@shell_exec("cd /d {$escapedWs} && git status --porcelain 2>&1");

        if (preg_match('/^(UU|AA|DD|DU|UD)\s/m', $statusCheck) || stripos($testRevert, 'conflict') !== false || stripos($testRevert, 'error:') !== false) {
            @shell_exec("cd /d {$escapedWs} && git revert --abort 2>nul || git reset --hard HEAD 2>nul");

            return [
                'success'      => false,
                'has_conflict' => true,
                'commit_hash'  => substr($hash, 0, 7),
                'error'        => str_replace('{task_code}', $task->task_code, __('GithubDevops::err_conflict_detected')),
                'details'      => $testRevert
            ];
        }

        $revertMsg = "Revert \"{$task->task_code}: {$task->title}\" (Commit {$hash})";
        @shell_exec("cd /d {$escapedWs} && git commit -m " . escapeshellarg($revertMsg) . " 2>&1");

        $branch = self::detectActiveBranch($ws);
        $creds = $model->getGithubCredentials();
        if (!empty($creds['username']) && !empty($creds['token'])) {
            $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
            @shell_exec("cd /d {$escapedWs} && git -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1");
        } else {
            @shell_exec("cd /d {$escapedWs} && git push origin {$branch} 2>&1");
        }

        $dbRolledBack = false;
        if (!empty($sub->rollback_sql)) {
            $statements = array_filter(array_map('trim', explode(';', $sub->rollback_sql)));
            foreach ($statements as $sqlStmt) {
                $cleanSql = trim(preg_replace('/^--.*$/m', '', $sqlStmt));
                if ($cleanSql !== '') {
                    try {
                        $model->executeRaw($cleanSql);
                        $dbRolledBack = true;
                    } catch (\Throwable $e) {
                        error_log("Rollback SQL error: " . $e->getMessage());
                    }
                }
            }
        }

        $model->markSubmissionReverted((int)$sub->id);

        $dbSummary = $dbRolledBack ? str_replace('{summary}', (string)$sub->schema_diff_summary, __('GithubDevops::part_and_db')) : '';
        $comment = str_replace(['{db_part}', '{hash}'], [$dbSummary, substr($hash, 0, 7)], __('GithubDevops::activity_task_reverted'));
        $userId = $_SESSION['user']->id ?? 1;
        $model->logTaskActivity((int)$task->id, (int)$userId, 'task_reverted', $comment);

        $dbMysql = $dbRolledBack ? __('GithubDevops::part_and_db_mysql') : '';
        $message = str_replace(['{task_code}', '{db_part}'], [$task->task_code, $dbMysql], __('GithubDevops::msg_task_reverted_success'));

        return [
            'success'        => true,
            'db_rolled_back' => $dbRolledBack,
            'message'        => $message
        ];
    }

    /**
     * Git diff analizi yapar (İki commit arasındaki değişen dosyaları döndürür)
     */
    public static function getDiffFiles(string $workspacePath, ?string $fromCommit = null, string $toCommit = 'HEAD'): array
    {
        $escapedWs = escapeshellarg($workspacePath);
        if (empty($fromCommit)) {
            $output = @shell_exec("cd /d {$escapedWs} && git ls-files 2>nul");
        } else {
            $output = @shell_exec("cd /d {$escapedWs} && git diff --name-status " . escapeshellarg($fromCommit) . " " . escapeshellarg($toCommit) . " 2>nul");
        }

        if (empty($output)) {
            return [];
        }

        $lines = explode("\n", trim($output));
        $files = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (empty($fromCommit)) {
                $files[] = ['status' => 'A', 'file' => $line];
            } else {
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) === 2) {
                    $files[] = ['status' => $parts[0], 'file' => $parts[1]];
                }
            }
        }

        return $files;
    }

    /**
     * Projeye ait Git commit geçmişini ve zenginleştirilmiş zaman tüneli verilerini döndürür.
     */
    public static function getProjectTimeline(int $projectId, int $limit = 30): array
    {
        $model = self::getModel();
        $project = $model->getProjectById($projectId);
        if (!$project) {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_project_not_found')
            ];
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws) || !is_dir($ws . '/.git')) {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_workspace_not_found')
            ];
        }

        $escapedWs = escapeshellarg($ws);
        $limit = max(1, min(100, $limit));

        // Format: %H@@@%h@@@%an@@@%ae@@@%ad@@@%s
        $cmd = "cd /d {$escapedWs} && git -c safe.directory=* -c i18n.logOutputEncoding=UTF-8 log -n {$limit} --encoding=UTF-8 --pretty=format:\"%H@@@%h@@@%an@@@%ae@@@%ad@@@%s\" --date=iso 2>&1";
        $logOutput = @shell_exec($cmd);

        // Dubious ownership veya fatal durumunda global safe.directory ekleyip bir kez daha dene
        if ($logOutput && str_contains($logOutput, 'fatal:')) {
            @shell_exec('git config --global --add safe.directory "*" 2>nul');
            $logOutput = @shell_exec($cmd);
        }

        if (empty($logOutput) || str_contains((string)$logOutput, 'fatal:')) {
            return [
                'success'        => true,
                'timeline'       => [],
                'is_clean'       => true,
                'current_branch' => self::detectActiveBranch($ws),
                'project_title'  => $project->title ?? ''
            ];
        }

        $lines = explode("\n", trim($logOutput));
        $timeline = [];
        $isFirst = true;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode('@@@', $line, 6);
            if (count($parts) < 6) continue;

            [$fullHash, $shortHash, $authorName, $authorEmail, $dateStr, $subject] = $parts;

            // UTF-8 temizliği (json_encode çökmesini önler)
            $authorName = mb_convert_encoding($authorName, 'UTF-8', 'UTF-8');
            $subject    = mb_convert_encoding($subject, 'UTF-8', 'UTF-8');

            // Commit mesajından görev kodunu yakala (Örn: TSK-1293, 01-105, TASK-12 vb.)
            $taskCode = null;
            $matchedTask = null;
            if (preg_match('/\b([A-Za-z0-9]+-\d+)\b/', $subject, $m)) {
                $taskCode = strtoupper($m[1]);
                $matchedTask = $model->getTaskByCode($taskCode);
            }

            // Hızlı değişen dosya sayısı
            $changedFilesCount = 0;

            // Görevi yapan veya atanan kullanıcı bilgisi
            $assignedUser = null;
            if ($matchedTask && !empty($matchedTask->assigned_to)) {
                $u = $model->getUserById((int)$matchedTask->assigned_to);
                if ($u) {
                    $assignedUser = [
                        'name'   => mb_convert_encoding($u->name, 'UTF-8', 'UTF-8'),
                        'email'  => $u->email,
                        'avatar' => $u->avatar_color ?? null
                    ];
                }
            }

            $timeline[] = [
                'commit_hash'         => $fullHash,
                'short_hash'          => $shortHash,
                'author_name'         => $authorName,
                'author_email'        => $authorEmail,
                'committed_at'        => $dateStr,
                'subject'             => $subject,
                'is_current'          => $isFirst,
                'task_code'           => $taskCode,
                'task_title'          => $matchedTask ? mb_convert_encoding($matchedTask->title, 'UTF-8', 'UTF-8') : null,
                'task_id'             => $matchedTask ? (int)$matchedTask->id : null,
                'assigned_user'       => $assignedUser,
                'changed_files_count' => $changedFilesCount
            ];

            $isFirst = false;
        }

        // Git repo temiz mi kontrol et
        $statusCheck = trim((string)@shell_exec("cd /d {$escapedWs} && git -c safe.directory=* status --porcelain 2>nul"));
        $isClean = empty($statusCheck);

        return [
            'success'        => true,
            'timeline'       => $timeline,
            'is_clean'       => $isClean,
            'current_branch' => self::detectActiveBranch($ws),
            'project_title'  => $project->title
        ];
    }

    /**
     * Projeyi güvenli bir şekilde hedef commit'e geri döndürür.
     * Öncesinde yedek branch/tag açar, varsa aradaki .migrations/*_down.sql dosyalarını çalıştırır.
     */
    public static function rollbackToCommit(int $projectId, string $targetCommitHash, int $userId): array
    {
        $model = self::getModel();
        $project = $model->getProjectById($projectId);
        if (!$project) {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_project_not_found')
            ];
        }

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws) || !is_dir($ws . '/.git')) {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_workspace_not_found')
            ];
        }

        $escapedWs = escapeshellarg($ws);
        $targetHashClean = trim($targetCommitHash);

        if (empty($targetHashClean) || !preg_match('/^[a-f0-9]{7,40}$/i', $targetHashClean)) {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_no_commit_hash')
            ];
        }

        // 1. Hedef commit'in varlığını doğrula
        $commitExists = trim((string)@shell_exec("git -C {$escapedWs} cat-file -t " . escapeshellarg($targetHashClean) . " 2>nul"));
        if ($commitExists !== 'commit') {
            return [
                'success' => false,
                'error'   => __('GithubDevops::err_no_commit_hash')
            ];
        }

        // 2. Güvenlik Ağı (Safety Net): Geri almadan önce mevcut HEAD durumunu yedek branch'e al
        $timestamp = date('Ymd_His');
        $backupBranch = "backup_before_rollback_{$timestamp}";
        @shell_exec("git -C {$escapedWs} branch " . escapeshellarg($backupBranch) . " HEAD 2>nul");

        // 3. Aradaki ters SQL (.migrations/{task_code}_down.sql) scriptlerini tespit et ve çalıştır
        $dbRolledBackCount = 0;
        $revListCmd = "git -C {$escapedWs} rev-list " . escapeshellarg($targetHashClean) . "..HEAD 2>nul";
        $revOutput = trim((string)@shell_exec($revListCmd));

        if (!empty($revOutput)) {
            $hashesToRevert = array_filter(explode("\n", $revOutput));
            foreach ($hashesToRevert as $revHash) {
                $revHash = trim($revHash);
                if (empty($revHash)) continue;

                // Commit mesajından görev kodunu yakala
                $msgCmd = "git -C {$escapedWs} log -1 --pretty=format:%s " . escapeshellarg($revHash) . " 2>nul";
                $cMsg = trim((string)@shell_exec($msgCmd));

                if (preg_match('/\b([A-Za-z0-9]+-\d+)\b/', $cMsg, $m)) {
                    $tc = strtoupper($m[1]);
                    $downSqlPath = $ws . '/.migrations/' . $tc . '_down.sql';
                    if (file_exists($downSqlPath)) {
                        $sqlContent = file_get_contents($downSqlPath);
                        if (!empty($sqlContent)) {
                            $stmts = array_filter(array_map('trim', explode(';', $sqlContent)));
                            foreach ($stmts as $stmt) {
                                $cleanStmt = trim(preg_replace('/^--.*$/m', '', $stmt));
                                if ($cleanStmt !== '') {
                                    try {
                                        $model->executeRaw($cleanStmt);
                                        $dbRolledBackCount++;
                                    } catch (\Throwable $e) {
                                        error_log("Time machine reverse SQL error ({$tc}): " . $e->getMessage());
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Hedef commit durumunu çalışma dizinine uygula
        // git revert --no-commit target..HEAD veya temiz checkout
        $revertCmd = "git -C {$escapedWs} revert --no-commit " . escapeshellarg($targetHashClean) . "..HEAD 2>&1";
        $revertOutput = (string)@shell_exec($revertCmd);

        $statusCheck = (string)@shell_exec("git -C {$escapedWs} status --porcelain 2>&1");
        $hasConflict = preg_match('/^(UU|AA|DD|DU|UD)\s/m', $statusCheck) || stripos($revertOutput, 'conflict') !== false;

        if ($hasConflict) {
            // Çakışma durumunda hedef commit'in tam dosya ağacını checkout edip temizleyelim
            @shell_exec("git -C {$escapedWs} revert --abort 2>nul");
            @shell_exec("git -C {$escapedWs} checkout " . escapeshellarg($targetHashClean) . " -- . 2>nul");
        }

        // Commit oluştur: Geri alma işlemi
        $shortTarget = substr($targetHashClean, 0, 7);
        $revertCommitMsg = "TimeMachine: Rollback project to commit {$shortTarget} (Backup: {$backupBranch})";
        @shell_exec("git -C {$escapedWs} add -A 2>&1");
        @shell_exec("git -C {$escapedWs} commit -m " . escapeshellarg($revertCommitMsg) . " 2>&1");

        // Remote push
        $branch = self::detectActiveBranch($ws);
        $creds = $model->getGithubCredentials();
        if (!empty($creds['username']) && !empty($creds['token'])) {
            $authHeader = base64_encode("{$creds['username']}:{$creds['token']}");
            @shell_exec("git -C {$escapedWs} -c http.extraheader=\"AUTHORIZATION: basic {$authHeader}\" push origin {$branch} 2>&1");
        } else {
            @shell_exec("git -C {$escapedWs} push origin {$branch} 2>&1");
        }

        // Log aktivitesi
        $logComment = str_replace(
            ['{hash}', '{backup}'],
            [$shortTarget, $backupBranch],
            __('GithubDevops::activity_project_timeline_reverted')
        );
        $model->logRollbackAction((int)$projectId, 'time_machine', $shortTarget, $userId);

        $successMsg = str_replace(
            ['{hash}', '{backup}'],
            [$shortTarget, $backupBranch],
            __('GithubDevops::msg_timeline_rollback_success')
        );

        return [
            'success'           => true,
            'target_commit'     => $shortTarget,
            'backup_branch'     => $backupBranch,
            'db_queries_rolled' => $dbRolledBackCount,
            'message'           => $successMsg
        ];
    }
}
