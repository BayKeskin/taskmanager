<?php
use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('aiarchitect-styles', '/taskmanager/Modules/AiArchitect/Assets/css/aiarchitect.css');
AssetManager::enqueueJs('aiarchitect-js', '/taskmanager/Modules/AiArchitect/Assets/js/aiarchitect.js');

AssetManager::localizeScript('aiarchitect-js', 'AI_ARCHITECT_I18N', [
    'empty_brief_error'        => __('AiArchitect::empty_brief_error'),
    'ready_for_analysis'       => __('AiArchitect::ready_for_analysis'),
    'analyzing_btn'            => __('AiArchitect::analyzing_btn'),
    'server_http_error'        => __('AiArchitect::server_http_error'),
    'invalid_json_error'       => __('AiArchitect::invalid_json_error'),
    'default_module_title'     => __('AiArchitect::default_module_title'),
    'default_subtask_desc'     => __('AiArchitect::default_subtask_desc'),
    'decompose_done_toast'     => __('AiArchitect::decompose_done_toast'),
    'decompose_failed_toast'   => __('AiArchitect::decompose_failed_toast'),
    'conn_error_toast'         => __('AiArchitect::conn_error_toast'),
    'default_admin_name'       => __('AiArchitect::default_admin_name'),
    'subtask_moved_toast'      => __('AiArchitect::subtask_moved_toast'),
    'drag_handle_title'        => __('AiArchitect::drag_handle_title'),
    'ai_arch_subtask_title_ph' => __('AiArchitect::ai_arch_subtask_title_ph'),
    'toggle_how_to_title'      => __('AiArchitect::toggle_how_to_title'),
    'ai_arch_remove_subtask'   => __('AiArchitect::ai_arch_remove_subtask'),
    'ai_arch_subtask_details_ph' => __('AiArchitect::ai_arch_subtask_details_ph'),
    'ai_arch_remove_module'    => __('AiArchitect::ai_arch_remove_module'),
    'ai_arch_epic_desc_ph'     => __('AiArchitect::ai_arch_epic_desc_ph'),
    'ai_arch_empty_epic'       => __('AiArchitect::ai_arch_empty_epic'),
    'img_note_badge'           => __('AiArchitect::img_note_badge'),
    'tree_summary_badge'       => __('AiArchitect::tree_summary_badge'),
    'new_epic_default_title'   => __('AiArchitect::new_epic_default_title'),
    'new_epic_default_desc'    => __('AiArchitect::new_epic_default_desc'),
    'new_subtask_default_title'=> __('AiArchitect::new_subtask_default_title'),
    'new_subtask_default_desc' => __('AiArchitect::new_subtask_default_desc'),
    'no_module_to_save'        => __('AiArchitect::no_module_to_save'),
    'processing_btn'           => __('AiArchitect::processing_btn'),
]);


// Modül İçi Yardımcı Fonksiyon
if (!function_exists('ai_architect')) {
    function ai_architect(): \Modules\AiArchitect\Services\GeminiClient {
        static $instance = null;
        if ($instance === null) {
            $instance = new \Modules\AiArchitect\Services\GeminiClient();
        }
        return $instance;
    }
}
