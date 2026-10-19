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
    ]
]);
