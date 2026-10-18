<?php
namespace Modules\Settings\Models;

use Core\BaseModel;

class SettingsModel extends BaseModel
{
    /**
     * Sistem ayarlarını çeker
     */
    public function getSettings(): object
    {
        $rows = $this->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        if (!empty($rows)) {
            return (object)$rows[0];
        }

        return (object)[
            'id'                        => 1,
            'title'                     => 'TaskManager Pro',
            'siteurl'                   => 'http://localhost/taskmanager',
            'employee_can_create_tasks' => 1,
            'employee_can_ai_tasks'     => 0,
            'default_language'          => 'tr'
        ];
    }
}
