<?php
namespace Modules\Chat\Models;

use Core\BaseModel;

class ChatModel extends BaseModel
{
    protected string $table = 'chat_messages';

    /**
     * Mesajları getirir (Genel kanal veya birebir DM)
     */
    public function getMessages(int $currentUserId, int $receiverId = 0, int $lastId = 0, int $limit = 60): array
    {
        $sql = "
            SELECT m.*, u.name as user_name, u.title as user_title, u.avatar_color, u.role as user_role
            FROM chat_messages m
            LEFT JOIN users u ON m.user_id = u.id
        ";
        $params = [];

        if ($receiverId > 0) {
            $sql .= " WHERE ((m.user_id = ? AND m.receiver_id = ?) OR (m.user_id = ? AND m.receiver_id = ?)) ";
            $params[] = $currentUserId;
            $params[] = $receiverId;
            $params[] = $receiverId;
            $params[] = $currentUserId;

            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }

            // Okundu işaretle
            $this->markAsRead($receiverId, $currentUserId);
        } else {
            $sql .= " WHERE (m.receiver_id IS NULL OR m.receiver_id = 0) ";
            if ($lastId > 0) {
                $sql .= " AND m.id > ? ";
                $params[] = $lastId;
            }
        }

        $sql .= " ORDER BY m.id DESC LIMIT " . (int)$limit;
        $messages = $this->query($sql, $params);
        return array_reverse($messages);
    }

    /**
     * Mesajları okundu olarak işaretler
     */
    public function markAsRead(int $senderId, int $receiverId): void
    {
        $stmt = $this->getPdo()->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$senderId, $receiverId]);
    }

    /**
     * Tek kullanıcı bilgilerini çeker
     */
    public function getUserById(int $userId): ?object
    {
        $rows = $this->query("SELECT id, name, title, role, avatar_color, status FROM users WHERE id = ? LIMIT 1", [$userId]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Yeni sohbet mesajı ekler
     */
    public function saveMessage(int $userId, ?int $receiverId, string $message, string $attachmentUrl = ''): int
    {
        $stmt = $this->getPdo()->prepare("INSERT INTO chat_messages (user_id, receiver_id, message, attachment_url, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$userId, $receiverId, $message, $attachmentUrl]);
        return (int)$this->getPdo()->lastInsertId();
    }

    /**
     * Kullanıcının aktif konuşma listesini ve okunmamış sayılarını döner
     */
    public function getConversations(int $currentUserId, bool $isClient = false): array
    {
        $userCondition = "u.status = 'active' AND u.id != ?";
        if ($isClient) {
            $userCondition .= " AND u.role != 'client'";
        }

        $users = $this->query("
            SELECT u.id, u.name, u.title, u.avatar_color, u.role, u.status
            FROM users u
            WHERE {$userCondition}
            ORDER BY u.name ASC
        ", [$currentUserId]);

        $attachmentText = __('Chat::attachment_label');

        $conversations = [];
        foreach ($users as $u) {
            $u = (object)$u;
            $uid = (int)$u->id;
            $unreadRows = $this->query("
                SELECT COUNT(*) as cnt FROM chat_messages
                WHERE user_id = ? AND receiver_id = ? AND is_read = 0
            ", [$uid, $currentUserId]);
            $firstUnread = !empty($unreadRows) ? (object)$unreadRows[0] : null;
            $unreadCount = $firstUnread ? (int)($firstUnread->cnt ?? 0) : 0;

            $lastMsgRows = $this->query("
                SELECT message, attachment_url, created_at, user_id
                FROM chat_messages
                WHERE ((user_id = ? AND receiver_id = ?) OR (user_id = ? AND receiver_id = ?))
                ORDER BY id DESC LIMIT 1
            ", [$currentUserId, $uid, $uid, $currentUserId]);

            $lastMsg = !empty($lastMsgRows) ? (object)$lastMsgRows[0] : null;

            $conversations[] = [
                'id'                => $uid,
                'name'              => $u->name,
                'title'             => $u->title ?: ucfirst($u->role),
                'avatar_color'      => $u->avatar_color ?: '#7B68EE',
                'role'              => $u->role,
                'unread_count'      => $unreadCount,
                'last_message'      => $lastMsg ? ($lastMsg->message ?: (!empty($lastMsg->attachment_url) ? $attachmentText : '')) : null,
                'last_message_time' => $lastMsg ? date('H:i', strtotime($lastMsg->created_at)) : null,
                'last_message_by_me'=> $lastMsg ? ((int)$lastMsg->user_id === $currentUserId) : false
            ];
        }

        $totalUnreadRows = $this->query("
            SELECT COUNT(*) as cnt FROM chat_messages
            WHERE receiver_id = ? AND is_read = 0
        ", [$currentUserId]);
        $firstTotal = !empty($totalUnreadRows) ? (object)$totalUnreadRows[0] : null;
        $totalUnread = $firstTotal ? (int)($firstTotal->cnt ?? 0) : 0;

        return [
            'conversations' => $conversations,
            'total_unread'  => $totalUnread
        ];
    }
}
