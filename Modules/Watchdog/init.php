<?php
/**
 * Watchdog Modülü Başlatıcı (init.php)
 */
use Core\EventManager;
use Modules\Watchdog\Services\WatchdogService;

// Watchdog denetim kancası
EventManager::listen('watchdog.inspect', function() {
    return WatchdogService::runInspection();
});
