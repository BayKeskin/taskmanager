<?php
namespace Modules\GithubDevops\Services;

use Core\BaseModel;

/**
 * GitService - Projelerin yerel ve uzak Git/GitHub operasyonlarını yönetir.
 * Atomic commit, origin push, diff analizi, schema snapshot ve cerrahi rollback işlemlerini yürütür.
 */
class GitService extends BaseModel
{
    /**
     * Gerekli veritabanı sütunlarını (settings, projects, task_submissions) otomatik kontrol eder.
     */
    public static function ensureGithubAndRollbackSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
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
     * Sistem Ayarlarındaki GitHub Kimlik Bilgilerini döndürür.
     */
    public static function getGithubCredentials(): array
    {
        self::ensureGithubAndRollbackSchema();
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }
        $row = $db->row_query("SELECT github_username, github_token FROM settings WHERE id = 1 LIMIT 1");
        return [
            'username' => trim((string)($row->github_username ?? '')),
            'token'    => trim((string)($row->github_token ?? ''))
        ];
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
        $branch = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse --abbrev-ref HEAD 2>nul"));
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
        self::ensureGithubAndRollbackSchema();
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        $projects = $db->multi_record_query("SELECT id, project_code, title, workspace_path, github_repo FROM projects") ?: [];
        $results = [];

        foreach ($projects as $p) {
            $ws = rtrim(str_replace('\\', '/', trim((string)($p->workspace_path ?? ''))), '/');
            if (empty($ws) || !is_dir($ws) || !is_dir($ws . '/.git')) {
                continue;
            }

            $escapedWs = escapeshellarg($ws);
            $remoteUrl = trim((string)@shell_exec("cd /d {$escapedWs} && git remote get-url origin 2>nul"));

            if (!empty($remoteUrl)) {
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
     * Projeyi GitHub'a bağlar (Var olanı korur, yoksa private repo açar)
     */
    public static function initAndSyncProjectGithubRepo(int $projectId): array
    {
        self::ensureGithubAndRollbackSchema();
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        $project = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$projectId]);
        if (!$project) return ['success' => false, 'error' => 'Proje bulunamadı.'];

        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => 'Projenin yerel çalışma dizini (workspace_path) tanımlı değil veya klasör bulunamadı.'];
        }

        $escapedWs = escapeshellarg($ws);

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
     * Görev tesliminde otomatik atomic commit ve push yapar.
     */
    public static function autoCommitAndPushTask(object $project, object $task): array
    {
        $ws = rtrim(str_replace('\\', '/', trim((string)($project->workspace_path ?? ''))), '/');
        if (empty($ws) || !is_dir($ws)) {
            return ['success' => false, 'error' => 'Workspace dizini yok.'];
        }

        $escapedWs = escapeshellarg($ws);

        if (!is_dir($ws . '/.git')) {
            return ['success' => false, 'error' => 'Git deposu (.git) bulunamadı.'];
        }

        $commitMsg = "feat({$task->task_code}): " . str_replace(['"', '`', '$'], '', $task->title);

        $commitCmd = "cd /d {$escapedWs} && git add -A && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
        @shell_exec($commitCmd);

        $hash = trim((string)@shell_exec("cd /d {$escapedWs} && git rev-parse HEAD 2>nul"));
        if (empty($hash) || strlen($hash) < 7) {
            return ['success' => false, 'error' => 'Commit oluşturulamadı veya dosya değişikliği yok.'];
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
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
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
     * Görev öncesi şema anlık görüntüsünü çeker.
     */
    public static function capturePreTaskSnapshot(int $taskId): void
    {
        try {
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
            }

            $task = $db->row_query("SELECT id, task_code, project_id FROM tasks WHERE id = ? LIMIT 1", [$taskId]);
            if (!$task) return;
            $project = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$task->project_id]);
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
                    $summaryParts[]  = "Ajan özel geri alma SQL'i (.migrations/{$taskCode}_down.sql)";
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
                        $summaryParts[]  = "`{$tableName}` tablosu oluşturuldu";
                    } else {
                        foreach ($cols as $colName => $colType) {
                            if (!isset($oldMap[$tableName][$colName])) {
                                $sqlStatements[] = "ALTER TABLE `{$dbName}`.`{$tableName}` DROP COLUMN `{$colName}`;";
                                $summaryParts[]  = "`{$tableName}.{$colName}` sütunu eklendi";
                            }
                        }
                    }
                }
            }
            @unlink($snapFile);
        }

        return [
            'rollback_sql' => implode("\n", $sqlStatements),
            'summary'      => !empty($summaryParts) ? implode(', ', $summaryParts) : 'Veritabanı şema değişikliği yok (Sadece Kod)'
        ];
    }

    /**
     * CERRAHİ GERİ ALMA (ROLLBACK):
     * Görevin Git değişikliklerini ve veritabanı şemasını diğer görevleri bozmadan güvenle geri alır.
     */
    public static function rollbackTaskChanges(int $taskId, string $mode = 'safe'): array
    {
        self::ensureGithubAndRollbackSchema();
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
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
            
            if (class_exists('Modules\\Board\\Services\\TaskLifecycleService')) {
                \Modules\Board\Services\TaskLifecycleService::triggerTaskRevisionWebhook((int)$task->id, $reason);
            }

            return [
                'success' => true,
                'mode'    => 'ai_surgeon',
                'message' => "🤖 AI Cerrah görevlendirildi! Sonraki görevlere dokunmadan sadece {$task->task_code} görevinin kodlarını ve DB değişikliklerini ayıklayacak."
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
                'error'        => "Bağımlılık / Çakışma Tespit Edildi! {$task->task_code} görevinden sonra yapılan bazı görevler bu görevin yazdığı kodların üstüne inşa edilmiş.",
                'details'      => $testRevert
            ];
        }

        $revertMsg = "Revert \"{$task->task_code}: {$task->title}\" (Commit {$hash})";
        @shell_exec("cd /d {$escapedWs} && git commit -m " . escapeshellarg($revertMsg) . " 2>&1");

        $branch = self::detectActiveBranch($ws);
        $creds = self::getGithubCredentials();
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
                        $db->exec_query($cleanSql);
                        $dbRolledBack = true;
                    } catch (\Throwable $e) {
                        error_log("Rollback SQL error: " . $e->getMessage());
                    }
                }
            }
        }

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
}
