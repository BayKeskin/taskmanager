<?php
namespace Modules\SmokeTest\Services;

use Helper;

class SmokeTestService
{
    private array $results = [];
    private $db;
    private string $lang = 'en';

    public function __construct(?string $lang = null)
    {
        $this->db = $GLOBALS['db'] ?? null;
        $this->lang = $lang ?? (class_exists('Language') && \Language::getCurrentLanguage() === 'tr' ? 'tr' : 'en');
    }

    private function assert(string $testName, bool $condition, string $details = ''): void
    {
        $status = $condition ? '✅ PASS' : '❌ FAIL';
        $this->results[] = [
            'name' => $testName,
            'passed' => $condition,
            'details' => $details
        ];
        echo "{$status} | {$testName}" . ($details ? " -> {$details}" : "") . PHP_EOL;
    }

    public function run(): array
    {
        $isTr = ($this->lang === 'tr');

        echo "========================================================\n";
        echo $isTr 
            ? "🧪 TaskManager Otomatik Doğrulama ve QA Test Paketi Başlatılıyor\n"
            : "🧪 TaskManager Autonomous Smoke Test & QA Suite Starting\n";
        echo "========================================================\n\n";

        if (!$this->db) {
            echo "❌ Database connection not found.\n";
            return ['total' => 0, 'passed' => 0, 'failed' => 1, 'results' => []];
        }

        // 1. Database and Critical Tables Verification
        $tables = ['projects', 'tasks', 'users', 'docs', 'chat_messages', 'live_events', 'task_checklists', 'task_comments'];
        foreach ($tables as $tbl) {
            $exists = (bool)$this->db->row_query("SHOW TABLES LIKE '{$tbl}'");
            $testTitle = $isTr ? "Tablo Varlığı: {$tbl}" : "Table Existence: {$tbl}";
            $detailMsg = $exists 
                ? ($isTr ? 'Mevcut' : 'Exists') 
                : ($isTr ? 'Eksik!' : 'Missing!');
            $this->assert($testTitle, $exists, $detailMsg);
        }

        // 2. Client Portal Role & Isolation Verification
        $clientUser = $this->db->row_query("SELECT * FROM users WHERE role = 'client' LIMIT 1");
        $clientTitle = $isTr ? "Müşteri Hesabı Mevcudiyeti" : "Client Account Existence";
        $clientDetails = $clientUser 
            ? ($isTr ? "Bulundu: {$clientUser->email} (Proje ID: {$clientUser->client_project_id})" : "Found: {$clientUser->email} (Project ID: {$clientUser->client_project_id})") 
            : ($isTr ? "Client kullanıcısı yok!" : "No client user found!");
        $this->assert($clientTitle, !empty($clientUser), $clientDetails);

        if ($clientUser) {
            $allowedProjectTasks = $this->db->multi_record_query("SELECT id FROM tasks WHERE project_id = ?", [$clientUser->client_project_id]) ?: [];
            $accessTitle = $isTr ? "Müşteri Proje Görev Erişimi" : "Client Project Task Access";
            $accessDetails = $isTr 
                ? (count($allowedProjectTasks) . " görev erişilebilir")
                : (count($allowedProjectTasks) . " tasks accessible");
            $this->assert($accessTitle, count($allowedProjectTasks) > 0, $accessDetails);
        }

        // 3. Realtime Chat Persistence Verification
        $testMsgText = "Autonomous QA Test Message: " . time();
        $insId = $this->db->insert_return_count("chat_messages", ["user_id", "message"], [1, $testMsgText]);
        $chatInsTitle = $isTr ? "Chat Mesajı Kaydı" : "Chat Message Insert";
        $this->assert($chatInsTitle, $insId > 0, "ID: {$insId}");

        $retrieved = $this->db->row_query("SELECT * FROM chat_messages WHERE id = ?", [$insId]);
        $chatReadTitle = $isTr ? "Chat Mesajı Okuma" : "Chat Message Retrieval";
        $chatReadDetails = $isTr ? "İçerik doğrulandı: ID #{$insId}" : "Content verified: ID #{$insId}";
        $this->assert($chatReadTitle, $retrieved && $retrieved->message === $testMsgText, $chatReadDetails);

        // 4. Standup Aggregation & Summary Verification
        $standup = Helper::generateAiStandupSummary(1, $this->lang);
        $standupTitle = $isTr ? "AI Standup Rapor Üretimi" : "AI Standup Report Generation";
        $standupDetails = $isTr 
            ? "Tarih: {$standup['date']}, Tamamlanan: {$standup['raw_data']['completed_count']}"
            : "Date: {$standup['date']}, Completed: {$standup['raw_data']['completed_count']}";
        $this->assert($standupTitle, !empty($standup['success']) && !empty($standup['summary']), $standupDetails);

        // 5. Workspaces.json Dynamic Sync Verification
        $wsFile = dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/workspaces.json';
        $wsExists = file_exists($wsFile);
        $wsTitle = $isTr ? "Workspaces Yapılandırma Dosyası" : "Workspaces Configuration File";
        $this->assert($wsTitle, $wsExists, ($isTr ? "Yol: " : "Path: ") . $wsFile);
        if ($wsExists) {
            $wsMap = json_decode(file_get_contents($wsFile), true) ?: [];
            $wsValidTitle = $isTr ? "Workspaces JSON Geçerliliği" : "Workspaces JSON Validity";
            $wsValidDetails = ($isTr ? "Proje 01 eşleşmesi: " : "Project 01 mapping: ") . json_encode($wsMap['01'] ?? []);
            $this->assert($wsValidTitle, is_array($wsMap) && isset($wsMap['01']), $wsValidDetails);
        }

        // 6. Realtime SSE Live Event Broadcast Verification
        $broadcast = Helper::broadcastLiveEvent('test_ping', 1, null, 1, ['time' => time(), 'test' => true]);
        $sseTitle = $isTr ? "SSE Canlı Yayın (broadcastLiveEvent)" : "SSE Live Broadcast (broadcastLiveEvent)";
        $sseDetails = $isTr ? "live_events tablosuna kaydedildi" : "Recorded to live_events table";
        $this->assert($sseTitle, $broadcast, $sseDetails);

        echo "\n========================================================\n";
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $total = count($this->results);
        echo $isTr 
            ? "📊 Sonuç: {$passed} / {$total} Test Başarılı!\n"
            : "📊 Results: {$passed} / {$total} Tests Passed!\n";
        echo "========================================================\n";

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'results' => $this->results
        ];
    }
}
