<?php

require_once __DIR__ . '/Model.php';

class SettingsModel extends Model
{
    /**
     * Sistem ayarlarını çeker
     */
    public function getSettings(): object
    {
        $settings = $this->db->row_query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
        if (!$settings) {
            $settings = (object)[
                'id' => 1,
                'title' => 'TaskManager Pro',
                'siteurl' => 'http://localhost/taskmanager',
                'employee_can_create_tasks' => 1,
                'employee_can_ai_tasks' => 0,
                'default_language' => 'tr'
            ];
        }
        return $settings;
    }
}
