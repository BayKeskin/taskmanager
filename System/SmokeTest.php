<?php
/**
 * Legacy SmokeTest Facade
 * Delegates directly to HMVC Module: Modules\SmokeTest\Services\SmokeTestService
 */
require_once __DIR__ . '/System.php';

class SmokeTest
{
    private \Modules\SmokeTest\Services\SmokeTestService $service;

    public function __construct(?string $lang = null)
    {
        $this->service = new \Modules\SmokeTest\Services\SmokeTestService($lang);
    }

    public function run(): array
    {
        return $this->service->run();
    }
}

// Only auto-execute if called directly from CLI
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $lang = in_array('--tr', $argv ?? []) ? 'tr' : 'en';
    $tester = new SmokeTest($lang);
    $report = $tester->run();
    exit($report['failed'] > 0 ? 1 : 0);
}
