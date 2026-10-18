<?php
namespace Modules\Watchdog\Models;

use Core\BaseModel;

class WatchdogModel extends BaseModel
{
    /**
     * Bekçi kurtarma ve sağlık denetim kayıtlarını çeker/ekler
     */
    public function logRecovery(string $serviceName, string $status, string $details): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, 1, 'watchdog_recovery', ?, NOW())";
        $desc = "Watchdog [{$serviceName}] -> {$status}: {$details}";
        return $this->execute($sql, [$desc]);
    }
}
