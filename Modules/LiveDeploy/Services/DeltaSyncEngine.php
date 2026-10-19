<?php
use Modules\LiveDeploy\Models\DeployModel;

/**
 * DeltaSyncEngine - Akıllı Delta FTP/SFTP Senkronizasyon ve Canlı DB Migration Eşitleyici
 */
class DeltaSyncEngine
{
    private static function getModel(): DeployModel
    {
        static $model = null;
        if ($model === null) {
            $model = new DeployModel();
        }
        return $model;
    }

    /**
     * project_deployments tablosunun varlığını garanti eder.
     */
    public static function ensureSchema(): void
    {
        self::getModel()->ensureDeploymentsSchema();
    }

    /**
     * İki commit arasındaki (veya son dağıtımdan bu yana) dosya farklarını (Git Diff) döner.
     */
    public static function calculateGitDiff(string $workspacePath, ?string $fromCommit = null, ?string $toCommit = 'HEAD'): array
    {
        $normalizedPath = rtrim(str_replace('\\', '/', $workspacePath), '/');

        if (!is_dir("{$normalizedPath}/.git")) {
            return [
                'success' => false,
                'error'   => 'Directory is not a git repository'
            ];
        }

        $currentHead = trim(shell_exec("cd /d \"{$normalizedPath}\" && git rev-parse HEAD") ?: '');
        if ($toCommit === 'HEAD' || empty($toCommit)) {
            $toCommit = $currentHead;
        }

        $isFull = empty($fromCommit);
        $added = [];
        $modified = [];
        $deleted = [];

        if ($isFull) {
            $output = shell_exec("cd /d \"{$normalizedPath}\" && git ls-files") ?: '';
            $files = array_filter(array_map('trim', explode("\n", $output)));
            $added = array_values($files);
        } else {
            $cmd = "cd /d \"{$normalizedPath}\" && git diff --name-status {$fromCommit} {$toCommit}";
            $output = shell_exec($cmd) ?: '';
            $lines = array_filter(array_map('trim', explode("\n", $output)));

            foreach ($lines as $line) {
                if (empty($line)) continue;
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) < 2) continue;

                $status = $parts[0][0];
                $file   = $parts[1];

                if ($status === 'A') {
                    $added[] = $file;
                } elseif ($status === 'M') {
                    $modified[] = $file;
                } elseif ($status === 'D') {
                    $deleted[] = $file;
                } elseif ($status === 'R') {
                    $renamed = preg_split('/\s+/', $parts[1]);
                    if (count($renamed) >= 2) {
                        $deleted[] = $renamed[0];
                        $added[]   = $renamed[1];
                    }
                }
            }
        }

        return [
            'success'     => true,
            'is_full'     => $isFull,
            'from_commit' => $fromCommit,
            'to_commit'   => $toCommit,
            'added'       => $added,
            'modified'    => $modified,
            'deleted'     => $deleted,
            'total_files' => count($added) + count($modified) + count($deleted)
        ];
    }

    /**
     * İki commit arasındaki uygulanmamış yeni veritabanı şema göçlerini (.migrations/*.sql) tespit eder.
     */
    public static function detectPendingMigrations(string $workspacePath, ?string $fromCommit = null, ?string $toCommit = 'HEAD'): array
    {
        $diff = self::calculateGitDiff($workspacePath, $fromCommit, $toCommit);
        if (empty($diff['success'])) {
            return [];
        }

        $pending = [];
        $filesToCheck = array_merge($diff['added'], $diff['modified']);

        foreach ($filesToCheck as $file) {
            if (preg_match('#^\.migrations/([a-zA-Z0-9_\-]+)\.sql$#i', $file, $m)) {
                if (str_ends_with($file, '_down.sql')) {
                    continue;
                }

                $fullPath = rtrim($workspacePath, '/') . '/' . $file;
                if (file_exists($fullPath)) {
                    $pending[] = [
                        'file'      => $file,
                        'name'      => $m[1],
                        'sql'       => @file_get_contents($fullPath) ?: '',
                        'size'      => filesize($fullPath)
                    ];
                }
            }
        }

        return $pending;
    }

    /**
     * Canlı veritabanında şema göçlerini (migrations) çalıştırır.
     */
    public static function applyLiveMigrations(PDO $livePdo, array $migrations): array
    {
        $livePdo->exec("CREATE TABLE IF NOT EXISTS `taskmanager_schema_migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration_file` VARCHAR(255) NOT NULL UNIQUE,
            `applied_at` DATETIME NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'success'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $executed = [];
        $skipped = [];
        $errors = [];

        foreach ($migrations as $mig) {
            $file = $mig['file'];
            $sql = trim($mig['sql']);

            if (empty($sql)) {
                $skipped[] = $file;
                continue;
            }

            if (DeployModel::hasMigrationBeenRun($livePdo, $file)) {
                $skipped[] = $file;
                continue;
            }

            try {
                $livePdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
                $livePdo->exec($sql);
                DeployModel::recordLiveMigration($livePdo, $file);
                $executed[] = $file;
            } catch (PDOException $e) {
                $errors[] = [
                    'file'  => $file,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success'   => empty($errors),
            'executed'  => $executed,
            'skipped'   => $skipped,
            'errors'    => $errors,
            'count'     => count($executed)
        ];
    }

    /**
     * Son dağıtım kaydını çeker.
     */
    public static function getLastDeployment(int $projectId): ?object
    {
        return self::getModel()->getLastDeployment($projectId);
    }

    /**
     * Yeni bir dağıtım kaydı başlatır ve kaydeder.
     */
    public static function recordDeployment(int $projectId, array $data): int
    {
        return self::getModel()->recordDeployment($projectId, $data);
    }

    /**
     * Dağıtımı tamamlandı veya başarısız olarak günceller.
     */
    public static function updateDeploymentStatus(int $deploymentId, string $status, array $extra = []): void
    {
        self::getModel()->updateDeploymentStatus($deploymentId, $status, $extra);
    }

    /**
     * Canlı site sağlık kontrolünü gerçekleştirir (HTTP 200/300, yanıt süresi vb.)
     */
    public static function performHealthcheck(string $liveUrl, int $timeout = 8): array
    {
        if (empty($liveUrl) || !filter_var($liveUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'http_code' => 0,
                'response_time_ms' => 0,
                'message' => __('LiveDeploy::err_invalid_url_format')
            ];
        }

        $startTime = microtime(true);
        $ch = curl_init($liveUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $uaUrl = function_exists('siteUrl') ? siteUrl() : 'http://localhost';
        curl_setopt($ch, CURLOPT_USERAGENT, 'TaskManager-HealthCheck/1.0 (+' . $uaUrl . ')');

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $isOk = ($httpCode >= 200 && $httpCode < 400);

        $msgSuccess = str_replace(['{code}', '{time}'], [$httpCode, $durationMs], __('LiveDeploy::msg_health_success'));
        $msgErrConn = !empty($curlError) ? str_replace('{error}', $curlError, __('LiveDeploy::err_health_conn')) : str_replace('{code}', $httpCode, __('LiveDeploy::err_health_unexpected_code'));

        return [
            'success'          => $isOk,
            'http_code'        => $httpCode,
            'response_time_ms' => $durationMs,
            'body_length'      => strlen($body ?: ''),
            'message'          => $isOk ? $msgSuccess : $msgErrConn
        ];
    }

    /**
     * Cerrahi Geri Alma (Rollback): Dağıtımı önceki stabil commit'e döndürür ve inverse migration'ları (_down.sql) çalıştırır.
     */
    public static function rollbackDeployment(int $projectId, ?int $deploymentId = null, ?PDO $livePdo = null): array
    {
        $model = self::getModel();
        $dep = null;
        if ($deploymentId > 0) {
            $dep = $model->getDeploymentById($deploymentId, $projectId);
        } else {
            $dep = $model->getLastDeployment($projectId);
        }

        if (!$dep) {
            return [
                'success' => false,
                'message' => __('LiveDeploy::err_no_deployment_found')
            ];
        }

        $targetCommit = $dep->from_commit;
        if (empty($targetCommit)) {
            return [
                'success' => false,
                'message' => __('LiveDeploy::err_first_deployment_no_commit')
            ];
        }

        $proj = $model->getProjectById($projectId);
        $workspacePath = $proj->workspace_path ?? '';
        $rolledBackMigrations = [];

        if ($livePdo instanceof PDO && !empty($workspacePath)) {
            $diff = self::calculateGitDiff($workspacePath, $targetCommit, $dep->to_commit);
            $files = array_merge($diff['added'] ?? [], $diff['modified'] ?? []);

            foreach ($files as $file) {
                if (preg_match('#^\.migrations/([a-zA-Z0-9_\-]+)\.sql$#i', $file, $m)) {
                    if (str_ends_with($file, '_down.sql')) continue;
                    
                    $downFile = str_replace('.sql', '_down.sql', $file);
                    $fullDownPath = rtrim($workspacePath, '/') . '/' . $downFile;

                    if (file_exists($fullDownPath)) {
                        $downSql = trim(@file_get_contents($fullDownPath) ?: '');
                        if (!empty($downSql)) {
                            try {
                                $livePdo->exec($downSql);
                                DeployModel::removeLiveMigration($livePdo, $file);
                                $rolledBackMigrations[] = $downFile;
                            } catch (Exception $e) {
                                // Geri alma devam eder
                            }
                        }
                    }
                }
            }
        }

        $rollbackDepId = $model->recordDeployment($projectId, [
            'deployment_type' => 'delta',
            'from_commit'     => $dep->to_commit,
            'to_commit'       => $targetCommit,
            'status'          => 'rolled_back',
            'files_uploaded'  => 0,
            'files_deleted'   => 0,
            'migrations_run'  => count($rolledBackMigrations),
            'details'         => [
                'rolled_back_from_id' => $dep->id,
                'rolled_back_migrations' => $rolledBackMigrations
            ],
            'deployed_by'     => $_SESSION['user']->id ?? 1
        ]);

        $model->updateDeploymentStatus((int)$dep->id, 'rolled_back');

        return [
            'success'                 => true,
            'rollback_deployment_id' => $rollbackDepId,
            'restored_commit'         => $targetCommit,
            'rolled_back_migrations'  => $rolledBackMigrations,
            'message'                 => str_replace('{target_commit}', $targetCommit, __('LiveDeploy::msg_rollback_surgical_success'))
        ];
    }
}
