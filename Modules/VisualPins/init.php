<?php
use Core\EventManager;
use Core\AssetManager;
use Modules\VisualPins\Controllers\PinController;

// 1. Modül Varlıklarını (JS/CSS) Kuyruğa Ekle
AssetManager::enqueueJs('visual-pins', '/taskmanager/Modules/VisualPins/Assets/js/taskmanager-pin.js');
AssetManager::enqueueCss('visual-pins', '/taskmanager/Modules/VisualPins/Assets/css/visual-pins.css');

AssetManager::localizeScript('visual-pins', 'PIN_I18N', [
    'placeholder_title' => __('VisualPins::js_placeholder_title'),
    'placeholder_desc'  => __('VisualPins::js_placeholder_desc'),
    'cancel'            => __('VisualPins::js_cancel'),
    'selector_label'    => __('VisualPins::js_selector_label'),
    'capturing'         => __('VisualPins::js_capturing'),
    'item_label'        => __('VisualPins::js_item_label'),
    'issue_in_element'  => __('VisualPins::js_issue_in_element'),
    'image_ready'       => __('VisualPins::js_image_ready'),
    'title_required'    => __('VisualPins::js_title_required'),
    'sending'           => __('VisualPins::js_sending'),
    'page_label'        => __('VisualPins::js_page_label'),
    'view_label'        => __('VisualPins::js_view_label'),
    'task_created'      => __('VisualPins::js_task_created'),
    'task_code'         => __('VisualPins::js_task_code'),
    'task_saved'        => __('VisualPins::js_task_saved'),
    'error'             => __('VisualPins::js_error'),
    'create_failed'     => __('VisualPins::js_create_failed'),
    'conn_error'        => __('VisualPins::js_conn_error'),
    'send_btn'          => __('VisualPins::js_send_btn'),
]);

// 2. Görsel Pin Oluşturma Hook Dinleyicisi
EventManager::listen('visual_pins.create', function(array $payload) {
    $controller = new PinController();
    return $controller->handleFeedback($payload);
});
