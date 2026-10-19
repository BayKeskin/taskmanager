<?php
namespace Modules\GithubDevops\Models;

use Core\BaseModel;

class GitDevopsModel extends BaseModel
{
    /**
     * Projeye ait GitHub repo ayarlarını getirir
     */
    public function getProjectRepoSettings(int $projectId): ?object
    {
        $sql = "SELECT id, title, project_code, workspace_path, github_repo FROM projects WHERE id = ? LIMIT 1";
        $row = $this->first($sql, [$projectId]);
        return $row ?: null;
    }

    /**
     * Göreve ait git commit ve rollback audit kaydı
     */
    public function logRollbackAction(int $taskId, string $mode, string $commitHash, int $userId): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (?, ?, 'rollback', ?, NOW())";
        $desc = "Git rollback executed. Mode: {$mode}, Commit: {$commitHash}";
        return $this->execute($sql, [$taskId, $userId, $desc]);
    }

    /**
     * Settings tablosundaki GitHub ayarlarını günceller
     */
    public function updateGithubSettings(string $username, string $token): bool
    {
        $sql = "UPDATE settings SET github_username = ?, github_token = ? WHERE id = 1";
        return (bool)$this->execute($sql, [$username, $token]);
    }

    /**
     * Settings tablosundan GitHub kimlik bilgilerini çeker
     */
    public function getGithubCredentials(): array
    {
        $sql = "SELECT github_username, github_token FROM settings WHERE id = 1 LIMIT 1";
        $row = $this->first($sql);
        return [
            'username' => trim((string)($row->github_username ?? '')),
            'token'    => trim((string)($row->github_token ?? ''))
        ];
    }

    /**
     * Gerekli GitHub DevOps şemasını kontrol eder ve ekler
     */
    public function ensureGithubAndRollbackSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $colsSettings = $this->fetchAll("SHOW COLUMNS FROM settings") ?: [];
            $sNames = array_map(fn($c) => is_object($c) ? $c->Field : ($c['Field'] ?? ''), $colsSettings);
            if (!in_array('github_username', $sNames)) {
                $this->execute("ALTER TABLE settings ADD COLUMN github_username VARCHAR(120) NULL DEFAULT NULL");
            }
            if (!in_array('github_token', $sNames)) {
                $this->execute("ALTER TABLE settings ADD COLUMN github_token VARCHAR(255) NULL DEFAULT NULL");
            }

            $colsProjects = $this->fetchAll("SHOW COLUMNS FROM projects") ?: [];
            $pNames = array_map(fn($c) => is_object($c) ? $c->Field : ($c['Field'] ?? ''), $colsProjects);
            if (!in_array('github_repo', $pNames)) {
                $this->execute("ALTER TABLE projects ADD COLUMN github_repo VARCHAR(180) NULL DEFAULT NULL");
            }
            if (!in_array('db_name', $pNames)) {
                $this->execute("ALTER TABLE projects ADD COLUMN db_name VARCHAR(120) NULL DEFAULT NULL");
            }

            $colsSub = $this->fetchAll("SHOW COLUMNS FROM task_submissions") ?: [];
            $subNames = array_map(fn($c) => is_object($c) ? $c->Field : ($c['Field'] ?? ''), $colsSub);
            if (!in_array('commit_hash', $subNames)) {
                $this->execute("ALTER TABLE task_submissions ADD COLUMN commit_hash VARCHAR(64) NULL DEFAULT NULL");
            }
            if (!in_array('rollback_sql', $subNames)) {
                $this->execute("ALTER TABLE task_submissions ADD COLUMN rollback_sql LONGTEXT NULL DEFAULT NULL");
            }
            if (!in_array('schema_diff_summary', $subNames)) {
                $this->execute("ALTER TABLE task_submissions ADD COLUMN schema_diff_summary TEXT NULL DEFAULT NULL");
            }
            if (!in_array('is_reverted', $subNames)) {
                $this->execute("ALTER TABLE task_submissions ADD COLUMN is_reverted TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            error_log("ensureGithubAndRollbackSchema error: " . $e->getMessage());
        }
    }

    /**
     * Tüm projeleri listeler
     */
    public function getAllProjects(): array
    {
        return $this->fetchAll("SELECT id, project_code, title, workspace_path, github_repo FROM projects") ?: [];
    }

    /**
     * Projeyi id ile getirir
     */
    public function getProjectById(int $projectId): ?object
    {
        return $this->first("SELECT * FROM projects WHERE id = ? LIMIT 1", [$projectId]) ?: null;
    }

    /**
     * Projenin github_repo alanını günceller
     */
    public function updateProjectGithubRepo(int $projectId, string $repoSlug): bool
    {
        return (bool)$this->execute("UPDATE projects SET github_repo = ?, updated_at = NOW() WHERE id = ?", [$repoSlug, $projectId]);
    }

    /**
     * Görevi id ile getirir
     */
    public function getTaskById(int $taskId): ?object
    {
        return $this->first("SELECT * FROM tasks WHERE id = ? LIMIT 1", [$taskId]) ?: null;
    }

    /**
     * Görevin en son teslim kaydını getirir
     */
    public function getLatestSubmission(int $taskId): ?object
    {
        return $this->first("SELECT * FROM task_submissions WHERE task_id = ? ORDER BY id DESC LIMIT 1", [$taskId]) ?: null;
    }

    /**
     * Görevin durumunu revizyona çeker ve talep kaydeder
     */
    public function requestModification(int $taskId, int $userId, string $reason): void
    {
        $this->execute("UPDATE tasks SET status = 'modification_requested', updated_at = NOW() WHERE id = ?", [$taskId]);
        $this->execute("INSERT INTO task_modifications (task_id, requested_by, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())", [
            $taskId, $userId, $reason
        ]);
    }

    /**
     * Veritabanının şema kolon haritasını çeker
     */
    public function getSchemaColumns(string $dbName): array
    {
        return $this->fetchAll("
            SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            ORDER BY TABLE_NAME, ORDINAL_POSITION
        ", [$dbName]) ?: [];
    }

    /**
     * Ham SQL çalıştırır (Örn: Rollback komutları)
     */
    public function executeRaw(string $sql): bool
    {
        return (bool)$this->execute($sql);
    }

    /**
     * Teslimatı geri alındı olarak işaretler
     */
    public function markSubmissionReverted(int $submissionId): bool
    {
        return (bool)$this->execute("UPDATE task_submissions SET is_reverted = 1 WHERE id = ?", [$submissionId]);
    }

    /**
     * Göreve aktivite kaydı ekler
     */
    public function logTaskActivity(int $taskId, int $userId, string $actionType, string $comment): int
    {
        return $this->execute("
            INSERT INTO task_activities (task_id, user_id, action_type, comment, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ", [$taskId, $userId, $actionType, $comment]);
    }
}
