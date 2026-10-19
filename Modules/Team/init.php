<?php
// Modules/Team/init.php
namespace Modules\Team;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('team-styles', '/Modules/Team/Assets/css/team.css');
AssetManager::enqueueJs('team-js', '/Modules/Team/Assets/js/team.js');

AssetManager::localizeScript('team-js', 'TEAM_I18N', [
    'confirm_delete'       => __('Team::team_confirm_delete'),
    'invite_copied'        => __('Team::team_invite_copied'),
    'invite_prompt'        => __('Team::team_invite_prompt'),
    'default_user_name'    => __('Team::default_user_name'),
    'updating_btn'         => __('Team::updating_btn'),
    'pwd_min_len_alert'    => __('Team::pwd_min_len_alert'),
    'pwd_mismatch_alert'   => __('Team::pwd_mismatch_alert'),
    'pwd_current_req_alert'=> __('Team::pwd_current_req_alert'),
    'server_error_prefix'  => __('Team::server_error_prefix'),
    'title_client_default' => __('Team::title_client_default'),
    'title_member_default' => __('Team::title_member_default'),
    'title_admin_default'  => __('Team::title_admin_default'),
    'member_added_toast'   => __('Team::member_added_toast'),
    'error_occurred'       => __('Team::error_occurred'),
    'member_deleted_toast' => __('Team::member_deleted_toast'),
    'delete_error_toast'   => __('Team::delete_error_toast'),
    'token_copied_toast'   => __('Team::token_copied_toast'),
    'invite_body_intro'    => __('Team::invite_body_intro'),
    'invite_body_login'    => __('Team::invite_body_login'),
    'invite_body_pwd'      => __('Team::invite_body_pwd'),
    'invite_body_outro'    => __('Team::invite_body_outro'),
]);

