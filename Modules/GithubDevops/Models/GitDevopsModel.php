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
}
