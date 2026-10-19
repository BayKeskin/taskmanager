<?php
namespace Modules\Chat\Controllers;

use Core\BaseController;
use Modules\Chat\Models\ChatModel;

class ChatController extends BaseController
{
    private ChatModel $chatModel;

    public function __construct()
    {
        parent::__construct();
        $this->chatModel = new ChatModel();
    }

    /**
     * Sohbet ekranı için kullanıcı ve kanal verilerini hazırlar
     */
    public function index(?int $initialTargetUserId = null, ?object $currentUser = null): array
    {
        $defaultUserName = __('Chat::chat_default_user');
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'name' => $defaultUserName, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        
        $convData = $this->chatModel->getConversations((int)$currentUser->id, $isClient);
        $teamUsers = $convData['conversations'];
        $initialTargetUserId = $initialTargetUserId ?: 0;

        return [
            'currentUser'         => $currentUser,
            'isClient'            => $isClient,
            'teamUsers'           => $teamUsers,
            'initialTargetUserId' => $initialTargetUserId
        ];
    }

    /**
     * AJAX: Mesajları listele
     */
    public function getMessages(): void
    {
        $currentUser = $this->getCurrentUser();
        $receiverId = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

        if ($receiverId === 0 && $currentUser->role === 'client') {
            $this->json(['error' => __('Chat::client_channel_forbidden')], 403);
            return;
        }

        $messages = $this->chatModel->getMessages((int)$currentUser->id, $receiverId, $lastId);
        $this->json(['success' => true, 'messages' => $messages]);
    }
}
