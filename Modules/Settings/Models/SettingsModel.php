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
            'siteurl'                   => function_exists('siteUrl') ? siteUrl() : 'http://localhost',
            'employee_can_create_tasks' => 1,
            'employee_can_ai_tasks'     => 0,
            'default_language'          => 'tr'
        ];
    }

    public function updateSettings(array $data): bool
    {
        $sql = "UPDATE settings 
            SET title = ?, siteurl = ?, employee_can_create_tasks = ?, employee_can_ai_tasks = ?, default_language = ?,
                active_ai_provider = ?, default_ai_model = ?, openai_api_key = ?, anthropic_api_key = ?, gemini_api_key = ?, deepseek_api_key = ?, ai_system_prompt_prefix = ?,
                telegram_bot_token = ?, telegram_chat_id = ?, telegram_notifications_enabled = ?, slack_webhook_url = ?, discord_webhook_url = ?,
                smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?, smtp_pass = ?, smtp_from_email = ?, smtp_from_name = ?,
                bugherd_api_key = ?, bugherd_project_id = ?, github_token = ?, github_repo = ?,
                session_timeout_minutes = ?, max_file_upload_mb = ?, allowed_file_types = ?
            WHERE id = 1";

        $defaultSiteUrl = function_exists('siteUrl') ? siteUrl() : 'http://localhost';
        return $this->db->exec_query($sql, [
            $data['title'] ?? 'TaskManager Pro',
            $data['siteurl'] ?? $defaultSiteUrl,
            $data['employee_can_create_tasks'] ?? 1,
            $data['employee_can_ai_tasks'] ?? 0,
            $data['default_language'] ?? 'tr',
            $data['active_ai_provider'] ?? 'gemini',
            $data['default_ai_model'] ?? 'gemini-1.5-pro',
            $data['openai_api_key'] ?? '',
            $data['anthropic_api_key'] ?? '',
            $data['gemini_api_key'] ?? '',
            $data['deepseek_api_key'] ?? '',
            $data['ai_system_prompt_prefix'] ?? '',
            $data['telegram_bot_token'] ?? '',
            $data['telegram_chat_id'] ?? '',
            $data['telegram_notifications_enabled'] ?? 1,
            $data['slack_webhook_url'] ?? '',
            $data['discord_webhook_url'] ?? '',
            $data['smtp_host'] ?? '',
            $data['smtp_port'] ?? 587,
            $data['smtp_secure'] ?? 'tls',
            $data['smtp_user'] ?? '',
            $data['smtp_pass'] ?? '',
            $data['smtp_from_email'] ?? '',
            $data['smtp_from_name'] ?? 'TaskManager',
            $data['bugherd_api_key'] ?? '',
            $data['bugherd_project_id'] ?? '',
            $data['github_token'] ?? '',
            $data['github_repo'] ?? '',
            $data['session_timeout_minutes'] ?? 120,
            $data['max_file_upload_mb'] ?? 25,
            $data['allowed_file_types'] ?? 'jpg,png,pdf,zip,docx,xlsx,txt'
        ]) !== false;
    }

    public function getSlackWebhookUrl(): string
    {
        $s = $this->db->row_query("SELECT slack_webhook_url FROM settings WHERE id = 1");
        return trim($s->slack_webhook_url ?? '');
    }

    public function getDiscordWebhookUrl(): string
    {
        $s = $this->db->row_query("SELECT discord_webhook_url FROM settings WHERE id = 1");
        return trim($s->discord_webhook_url ?? '');
    }
}
