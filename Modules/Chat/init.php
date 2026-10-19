<?php
// Modules/Chat/init.php
namespace Modules\Chat;

use Core\EventManager;
use Core\AssetManager;

// 1. Modül JS ve CSS Varlıklarını Kuyruğa Al
AssetManager::enqueueCss('chat-styles', '/taskmanager/Modules/Chat/Assets/css/chat.css');
AssetManager::enqueueJs('chat-js', '/taskmanager/Modules/Chat/Assets/js/chat.js');

// 2. JavaScript İstemci Tarafı Dil Dizgilerini ve Kullanıcı Bilgilerini Aktar
$currentUser = $_SESSION['user'] ?? null;
AssetManager::localizeScript('chat-js', 'CHAT_APP_DATA', [
    'currentUser' => [
        'id'       => $currentUser ? (int)$currentUser->id : 1,
        'isClient' => $currentUser ? ($currentUser->role === 'client') : false,
    ],
    'i18n' => [
        'channel_general'      => __('Chat::chat_channel_general'),
        'all_team'             => __('Chat::chat_all_team'),
        'general_desc'         => __('Chat::chat_header_general_desc'),
        'input_placeholder'    => __('Chat::chat_input_placeholder'),
        'loading'              => __('Chat::loading'),
        'loading_history'      => __('Chat::chat_loading_history'),
        'no_other_members'     => __('Chat::chat_no_other_members'),
        'default_role'         => __('Chat::chat_default_role'),
        'user_not_found'       => __('Chat::user_not_found'),
        'attachment_label'     => __('Chat::attachment_label'),
        'messages_listed'      => __('Chat::messages_listed'),
        'message_sent'         => __('Chat::message_sent'),
        'online'               => __('Chat::online'),
        'private_chat_with'    => __('Chat::private_chat_with'),
        'private_chat_desc'    => __('Chat::private_chat_desc'),
        'dm_badge'             => __('Chat::dm_badge'),
        'private_input_placeholder' => __('Chat::private_input_placeholder'),
        'loading_messages'     => __('Chat::loading_messages'),
        'messages_count'       => __('Chat::messages_count'),
        'error_prefix'         => __('Chat::error_prefix'),
        'conn_error_prefix'    => __('Chat::conn_error_prefix'),
        'server_error_prefix'  => __('Chat::server_error_prefix'),
        'load_failed'          => __('Chat::load_failed'),
        'today'                => __('Chat::today'),
        'inspect_task'         => __('Chat::inspect_task'),
        'chat_image'           => __('Chat::chat_image'),
        'image_alt'            => __('Chat::image_alt'),
        'user_label'           => __('Chat::user_label'),
        'you_label'            => __('Chat::you_label'),
        'send_failed'          => __('Chat::send_failed'),
        'direct_msg_toast'     => __('Chat::direct_msg_toast'),
        'attachment_fallback'  => __('Chat::attachment_fallback'),
        'team_private_chat'    => __('Chat::team_private_chat'),
        'team_private_desc'    => __('Chat::team_private_desc'),
        'select_member_to_chat' => __('Chat::select_member_to_chat'),
        'welcome_general_title' => __('Chat::welcome_general_title'),
        'first_dm_desc'        => __('Chat::first_dm_desc'),
    ]
]);

