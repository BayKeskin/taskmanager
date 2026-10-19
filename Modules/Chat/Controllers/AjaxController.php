<?php
namespace Modules\Chat\Controllers;

use Core\BaseController;
use Modules\Chat\Models\ChatModel;

class AjaxController extends BaseController
{
    private ChatModel $chatModel;

    public function __construct()
    {
        parent::__construct();
        $this->chatModel = new ChatModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getMessages(): void
    {
        $lastId = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);
        $receiverId = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : (isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0);

        if ($receiverId === 0 && $this->user->role === 'client') {
            $this->respond(false, __('Chat::client_channel_forbidden'));
            return;
        }

        $messages = $this->chatModel->getMessages((int)$this->user->id, $receiverId, $lastId, 60);

        $targetUser = null;
        if ($receiverId > 0) {
            $tRow = $this->chatModel->getUserById($receiverId);
            if ($tRow) {
                $targetUser = [
                    'id'           => (int)$tRow->id,
                    'name'         => $tRow->name,
                    'title'        => $tRow->title ?: ucfirst($tRow->role),
                    'role'         => $tRow->role,
                    'avatar_color' => $tRow->avatar_color ?: '#7B68EE',
                    'status'       => $tRow->status
                ];
            }
        }

        $this->respond(true, __('Chat::messages_listed'), [
            'messages'        => $messages,
            'target_user'     => $targetUser,
            'is_direct'       => ($receiverId > 0),
            'current_user_id' => $this->user->id
        ]);
    }

    public function sendMessage(): void
    {
        $messageText = trim($_POST['message'] ?? '');
        $receiverId = isset($_POST['receiver_id']) && is_numeric($_POST['receiver_id']) && (int)$_POST['receiver_id'] > 0 ? (int)$_POST['receiver_id'] : null;
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');

        if (!empty($_FILES['attachment']) || !empty($_FILES['file'])) {
            $f = $_FILES['attachment'] ?? $_FILES['file'];
            $upRes = \Modules\Storage\Services\StorageService::upload($f, 'chat');
            if ($upRes['success']) {
                $attachmentUrl = $upRes['file_path'];
            }
        }

        if (empty($messageText) && empty($attachmentUrl)) {
            $this->respond(false, __('Chat::message_required'));
            return;
        }

        if ($receiverId === null && $this->user->role === 'client') {
            $this->respond(false, __('Chat::client_channel_forbidden'));
            return;
        }

        if ($receiverId !== null) {
            $targetExists = $this->chatModel->getUserById($receiverId);
            if (!$targetExists) {
                $this->respond(false, __('Chat::user_not_found'));
                return;
            }
            if ($receiverId == $this->user->id) {
                $this->respond(false, __('Chat::cannot_message_self'));
                return;
            }
        }

        $msgId = $this->chatModel->saveMessage((int)$this->user->id, $receiverId, $messageText, $attachmentUrl);

        $newMsg = (object)[
            'id'             => $msgId,
            'user_id'        => (int)$this->user->id,
            'receiver_id'    => $receiverId,
            'user_name'      => $this->user->name,
            'user_title'     => $this->user->title ?? '',
            'avatar_color'   => $this->user->avatar_color ?? '#7B68EE',
            'user_role'      => $this->user->role ?? 'user',
            'message'        => $messageText,
            'attachment_url' => $attachmentUrl,
            'created_at'     => date('Y-m-d H:i:s'),
            'formatted_time' => date('H:i'),
            'is_direct'      => ($receiverId !== null)
        ];

        if ($receiverId !== null) {
            \Modules\SSE\Services\SseBroadcaster::broadcast('new_direct_message', null, null, (int)$this->user->id, (array)$newMsg);
        } else {
            \Modules\SSE\Services\SseBroadcaster::broadcast('new_chat_message', null, null, (int)$this->user->id, (array)$newMsg);
        }

        $this->respond(true, __('Chat::message_sent'), [
            'chat_message' => $newMsg,
            'message_item' => $newMsg
        ]);
    }

    public function getConversations(): void
    {
        $isClient = ($this->user->role === 'client');
        $convData = $this->chatModel->getConversations((int)$this->user->id, $isClient);

        $this->respond(true, __('Chat::conversations_listed'), [
            'conversations' => $convData['conversations'],
            'total_unread'  => $convData['total_unread']
        ]);
    }
}
