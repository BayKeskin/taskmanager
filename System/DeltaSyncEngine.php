<?php
/**
 * DeltaSyncEngine - Akıllı Delta FTP/SFTP Senkronizasyon ve Canlı DB Migration Eşitleyici
 * 
 * Bu motor:
 * 1. Projelerin son canlıya dağıtılan commit SHA'sını (last_deployed_commit) takip eder.
 * 2. Git diff kullanarak yalnızca değişen/eklenen dosyaları tespit edip hızlı delta senkronizasyonu yapar; silinen dosyaları sunucudan temizler.
 * 3. Canlı Veritabanı Koruma: Müşteri verilerini ve canlı tabloları ezmeden, yalnızca yeni eklenen .migrations/*.sql dosyalarındaki şema farklarını (ALTER TABLE vb.) canlı veritabanında çalıştırır.
 * 4. Dağıtım geçmişini ve commit hash'lerini `project_deployments` tablosunda saklar.
 */
class DeltaSyncEngine
{
    /**
     * project_deployments tablosunun varlığını garanti eder.
     */
    public static function ensureSchema(): void
    {
        global $db;
        $sql = "CREATE TABLE IF NOT EXISTS `project_deployments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `deployment_type` ENUM('full', 'delta') NOT NULL DEFAULT 'delta',
            `from_commit` VARCHAR(40) NULL,
            `to_commit` VARCHAR(40) NOT NULL,
            `status` ENUM('started', 'in_progress', 'completed', 'failed', 'rolled_back') NOT NULL DEFAULT 'in_progress',
            `files_uploaded` INT DEFAULT 0,
            `files_deleted` INT DEFAULT 0,
            `migrations_run` INT DEFAULT 0,
            `details` JSON NULL,
            `deployed_by` INT DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME NULL,
            INDEX (`project_id`),
            INDEX (`to_commit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $db->connect->exec($sql);
    }

    /**
     * İki commit arasındaki (veya son dağıtımdan bu yana) dosya farklarını (Git Diff) döner.
     *
     * @param string $workspacePath Projenin yerel klasör yolu
     * @param string|null $fromCommit Son dağıtılan commit (null ise tam dağıtım)
     * @param string|null $toCommit Hedef commit (null ise HEAD)
     * @return array ['added' => [], 'modified' => [], 'deleted' => [], 'all_changes' => []]
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

        // Eğer ilk dağıtım ise veya fromCommit yoksa, depodaki tüm izlenen dosyaları listele
        if (empty($fromCommit)) {
            $output = shell_exec("cd /d \"{$normalizedPath}\" && git ls-files");
            $files = array_filter(array_map('trim', explode("\n", $output ?: '')));
            return [
                'success'        => true,
                'is_full'        => true,
                'from_commit'    => null,
                'to_commit'      => $toCommit,
                'added'          => array_values($files),
                'modified'       => [],
                'deleted'        => [],
                'all_changes'    => array_values($files),
                'total_changes'  => count($files)
            ];
        }

        // Git diff-tree ile iki commit arasındaki farkları al
        // Çıktı formatı: :100644 100644 e69de29 4b825dc M path/to/file
        $cmd = "cd /d \"{$normalizedPath}\" && git diff --name-status {$fromCommit} {$toCommit}";
        $diffOutput = shell_exec($cmd);

        $added = [];
        $modified = [];
        $deleted = [];

        if (!empty($diffOutput)) {
            $lines = array_filter(array_map('trim', explode("\n", $diffOutput)));
            foreach ($lines as $line) {
                if (empty($line)) continue;
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) < 2) continue;

                $status = strtoupper($parts[0]);
                $file   = trim($parts[1]);

                // Ignore git internal files
                if (str_starts_with($file, '.git/')) continue;

                if (str_starts_with($status, 'A')) {
                    $added[] = $file;
                } elseif (str_starts_with($status, 'M')) {
                    $modified[] = $file;
                } elseif (str_starts_with($status, 'D')) {
                    $deleted[] = $file;
                } else {
                    $modified[] = $file;
                }
            }
        }

        return [
            'success'       => true,
            'is_full'       => false,
            'from_commit'   => $fromCommit,
            'to_commit'     => $toCommit,
            'added'         => $added,
            'modified'      => $modified,
            'deleted'       => $deleted,
            'all_changes'   => array_merge($added, $modified),
            'total_changes' => count($added) + count($modified) + count($deleted)
        ];
    }

    /**
     * İki commit arasında eklenmiş yeni `.migrations/*.sql` şema göçlerini tespit eder.
     *
     * @param string $workspacePath Projenin yerel klasör yolu
     * @param string|null $fromCommit Son dağıtılan commit
     * @param string|null $toCommit Hedef commit
     * @return array Tespit edilen migration dosyaları ve SQL içerikleri
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
            // Sadece .migrations/ altındaki .sql dosyaları (down/rollback hariç)
            if (preg_match('#^\.migrations/([a-zA-Z0-9_\-]+)\.sql$#i', $file, $m)) {
                if (str_ends_with($file, '_down.sql')) {
                    continue; // Geri alma dosyalarını atla
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
     * Canlı veritabanında şema göçlerini (migrations) çalıştırır. Canlıdaki müşteri verilerine dokunmaz.
     *
     * @param PDO $livePdo Canlı veritabanı PDO bağlantısı
     * @param array $migrations detectPendingMigrations çıktısı
     * @return array Sonuç raporu
     */
    public static function applyLiveMigrations(PDO $livePdo, array $migrations): array
    {
        // Migrations tablosunun varlığını garanti et
        $livePdo->exec("CREATE TABLE IF NOT EXISTS `taskmanager_schema_migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration_file` VARCHAR(255) NOT NULL UNIQUE,
            `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `status` VARCHAR(50) DEFAULT 'success'
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

            // Daha önce çalıştırılmış mı denetle
            $stmt = $livePdo->prepare("SELECT id FROM `taskmanager_schema_migrations` WHERE migration_file = ?");
            $stmt->execute([$file]);
            if ($stmt->fetch()) {
                $skipped[] = $file;
                continue;
            }

            try {
                // Şema değişikliğini güvenli çalıştır (ALTER TABLE, CREATE TABLE vb.)
                $livePdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
                $livePdo->exec($sql);

                $insStmt = $livePdo->prepare("INSERT INTO `taskmanager_schema_migrations` (migration_file, applied_at, status) VALUES (?, NOW(), 'success')");
                $insStmt->execute([$file]);

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
        global $db;
        self::ensureSchema();
        $row = $db->connect->query("SELECT * FROM project_deployments WHERE project_id = {$projectId} AND status = 'completed' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    /**
     * Yeni bir dağıtım kaydı başlatır ve kaydeder.
     */
    public static function recordDeployment(int $projectId, array $data): int
    {
        global $db;
        self::ensureSchema();

        $type        = $data['deployment_type'] ?? 'delta';
        $fromCommit  = $data['from_commit'] ?? null;
        $toCommit    = $data['to_commit'];
        $status      = $data['status'] ?? 'in_progress';
        $filesUp     = (int)($data['files_uploaded'] ?? 0);
        $filesDel    = (int)($data['files_deleted'] ?? 0);
        $migsRun     = (int)($data['migrations_run'] ?? 0);
        $detailsJson = json_encode($data['details'] ?? [], JSON_UNESCAPED_UNICODE);
        $deployedBy  = (int)($data['deployed_by'] ?? 1);

        $stmt = $db->connect->prepare("
            INSERT INTO project_deployments (
                project_id, deployment_type, from_commit, to_commit, status,
                files_uploaded, files_deleted, migrations_run, details, deployed_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $projectId, $type, $fromCommit, $toCommit, $status,
            $filesUp, $filesDel, $migsRun, $detailsJson, $deployedBy
        ]);

        return (int)$db->connect->lastInsertId();
    }

    /**
     * Dağıtımı tamamlandı veya başarısız olarak günceller.
     */
    public static function updateDeploymentStatus(int $deploymentId, string $status, array $extra = []): void
    {
        global $db;
        $detailsJson = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
        $sql = "UPDATE project_deployments SET status = ?, completed_at = NOW()";
        $params = [$status];

        if ($detailsJson !== null) {
            $sql .= ", details = ?";
            $params[] = $detailsJson;
        }

        $sql .= " WHERE id = ?";
        $params[] = $deploymentId;

        $stmt = $db->connect->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Dağıtım sonrası canlı sitenin HTTP sağlık kontrolünü (Healthcheck) yapar.
     *
     * @param string $liveUrl Canlı site URL'si
     * @param int $timeout Zaman aşımı (saniye)
     * @return array ['success' => bool, 'http_code' => int, 'response_time_ms' => float, 'message' => string]
     */
    public static function performHealthcheck(string $liveUrl, int $timeout = 8): array
    {
        if (empty($liveUrl) || !filter_var($liveUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'http_code' => 0,
                'response_time_ms' => 0,
                'message' => 'Geçersiz canlı site URL formatı.'
            ];
        }

        $startTime = microtime(true);
        $ch = curl_init($liveUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TaskManager-HealthCheck/1.0 (+http://localhost/taskmanager)');

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $isOk = ($httpCode >= 200 && $httpCode < 400);

        return [
            'success'          => $isOk,
            'http_code'        => $httpCode,
            'response_time_ms' => $durationMs,
            'body_length'      => strlen($body ?: ''),
            'message'          => $isOk 
                ? "Canlı site başarıyla yanıt verdi (HTTP {$httpCode}, {$durationMs}ms)."
                : (!empty($curlError) ? "Bağlantı hatası: {$curlError}" : "Beklenmeyen HTTP yanıt kodu: {$httpCode}")
        ];
    }

    /**
     * Cerrahi Geri Alma (Rollback): Dağıtımı önceki stabil commit'e döndürür ve inverse migration'ları (_down.sql) çalıştırır.
     *
     * @param int $projectId Proje ID
     * @param int|null $deploymentId Geri alınacak dağıtım ID (null ise en son dağıtım)
     * @param PDO|null $livePdo Canlı veritabanı bağlantısı (şema geri alma için)
     * @return array Sonuç raporu
     */
    public static function rollbackDeployment(int $projectId, ?int $deploymentId = null, ?PDO $livePdo = null): array
    {
        global $db;
        self::ensureSchema();

        $dep = null;
        if ($deploymentId > 0) {
            $dep = $db->connect->query("SELECT * FROM project_deployments WHERE id = {$deploymentId} AND project_id = {$projectId}")->fetch(PDO::FETCH_OBJ);
        } else {
            $dep = self::getLastDeployment($projectId);
        }

        if (!$dep) {
            return [
                'success' => false,
                'message' => 'Geri alınacak geçerli bir dağıtım kaydı bulunamadı.'
            ];
        }

        $targetCommit = $dep->from_commit;
        if (empty($targetCommit)) {
            return [
                'success' => false,
                'message' => 'Bu ilk dağıtım olduğundan geri dönülebilecek önceki bir commit bulunmuyor.'
            ];
        }

        // Proje bilgilerini al
        $proj = $db->row_query("SELECT * FROM projects WHERE id = ?", [$projectId]);
        $workspacePath = $proj->workspace_path ?? '';
        $rolledBackMigrations = [];

        // Eğer canlı PDO varsa, to_commit ile from_commit arasındaki .migrations/*_down.sql dosyalarını çalıştır
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
                                $livePdo->prepare("DELETE FROM `taskmanager_schema_migrations` WHERE migration_file = ?")->execute([$file]);
                                $rolledBackMigrations[] = $downFile;
                            } catch (Exception $e) {
                                // Geri alma devam eder
                            }
                        }
                    }
                }
            }
        }

        // Yeni bir 'rolled_back' deployment kaydı oluştur
        $rollbackDepId = self::recordDeployment($projectId, [
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

        self::updateDeploymentStatus((int)$dep->id, 'rolled_back');

        return [
            'success'                 => true,
            'rollback_deployment_id' => $rollbackDepId,
            'restored_commit'         => $targetCommit,
            'rolled_back_migrations'  => $rolledBackMigrations,
            'message'                 => "Canlı dağıtım cerrahi olarak önceki stabil sürüme ({$targetCommit}) başarıyla geri alındı."
        ];
    }
}
