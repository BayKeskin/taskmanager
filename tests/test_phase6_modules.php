<?php
require_once __DIR__ . '/../Core/System.php';

use Core\ModuleLoader;
use Modules\Watchdog\Services\WatchdogService;
use Modules\SSE\Services\SseBroadcaster;

echo "=== AUTONOMOUS TEST: PHASE 6 (TELEGRAM, WATCHDOG, SSE) ===" . PHP_EOL;

// 1. Modül Yüklenme Kontrolü
assert(ModuleLoader::isModuleActive('telegram'), "Module 'telegram' must be active.");
assert(ModuleLoader::isModuleActive('watchdog'), "Module 'watchdog' must be active.");
assert(ModuleLoader::isModuleActive('sse'), "Module 'sse' must be active.");
echo "[PASS] All 3 modules ('telegram' #18, 'watchdog' #07, 'sse' #12) active in ModuleLoader." . PHP_EOL;

// 2. SseBroadcaster Test
$broadcastOk = SseBroadcaster::broadcast('test_event', 1, 100, 1, ['msg' => 'Realtime SSE Ping']);
assert($broadcastOk === true, "SseBroadcaster failed to write to live_events table.");
echo "[PASS] SseBroadcaster successfully wrote live_event to database." . PHP_EOL;

// 3. WatchdogService Test
$watchdogResult = WatchdogService::runInspection();
assert($watchdogResult['success'] === true, "WatchdogService inspection failed.");
echo "[PASS] WatchdogService executed inspection on active projects. Result: " . json_encode($watchdogResult['projects'] ?? []) . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $tMsg = __('Telegram::notify_created');
    $wMsg = __('Watchdog::ensured_running');
    $sMsg = __('SSE::connected');
    echo "  [PASS] Lang ({$loc}): Telegram => '{$tMsg}' | Watchdog => '{$wMsg}' | SSE => '{$sMsg}'" . PHP_EOL;
    assert(!empty($tMsg) && !str_starts_with($tMsg, 'Telegram::'), "Telegram translation missing for {$loc}");
    assert(!empty($wMsg) && !str_starts_with($wMsg, 'Watchdog::'), "Watchdog translation missing for {$loc}");
    assert(!empty($sMsg) && !str_starts_with($sMsg, 'SSE::'), "SSE translation missing for {$loc}");
}

echo "=== ALL PHASE 6 TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
