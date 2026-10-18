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
        $isTr = ($lang === 'tr') || (!$lang && class_exists('Core\\Language') && Language::current() === 'tr');

        $md = $isTr 
            ? "# 📋 Günlük AI Standup & Görev Özeti (" . date('d.m.Y') . ")\n\n"
            : "# 📋 Daily AI Standup & Task Summary (" . date('d.m.Y') . ")\n\n";

        $md .= $isTr 
            ? "### 🎯 1. Son 24 Saatte Tamamlanan & Teslim Edilenler ({$data['completed_count']})\n"
            : "### 🎯 1. Completed & Submitted in the Last 24 Hours ({$data['completed_count']})\n";

        if (empty($data['completed'])) {
            $md .= $isTr 
                ? "_Son 24 saatte tamamlanan veya teslim edilen görev kaydedilmedi._\n\n"
                : "_No tasks were completed or submitted in the last 24 hours._\n\n";
        } else {
            foreach ($data['completed'] as $c) {
                if ($isTr) {
                    $statusBadge = ($c->status === 'submitted') ? '[TESLİM EDİLDİ]' : '[TAMAMLANDI]';
                    $assignee = $c->assignee_name ?: 'Atanmamış';
                    $md .= "- **{$c->task_code}**: {$c->title} — {$statusBadge} _(Sorumlu: {$assignee})_\n";
                } else {
                    $statusBadge = ($c->status === 'submitted') ? '[SUBMITTED]' : '[COMPLETED]';
                    $assignee = $c->assignee_name ?: 'Unassigned';
                    $md .= "- **{$c->task_code}**: {$c->title} — {$statusBadge} _(Assignee: {$assignee})_\n";
                }
            }
            $md .= "\n";
        }

        $md .= $isTr 
            ? "### ⚡ 2. Aktif Çalışılan Görevler ({$data['in_progress_count']})\n"
            : "### ⚡ 2. Active Tasks in Progress ({$data['in_progress_count']})\n";

        if (empty($data['in_progress'])) {
            $md .= $isTr 
                ? "_Şu anda 'Çalışılıyor' durumunda bekleyen aktif görev yok._\n\n"
                : "_There are currently no active tasks in progress._\n\n";
        } else {
            foreach ($data['in_progress'] as $ip) {
                if ($isTr) {
                    $assignee = $ip->assignee_name ?: 'Atanmamış';
                    $md .= "- **{$ip->task_code}**: {$ip->title} _(Öncelik: {$ip->priority}, Sorumlu: {$assignee})_\n";
                } else {
                    $assignee = $ip->assignee_name ?: 'Unassigned';
                    $md .= "- **{$ip->task_code}**: {$ip->title} _(Priority: {$ip->priority}, Assignee: {$assignee})_\n";
                }
            }
            $md .= "\n";
        }

        $md .= $isTr 
            ? "### ⚠️ 3. Dikkat Gerektiren Riskli / Geciken Görevler ({$data['delayed_count']})\n"
            : "### ⚠️ 3. Risky / Overdue Tasks Requiring Attention ({$data['delayed_count']})\n";

        if (empty($data['delayed'])) {
            $md .= $isTr 
                ? "✅ _Tebrikler! Takvimde gecikmeye düşen veya kritik riskli görev bulunmuyor._\n\n"
                : "✅ _Great! No overdue or critically risky tasks on the schedule._\n\n";
        } else {
            foreach ($data['delayed'] as $d) {
                if ($isTr) {
                    $deadlineTxt = !empty($d->deadline) ? $d->deadline : 'Tarihsiz Acil';
                    $assignee = $d->assignee_name ?: 'Atanmamış';
                    $md .= "- 🔴 **{$d->task_code}**: {$d->title} (Bitiş: {$deadlineTxt}, Sorumlu: {$assignee})\n";
                } else {
                    $deadlineTxt = !empty($d->deadline) ? $d->deadline : 'Urgent / No Due Date';
                    $assignee = $d->assignee_name ?: 'Unassigned';
                    $md .= "- 🔴 **{$d->task_code}**: {$d->title} (Due: {$deadlineTxt}, Assignee: {$assignee})\n";
                }
            }
            $md .= "\n";
        }

        $md .= $isTr 
            ? "### 💡 4. Scrum Master & AI Tavsiyeleri\n"
            : "### 💡 4. Scrum Master & AI Recommendations\n";

        if ($data['delayed_count'] > 0) {
            $md .= $isTr 
                ? "- ⚠️ Geciken görevler için takım içi kaynak transferi önerilir.\n"
                : "- ⚠️ Internal team resource reallocation is recommended for delayed tasks.\n";
        }
        if ($data['in_progress_count'] > 5) {
            $md .= $isTr 
                ? "- 📌 WIP (Work in Progress) limiti yüksek görünüyor; tamamlamaya odaklanılması önerilir.\n"
                : "- 📌 WIP (Work in Progress) limit appears high; focus on task completion is recommended.\n";
        } else {
            $md .= $isTr 
                ? "- 🚀 Ekip akışı sağlıklı, sprint hedeflerine uyum yüksek seyrediyor.\n"
                : "- 🚀 Team workflow is healthy and sprint target alignment is strong.\n";
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
        $isTr = (class_exists('Core\\Language') && Language::current() === 'tr');
        $subject = $isTr 
            ? ("📋 TaskManager Günlük AI Standup Bülteni - " . date('d.m.Y'))
            : ("📋 TaskManager Daily AI Standup Digest - " . date('d.m.Y'));

        $footerText = $isTr 
            ? "Bu bülten TaskManager Otonom AI Asistanı tarafından otomatik oluşturulmuştur."
            : "This digest was automatically generated by TaskManager Autonomous AI Assistant.";

        $htmlBody = "<h2>" . htmlspecialchars($subject) . "</h2>";
        $htmlBody .= "<div style='font-family:sans-serif; line-height:1.6; color:#333; background:#f9f9f9; padding:20px; border-radius:8px;'>";
        $htmlBody .= nl2br(htmlspecialchars($standupData['summary'] ?? ''));
        $htmlBody .= "</div><p style='font-size:12px; color:#888;'>" . htmlspecialchars($footerText) . "</p>";

        if (class_exists('Modules\\Mailer\\Services\\MailerService')) {
            return \Modules\Mailer\Services\MailerService::sendHtmlMail($recipientEmail, $subject, $htmlBody);
        }

        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: TaskManager AI <noreply@taskmanager.local>\r\n";
        return @mail($recipientEmail, $subject, $htmlBody, $headers);
    }
}
