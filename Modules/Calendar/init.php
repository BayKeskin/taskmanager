<?php
/**
 * Calendar Modülü Başlatıcı (init.php)
 * Kancaları ve Varlıkları Kaydeder.
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\Calendar\Controllers\CalendarController;

// 1. Varlıkları (CSS / JS) Kuyruğa Al
AssetManager::enqueueCss('calendar-gantt', '/taskmanager/Modules/Calendar/Assets/css/gantt.css');
AssetManager::enqueueCss('calendar-styles', '/taskmanager/Modules/Calendar/Assets/css/calendar.css');
AssetManager::enqueueJs('calendar-gantt-drag', '/taskmanager/Modules/Calendar/Assets/js/gantt-drag.js');
AssetManager::enqueueJs('calendar-js', '/taskmanager/Modules/Calendar/Assets/js/calendar.js');

// 2. JavaScript Dil Dizgilerini Aktar
AssetManager::localizeScript('calendar-js', 'CALENDAR_DATA', [
    'lang' => [
        'drag_success'  => __('Calendar::gantt_drag_success'),
        'update_failed' => __('Calendar::update_failed'),
        'server_error'  => __('Calendar::server_error'),
    ]
]);

// 3. Takvim Verisi Çekme Kancası
EventManager::listen('calendar.get_data', function(array $params) {
    $controller = new CalendarController();
    return $controller->index(
        $params['year'] ?? null,
        $params['month'] ?? null,
        $params['project_id'] ?? null,
        $params['scale'] ?? 'month'
    );
});
