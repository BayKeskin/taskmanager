<?php
namespace Modules\Reports\Services;

use Core\BaseModel;
use Core\Language;

/**
 * StandupService - Günlük standup verilerini toplar, analiz eder ve AI özet bülteni üretir.
 */
class StandupService extends BaseModel
{
    /**
     * Son 24 saatteki görev hareketlerini toplayan Standup Aggregation motoru
     */
    public static function getDailyStandupData(?int $projectId = null): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        $projFilterAct = $projectId ? " AND t.project_id = " . (int)$projectId : "";
        $projFilterTask = $projectId ? " AND project_id = " . (int)$projectId : "";

        // 1. Dün / Son 24 Saatte Tamamlanan veya Teslim Edilen Görevler
        $completedTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.status, u.name as assignee_name, a.created_at as activity_time
            FROM task_activities a
            JOIN tasks t ON a.task_id = t.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE a.new_value IN ('completed', 'submitted')
              AND a.created_at >= NOW() - INTERVAL 24 HOUR
              {$projFilterAct}
            GROUP BY t.id
            ORDER BY a.id DESC
        ") ?: [];

        // 2. Bugün Devam Eden / Çalışılan Görevler
        $inProgressTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.deadline, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status = 'in_progress'
              {$projFilterTask}
            ORDER BY t.priority = 'urgent' DESC, t.updated_at DESC
        ") ?: [];

        // 3. Geciken / Riskli Görevler
        $delayedTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, t.deadline, t.status, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status NOT IN ('completed', 'cancelled')
              AND (
                  (t.deadline IS NOT NULL AND t.deadline < CURDATE())
                  OR t.priority = 'urgent'
              )
              {$projFilterTask}
            ORDER BY t.deadline ASC LIMIT 15
        ") ?: [];

        // 4. Son 24 Saatte Yeni Eklenen Görevler
        $newTasks = $db->multi_record_query("
            SELECT t.id, t.task_code, t.title, t.priority, u.name as assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.created_at >= NOW() - INTERVAL 24 HOUR
              {$projFilterTask}
            ORDER BY t.id DESC LIMIT 15
        ") ?: [];

        return [
            'period'           => date('d.m.Y H:i', strtotime('-24 hours')) . ' - ' . date('d.m.Y H:i'),
            'completed'        => $completedTasks,
            'completed_count'  => count($completedTasks),
            'in_progress'      => $inProgressTasks,
            'in_progress_count'=> count($inProgressTasks),
            'delayed'          => $delayedTasks,
            'delayed_count'    => count($delayedTasks),
            'new_tasks'        => $newTasks,
            'new_count'        => count($newTasks)
        ];
    }

    /**
     * Günlük Standup özetini zengin bültene dönüştürür.
     */
    public static function generateAiStandupSummary(?int $projectId = null, ?string $lang = null): array
    {
        $data = self::getDailyStandupData($projectId);
        $dateStr = date('d.m.Y');

        $md = str_replace(':date', $dateStr, __('Reports::standup_header')) . "\n\n";

        $md .= str_replace(':count', (string)$data['completed_count'], __('Reports::standup_completed_sec')) . "\n";

        if (empty($data['completed'])) {
            $md .= __('Reports::standup_no_completed') . "\n\n";
        } else {
            foreach ($data['completed'] as $c) {
                $statusBadge = ($c->status === 'submitted') ? __('Reports::status_submitted') : __('Reports::status_completed');
                $assignee = $c->assignee_name ?: __('Reports::unassigned');
                $md .= "- **{$c->task_code}**: {$c->title} — {$statusBadge} _(" . __('Reports::assignee_label') . " {$assignee})_\n";
            }
            $md .= "\n";
        }

        $md .= str_replace(':count', (string)$data['in_progress_count'], __('Reports::standup_active_sec')) . "\n";

        if (empty($data['in_progress'])) {
            $md .= __('Reports::standup_no_active') . "\n\n";
        } else {
            foreach ($data['in_progress'] as $ip) {
                $assignee = $ip->assignee_name ?: __('Reports::unassigned');
                $md .= "- **{$ip->task_code}**: {$ip->title} _(" . __('Reports::priority_label') . " {$ip->priority}, " . __('Reports::assignee_label') . " {$assignee})_\n";
            }
            $md .= "\n";
        }

        $md .= str_replace(':count', (string)$data['delayed_count'], __('Reports::standup_delayed_sec')) . "\n";

        if (empty($data['delayed'])) {
            $md .= __('Reports::standup_no_delayed') . "\n\n";
        } else {
            foreach ($data['delayed'] as $d) {
                $deadlineTxt = !empty($d->deadline) ? $d->deadline : __('Reports::deadline_urgent');
                $assignee = $d->assignee_name ?: __('Reports::unassigned');
                $md .= "- 🔴 **{$d->task_code}**: {$d->title} (" . __('Reports::deadline_label') . " {$deadlineTxt}, " . __('Reports::assignee_label') . " {$assignee})\n";
            }
            $md .= "\n";
        }

        $md .= __('Reports::standup_advice_sec') . "\n";

        if ($data['delayed_count'] > 0) {
            $md .= __('Reports::standup_advice_delay') . "\n";
        }
        if ($data['in_progress_count'] > 5) {
            $md .= __('Reports::standup_advice_wip') . "\n";
        } else {
            $md .= __('Reports::standup_advice_smooth') . "\n";
        }

        return [
            'success'   => true,
            'date'      => date('d.m.Y'),
            'summary'   => $md,
            'raw_data'  => $data
        ];
    }

    /**
     * Günlük Standup E-posta bülteni gönderimi
     */
    public static function sendDailyStandupEmail(string $recipientEmail, array $standupData): bool
    {
        $subject = str_replace(':date', date('d.m.Y'), __('Reports::standup_email_subject'));
        $footerText = __('Reports::standup_email_footer');

        $htmlBody = "<h2>" . htmlspecialchars($subject) . "</h2>";
        $htmlBody .= "<div class=\"standup-email-content\">";
        $htmlBody .= nl2br(htmlspecialchars($standupData['summary'] ?? ''));
        $htmlBody .= "</div><p class=\"standup-email-footer\">" . htmlspecialchars($footerText) . "</p>";

        if (class_exists('Modules\\Mailer\\Services\\MailerService')) {
            return \Modules\Mailer\Services\MailerService::sendHtmlMail($recipientEmail, $subject, $htmlBody);
        }

        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: TaskManager AI <noreply@taskmanager.local>\r\n";
        return @mail($recipientEmail, $subject, $htmlBody, $headers);
    }
}
