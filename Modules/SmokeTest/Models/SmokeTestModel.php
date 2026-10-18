<?php
namespace Modules\SmokeTest\Models;

use Core\BaseModel;

class SmokeTestModel extends BaseModel
{
    /**
     * Otonom smoke test sonucunu denetim günlüğüne yazar
     */
    public function logTestRun(string $suiteName, bool $isSuccess, string $summary): int
    {
        $sql = "INSERT INTO task_activities (task_id, user_id, action_type, description, created_at) 
                VALUES (0, 1, 'smoke_test', ?, NOW())";
        $status = $isSuccess ? 'PASS' : 'FAIL';
        $desc = "SmokeTest [{$suiteName}] -> {$status}: {$summary}";
        return $this->execute($sql, [$desc]);
    }
}
