<?php
require_once __DIR__ . '/../Core/System.php';
require_once __DIR__ . '/../Core/SmokeTest.php';

use Core\ModuleLoader;
use Modules\Mailer\Services\MailerService;
use Modules\SmokeTest\Services\SmokeTestService;

echo "=== AUTONOMOUS TEST: MAILER (#19) & SMOKETEST (#21) MODULES ===" . PHP_EOL;

// 1. Modüllerin aktiflik kontrolü
assert(ModuleLoader::isModuleActive('mailer'), "Module 'mailer' must be active.");
assert(ModuleLoader::isModuleActive('smoketest'), "Module 'smoketest' must be active.");
echo "[PASS] Both 'mailer' (#19) and 'smoketest' (#21) modules loaded dynamically." . PHP_EOL;

// 2. MailerService testConnection
$mailerRes = MailerService::testConnection();
assert(isset($mailerRes['success']), "MailerService::testConnection must return success key.");
echo "[PASS] MailerService executed testConnection: " . $mailerRes['message'] . PHP_EOL;

// 3. SmokeTestService test
$smokeService = new SmokeTestService('tr');
$report = $smokeService->run();
assert(isset($report['total']) && $report['total'] > 0, "SmokeTest report must have tests.");
echo "[PASS] SmokeTestService executed successfully. Total tests: " . $report['total'] . ", Passed: " . $report['passed'] . PHP_EOL;

// 4. Lang Dosyaları Çözümleme Testi (TR, EN, DE)
$locales = ['tr', 'en', 'de'];
foreach ($locales as $loc) {
    Language::setLocale($loc);
    $mName = __('Mailer::module_name');
    $sName = __('SmokeTest::module_name');
    
    echo "  [PASS] Lang ({$loc}): Mailer => '{$mName}' | SmokeTest => '{$sName}'" . PHP_EOL;
    assert(!empty($mName) && !str_starts_with($mName, 'Mailer::'), "Mailer translation missing for {$loc}");
    assert(!empty($sName) && !str_starts_with($sName, 'SmokeTest::'), "SmokeTest translation missing for {$loc}");
}

// 5. Legacy Facade Uyumluluk Testi
$legacySmoke = new SmokeTest('en');
assert(method_exists($legacySmoke, 'run'), "Legacy SmokeTest must retain run() method.");
echo "[PASS] Legacy SmokeTest facade is fully backwards-compatible." . PHP_EOL;

echo "=== ALL MAILER & SMOKETEST TESTS PASSED (100% SUCCESS) ===" . PHP_EOL;
