<?php
/**
 * SSE Modülü Başlatıcı (init.php)
 */
use Core\EventManager;
use Core\AssetManager;
use Modules\SSE\Services\SseBroadcaster;

// 1. Canlı SSE Senkronizasyon İstemci Kütüphanesini Kuyruğa Al
AssetManager::enqueueJs('sse-realtime', '/taskmanager/Modules/SSE/Assets/js/realtime.js');
AssetManager::localizeScript('sse-realtime', 'SSE_I18N', [
    'unsupported_browser'  => __('SSE::unsupported_browser'),
    'team_member'          => __('SSE::team_member'),
    'a_member'             => __('SSE::a_member'),
    'sent_private_message' => __('SSE::sent_private_message'),
    'attached_file'        => __('SSE::attached_file'),
    'go_to_chat'           => __('SSE::go_to_chat'),
    'new_attachment_added' => __('SSE::new_attachment_added'),
    'status_active'        => __('SSE::status_active'),
    'status_reconnecting'  => __('SSE::status_reconnecting'),
    'text_live'            => __('SSE::text_live'),
    'text_connecting'      => __('SSE::text_connecting'),
    'task'                 => __('SSE::task'),
    'team'                 => __('SSE::team'),
    'status_changed_to'    => __('SSE::status_changed_to'),
    'task_submitted_msg'   => __('SSE::task_submitted_msg'),
    'task_deleted_msg'     => __('SSE::task_deleted_msg'),
    'new_task_created'     => __('SSE::new_task_created'),
    'tasks_suffix'         => __('SSE::tasks_suffix')
]);

// Canlı SSE yayını kancası
EventManager::listen('live_event.broadcast', function(array $data) {
    return SseBroadcaster::broadcast(
        $data['event_type'],
        $data['project_id'] ?? null,
        $data['task_id'] ?? null,
        $data['user_id'] ?? null,
        $data['payload'] ?? []
    );
});
