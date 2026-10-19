<?php
// Modules/Projects/init.php
namespace Modules\Projects;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('projects-styles', '/taskmanager/Modules/Projects/Assets/css/projects.css');
AssetManager::enqueueJs('projects-js', '/taskmanager/Modules/Projects/Assets/js/projects.js');

AssetManager::localizeScript('projects-js', 'PROJECTS_I18N', [
    'project_created'        => __('Projects::js_project_created'),
    'project_create_failed'  => __('Projects::js_project_create_failed'),
    'server_error'           => __('Projects::js_server_error'),
    'settings_saved'         => __('Projects::js_settings_saved'),
    'update_failed'          => __('Projects::js_update_failed'),
    'webhook_testing'        => __('Projects::js_webhook_testing'),
    'conn_error'             => __('Projects::js_conn_error'),
    'syncing_rules'          => __('Projects::js_syncing_rules'),
    'rules_updated'          => __('Projects::js_rules_updated'),
    'sync_failed'            => __('Projects::js_sync_failed'),
    'refine_prompt'          => __('Projects::js_refine_prompt'),
    'rules_compiled'         => __('Projects::js_rules_compiled'),
    'rules_compile_failed'   => __('Projects::js_rules_compile_failed'),
    'archive_confirm'        => __('Projects::js_archive_confirm'),
    'restore_confirm'        => __('Projects::js_restore_confirm'),
    'action_failed'          => __('Projects::js_action_failed'),
    'delete_confirm'         => __('Projects::js_delete_confirm'),
]);
