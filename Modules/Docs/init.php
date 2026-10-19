<?php
// Modules/Docs/init.php
namespace Modules\Docs;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('quill-snow', '/taskmanager/Modules/Docs/Assets/css/quill.snow.css');
AssetManager::enqueueCss('docs-styles', '/taskmanager/Modules/Docs/Assets/css/docs.css');
AssetManager::enqueueJs('marked-js', '/taskmanager/Modules/Docs/Assets/js/marked.min.js');
AssetManager::enqueueJs('quill-js', '/taskmanager/Modules/Docs/Assets/js/quill.min.js');
AssetManager::enqueueJs('docs-js', '/taskmanager/Modules/Docs/Assets/js/docs.js');

// 2. İstemci Tarafı Dil Dizgilerini Aktar
AssetManager::localizeScript('docs-js', 'DOCS_I18N', [
    'docs_width_standard'     => __('Docs::docs_width_standard'),
    'docs_width_wide'         => __('Docs::docs_width_wide'),
    'docs_editor_placeholder' => __('Docs::docs_editor_placeholder'),
    'docs_save_status_saved'  => __('Docs::docs_save_status_saved'),
    'docs_save_status_unsaved'=> __('Docs::docs_save_status_unsaved'),
    'docs_save_status_saving' => __('Docs::docs_save_status_saving'),
    'docs_toast_load_err'     => __('Docs::docs_toast_load_err'),
    'docs_toast_conn_err'     => __('Docs::docs_toast_conn_err'),
    'docs_toast_title_req'    => __('Docs::docs_toast_title_req'),
    'docs_toast_save_success' => __('Docs::docs_toast_save_success'),
    'docs_toast_save_fail'    => __('Docs::docs_toast_save_fail'),
    'docs_create_prompt'      => __('Docs::docs_create_prompt'),
    'docs_create_default'     => __('Docs::docs_create_default'),
    'docs_toast_created'      => __('Docs::docs_toast_created'),
    'docs_toast_create_fail'  => __('Docs::docs_toast_create_fail'),
    'docs_confirm_delete'     => __('Docs::docs_confirm_delete'),
    'docs_toast_deleted'      => __('Docs::docs_toast_deleted'),
    'docs_toast_delete_fail'  => __('Docs::docs_toast_delete_fail'),
    'docs_toast_select_first' => __('Docs::docs_toast_select_first'),
    'docs_toast_uploading'    => __('Docs::docs_toast_uploading'),
    'docs_toast_upload_success'=> __('Docs::docs_toast_upload_success'),
    'docs_toast_upload_fail'  => __('Docs::docs_toast_upload_fail'),
    'docs_confirm_delete_att' => __('Docs::docs_confirm_delete_att'),
    'docs_toast_att_deleted'  => __('Docs::docs_toast_att_deleted'),
    'docs_toast_paste_success'=> __('Docs::docs_toast_paste_success'),
    'docs_attachments_empty'  => __('Docs::docs_attachments_empty'),
    'docs_attachment_download'=> __('Docs::docs_attachment_download'),
    'docs_btn_delete'         => __('Docs::docs_btn_delete'),
]);

