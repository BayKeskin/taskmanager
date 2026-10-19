<?php
namespace Modules\SmokeTest\Services;

use Modules\SmokeTest\Models\SmokeTestModel;

class SmokeTestService
{
    private array $results = [];
    private SmokeTestModel $model;
    private string $lang = 'en';

    public function __construct(?string $lang = null)
    {
        $this->model = new SmokeTestModel();
        $this->lang = $lang ?? (class_exists('Language') && \Language::getCurrentLanguage() === 'tr' ? 'tr' : 'en');
    }

    private function assert(string $testName, bool $condition, string $details = ''): void
    {
        $status = $condition ? 'PASS' : 'FAIL';
        $this->results[] = [
            'name' => $testName,
            'passed' => $condition,
            'details' => $details
        ];
        echo "[{$status}] {$testName}" . ($details ? " -> {$details}" : "") . PHP_EOL;
    }

    public function run(): array
    {
        echo "========================================================\n";
        echo __('SmokeTest::starting_suite') . "\n";
        echo "========================================================\n\n";

        // 1. Database and Critical Tables Verification
        $tables = ['projects', 'tasks', 'users', 'docs', 'chat_messages', 'live_events', 'task_checklists', 'task_comments'];
        foreach ($tables as $tbl) {
            $exists = $this->model->checkTableExists($tbl);
            $testTitle = str_replace(':table', $tbl, __('SmokeTest::table_exists'));
            $detailMsg = $exists 
                ? __('SmokeTest::table_present') 
                : __('SmokeTest::table_missing');
            $this->assert($testTitle, $exists, $detailMsg);
        }

        // 2. Active 24 HMVC Modules Verification
        $loadedModules = \Core\ModuleLoader::getLoadedModules();
        $modCount = count($loadedModules);
        $this->assert(__('SmokeTest::cat_modules'), $modCount >= 24, "{$modCount}/24 Active Modules Loaded");

        // 3. Multilingual Dictionaries Verification (TR, EN, DE)
        $langCheckOk = true;
        $checkedLocales = ['tr', 'en', 'de'];
        foreach ($checkedLocales as $loc) {
            if (!\Language::isLocaleAvailable($loc)) {
                $langCheckOk = false;
                break;
            }
        }
        $this->assert(__('SmokeTest::cat_languages'), $langCheckOk, "TR, EN, DE Verified Active");

        // 4. REST API & Endpoints Verification
        $restApiController = class_exists('\Modules\RestApi\Controllers\RestApiController');
        $this->assert(__('SmokeTest::cat_api'), $restApiController, "RestApiController Active & Ready");

        // 5. Standup Aggregation & Summary Verification
        $standup = \Modules\Reports\Services\StandupService::generateAiStandupSummary(1, $this->lang);
        $standupTitle = __('SmokeTest::ai_standup');
        $standupDetails = str_replace(
            [':date', ':completed'], 
            [$standup['date'] ?? '', (string)($standup['raw_data']['completed_count'] ?? 0)], 
            __('SmokeTest::ai_standup_details')
        );
        $this->assert($standupTitle, !empty($standup['success']) && !empty($standup['summary']), $standupDetails);

        // 6. Workspaces.json Dynamic Sync Verification
        $wsFile = dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/workspaces.json';
        $wsExists = file_exists($wsFile);
        $wsTitle = __('SmokeTest::ws_config_file');
        $this->assert($wsTitle, $wsExists, str_replace(':path', $wsFile, __('SmokeTest::ws_path_prefix')));
        if ($wsExists) {
            $wsMap = json_decode(file_get_contents($wsFile), true) ?: [];
            $wsValidTitle = __('SmokeTest::ws_validity');
            $wsValidDetails = str_replace(':mapping', json_encode($wsMap['01'] ?? []), __('SmokeTest::ws_mapping'));
            $this->assert($wsValidTitle, is_array($wsMap) && isset($wsMap['01']), $wsValidDetails);
        }

        // 7. Realtime SSE Live Event Broadcast Verification
        $broadcast = \Modules\SSE\Services\SseBroadcaster::broadcast('test_ping', 1, null, 1, ['time' => time(), 'test' => true]);
        $sseTitle = __('SmokeTest::sse_broadcast');
        $sseDetails = __('SmokeTest::sse_recorded');
        $this->assert($sseTitle, $broadcast, $sseDetails);

        echo "\n========================================================\n";
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $total = count($this->results);
        echo str_replace([':passed', ':total'], [(string)$passed, (string)$total], __('SmokeTest::summary_results')) . "\n";
        echo "========================================================\n";

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'results' => $this->results
        ];
    }

}
