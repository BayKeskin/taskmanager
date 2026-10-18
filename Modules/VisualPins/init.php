<?php
use Core\EventManager;
use Core\AssetManager;
use Modules\VisualPins\Controllers\PinController;

// 1. Modül Varlıklarını (JS/CSS) Kuyruğa Ekle
AssetManager::enqueueJs('visual-pins', '/taskmanager/Modules/VisualPins/Assets/js/visual-pins.js');
AssetManager::enqueueCss('visual-pins', '/taskmanager/Modules/VisualPins/Assets/css/visual-pins.css');

// 2. Görsel Pin Oluşturma Hook Dinleyicisi
EventManager::listen('visual_pins.create', function(array $payload) {
    $controller = new PinController();
    return $controller->handleFeedback($payload);
});
