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
        
        $sql = "
            SELECT m.*, u.name as user_name, u.title as user_title, u.avatar_color, u.role as user_role
            FROM chat_messages m
            LEFT JOIN users u ON m.user_id = u.id
        ";
        $params = [];

        if ($receiverId > 0) {
            $sql .= " WHERE ((m.user_id = ? AND m.receiver_id = ?) OR (m.user_id = ? AND m.receiver_id = ?)) ";
            $params[] = $this->user->id;
            $params[] = $receiverId;
            $params[] = $receiverId;
            $params[] = $this->user->id;

            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }

            $this->db->exec_query("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND receiver_id = ? AND is_read = 0", [
                $receiverId, $this->user->id
            ]);
        } else {
            if ($this->user->role === 'client') {
                $this->respond(false, 'Müşteriler şirket içi genel sohbet kanalına erişemez. Lütfen yetkili ekip üyesi ile özel mesajlaşmayı kullanın.');
            }
            $sql .= " WHERE (m.receiver_id IS NULL OR m.receiver_id = 0) ";
            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }
        }

        $sql .= " ORDER BY m.id DESC LIMIT 60 ";
        $messages = $this->db->multi_record_query($sql, $params) ?: [];
        $messages = array_reverse($messages);

        $targetUser = null;
        if ($receiverId > 0) {
            $tRow = $this->db->row_query("SELECT id, name, title, role, avatar_color, status FROM users WHERE id = ? LIMIT 1", [$receiverId]);
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

        $this->respond(true, 'Mesajlar listelendi.', [
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
            $this->respond(false, 'Mesaj metni veya dosya eki zorunludur.');
        }

        if ($receiverId === null && $this->user->role === 'client') {
            $this->respond(false, 'Müşteriler şirket içi genel sohbet kanalına mesaj gönderemez. Lütfen bir ekip üyesine özel mesaj gönderin.');
        }

        if ($receiverId !== null) {
            $targetExists = $this->db->row_query("SELECT id, name FROM users WHERE id = ? LIMIT 1", [$receiverId]);
            if (!$targetExists) {
                $this->respond(false, 'Mesaj gönderilecek kullanıcı bulunamadı.');
            }
            if ($receiverId == $this->user->id) {
                $this->respond(false, 'Kendinize özel mesaj gönderemezsiniz.');
            }
        }

        $msgId = (int)$this->db->insert_return_count("chat_messages", [
            'user_id', 'receiver_id', 'message', 'attachment_url', 'is_read'
        ], [
            $this->user->id, $receiverId, $messageText, $attachmentUrl, 0
        ]);

        if ($msgId <= 0) {
            $msgId = (int)$this->db->single_query("SELECT MAX(id) FROM chat_messages");
        }

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

        $this->respond(true, 'Mesaj gönderildi.', [
            'chat_message' => $newMsg,
            'message_item' => $newMsg
        ]);
    }

    public function getConversations(): void
    {
        $userCondition = "u.status = 'active' AND u.id != ?";
        if ($this->user->role === 'client') {
            $userCondition .= " AND u.role != 'client'";
        }
        $users = $this->db->multi_record_query("
            SELECT u.id, u.name, u.title, u.avatar_color, u.role, u.status
            FROM users u
            WHERE {$userCondition}
            ORDER BY u.name ASC
        ", [$this->user->id]) ?: [];

        $conversations = [];
        foreach ($users as $u) {
            $unreadCount = (int)$this->db->single_query("
                SELECT COUNT(*) FROM chat_messages
                WHERE user_id = ? AND receiver_id = ? AND is_read = 0
            ", [$u->id, $this->user->id]);

            $lastMsg = $this->db->row_query("
                SELECT message, attachment_url, created_at, user_id
                FROM chat_messages
                WHERE ((user_id = ? AND receiver_id = ?) OR (user_id = ? AND receiver_id = ?))
                ORDER BY id DESC LIMIT 1
            ", [$this->user->id, $u->id, $u->id, $this->user->id]);

            $conversations[] = [
                'id'                => (int)$u->id,
                'name'              => $u->name,
                'title'             => $u->title ?: ucfirst($u->role),
                'avatar_color'      => $u->avatar_color ?: '#7B68EE',
                'role'              => $u->role,
                'unread_count'      => $unreadCount,
                'last_message'      => $lastMsg ? ($lastMsg->message ?: ($lastMsg->attachment_url ? '📎 Dosya / Görsel' : '')) : null,
                'last_message_time' => $lastMsg ? date('H:i', strtotime($lastMsg->created_at)) : null,
                'last_message_by_me'=> $lastMsg ? ((int)$lastMsg->user_id === (int)$this->user->id) : false
            ];
        }

        $totalUnread = (int)$this->db->single_query("
            SELECT COUNT(*) FROM chat_messages
            WHERE receiver_id = ? AND is_read = 0
        ", [$this->user->id]);

        $this->respond(true, 'Konuşmalar listelendi.', [
            'conversations' => $conversations,
            'total_unread'  => $totalUnread
        ]);
    }
}
