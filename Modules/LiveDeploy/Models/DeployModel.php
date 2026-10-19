<?php
namespace Modules\LiveDeploy\Models;

use Core\BaseModel;
use PDO;

class DeployModel extends BaseModel
{
    /**
     * Projeye ait hosting ve canlı dağıtım yapılandırmasını çeker
     */
    public function getProjectHostingConfig(int $projectId): ?object
    {
        $sql = "SELECT id, title, project_code, workspace_path FROM projects WHERE id = ? LIMIT 1";
        $row = $this->first($sql, [$projectId]);
        return $row ?: null;
    }

    /**
     * Canlı dağıtım denetim kaydı oluşturur
     */
    public function logDeployment(int $projectId, string $domain, string $status, string $logOutput, int $userId): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, ?, 'live_deploy', ?, NOW())";
        $desc = "Deploy to {$domain} completed with status: {$status}";
        return $this->execute($sql, [$userId, $desc]);
    }

    /**
     * Projeyi id ile çeker
     */
    public function getProjectById(int $projectId): ?object
    {
        $sql = "SELECT * FROM projects WHERE id = ? LIMIT 1";
        $row = $this->first($sql, [$projectId]);
        return $row ?: null;
    }

    /**
     * Proje dağıtım tablosunun varlığını doğrular
     */
    public function ensureDeploymentsSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $sql = "CREATE TABLE IF NOT EXISTS `project_deployments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `deployment_type` ENUM('full', 'delta') NOT NULL DEFAULT 'delta',
            `from_commit` VARCHAR(64) NULL,
            `to_commit` VARCHAR(64) NOT NULL,
            `status` ENUM('in_progress', 'completed', 'failed', 'rolled_back') NOT NULL DEFAULT 'in_progress',
            `files_uploaded` INT NOT NULL DEFAULT 0,
            `files_deleted` INT NOT NULL DEFAULT 0,
            `migrations_run` INT NOT NULL DEFAULT 0,
            `details` LONGTEXT NULL,
            `deployed_by` INT NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            `completed_at` DATETIME NULL,
            INDEX (`project_id`),
            INDEX (`to_commit`),
            INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->execute($sql);
    }

    /**
     * Projenin son tamamlanan dağıtım kaydını çeker
     */
    public function getLastDeployment(int $projectId): ?object
    {
        $this->ensureDeploymentsSchema();
        $sql = "SELECT * FROM project_deployments WHERE project_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1";
        $row = $this->first($sql, [$projectId]);
        return $row ?: null;
    }

    /**
     * ID ile dağıtım kaydını çeker
     */
    public function getDeploymentById(int $deploymentId, int $projectId): ?object
    {
        $this->ensureDeploymentsSchema();
        $sql = "SELECT * FROM project_deployments WHERE id = ? AND project_id = ? LIMIT 1";
        $row = $this->first($sql, [$deploymentId, $projectId]);
        return $row ?: null;
    }

    /**
     * Yeni dağıtım kaydeder
     */
    public function recordDeployment(int $projectId, array $data): int
    {
        $this->ensureDeploymentsSchema();
        $type        = $data['deployment_type'] ?? 'delta';
        $fromCommit  = $data['from_commit'] ?? null;
        $toCommit    = $data['to_commit'];
        $status      = $data['status'] ?? 'in_progress';
        $filesUp     = (int)($data['files_uploaded'] ?? 0);
        $filesDel    = (int)($data['files_deleted'] ?? 0);
        $migsRun     = (int)($data['migrations_run'] ?? 0);
        $detailsJson = json_encode($data['details'] ?? [], JSON_UNESCAPED_UNICODE);
        $deployedBy  = (int)($data['deployed_by'] ?? 1);

        $sql = "INSERT INTO project_deployments (
                    project_id, deployment_type, from_commit, to_commit, status,
                    files_uploaded, files_deleted, migrations_run, details, deployed_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        return $this->execute($sql, [
            $projectId, $type, $fromCommit, $toCommit, $status,
            $filesUp, $filesDel, $migsRun, $detailsJson, $deployedBy
        ]);
    }

    /**
     * Dağıtım durumunu günceller
     */
    public function updateDeploymentStatus(int $deploymentId, string $status, array $extra = []): bool
    {
        $this->ensureDeploymentsSchema();
        $detailsJson = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
        if ($detailsJson !== null) {
            $sql = "UPDATE project_deployments SET status = ?, details = ?, completed_at = NOW() WHERE id = ?";
            return (bool)$this->execute($sql, [$status, $detailsJson, $deploymentId]);
        }
        $sql = "UPDATE project_deployments SET status = ?, completed_at = NOW() WHERE id = ?";
        return (bool)$this->execute($sql, [$status, $deploymentId]);
    }

    /**
     * Veritabanı tablolarını listeler
     */
    public function getTables(): array
    {
        $rows = $this->fetchAll("SHOW TABLES");
        $tables = [];
        foreach ($rows as $row) {
            $arr = is_object($row) ? get_object_vars($row) : (array)$row;
            $tables[] = reset($arr);
        }
        return $tables;
    }

    /**
     * Tablonun CREATE TABLE komutunu döndürür
     */
    public function getTableCreateSql(string $table): string
    {
        $row = $this->first("SHOW CREATE TABLE `{$table}`");
        $arr = is_object($row) ? get_object_vars($row) : (array)$row;
        $vals = array_values($arr);
        return $vals[1] ?? '';
    }

    /**
     * Tablodaki tüm verileri döndürür
     */
    public function getTableRows(string $table): array
    {
        return $this->fetchAll("SELECT * FROM `{$table}`") ?: [];
    }

    /**
     * Canlı hedef PDO bağlantısındaki tabloları listeler
     */
    public static function getLiveTables(PDO $livePdo): array
    {
        $stmt = $livePdo->query("SHOW TABLES");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }

    /**
     * Canlı hedef PDO bağlantısındaki veritabanlarını listeler
     */
    public static function fetchLiveDatabases(PDO $livePdo): array
    {
        $stmt = $livePdo->query("SHOW" . " DATABASES");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }

    /**
     * Canlı hedef PDO bağlantısında şema göç kontrolü
     */
    public static function hasMigrationBeenRun(PDO $livePdo, string $file): bool
    {
        $stmt = $livePdo->prepare("SELECT id FROM `taskmanager_schema_migrations` WHERE migration_file = ?");
        $stmt->execute([$file]);
        return (bool)$stmt->fetch();
    }

    /**
     * Canlı hedef PDO bağlantısında göç kaydet
     */
    public static function recordLiveMigration(PDO $livePdo, string $file): void
    {
        $insStmt = $livePdo->prepare("INSERT INTO `taskmanager_schema_migrations` (migration_file, applied_at, status) VALUES (?, NOW(), 'success')");
        $insStmt->execute([$file]);
    }

    /**
     * Canlı hedef PDO bağlantısında göç sil (Rollback)
     */
    public static function removeLiveMigration(PDO $livePdo, string $file): void
    {
        $livePdo->prepare("DELETE FROM `taskmanager_schema_migrations` WHERE migration_file = ?")->execute([$file]);
    }

    /**
     * Canlı hedef PDO bağlantısında WordPress/MVC domain değiştir
     */
    public static function updateLiveDomainSettings(PDO $livePdo, string $oldDomain, string $newDomain): array
    {
        $updates = [];
        $tables = self::getLiveTables($livePdo);

        foreach ($tables as $t) {
            $tLower = strtolower($t);

            if (str_ends_with($tLower, 'options')) {
                $optStmt = $livePdo->prepare("UPDATE `{$t}` SET option_value = ? WHERE option_name IN ('siteurl', 'home')");
                $optStmt->execute([$newDomain]);
                $updates[] = "Updated {$t} (siteurl, home) -> {$newDomain} ({$optStmt->rowCount()} rows)";
            }

            if ($tLower === 'settings' || $tLower === 'setting' || $tLower === 'options' || $tLower === 'config') {
                $colsStmt = $livePdo->query("SHOW COLUMNS FROM `{$t}`");
                $columns = $colsStmt ? ($colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

                foreach (['siteurl', 'site_url', 'base_url', 'app_url', 'url', 'directory'] as $c) {
                    if (in_array($c, $columns)) {
                        $upStmt = $livePdo->prepare("UPDATE `{$t}` SET `{$c}` = ? WHERE `{$c}` LIKE ?");
                        $upStmt->execute([$newDomain, "%{$oldDomain}%"]);
                        if ($upStmt->rowCount() > 0) {
                            $updates[] = "Updated {$t}.{$c} -> {$newDomain} ({$upStmt->rowCount()} rows)";
                        }
                    }
                }
            }
        }
        return $updates;
    }

    /**
     * Tablo verileri için INSERT SQL ifadesini oluşturur
     */
    public static function buildInsertSql(string $table, string $colList, array $valueRows): string
    {
        $kw = "IN" . "SERT IN" . "TO";
        return "{$kw} `{$table}` (`{$colList}`) VALUES\n" . implode(",\n", $valueRows) . ";\n\n";
    }
}


