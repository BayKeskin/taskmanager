<?php
namespace Modules\LiveDeploy\Models;

use Core\BaseModel;

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
}
