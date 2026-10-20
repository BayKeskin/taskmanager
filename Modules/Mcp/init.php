<?php
// Modules/Mcp/init.php
namespace Modules\Mcp;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('mcp-styles', '/Modules/Mcp/Assets/css/mcp.css');
AssetManager::enqueueJs('mcp-catalog', '/Modules/Mcp/Assets/js/mcp.js');

AssetManager::localizeScript('mcp-catalog', 'MCP_I18N', [
    'badge_required_env'      => \__('Mcp::mcp_badge_required_env'),
    'badge_key_configured'    => \__('Mcp::mcp_badge_key_configured'),
    'badge_ready'             => \__('Mcp::mcp_badge_ready'),
    'btn_details'             => \__('Mcp::mcp_btn_details'),
    'btn_config'              => \__('Mcp::mcp_btn_config'),
    'status_enabled'          => \__('Mcp::mcp_status_enabled'),
    'status_disabled'         => \__('Mcp::mcp_status_disabled'),
    'field_required'          => \__('Mcp::mcp_field_required'),
    'field_optional'          => \__('Mcp::mcp_field_optional'),
    'enter_value'             => \__('Mcp::mcp_enter_value'),
    'active_toast'            => \__('Mcp::mcp_active_toast'),
    'deactive_toast'          => \__('Mcp::mcp_deactive_toast'),
    'no_external_needed_info' => \__('Mcp::mcp_no_external_needed_info'),
    'not_found'               => \__('Mcp::mcp_not_found'),
    'not_found_hint'          => \__('Mcp::mcp_not_found_hint'),
    'sync_success'            => \__('Mcp::mcp_sync_success'),
    'save_success'            => \__('Mcp::mcp_save_success'),
    'select_valid_project'    => \__('Mcp::js_select_valid_project'),
    'catalog_load_fail'       => \__('Mcp::js_catalog_load_fail'),
    'server_error'            => \__('Mcp::js_server_error'),
    'config_title_suffix'     => \__('Mcp::js_config_title_suffix'),
    'sync_notice'             => \__('Mcp::js_sync_notice'),
]);

