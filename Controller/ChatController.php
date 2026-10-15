<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/UserModel.php';

class ChatController extends Controller
{
    private UserModel $userModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Sohbet ekranı için kullanıcı ve kanal verilerini hazırlar
     */
    public function index(?int $initialTargetUserId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'name' => 'Kullanıcı', 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        $teamUsers = $this->userModel->getChatTeamUsers($currentUser->id, $isClient);
        $initialTargetUserId = $initialTargetUserId ?: 0;

        return [
            'currentUser'         => $currentUser,
            'isClient'            => $isClient,
            'teamUsers'           => $teamUsers,
            'initialTargetUserId' => $initialTargetUserId
        ];
    }
}
