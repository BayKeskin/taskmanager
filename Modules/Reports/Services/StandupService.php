<?php
namespace Modules\Reports\Services;

use Core\BaseModel;
use Core\Language;

/**
 * StandupService - Günlük standup verilerini toplar, analiz eder ve AI özet bülteni üretir.
 */
class StandupService
{
    /**
     * Son 24 saatteki görev hareketlerini toplayan Standup Aggregation motoru
     */
    public static function getDailyStandupData(?int $projectId = null): array
    {
        $reportModel = new \Modules\Reports\Models\ReportModel();

        // 1. Dün / Son 24 Saatte Tamamlanan veya Teslim Edilen Görevler
        $completedTasks = $reportModel->getStandupCompletedTasks($projectId);

        // 2. Bugün Devam Eden / Çalışılan Görevler
        $inProgressTasks = $reportModel->getStandupInProgressTasks($projectId);

        // 3. Geciken / Riskli Görevler
        $delayedTasks = $reportModel->getStandupDelayedTasks($projectId);

        // 4. Son 24 Saatte Yeni Eklenen Görevler
        $newTasks = $reportModel->getStandupNewTasks($projectId);

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
